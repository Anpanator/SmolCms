import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, submitForm, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createUsersView} from '../../public/js/views/users.js';

const USERS = [
    {
        id: 2,
        loginName: 'amy',
        displayName: 'Amy Adams',
        accessLevel: 400,
        registerDate: '2026-10-12 09:30:00',
    },
    {
        id: 3,
        loginName: 'admin',
        displayName: 'Site Administrator',
        accessLevel: 600,
        registerDate: '2026-10-11 08:20:00',
    },
    {
        id: 4,
        loginName: 'mod',
        displayName: 'Morgan Moderator',
        accessLevel: 500,
        registerDate: '2026-10-10 07:10:00',
    },
];

async function createLoadedView(handler = () => undefined, {accessLevel = 500, query = ''} = {}) {
    const fetchMock = mockFetch(async (url, options) => {
        if (url === '/api/session') {
            return sessionResponse(accessLevel === null ? null : testUser({accessLevel}));
        }
        const response = await handler(url, options);
        return response === undefined ? defaultResponse(url, options) : response;
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    const navigateCalls = [];
    const view = createUsersView({
        session,
        navigate: (path) => navigateCalls.push(path),
        query: new URLSearchParams(query),
    });
    await settle();
    return {view, fetchMock, navigateCalls, restore};
}

function defaultResponse(url, options = {}) {
    const parsed = new URL(url, 'http://localhost');
    if (parsed.pathname === '/api/users' && (options.method ?? 'GET') === 'GET') {
        return {
            status: 200,
            body: {
                users: USERS,
                page: Number(parsed.searchParams.get('page') ?? 1),
                perPage: 30,
                total: 61,
            },
        };
    }
    return {status: 200, body: null};
}

function userListCalls(fetchMock) {
    return fetchMock.calls.filter((call) => (
        new URL(call.url, 'http://localhost').pathname === '/api/users'
        && (call.options.method ?? 'GET') === 'GET'
    ));
}

function queryParams(call) {
    return new URL(call.url, 'http://localhost').searchParams;
}

export async function testUsersPageIsDeniedBelowModeratorAccess() {
    for (const accessLevel of [null, 100, 400]) {
        const {view, fetchMock, navigateCalls, restore} = await createLoadedView((url) => {
            if (url.startsWith('/api/users')) {
                throw new Error('the users endpoint must not be fetched without moderator access');
            }
            return undefined;
        }, {accessLevel});
        try {
            const denied = view.querySelector('.users-denied-page .internal-admin-denied');
            assertTrue(denied !== null, 'lower access should show a denied notice');
            assertTrue(denied.textContent.includes('permission'));
            assertEqual(view.querySelector('.page-title'), null, 'the internal page should have no page title');
            assertEqual(userListCalls(fetchMock).length, 0, 'denied users pages must not call the API');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
        } finally {
            restore();
        }
    }
}

export async function testUserTableRendersAccessNamesAndProtectsHigherLevelRows() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        assertEqual(view.querySelector('.page-title'), null, 'the user management page has no title');
        assertTrue(view.querySelector('.users-table')?.classList.contains('data-table'), 'admin tables should use the shared table component');
        assertTrue(view.querySelector('.internal-admin-table-wrap')?.classList.contains('data-table-wrap'));
        assertEqual(
            [...view.querySelectorAll('.users-table thead th')].map((cell) => cell.textContent).join('|'),
            'ID|Login name|Display name|Access level|Register date|Actions',
        );
        assertEqual(view.querySelectorAll('.users-table-row').length, 3);

        const row = view.querySelector('.users-table-row[data-user-id="2"]');
        assertEqual(row.querySelector('.users-id-cell').textContent, '2');
        assertEqual(row.querySelector('.users-login-cell').textContent, 'amy');
        assertEqual(row.querySelector('.users-display-name-cell').textContent, 'Amy Adams');
        assertEqual(row.querySelector('.users-display-name-cell a')?.getAttribute('href'), '/user?id=2');
        assertEqual(row.querySelector('.users-access-level-cell').textContent, 'Wizard');
        assertEqual(row.querySelector('.users-register-date-cell').textContent, '2026-10-12 09:30:00');
        assertTrue(row.querySelector('.users-edit-button') !== null, 'users at or below the current level can be edited');

        const adminRow = view.querySelector('.users-table-row[data-user-id="3"]');
        assertEqual(adminRow.querySelector('.users-access-level-cell').textContent, 'Admin');
        assertEqual(adminRow.querySelector('.users-edit-button'), null, 'a MOD must not be able to edit ADMIN rows');
        assertEqual(userListCalls(fetchMock).length, 1);
    } finally {
        restore();
    }
}

