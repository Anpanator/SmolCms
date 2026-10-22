import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, submitForm, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createProfileView} from '../../public/js/views/profile.js';

async function createLoggedInSession(
    user = testUser(),
    profileResponse = {status: 200, body: {approvedTagSuggestions: 3, recentUploads: [], hasApiKey: false}},
    apiKeyResponses = {},
) {
    const fetchMock = mockFetch((url, options) => {
        if (url === '/api/session') {
            return sessionResponse(user);
        }
        if (url === '/api/profile') {
            return profileResponse;
        }
        if (url === '/api/profile/api-key') {
            return apiKeyResponses[options.method]
                ?? (options.method === 'DELETE'
                    ? {status: 204, body: null}
                    : {status: 201, body: {apiKey: 'generated-secret-key'}});
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

export async function testProfileApiKeySectionIsLimitedToWizardAndAbove() {
    const {session, restore} = await createLoggedInSession(testUser({accessLevel: 400}));
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();
        const section = view.querySelector('.profile-api-key');
        assertTrue(section !== null, 'WIZARD users should see the API key section');
        assertEqual(section.querySelector('h2')?.textContent, 'API key');
        assertTrue(
            section.textContent.includes('shown only once')
                && section.textContent.includes('endpoints enabled for API keys'),
            'the section should explain one-time display and API access',
        );
        assertTrue(
            section.querySelector('.profile-api-key-generate-button') !== null,
            'WIZARD users should be able to generate a key',
        );
    } finally {
        restore();
    }

    const nonWizard = await createLoggedInSession(testUser({accessLevel: 300}));
    try {
        const view = createProfileView({session: nonWizard.session, navigate: () => {}});
        assertEqual(view.querySelector('.profile-api-key'), null, 'MASTER users should not see the API key section');
    } finally {
        nonWizard.restore();
    }
}

export async function testProfileApiKeyControlsShowRotateAndDeleteWhenKeyExists() {
    const profileResponse = {
        status: 200,
        body: {approvedTagSuggestions: 3, recentUploads: [], hasApiKey: true},
    };
    const {session, restore} = await createLoggedInSession(testUser({accessLevel: 400}), profileResponse);
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        const generateButton = view.querySelector('.profile-api-key-generate-button');
        const deleteButton = view.querySelector('.profile-api-key-delete-button');
        assertEqual(generateButton?.textContent, 'Rotate API key');
        assertTrue(deleteButton !== null && !deleteButton.hidden, 'users with a key should see its delete button');
        assertTrue(deleteButton?.classList.contains('button--danger'), 'the delete button should use the danger style');
    } finally {
        restore();
    }
}

export async function testProfileApiKeyControlsShowGenerateWithoutDeleteWhenNoKeyExists() {
    const {session, restore} = await createLoggedInSession(testUser({accessLevel: 400}));
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        assertEqual(view.querySelector('.profile-api-key-generate-button')?.textContent, 'Generate API key');
        assertTrue(
            view.querySelector('.profile-api-key-delete-button')?.hidden,
            'users without a key should not see the delete button',
        );
    } finally {
        restore();
    }
}

export async function testProfileGeneratesAndShowsApiKeyOnce() {
    const {session, fetchMock, restore} = await createLoggedInSession(testUser({accessLevel: 400}));
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();
        const button = view.querySelector('.profile-api-key-generate-button');
        button.click();
        await settle();

        const call = fetchMock.calls.find((request) => request.url === '/api/profile/api-key');
        assertTrue(call !== undefined, 'the API key button should call its endpoint');
        assertEqual(call.options.method, 'POST');
        assertEqual(call.options.body, '{}');
        assertEqual(
            view.querySelector('.profile-api-key-value')?.textContent,
            'generated-secret-key',
            'the generated key should be shown after success',
        );
        assertEqual(view.querySelector('.profile-api-key-output')?.hidden, false);
        assertEqual(button.textContent, 'Rotate API key', 'generating a key should switch to the rotate label');
        assertTrue(
            !view.querySelector('.profile-api-key-delete-button')?.hidden,
            'generating a key should reveal the delete button',
        );
        assertTrue(
            view.querySelector('.profile-api-key .form-message')?.textContent.includes('only be shown once'),
            'the result should remind the user the key is shown once',
        );
    } finally {
        restore();
    }
}

