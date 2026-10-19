/**
 * Internal audit log for moderators and administrators.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {AccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createPagination} from '../pagination.js';
import {createSearchBox} from '../searchBox.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage} from '../forms.js';

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
    if (!hasAuditLogAccess(session?.getUser?.())) {
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
    let currentPage = parsePositiveInteger(params.get('page'), 1);
    let currentQuery = (params.get('q') ?? '').trim();
    let requestId = 0;

    const listMessage = createMessageBox();
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading audit log…'),
    });
    const table = el('table', {
        className: 'internal-admin-table audit-log-table',
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
    const pagination = createPagination(currentPage, (pageNumber) => {
        currentPage = pageNumber;
        loadAuditLog(currentPage, currentQuery);
    });
    pagination.element.setAttribute('aria-label', 'Audit log pages');

    const search = createSearchBox({
        placeholder: 'Search audit log users',
        buttonText: 'Search',
        onSearch(queryText) {
            currentQuery = String(queryText ?? '').trim();
            search.setValue(currentQuery);
            currentPage = 1;
            loadAuditLog(currentPage, currentQuery);
        },
        onReset() {
            currentQuery = '';
            currentPage = 1;
            loadAuditLog(currentPage, currentQuery);
        },
    });
    search.setValue(currentQuery);

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
                        el('div', {className: 'internal-admin-table-wrap', children: table}),
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

    loadAuditLog(currentPage, currentQuery);
    return page;

    async function loadAuditLog(pageNumber, queryText) {
        const request = ++requestId;
        currentPage = pageNumber;
        clearMessage(listMessage);
        tableBody.replaceChildren(createTableStatusRow('Loading audit log…'));
        pagination.loading(pageNumber);

        const queryParams = new URLSearchParams();
        queryParams.set('page', String(pageNumber));
        if (queryText !== '') {
            queryParams.set('q', queryText);
        }

        try {
            const data = await get(`${AUDIT_LOG_URL}?${queryParams}`);
            if (request !== requestId) {
                return;
            }

            const entries = Array.isArray(data?.entries) ? data.entries : [];
            const total = Number.isSafeInteger(data?.total) && data.total >= 0 ? data.total : entries.length;
            const perPage = parsePositiveInteger(data?.perPage, PER_PAGE);
            const responsePage = parsePositiveInteger(data?.page, pageNumber);
            const pageCount = Math.max(1, Math.ceil(total / perPage));

            if (responsePage > pageCount) {
                currentPage = pageCount;
                loadAuditLog(currentPage, queryText);
                return;
            }

            currentPage = responsePage;
            pagination.update({page: responsePage, total, perPage});
            if (entries.length === 0) {
                tableBody.replaceChildren(createTableStatusRow(
                    queryText === '' ? 'No audit log entries found.' : 'No audit log entries match your search.',
                ));
                return;
            }
            tableBody.replaceChildren(...entries.map(createAuditLogRow));
        } catch (error) {
            if (request !== requestId) {
                return;
            }
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(listMessage, messageForStatus(error?.status ?? 0));
            tableBody.replaceChildren(createTableStatusRow('Could not load the audit log.'));
            pagination.update({page: pageNumber, total: 0, perPage: PER_PAGE});
        }
    }
}

function createAuditLogRow(entry) {
    const user = entry?.user ?? {};
    return el('tr', {
        className: 'internal-admin-table-row audit-log-table-row',
        attributes: {'data-audit-log-id': entry?.id ?? ''},
        children: [
            el('td', {className: 'audit-log-id-cell', children: entry?.id ?? ''}),
            el('td', {
                className: 'audit-log-user-cell',
                children: `#${user.id ?? ''} — ${user.loginName ?? ''} (${user.displayName ?? ''})`,
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
        className: 'internal-admin-table-status',
        children: el('td', {attributes: {colspan: '5'}, children: text}),
    });
}

function parsePositiveInteger(value, fallback) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : fallback;
    }
    if (!/^[1-9][0-9]*$/.test(value ?? '')) {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : fallback;
}

function hasAuditLogAccess(user) {
    return Number(user?.accessLevel ?? 0) >= AccessLevel.MOD;
}
