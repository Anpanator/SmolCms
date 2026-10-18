import { assertEqual, assertTrue } from './assert.js';
import { mockFetch, sessionResponse, submitForm, testUser, useFetch } from './mocks.js';import { createSession } from '../../public/js/session.js';
import { createSettingsView } from '../../public/js/views/settings.js';

async function createLoggedInSession(user = testUser()) {
    const fetchMock = mockFetch((url) => {
        if (url === '/api/session') {
            return sessionResponse(user);
        }
        throw new Error(`unexpected fetch of ${url}`);
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    return { session, fetchMock, restore };
}

export async function testSettingsPrefillsDisplayName() {
    const { session, restore } = await createLoggedInSession(testUser({ displayName: 'Amy Pond' }));
    try {
        const view = createSettingsView({ session, navigate: () => {} });
        const input = view.querySelector('input[name="displayName"]');
        assertEqual(input?.value, 'Amy Pond', 'display name should be prefilled from session');
    } finally {
        restore();
    }
}

export async function testInvalidDisplayNameShowsErrorWithoutFetching() {
    const { session, fetchMock, restore } = await createLoggedInSession();
    try {
        const view = createSettingsView({ session, navigate: () => {} });
        view.querySelector('input[name="displayName"]').value = '   ';
        await submitForm(view.querySelector('form'));

        const error = view.querySelector('.field-error');
        assertTrue(!error.hidden, 'empty display name should show an error');
        assertEqual(fetchMock.calls.length, 1, 'only the initial session load should have fetched');
    } finally {
        restore();
    }
}

export async function testValidSubmitPostsSettingsAndUpdatesSession() {
    const { session, restore } = await createLoggedInSession();
    try {
        const navigateCalls = [];
        const view = createSettingsView({ session, navigate: (path) => navigateCalls.push(path) });
        view.querySelector('input[name="displayName"]').value = 'New Name';

        const fetchMock = mockFetch((url) => {
            if (url === '/settings') {
                return { status: 200, body: { user: testUser({ displayName: 'New Name' }) } };
            }
            throw new Error(`unexpected fetch of ${url}`);
        });
        const restoreSettings = useFetch(fetchMock);
        try {
            await submitForm(view.querySelector('form'));

            assertEqual(fetchMock.calls.length, 1, 'only the settings POST should be sent');
            const { options } = fetchMock.calls[0];
            assertEqual(options.method, 'POST');
            assertEqual(JSON.parse(options.body).displayName, 'New Name');

            assertEqual(session.getUser()?.displayName, 'New Name', 'session should be updated from the response');
            const message = view.querySelector('.form-message');
            assertTrue(!message.hidden, 'success message should be visible');
            assertTrue(message.className.includes('form-message--success'));
            assertEqual(navigateCalls.length, 0, 'successful save should stay on the page');
        } finally {
            restoreSettings();
        }
    } finally {
        restore();
    }
}

export async function testUnauthorizedNavigatesToLogin() {
    const { session, restore } = await createLoggedInSession();
    try {
        const navigateCalls = [];
        const fetchMock = mockFetch((url) => {
            if (url === '/settings') {
                return { status: 401, body: null };
            }
            throw new Error(`unexpected fetch of ${url}`);
        });
        const restoreSettings = useFetch(fetchMock);
        try {
            const view = createSettingsView({ session, navigate: (path) => navigateCalls.push(path) });
            view.querySelector('input[name="displayName"]').value = 'New Name';
            await submitForm(view.querySelector('form'));
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
        } finally {
            restoreSettings();
        }
    } finally {
        restore();
    }
}

export async function testAnonymousSettingsShowsLoginNotice() {
    const fetchMock = mockFetch(() => sessionResponse(null));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        const view = createSettingsView({ session, navigate: () => {} });
        assertTrue(view.querySelector('.notice--warning') !== null, 'anonymous users should see a login hint');
        assertTrue(view.querySelector('input[name="displayName"]') !== null, 'form should still render');
    } finally {
        restore();
    }
}
