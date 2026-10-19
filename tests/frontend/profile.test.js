import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, submitForm, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createProfileView} from '../../public/js/views/profile.js';

async function createLoggedInSession(
    user = testUser(),
    profileResponse = {status: 200, body: {approvedTagSuggestions: 3}},
) {
    const fetchMock = mockFetch((url) => {
        if (url === '/api/session') {
            return sessionResponse(user);
        }
        if (url === '/api/profile') {
            return profileResponse;
        }
        throw new Error(`unexpected fetch of ${url}`);
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    return {session, fetchMock, restore};
}

export async function testProfilePrefillsDisplayName() {
    const {session, restore} = await createLoggedInSession(testUser({displayName: 'Amy Pond'}));
    try {
        const view = createProfileView({
            session, navigate: () => {
            }
        });
        const input = view.querySelector('input[name="displayName"]');
        assertEqual(input?.value, 'Amy Pond', 'display name should be prefilled from session');
    } finally {
        restore();
    }
}

export async function testProfileDisplaysAccessLevelReadOnly() {
    const {session, restore} = await createLoggedInSession(testUser({accessLevel: 300}));
    try {
        const view = createProfileView({
            session, navigate: () => {
            }
        });
        const accessLevel = view.querySelector('input[name="accessLevel"]');
        assertEqual(accessLevel?.value, 'Master', 'profile should display the named access level');
        assertTrue(accessLevel?.readOnly, 'access level should be read-only');
    } finally {
        restore();
    }
}

export async function testProfileDisplaysApprovedTagSuggestionCount() {
    const {session, fetchMock, restore} = await createLoggedInSession();
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        const count = view.querySelector('input[name="approvedTagSuggestions"]');
        assertEqual(
            view.querySelector('label[for="field-approvedTagSuggestions"]')?.textContent,
            'Approved tag suggestions',
        );
        assertEqual(count?.value, '3', 'profile should display the approved tag suggestion count');
        assertTrue(count?.readOnly, 'approved tag suggestion count should be read-only');
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/profile'), 'profile should fetch its approved count');
    } finally {
        restore();
    }
}

export async function testProfileRendersWhenApprovedTagSuggestionFetchFails() {
    const {session, restore} = await createLoggedInSession(testUser(), {status: 500, body: null});
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        assertTrue(view.querySelector('input[name="displayName"]') !== null, 'profile form should render on fetch failure');
        assertEqual(
            view.querySelector('input[name="approvedTagSuggestions"]')?.value,
            'Unavailable',
            'failed count requests should display an unavailable state',
        );
    } finally {
        restore();
    }
}

export async function testInvalidDisplayNameShowsErrorWithoutFetching() {
    const {session, fetchMock, restore} = await createLoggedInSession();
    try {
        const view = createProfileView({
            session, navigate: () => {
            }
        });
        view.querySelector('input[name="displayName"]').value = '   ';
        await submitForm(view.querySelector('form'));

        const error = view.querySelector('.field-error');
        assertTrue(!error.hidden, 'empty display name should show an error');
        assertEqual(fetchMock.calls.length, 2, 'only the session and profile data should have been fetched');
    } finally {
        restore();
    }
}

export async function testValidProfileSubmitUpdatesSession() {
    const {session, restore} = await createLoggedInSession();
    try {
        const navigateCalls = [];
        const view = createProfileView({session, navigate: (path) => navigateCalls.push(path)});
        view.querySelector('input[name="displayName"]').value = 'New Name';

        const fetchMock = mockFetch((url) => {
            if (url === '/settings') {
                return {status: 200, body: {user: testUser({displayName: 'New Name'})}};
            }
            throw new Error(`unexpected fetch of ${url}`);
        });
        const restoreProfilePost = useFetch(fetchMock);
        try {
            await submitForm(view.querySelector('form'));

            assertEqual(fetchMock.calls.length, 1, 'only the profile update should be sent');
            const {options} = fetchMock.calls[0];
            assertEqual(options.method, 'POST');
            assertEqual(JSON.parse(options.body).displayName, 'New Name');

            assertEqual(session.getUser()?.displayName, 'New Name', 'session should be updated from the response');
            const message = view.querySelector('.form-message');
            assertTrue(!message.hidden, 'success message should be visible');
            assertTrue(message.className.includes('form-message--success'));
            assertEqual(navigateCalls.length, 0, 'successful save should stay on the page');
        } finally {
            restoreProfilePost();
        }
    } finally {
        restore();
    }
}

export async function testUnauthorizedProfileUpdateNavigatesToLogin() {
    const {session, restore} = await createLoggedInSession();
    try {
        const navigateCalls = [];
        const fetchMock = mockFetch((url) => {
            if (url === '/settings') {
                return {status: 401, body: null};
            }
            throw new Error(`unexpected fetch of ${url}`);
        });
        const restoreProfilePost = useFetch(fetchMock);
        try {
            const view = createProfileView({session, navigate: (path) => navigateCalls.push(path)});
            view.querySelector('input[name="displayName"]').value = 'New Name';
            await submitForm(view.querySelector('form'));
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
        } finally {
            restoreProfilePost();
        }
    } finally {
        restore();
    }
}

export async function testAnonymousProfileShowsLoginNotice() {
    const fetchMock = mockFetch(() => sessionResponse(null));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        const view = createProfileView({
            session, navigate: () => {
            }
        });
        assertTrue(view.querySelector('.notice--warning') !== null, 'anonymous users should see a login hint');
        assertTrue(view.querySelector('input[name="displayName"]') !== null, 'form should still render');
        assertEqual(view.querySelector('input[name="accessLevel"]'), null, 'anonymous users have no access level to display');
        assertEqual(view.querySelector('input[name="approvedTagSuggestions"]'), null, 'anonymous users have no count to display');
        assertEqual(fetchMock.calls.length, 1, 'anonymous profiles should not fetch the approved count');
    } finally {
        restore();
    }
}
