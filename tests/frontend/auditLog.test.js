import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, submitForm, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createAuditLogView} from '../../public/js/views/auditLog.js';

const ENTRIES = [
    {
        id: 80,
        user: {id: 3, loginName: 'alice', displayName: 'Alice Smith'},
        actionType: 1,
        context: 'Added tag "landscape" to image #12',
        time: '2026-10-20 12:30:00',
    },
    {
        id: 79,
        user: {id: 7, loginName: 'bob', displayName: 'Bob Jones'},
        actionType: 2,
        context: 'Removed tag "portrait" from image #15',
        time: '2026-10-20 12:10:00',
    },
    {
        id: 78,
        user: {id: 3, loginName: 'alice', displayName: 'Alice Smith'},
        actionType: 99,
        context: 'Unrecognized action context',
        time: '2026-10-20 11:50:00',
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
    const view = createAuditLogView({
        session,
        navigate: (path) => navigateCalls.push(path),
        query: new URLSearchParams(query),
    });
    await settle();
    return {view, fetchMock, navigateCalls, restore};
}

function defaultResponse(url, options = {}) {
    const parsed = new URL(url, 'http://localhost');
    if (parsed.pathname === '/api/audit-log' && (options.method ?? 'GET') === 'GET') {
        return {
            status: 200,
            body: {
                entries: ENTRIES,
                page: Number(parsed.searchParams.get('page') ?? 1),
                perPage: 30,
                total: 61,
            },
        };
    }
    return {status: 200, body: null};
}

function auditLogCalls(fetchMock) {
    return fetchMock.calls.filter((call) => (
        new URL(call.url, 'http://localhost').pathname === '/api/audit-log'
        && (call.options.method ?? 'GET') === 'GET'
    ));
}

function queryParams(call) {
    return new URL(call.url, 'http://localhost').searchParams;
}

export async function testAuditLogIsDeniedBelowModeratorAccess() {
    for (const accessLevel of [null, 100, 400]) {
        const {view, fetchMock, navigateCalls, restore} = await createLoadedView((url) => {
            if (url.startsWith('/api/audit-log')) {
                throw new Error('the audit log endpoint must not be fetched without moderator access');
            }
            return undefined;
        }, {accessLevel});
        try {
            const denied = view.querySelector('.audit-log-denied-page .internal-admin-denied');
            assertTrue(denied !== null, 'lower access should show a denied notice');
            assertTrue(denied.textContent.includes('permission'));
            assertEqual(view.querySelector('.page-title'), null, 'the internal page should have no page title');
            assertEqual(auditLogCalls(fetchMock).length, 0, 'denied audit pages must not call the API');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
        } finally {
            restore();
        }
    }
}

export async function testAuditLogTableShowsUsersAndMapsActionNames() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        assertEqual(view.querySelector('.page-title'), null, 'the audit log page has no title');
        assertEqual(
            [...view.querySelectorAll('.audit-log-table thead th')].map((cell) => cell.textContent).join('|'),
            'ID|User|Action|Context|Time',
        );
        assertEqual(view.querySelectorAll('.audit-log-table-row').length, 3);

        const added = view.querySelector('.audit-log-table-row[data-audit-log-id="80"]');
        assertEqual(added.querySelector('.audit-log-id-cell').textContent, '80');
        assertEqual(added.querySelector('.audit-log-user-cell').textContent, '#3 — alice (Alice Smith)');
        assertEqual(added.querySelector('.audit-log-action-cell').textContent, 'Tag added');
        assertEqual(added.querySelector('.audit-log-context-cell').textContent, ENTRIES[0].context);
        assertEqual(added.querySelector('.audit-log-time-cell').textContent, ENTRIES[0].time);
        assertEqual(
            view.querySelector('.audit-log-table-row[data-audit-log-id="79"] .audit-log-action-cell').textContent,
            'Tag removed',
        );
        assertEqual(
            view.querySelector('.audit-log-table-row[data-audit-log-id="78"] .audit-log-action-cell').textContent,
            'Unknown',
        );
        assertEqual(auditLogCalls(fetchMock).length, 1);
    } finally {
        restore();
    }
}

export async function testAuditLogSearchResetAndPaginationKeepTheExpectedQuery() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        const searchForm = view.querySelector('.search-box');
        const searchInput = view.querySelector('.search-box-input');
        searchInput.value = 'alice';
        await submitForm(searchForm);

        const filteredCall = auditLogCalls(fetchMock).at(-1);
        assertEqual(queryParams(filteredCall).get('q'), 'alice');
        assertEqual(queryParams(filteredCall).get('page'), '1');

        view.querySelector('.pagination-button--next').click();
        await settle();
        const paginatedCall = auditLogCalls(fetchMock).at(-1);
        assertEqual(queryParams(paginatedCall).get('page'), '2');
        assertEqual(queryParams(paginatedCall).get('q'), 'alice', 'pagination should preserve the current search');

        view.querySelector('.search-box-reset').click();
        await settle();
        const resetCall = auditLogCalls(fetchMock).at(-1);
        assertEqual(queryParams(resetCall).get('page'), '1');
        assertEqual(queryParams(resetCall).get('q'), null, 'reset should reload without a q filter');
        assertEqual(searchInput.value, '', 'reset should clear the search field');
    } finally {
        restore();
    }
}
