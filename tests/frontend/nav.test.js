import { assertEqual, assertTrue, settle } from './assert.js';
import { mockFetch, sessionResponse, testUser, useFetch } from './mocks.js';
import { createSession } from '../../public/js/session.js';
import { createHeaderNav, createFooterNav } from '../../public/js/nav.js';

/**
 * Creates a session loaded once through a mocked fetch. `handler` receives
 * (url) and returns a response descriptor; it sees every subsequent request
 * (e.g. POST /logout, re-fetches of /api/session) as well.
 */
async function createLoadedSession(handler) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    return { session, fetchMock, restore };
}

export async function testAnonymousNavRendersLoginAndRegister() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(null));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        const links = [...nav.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(JSON.stringify(links), JSON.stringify(['/', '/login', '/register']));
        assertEqual(nav.element.querySelector('.nav-logout'), null, 'anonymous nav must not have a logout button');
        assertTrue(!nav.element.textContent.includes('Settings'), 'anonymous nav must not link settings');
    } finally {
        restore();
    }
}

export async function testLoggedInNavRendersUserAndLogout() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser({ displayName: 'Amy' })));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        const links = [...nav.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(JSON.stringify(links), JSON.stringify(['/', '/settings', '/upload-image']));
        assertTrue(nav.element.textContent.includes('Amy'), 'display name should be rendered');
        assertTrue(nav.element.querySelector('.nav-logout') !== null, 'logout button should exist');
        assertTrue(nav.element.querySelector('.has-submenu .submenu') !== null, 'account dropdown should exist');
        assertTrue(nav.element.querySelector('.has-submenu .submenu .nav-logout') !== null, 'logout should live in the dropdown');
    } finally {
        restore();
    }
}

export async function testLogoutPostsRefreshesAndNavigatesHome() {
    let sessionCalls = 0;
    const { session, fetchMock, restore } = await createLoadedSession((url) => {
        if (url === '/logout') {
            return { status: 204, body: null };
        }
        sessionCalls++;
        return sessionResponse(sessionCalls === 1 ? testUser() : null);
    });
    try {
        const navigateCalls = [];
        const nav = createHeaderNav({ session, navigate: (path) => navigateCalls.push(path) });
        nav.element.querySelector('.nav-logout').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(fetchMock.calls.length, 3, 'logout + refresh should fetch twice more');
        assertEqual(fetchMock.calls[1].url, '/logout');
        assertEqual(fetchMock.calls[1].options.method, 'POST');
        assertEqual(fetchMock.calls[2].url, '/api/session');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/']), 'should navigate home after logout');
        assertTrue(nav.element.textContent.includes('Login'), 'nav should re-render anonymous after logout');
    } finally {
        restore();
    }
}

export async function testSessionChangeReRendersNav() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(null));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        assertTrue(nav.element.textContent.includes('Register'), 'should start anonymous');
        session.setUser(testUser({ displayName: 'Bob' }));
        assertTrue(nav.element.textContent.includes('Bob'), 'nav should re-render on session change');
        assertTrue(!nav.element.textContent.includes('Register'), 'anonymous links should disappear');
    } finally {
        restore();
    }
}

export async function testSetActiveMarksActiveLink() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(null));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        nav.setActive('/login');
        assertEqual(nav.element.querySelector('a.nav-link.active')?.getAttribute('href'), '/login');
        assertEqual(nav.element.querySelectorAll('a.nav-link.active').length, 1);

        nav.setActive('/');
        assertEqual(nav.element.querySelector('a.nav-link.active')?.getAttribute('href'), '/');
    } finally {
        restore();
    }
}

export async function testFooterNavMirrorsSession() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser()));
    try {
        const footer = createFooterNav({ session });
        const links = [...footer.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(JSON.stringify(links), JSON.stringify(['/', '/settings', '/upload-image']));

        footer.setActive('/upload-image');
        assertEqual(footer.element.querySelector('a.nav-link.active')?.getAttribute('href'), '/upload-image');
    } finally {
        restore();
    }
}