export async function testProfileDeleteApiKeyConfirmsAndResetsTheKeyControls() {
    const profileResponse = {
        status: 200,
        body: {approvedTagSuggestions: 3, recentUploads: [], hasApiKey: true},
    };
    const {session, fetchMock, restore} = await createLoggedInSession(
        testUser({accessLevel: 400}),
        profileResponse,
    );
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        const generateButton = view.querySelector('.profile-api-key-generate-button');
        generateButton.click();
        await settle();
        assertEqual(view.querySelector('.profile-api-key-value')?.textContent, 'generated-secret-key');
        assertEqual(view.querySelector('.profile-api-key-output')?.hidden, false);
        assertEqual(generateButton.textContent, 'Rotate API key', 'generating another key should keep the rotate label');

        const deleteButton = view.querySelector('.profile-api-key-delete-button');
        deleteButton.click();
        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'deleting a key should ask for confirmation');
        assertTrue(
            dialog.querySelector('.modal-message')?.textContent.includes('no longer be able to authenticate'),
            'the confirmation should warn that deletion revokes API access',
        );
        assertTrue(
            !fetchMock.calls.some((call) => call.url === '/api/profile/api-key' && call.options.method === 'DELETE'),
            'the delete request should wait for confirmation',
        );

        dialog.querySelector('.modal-actions .button--primary').click();
        await settle();

        const deleteRequest = fetchMock.calls.find(
            (call) => call.url === '/api/profile/api-key' && call.options.method === 'DELETE',
        );
        assertTrue(deleteRequest !== undefined, 'confirmation should send a DELETE request');
        assertEqual(view.querySelector('.profile-api-key-generate-button')?.textContent, 'Generate API key');
        assertTrue(view.querySelector('.profile-api-key-delete-button')?.hidden);
        assertEqual(view.querySelector('.profile-api-key-output')?.hidden, true);
        assertEqual(view.querySelector('.profile-api-key-value')?.textContent, '');
        assertEqual(view.querySelector('.profile-api-key .form-message')?.textContent, 'API key deleted.');
    } finally {
        restore();
    }
}

export async function testProfileDeleteApiKeyTreatsNotFoundAsNoKey() {
    const profileResponse = {
        status: 200,
        body: {approvedTagSuggestions: 3, recentUploads: [], hasApiKey: true},
    };
    const {session, restore} = await createLoggedInSession(
        testUser({accessLevel: 400}),
        profileResponse,
        {DELETE: {status: 404, body: null}},
    );
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        view.querySelector('.profile-api-key-delete-button').click();
        document.querySelector('.modal-backdrop .modal-actions .button--primary').click();
        await settle();

        assertEqual(view.querySelector('.profile-api-key-generate-button')?.textContent, 'Generate API key');
        assertTrue(view.querySelector('.profile-api-key-delete-button')?.hidden);
        assertEqual(view.querySelector('.profile-api-key-output')?.hidden, true);
    } finally {
        restore();
    }
}

export async function testUnauthorizedApiKeyDeletionNavigatesToLogin() {
    const profileResponse = {
        status: 200,
        body: {approvedTagSuggestions: 3, recentUploads: [], hasApiKey: true},
    };
    const {session, restore} = await createLoggedInSession(
        testUser({accessLevel: 400}),
        profileResponse,
        {DELETE: {status: 401, body: null}},
    );
    try {
        const navigateCalls = [];
        const view = createProfileView({session, navigate: (path) => navigateCalls.push(path)});
        await settle();

        view.querySelector('.profile-api-key-delete-button').click();
        document.querySelector('.modal-backdrop .modal-actions .button--primary').click();
        await settle();

        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']));
        assertEqual(view.querySelector('.profile-api-key-generate-button')?.textContent, 'Rotate API key');
        assertTrue(!view.querySelector('.profile-api-key-delete-button')?.hidden);
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

export async function testProfileRendersAtMostFifteenRecentUploadsInFiveColumnGrid() {
    const recentUploads = Array.from({length: 18}, (_, index) => ({
        id: 100 + index,
        status: index === 4 ? 'pending_review' : 'accepted',
        thumbSrc: `/public/img/thumb/${index}.avif`,
        uploadDate: `2026-10-01 00:${String(index).padStart(2, '0')}:00`,
    }));
    const {session, restore} = await createLoggedInSession(testUser(), {
        status: 200,
        body: {approvedTagSuggestions: 0, recentUploads},
    });
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        const section = view.querySelector('.profile-recent-uploads');
        const grid = section?.querySelector('.profile-recent-uploads-grid');
        const tiles = Array.from(grid?.querySelectorAll('a') ?? []);
        assertEqual(section?.querySelector('h2')?.textContent, 'Recent Uploads');
        assertTrue(grid?.classList.contains('profile-recent-uploads-grid'), 'recent uploads should use the five-column grid class');
        assertEqual(tiles.length, 15, 'the profile should render no more than fifteen uploads');
        assertEqual(tiles[0]?.getAttribute('href'), '/image?id=100');
        assertEqual(tiles[14]?.getAttribute('href'), '/image?id=114');
        assertTrue(tiles[4]?.classList.contains('tile-unapproved'), 'pending-review uploads should use the orange-border class');
        assertTrue(!tiles[0]?.classList.contains('tile-unapproved'), 'accepted uploads should keep the normal tile style');
    } finally {
        restore();
    }
}

export async function testProfileShowsEmptyRecentUploadsState() {
    const {session, restore} = await createLoggedInSession(testUser(), {
        status: 200,
        body: {approvedTagSuggestions: 0, recentUploads: []},
    });
    try {
        const view = createProfileView({session, navigate: () => {}});
        await settle();

        assertEqual(
            view.querySelector('.profile-recent-uploads-status')?.textContent,
            'No uploads yet.',
            'profiles without uploads should show an empty state',
        );
        assertEqual(view.querySelectorAll('.profile-recent-uploads-grid > a').length, 0);
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
        assertEqual(
            view.querySelector('.profile-recent-uploads-status')?.textContent,
            'Recent uploads are unavailable.',
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
        assertEqual(view.querySelector('.profile-recent-uploads'), null, 'anonymous users have no recent uploads section');
        assertEqual(fetchMock.calls.length, 1, 'anonymous profiles should not fetch the approved count');
    } finally {
        restore();
    }
}
