import {assertEqual, assertTrue} from './assert.js';
import {createPagination} from '../../public/js/pagination.js';

export function testPaginationUpdatesPageStateAndCallsAdjacentPages() {
    const requestedPages = [];
    const pagination = createPagination(2, (page) => requestedPages.push(page));
    const previous = pagination.element.querySelector('.pagination-button--previous');
    const next = pagination.element.querySelector('.pagination-button--next');

    assertEqual(pagination.element.className, 'pagination');
    assertEqual(pagination.element.getAttribute('aria-label'), 'Pages');
    assertEqual(pagination.element.querySelector('.pagination-label').textContent, 'Loading…');
    assertTrue(previous.disabled);
    assertTrue(next.disabled);

    pagination.update({page: 2, total: 51, perPage: 25});
    assertEqual(pagination.element.querySelector('.pagination-label').textContent, 'Page 2 of 3');
    assertTrue(!previous.disabled);
    assertTrue(!next.disabled);
    previous.click();
    next.click();
    assertEqual(JSON.stringify(requestedPages), JSON.stringify([1, 3]));

    pagination.update({page: 3, total: 51, perPage: 25});
    assertTrue(next.disabled);
}

export function testPaginationLoadingDisablesControlsUntilUpdated() {
    const requestedPages = [];
    const pagination = createPagination(1, (page) => requestedPages.push(page));
    const previous = pagination.element.querySelector('.pagination-button--previous');
    const next = pagination.element.querySelector('.pagination-button--next');

    pagination.update({page: 1, total: 201, perPage: 100});
    assertTrue(!next.disabled);
    pagination.loading(2);
    assertEqual(pagination.element.querySelector('.pagination-label').textContent, 'Loading…');
    assertTrue(previous.disabled);
    assertTrue(next.disabled);
    next.click();
    assertEqual(requestedPages.length, 0, 'loading controls must not request another page');

    pagination.update({page: 2, total: 201, perPage: 100});
    assertEqual(pagination.element.querySelector('.pagination-label').textContent, 'Page 2 of 3');
    assertTrue(!previous.disabled);
    assertTrue(!next.disabled);
}
