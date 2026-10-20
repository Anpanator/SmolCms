/** Shared page/query state and asynchronous rendering for paginated tables. */
import {clearMessage, isAuthError, messageForStatus, showMessage} from './forms.js';

/** Parse a positive safe integer or return the supplied fallback. */
export function parsePositiveInteger(value, fallback) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : fallback;
    }
    if (!/^[1-9][0-9]*$/.test(value ?? '')) {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : fallback;
}

/**
 * @param {{
 *   tableBody: HTMLTableSectionElement,
 *   listMessage: HTMLElement,
 *   pagination: {loading: (page: number) => void, update: (state: {page: number, total: number, perPage: number}) => void},
 *   fetchPage: (state: {page: number, query: string}) => Promise<object>,
 *   getItems: (data: object) => Array<*>,
 *   renderRow: (item: *) => Node,
 *   renderStatusRow: (text: string) => Node,
 *   messages: {loading: string, empty: string, emptySearch: string, error: string},
 *   defaultPerPage: number,
 *   initialPage?: number,
 *   initialQuery?: string,
 *   onItems?: (items: Array<*>, data: object) => void,
 *   navigate?: (path: string) => void,
 * }} options
 */
export function createPaginatedTable({
    tableBody,
    listMessage,
    pagination,
    fetchPage,
    getItems,
    renderRow,
    renderStatusRow,
    messages,
    defaultPerPage,
    initialPage = 1,
    initialQuery = '',
    onItems = () => {},
    navigate = () => {},
}) {
    let currentPage = parsePositiveInteger(initialPage, 1);
    let currentQuery = String(initialQuery ?? '').trim();
    let requestId = 0;
    let items = [];

    return {
        load,
        reload: () => load(currentPage, currentQuery),
        setPage: (page) => load(page, currentQuery),
        setQuery: (query) => load(1, String(query ?? '').trim()),
        getPage: () => currentPage,
        getQuery: () => currentQuery,
        getItems: () => items,
    };

    async function load(page, query) {
        const requestedPage = parsePositiveInteger(page, currentPage);
        const requestedQuery = String(query ?? '').trim();
        const request = ++requestId;
        currentPage = requestedPage;
        currentQuery = requestedQuery;
        clearMessage(listMessage);
        tableBody.replaceChildren(renderStatusRow(messages.loading));
        pagination.loading(requestedPage);

        try {
            const data = await fetchPage({page: requestedPage, query: requestedQuery});
            if (request !== requestId) {
                return;
            }

            const responseItems = getItems(data);
            const loadedItems = Array.isArray(responseItems) ? responseItems : [];
            const total = Number.isSafeInteger(data?.total) && data.total >= 0 ? data.total : loadedItems.length;
            const perPage = parsePositiveInteger(data?.perPage, defaultPerPage);
            const responsePage = parsePositiveInteger(data?.page, requestedPage);
            const pageCount = Math.max(1, Math.ceil(total / perPage));

            if (responsePage > pageCount) {
                currentPage = pageCount;
                return load(pageCount, requestedQuery);
            }

            currentPage = responsePage;
            items = loadedItems;
            onItems(items, data);
            pagination.update({page: responsePage, total, perPage});
            if (items.length === 0) {
                const message = requestedQuery === '' ? messages.empty : messages.emptySearch;
                tableBody.replaceChildren(renderStatusRow(message));
                return;
            }
            tableBody.replaceChildren(...items.map(renderRow));
        } catch (error) {
            if (request !== requestId) {
                return;
            }
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(listMessage, messageForStatus(error?.status ?? 0));
            tableBody.replaceChildren(renderStatusRow(messages.error));
            pagination.update({page: requestedPage, total: 0, perPage: defaultPerPage});
        }
    }
}
