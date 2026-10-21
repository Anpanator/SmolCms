/**
 * Internal audit log for moderators and administrators.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createPagination} from '../pagination.js';
import {createPaginatedTable, parsePositiveInteger} from '../paginatedTable.js';
import {createSearchBox} from '../searchBox.js';
import {createMessageBox} from '../forms.js';
import {userProfileHref} from '../routes.js';

const AUDIT_LOG_URL = '/api/audit-log';
const PER_PAGE = 30;
const ACTION_NAMES = new Map([
    [1, 'Tag added'],
    [2, 'Tag removed'],
]);

/**
 * @param {{session: *, navigate?: (path: string) => void, query?: URLSearchParams|string}} [context]
 */
export function createAuditLogView({
    session,
    navigate = () => {},
    query = new URLSearchParams(window.location.search),
} = {}) {
    if (!hasAccessLevel(session?.getUser?.(), AccessLevel.MOD)) {
        return el('section', {
            className: 'internal-admin-denied-page audit-log-denied-page',
            attributes: {tabindex: '-1', 'aria-label': 'Audit log'},
            children: el('p', {
                className: 'notice notice--warning internal-admin-denied',
                children: 'You do not have permission to view the audit log.',
            }),
        });
    }

    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query);
    const initialPage = parsePositiveInteger(params.get('page'), 1);
    const initialQuery = (params.get('q') ?? '').trim();

    const listMessage = createMessageBox();
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading audit log…'),
    });
    const table = el('table', {
        className: 'data-table internal-admin-table audit-log-table',
        attributes: {'aria-label': 'Audit log entries'},
        children: [
            el('thead', {
                children: el('tr', {
                    children: ['ID', 'User', 'Action', 'Context', 'Time'].map((label) => el('th', {
                        attributes: {scope: 'col'},
                        children: label,
                    })),
                }),
            }),
            tableBody,
        ],
    });
    let paginatedTable;
    const pagination = createPagination(initialPage, (pageNumber) => paginatedTable.setPage(pageNumber));
    pagination.element.setAttribute('aria-label', 'Audit log pages');

    const search = createSearchBox({
        placeholder: 'Search audit log users',
        buttonText: 'Search',
        onSearch(queryText) {
            const currentQuery = String(queryText ?? '').trim();
            search.setValue(currentQuery);
            paginatedTable.setQuery(currentQuery);
        },
        onReset() {
            paginatedTable.setQuery('');
        },
    });
    search.setValue(initialQuery);

    const page = el('section', {
        className: 'internal-admin-page audit-log-page',
        attributes: {'aria-label': 'Audit log'},
        children: el('div', {
            className: 'internal-admin-workspace',
            children: [
                el('section', {
                    className: 'internal-admin-list-panel audit-log-list-panel',
                    attributes: {'aria-label': 'Audit log entries'},
                    children: [
                        listMessage,
                        el('div', {className: 'data-table-wrap internal-admin-table-wrap', children: table}),
                        pagination.element,
                    ],
                }),
                el('aside', {
                    className: 'internal-admin-sidebar audit-log-sidebar',
                    attributes: {'aria-label': 'Search audit log'},
                    children: search.element,
                }),
            ],
        }),
    });

    paginatedTable = createPaginatedTable({
        tableBody,
        listMessage,
        pagination,
        fetchPage({page: pageNumber, query: queryText}) {
            const queryParams = new URLSearchParams();
            queryParams.set('page', String(pageNumber));
            if (queryText !== '') {
                queryParams.set('q', queryText);
            }
            return get(`${AUDIT_LOG_URL}?${queryParams}`);
        },
        getItems: (data) => data?.entries,
        renderRow: createAuditLogRow,
        renderStatusRow: createTableStatusRow,
        messages: {
            loading: 'Loading audit log…',
            empty: 'No audit log entries found.',
            emptySearch: 'No audit log entries match your search.',
            error: 'Could not load the audit log.',
        },
        defaultPerPage: PER_PAGE,
        initialPage,
        initialQuery,
        navigate,
    });
    paginatedTable.load();
    return page;
}

function createAuditLogRow(entry) {
    const user = entry?.user ?? {};
    const displayName = user.displayName ?? '';
    const profileHref = userProfileHref(user.id);
    return el('tr', {
        className: 'internal-admin-table-row audit-log-table-row',
        attributes: {'data-audit-log-id': entry?.id ?? ''},
        children: [
            el('td', {className: 'audit-log-id-cell', children: entry?.id ?? ''}),
            el('td', {
                className: 'audit-log-user-cell',
                children: [
                    `#${user.id ?? ''} — ${user.loginName ?? ''} (`,
                    profileHref === null
                        ? displayName
                        : el('a', {
                            className: 'user-profile-link',
                            attributes: {href: profileHref},
                            children: displayName,
                        }),
                    ')',
                ],
            }),
            el('td', {
                className: 'audit-log-action-cell',
                children: ACTION_NAMES.get(entry?.actionType) ?? 'Unknown',
            }),
            el('td', {className: 'audit-log-context-cell', children: entry?.context ?? ''}),
            el('td', {className: 'audit-log-time-cell', children: entry?.time ?? ''}),
        ],
    });
}

function createTableStatusRow(text) {
    return el('tr', {
        className: 'internal-admin-table-status data-table-status',
        children: el('td', {attributes: {colspan: '5'}, children: text}),
    });
}
