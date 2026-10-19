import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, testUser, useFetch} from './mocks.js';
import {createTagList} from '../../public/js/tagList.js';

function dispatchKey(input, key) {
    input.dispatchEvent(new KeyboardEvent('keydown', {key, bubbles: true, cancelable: true}));
}

function wait(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

export function testAnonymousTagListStaysReadOnly() {
    const list = createTagList({session: {getUser: () => null}});
    list.update({id: 7, tags: ['nature', 'sky']});

    assertEqual(list.element.querySelector('.tag-add-row'), null);
    assertEqual(list.element.querySelector('.tag-remove-button'), null);
    assertEqual(list.element.querySelectorAll('.tags-link').length, 2);
    assertEqual(list.element.querySelector('.tags-link[data-tag-name="nature"]')?.getAttribute('href'), '/?tags=nature');
}

export function testLoggedInTagListRendersSortedEditableTags(fixture) {
    const list = createTagList({session: {getUser: () => testUser()}});
    fixture.append(list.element);
    list.update({id: 7, tags: ['zebra', 'ant', 'zebra']});

    const tags = [...list.element.querySelectorAll('.tags-item')];
    assertEqual(tags.length, 2, 'tag names should be deduplicated');
    assertEqual(tags.map((item) => item.querySelector('.tags-link')?.textContent).join('|'), 'ant|zebra');
    assertTrue(tags[0].firstElementChild.matches('.tag-remove-button'), 'the remove icon should come before its tag link');
    assertEqual(list.element.querySelectorAll('.tag-remove-button').length, 2);
    assertTrue(!list.element.querySelector('.tag-add-submit').disabled, 'the Add button should be enabled after an image is set');
}

export async function testLoggedInTagListUsesSharedTagCompletion(fixture) {
    const fetchMock = mockFetch(() => ({status: 200, body: {tags: ['cat']}}));
    const restore = useFetch(fetchMock);
    const list = createTagList({session: {getUser: () => testUser()}});
    fixture.append(list.element);
    list.update({id: 7, tags: []});
    const input = list.element.querySelector('.tag-add-input');
    try {
        input.value = 'animals, ca';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        await wait(280);
        await settle();

        assertEqual(fetchMock.calls[0].url, '/api/tags/suggest?q=ca');
        assertEqual(list.element.querySelector('.search-suggestions [role="option"]')?.textContent, 'cat');
        dispatchKey(input, 'ArrowDown');
        dispatchKey(input, 'Enter');
        assertEqual(input.value, 'animals, cat, ');
        assertTrue(list.element.querySelector('.search-suggestions').hidden);
        assertEqual(fetchMock.calls.length, 1, 'choosing a completion should not submit the tag input');
    } finally {
        restore();
    }
}