export async function testEditUserModalPostsAndUpdatesTheTableRow() {
    const {view, fetchMock, restore} = await createLoadedView((url, options) => {
        if (url === '/api/users/update?id=2' && options.method === 'POST') {
            return {
                status: 200,
                body: {user: {id: 2, loginName: 'amy-updated', displayName: 'Updated Amy', accessLevel: 300}},
            };
        }
        return undefined;
    });
    try {
        view.querySelector('.users-table-row[data-user-id="2"] .users-edit-button').click();
        const modal = document.querySelector('.modal-backdrop');
        assertTrue(modal !== null, 'editing should open a modal');
        assertEqual(modal.getAttribute('role'), 'dialog');
        assertTrue(modal.querySelector('input[name="id"]').readOnly, 'the user ID is read-only');
        assertEqual(modal.querySelector('input[name="loginName"]').value, 'amy', 'the login name should be prefilled');
        assertTrue(!modal.querySelector('input[name="loginName"]').readOnly, 'the login name should be editable');
        assertTrue(modal.querySelector('input[name="registerDate"]').readOnly, 'the register date is read-only');
        assertEqual(
            [...modal.querySelectorAll('select[name="accessLevel"] option')].map((option) => option.value).join('|'),
            '100|200|300|400|500|600',
        );

        modal.querySelector('input[name="loginName"]').value = 'amy-updated';
        modal.querySelector('input[name="displayName"]').value = 'Updated Amy';
        modal.querySelector('select[name="accessLevel"]').value = '300';
        await submitForm(modal.querySelector('.user-edit-form'));

        const updateCall = fetchMock.calls.find((call) => call.url === '/api/users/update?id=2');
        assertTrue(updateCall !== undefined, 'saving should call the user update endpoint');
        assertEqual(updateCall.options.method, 'POST');
        assertEqual(
            JSON.stringify(JSON.parse(updateCall.options.body)),
            JSON.stringify({loginName: 'amy-updated', displayName: 'Updated Amy', accessLevel: 300}),
        );
        const updatedRow = view.querySelector('.users-table-row[data-user-id="2"]');
        assertEqual(updatedRow.querySelector('.users-login-cell').textContent, 'amy-updated');
        assertEqual(updatedRow.querySelector('.users-display-name-cell').textContent, 'Updated Amy');
        assertEqual(updatedRow.querySelector('.users-access-level-cell').textContent, 'Master');
        assertEqual(updatedRow.querySelector('.users-register-date-cell').textContent, USERS[0].registerDate);
        assertEqual(document.querySelector('.modal-backdrop'), null, 'successful save should close the modal');
        assertTrue(view.querySelector('.form-message--success')?.textContent.includes('updated'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testEditUserValidatesDisplayNameAndShowsForbiddenInModal() {
    const {view, fetchMock, restore} = await createLoadedView((url, options) => (
        url === '/api/users/update?id=2' && options.method === 'POST'
            ? {status: 403, body: null}
            : undefined
    ));
    try {
        view.querySelector('.users-table-row[data-user-id="2"] .users-edit-button').click();
        const modal = document.querySelector('.modal-backdrop');
        const form = modal.querySelector('.user-edit-form');
        const loginName = modal.querySelector('input[name="loginName"]');
        const displayName = modal.querySelector('input[name="displayName"]');
        displayName.value = '   ';
        await submitForm(form);
        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 0);
        assertEqual(modal.querySelector('.field-error:not([hidden])')?.textContent, 'Display name is required.');
        assertEqual(modal.querySelector('.form-message:not([hidden])')?.textContent, 'Display name is required.');

        displayName.value = 'Valid Name';
        loginName.value = '   ';
        await submitForm(form);
        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 0);
        assertEqual(loginName.parentElement.querySelector('.field-error:not([hidden])')?.textContent, 'Login name is required.');
        assertEqual(modal.querySelector('.form-message:not([hidden])')?.textContent, 'Login name is required.');

        loginName.value = 'valid-login';
        await submitForm(form);
        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 1);
        assertTrue(modal.querySelector('.form-message:not([hidden])')?.textContent.includes('permission'));
        assertTrue(document.querySelector('.modal-backdrop') !== null, 'a forbidden save should leave the modal open');
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testDuplicateLoginNameShowsConflictInEditModal() {
    const {view, fetchMock, restore} = await createLoadedView((url, options) => (
        url === '/api/users/update?id=2' && options.method === 'POST'
            ? {status: 409, body: null}
            : undefined
    ));
    try {
        view.querySelector('.users-table-row[data-user-id="2"] .users-edit-button').click();
        const modal = document.querySelector('.modal-backdrop');
        modal.querySelector('input[name="loginName"]').value = 'already-taken';
        await submitForm(modal.querySelector('.user-edit-form'));

        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 1);
        assertTrue(
            modal.querySelector('.form-message:not([hidden])')?.textContent.toLowerCase().includes('login name')
            && modal.querySelector('.form-message:not([hidden])')?.textContent.toLowerCase().includes('taken'),
            'a duplicate login name should show a clear conflict message',
        );
        assertTrue(document.querySelector('.modal-backdrop') !== null, 'a conflict should leave the modal open');
        assertEqual(
            view.querySelector('.users-table-row[data-user-id="2"] .users-login-cell').textContent,
            'amy',
            'a conflict should not update the table row',
        );
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testUserSearchResetAndPaginationKeepTheExpectedQuery() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        const searchForm = view.querySelector('.search-box');
        const searchInput = view.querySelector('.search-box-input');
        searchInput.value = 'amy';
        await submitForm(searchForm);

        const filteredCall = userListCalls(fetchMock).at(-1);
        assertEqual(queryParams(filteredCall).get('q'), 'amy');
        assertEqual(queryParams(filteredCall).get('page'), '1');

        view.querySelector('.pagination-button--next').click();
        await settle();
        const paginatedCall = userListCalls(fetchMock).at(-1);
        assertEqual(queryParams(paginatedCall).get('page'), '2');
        assertEqual(queryParams(paginatedCall).get('q'), 'amy', 'pagination should preserve the current search');

        view.querySelector('.search-box-reset').click();
        await settle();
        const resetCall = userListCalls(fetchMock).at(-1);
        assertEqual(queryParams(resetCall).get('page'), '1');
        assertEqual(queryParams(resetCall).get('q'), null, 'reset should reload without a q filter');
        assertEqual(searchInput.value, '', 'reset should clear the search field');
    } finally {
        restore();
    }
}
