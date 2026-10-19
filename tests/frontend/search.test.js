import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, useFetch} from './mocks.js';
import {createSearchView} from '../../public/js/search.js';

function dispatchInput(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function dispatchKey(input, key) {
    input.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
}

function wait(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function mountSearch(fixture, handler, onSearch = () => {}) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const search = createSearchView({ onSearch });
    fixture.append(search.element);
    search.input.focus();
    return { ...search, fetchMock, restore };
}

export async function testSuggestionsUseCurrentCommaSegmentAndWaitForTwoCharacters(fixture) {
    const { input, fetchMock, restore } = mountSearch(fixture, () => ({ status: 200, body: { tags: ['bear'] } }));
    try {
        input.value = 'cats,b';
        dispatchInput(input);
        await wait(280);
        assertEqual(fetchMock.calls.length, 0, 'a one-character tag segment should not make a request');

        input.value = 'cats,be';
        dispatchInput(input);
        await wait(280);
        await settle();
        assertEqual(fetchMock.calls.length, 1, 'a two-character segment should request suggestions');
        assertEqual(fetchMock.calls[0].url, '/api/tags/suggest?q=be');
    } finally {
        restore();
    }
}

export async function testSuggestionTriggerCountsWhitespaceInCurrentSegment() {
    const {input, fetchMock, restore} = mountSearch(
        document.getElementById('test-fixture'),
        () => ({status: 200, body: {tags: []}}),
    );
    try {
        input.value = 'a';
        dispatchInput(input);
        await wait(280);
        assertEqual(fetchMock.calls.length, 0, 'one character should not request suggestions');

        input.value = 'a ';
        dispatchInput(input);
        await wait(280);
        await settle();
        assertEqual(fetchMock.calls.length, 1, 'the trailing space should count as the second character');
        assertEqual(fetchMock.calls[0].url, '/api/tags/suggest?q=a%20');
    } finally {
        restore();
    }
}

export async function testSuggestionRequestsAreDebounced(fixture) {
    const { input, fetchMock, restore } = mountSearch(fixture, () => ({ status: 200, body: { tags: [] } }));
    try {
        input.value = 'ca';
        dispatchInput(input);
        await wait(120);
        input.value = 'cat';
        dispatchInput(input);
        await wait(160);
        assertEqual(fetchMock.calls.length, 0, 'the request should wait for a quiet debounce period');
        await wait(120);
        await settle();
        assertEqual(fetchMock.calls.length, 1, 'only the latest segment should be requested');
        assertEqual(fetchMock.calls[0].url, '/api/tags/suggest?q=cat');
    } finally {
        restore();
    }
}

export async function testSuggestionListIsLimitedToFifteen(fixture) {
    const tags = Array.from({ length: 20 }, (_, index) => `tag-${index}`);
    const { input, fetchMock, suggestions, restore } = mountSearch(
        fixture,
        () => ({ status: 200, body: { tags } }),
    );
    try {
        input.value = 'ta';
        dispatchInput(input);
        await wait(280);
        await settle();
        assertEqual(fetchMock.calls.length, 1);
        assertEqual(suggestions.querySelectorAll('[role="option"]').length, 15);
        assertEqual(suggestions.querySelector('[data-tag="tag-0"]')?.textContent, 'tag-0', 'API order should be preserved');
        assertEqual(suggestions.querySelector('[data-tag="tag-14"]')?.textContent, 'tag-14');
    } finally {
        restore();
    }
}

export async function testArrowNavigationAndEnterInsertSelectedTag(fixture) {
    const { input, suggestions, restore } = mountSearch(
        fixture,
        () => ({ status: 200, body: { tags: ['cat', 'caterpillar'] } }),
    );
    try {
        input.value = 'cats, ca';
        dispatchInput(input);
        await wait(280);
        await settle();
        assertEqual(suggestions.querySelectorAll('[role="option"]').length, 2);

        const catOption = suggestions.querySelector('[data-tag="cat"]');
        const caterpillarOption = suggestions.querySelector('[data-tag="caterpillar"]');
        dispatchKey(input, 'ArrowDown');
        assertTrue(catOption.classList.contains('search-suggestion--active'));
        dispatchKey(input, 'ArrowDown');
        assertTrue(caterpillarOption.classList.contains('search-suggestion--active'));
        dispatchKey(input, 'ArrowUp');
        assertTrue(catOption.classList.contains('search-suggestion--active'));
        assertEqual(input.getAttribute('aria-activedescendant'), catOption.id);

        dispatchKey(input, 'ArrowDown');
        dispatchKey(input, 'Enter');
        assertEqual(input.value, 'cats, caterpillar, ');
        assertTrue(suggestions.hidden, 'selecting a suggestion should close the list');
        assertEqual(document.activeElement, input, 'selecting a suggestion should keep focus in the input');
        assertEqual(input.selectionStart, input.value.length, 'caret should remain after the inserted comma');
    } finally {
        restore();
    }
}

export function testClickingSuggestionInsertsItAndKeepsFocus(fixture) {
    const { input, suggestions, restore } = mountSearch(
        fixture,
        () => ({ status: 200, body: { tags: ['bird'] } }),
    );
    try {
        input.value = 'bi';
        dispatchInput(input);
        return wait(280).then(settle).then(() => {
            suggestions.querySelector('[data-tag="bird"]').click();
            assertEqual(input.value, 'bird, ');
            assertTrue(suggestions.hidden);
            assertEqual(document.activeElement, input);
        }).finally(restore);
    } catch (error) {
        restore();
        throw error;
    }
}

export function testMagnifierButtonSubmitsNormalizedTagFilters(fixture) {
    const searches = [];
    const { input, element, restore } = mountSearch(fixture, () => ({ status: 200, body: { tags: [] } }), (tags) => {
        searches.push(tags);
    });
    try {
        const button = element.querySelector('.search-submit');
        assertEqual(button.getAttribute('aria-label'), 'Search tags');
        assertTrue(button.querySelector('.search-icon') !== null, 'the compact submit control should contain a magnifier');
        input.value = ' red, green ';
        button.click();
        assertEqual(searches[0], 'red,green');

        input.value = '';
        button.click();
        assertEqual(searches[1], '', 'an empty search should submit no tag filter');
    } finally {
        restore();
    }
}

export function testSearchInputHasNoVisibleOverflow(fixture) {
    const { input, restore } = mountSearch(fixture, () => ({ status: 200, body: { tags: [] } }));
    try {
        const style = getComputedStyle(input);
        assertTrue(['hidden', 'clip'].includes(style.overflowX), 'the search field should hide horizontal overflow');
        assertEqual(style.whiteSpace, 'nowrap');
    } finally {
        restore();
    }
}
