import {assertEqual, assertTrue} from './assert.js';
import {el} from '../../public/js/dom.js';
import {createPagination} from '../../public/js/pagination.js';
import {createPaginatedTable, parsePositiveInteger} from '../../public/js/paginatedTable.js';

function createTable(fetchPage, {initialPage = 1, initialQuery = ''} = {}) {
    const body = document.createElement('tbody');
    const listMessage = document.createElement('p');
    const pagination = createPagination(initialPage, () => {});
    const table = createPaginatedTable({
        tableBody: body,
        listMessage,
        pagination,
        fetchPage,
        getItems: (data) => data?.rows,
        renderRow: (row) => el('tr', {className: 'data-row', children: row}),
        renderStatusRow: (text) => el('tr', {className: 'status-row', children: text}),
        messages: {
            loading: 'Loading rows…',
            empty: 'No rows.',
            emptySearch: 'No rows match.',
            error: 'Could not load rows.',
        },
        defaultPerPage: 10,
        initialPage,
        initialQuery,
    });
    return {body, listMessage, pagination, table};
}

export function testParsePositiveIntegerUsesSafeFallbacks() {
    assertEqual(parsePositiveInteger('3', 1), 3);
    assertEqual(parsePositiveInteger(4, 1), 4);
    assertEqual(parsePositiveInteger('0', 1), 1);
    assertEqual(parsePositiveInteger('01', 1), 1);
    assertEqual(parsePositiveInteger('9007199254740992', 1), 1);
}

export async function testPaginatedTableUpdatesRowsAndQueryState() {
    const {body, pagination, table} = createTable(async ({page, query}) => ({
        rows: [`${query}:${page}`],
        page,
        perPage: 10,
        total: 21,
    }), {initialPage: 2, initialQuery: 'old'});

    await table.load(table.getPage(), table.getQuery());
    assertEqual(body.querySelector('.data-row')?.textContent, 'old:2');
    assertEqual(pagination.element.querySelector('.pagination-label')?.textContent, 'Page 2 of 3');
    assertEqual(table.getPage(), 2);
    assertEqual(table.getQuery(), 'old');

    await table.setQuery(' new ');
    assertEqual(body.querySelector('.data-row')?.textContent, 'new:1');
    assertEqual(table.getQuery(), 'new');
    assertEqual(table.getPage(), 1);
}

export async function testPaginatedTableCorrectsOutOfRangePagesAndRendersEmptyRows() {
    const requests = [];
    const {body, pagination, table} = createTable(async ({page}) => {
        requests.push(page);
        return {rows: [], page, perPage: 10, total: 15};
    });

    await table.setPage(4);
    assertEqual(requests.join('|'), '4|2');
    assertEqual(table.getPage(), 2);
    assertEqual(body.querySelector('.status-row')?.textContent, 'No rows.');
    assertEqual(pagination.element.querySelector('.pagination-label')?.textContent, 'Page 2 of 2');

    await table.setQuery('missing');
    assertEqual(body.querySelector('.status-row')?.textContent, 'No rows match.');
}

export async function testPaginatedTableIgnoresStaleResponses() {
    const requests = [];
    const {body, table} = createTable(({query}) => new Promise((resolve) => {
        requests.push({query, resolve});
    }));
    const first = table.setQuery('first');
    const second = table.setQuery('second');
    requests[1].resolve({rows: ['latest'], page: 1, perPage: 10, total: 1});
    await second;
    requests[0].resolve({rows: ['stale'], page: 1, perPage: 10, total: 1});
    await first;
    assertEqual(body.querySelector('.data-row')?.textContent, 'latest');
}

export async function testPaginatedTableShowsLoadingAndErrorRows() {
    let reject;
    const {body, listMessage, table} = createTable(() => new Promise((resolve, rejectRequest) => {
        reject = rejectRequest;
    }));
    const pending = table.load();
    assertEqual(body.querySelector('.status-row')?.textContent, 'Loading rows…');
    reject({status: 500});
    await pending;
    assertEqual(body.querySelector('.status-row')?.textContent, 'Could not load rows.');
    assertTrue(!listMessage.hidden);
}
