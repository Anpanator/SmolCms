import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createFooterNav, createHeaderNav} from '../../public/js/nav.js';

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
        assertTrue(!nav.element.textContent.includes('Profile'), 'anonymous nav must not link to the profile');
    } finally {
        restore();
    }
}

export async function testLoggedInNavRendersUserAndLogout() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser({ displayName: 'Amy' })));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        const links = [...nav.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(JSON.stringify(links), JSON.stringify(['/', '/upload-image', '/tagme', '/settings']));
        assertEqual(nav.element.querySelector('.nav-submenu-trigger')?.textContent, 'Amy', 'display name should title the account dropdown');
        assertEqual(nav.element.querySelector('.has-submenu .submenu a[href="/settings"]')?.textContent, 'Profile');
        assertEqual(nav.element.querySelector('.nav-list > .nav-item > a[href="/settings"]'), null, 'profile link should be in the account dropdown');
        assertTrue(nav.element.textContent.includes('Access level: Novice'), 'access level should use its name, not the numeric value');
        assertTrue(!nav.element.textContent.includes('100'), 'access level number should not be shown');
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

export async function testProfileLinkIsActiveInsideAccountDropdown() {
    const {session, restore} = await createLoadedSession(() => sessionResponse(testUser()));
    try {
        const nav = createHeaderNav({
            session, navigate: () => {
            }
        });
        nav.setActive('/settings');
        assertEqual(
            nav.element.querySelector('.has-submenu .submenu a.nav-link.active')?.getAttribute('href'),
            '/settings',
        );
    } finally {
        restore();
    }
}

export async function testFooterNavMirrorsSession() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser()));
    try {
        const footer = createFooterNav({ session });
        const links = [...footer.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(JSON.stringify(links), JSON.stringify(['/', '/upload-image', '/tagme']));

        footer.setActive('/upload-image');
        assertEqual(footer.element.querySelector('a.nav-link.active')?.getAttribute('href'), '/upload-image');
    } finally {
        restore();
    }
}

export async function testModQueueLinkVisibleForModerators() {
    for (const accessLevel of [500, 600]) {
        const { session, restore } = await createLoadedSession(() => sessionResponse(testUser({ accessLevel })));
        try {
            const nav = createHeaderNav({ session, navigate: () => {} });
            const reviewLink = nav.element.querySelector('a.nav-link[href="/review"]');
            assertTrue(reviewLink !== null, `access level ${accessLevel} should see the Mod Queue link`);
            assertEqual(reviewLink.textContent, 'Mod Queue');

            const links = [...nav.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
            assertEqual(
                JSON.stringify(links),
                JSON.stringify(['/', '/upload-image', '/tagme', '/review', '/tag-queue', '/tags', '/settings']),
                'moderation links should sit between Upload Image and the account dropdown',
            );
        } finally {
            restore();
        }
    }
}

export async function testTagsLinkVisibleForModerators() {
    for (const accessLevel of [500, 600]) {
        const {session, restore} = await createLoadedSession(() => sessionResponse(testUser({accessLevel})));
        try {
            const nav = createHeaderNav({session, navigate: () => {}});
            const tagsLink = nav.element.querySelector('a.nav-link[href="/tags"]');
            assertTrue(tagsLink !== null, `access level ${accessLevel} should see the Tags link`);
            assertEqual(tagsLink.textContent, 'Tags');
        } finally {
            restore();
        }
    }
}

export async function testTagsLinkHiddenBelowModerators() {
    for (const accessLevel of [100, 400]) {
        const {session, restore} = await createLoadedSession(() => sessionResponse(testUser({accessLevel})));
        try {
            const nav = createHeaderNav({session, navigate: () => {}});
            assertEqual(nav.element.querySelector('a.nav-link[href="/tags"]'), null);
        } finally {
            restore();
        }
    }
}

export async function testTagQueueLinkVisibleForModerators() {
    for (const accessLevel of [500, 600]) {
        const {session, restore} = await createLoadedSession(() => sessionResponse(testUser({accessLevel})));
        try {
            const nav = createHeaderNav({session, navigate: () => {}});
            const tagQueueLink = nav.element.querySelector('a.nav-link[href="/tag-queue"]');
            assertTrue(tagQueueLink !== null, `access level ${accessLevel} should see the Tag Queue link`);
            assertEqual(tagQueueLink.textContent, 'Tag Queue');
            assertEqual(
                tagQueueLink.parentElement?.previousElementSibling?.querySelector('a')?.getAttribute('href'),
                '/review',
                'Tag Queue should sit next to Mod Queue',
            );
        } finally {
            restore();
        }
    }
}

export async function testTagQueueLinkHiddenBelowModerators() {
    for (const accessLevel of [100, 400]) {
        const {session, restore} = await createLoadedSession(() => sessionResponse(testUser({accessLevel})));
        try {
            const nav = createHeaderNav({session, navigate: () => {}});
            assertEqual(nav.element.querySelector('a.nav-link[href="/tag-queue"]'), null);
        } finally {
            restore();
        }
    }
}

export async function testModQueueLinkHiddenBelowModerators() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser({ accessLevel: 100 })));
    try {
        const nav = createHeaderNav({ session, navigate: () => {} });
        assertEqual(nav.element.querySelector('a.nav-link[href="/review"]'), null, 'novices must not see the Mod Queue link');
    } finally {
        restore();
    }
}

export async function testFooterNavHasNoModQueueLinkForModerators() {
    const { session, restore } = await createLoadedSession(() => sessionResponse(testUser({ accessLevel: 500 })));
    try {
        const footer = createFooterNav({ session });
        const links = [...footer.element.querySelectorAll('a.nav-link')].map((link) => link.getAttribute('href'));
        assertEqual(
            JSON.stringify(links),
            JSON.stringify(['/', '/upload-image', '/tagme']),
            'the footer navigation must not gain a Mod Queue link',
        );
    } finally {
        restore();
    }
}

export async function testTagmeLinkIsVisibleForEveryLoggedInAccessLevel() {
    for (const accessLevel of [100, 200, 300, 400, 500, 600]) {
        const {session, restore} = await createLoadedSession(() => sessionResponse(testUser({accessLevel})));
        try {
            const nav = createHeaderNav({session, navigate: () => {}});
            const tagmeLink = nav.element.querySelector('a.nav-link[href="/tagme"]');
            assertTrue(tagmeLink !== null, `access level ${accessLevel} should see Tagme`);
            assertEqual(tagmeLink.textContent, 'Tagme');
        } finally {
            restore();
        }
    }
}
