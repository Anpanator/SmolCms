import {assertEqual, assertFalse, assertTrue, settle} from './assert.js';
import {mockFetch, useFetch} from './mocks.js';
import {createMessageBox} from '../../public/js/forms.js';
import {createQuickTagsView} from '../../public/js/quickTags.js';

function makeTags(prefix, count) {
    return Array.from({length: count}, (_, index) => ({
        name: `${prefix}-${index}`,
        category: index === 0 ? 'Artist' : 'General',
    }));
}

function createView(handler, fixture) {
    const status = createMessageBox();
    const quickTags = createQuickTagsView({status});
    quickTags.element.hidden = false;
    fixture.append(status, quickTags.element);
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    return {quickTags, status, fetchMock, restore};
}

export async function testQuickTagsLoadsTheThreeBoundedGroups(fixture) {
    const response = {
        global: makeTags('global', 25),
        userMostUsed: makeTags('used', 12),
        userRecent: makeTags('recent', 12),
    };
    const {quickTags, fetchMock, restore} = createView(
        (url) => url === '/api/tags/quick' ? {status: 200, body: response} : {status: 200, body: null},
        fixture,
    );
    try {
        await quickTags.load();

        assertEqual(fetchMock.calls.length, 1);
        assertEqual(fetchMock.calls[0].url, '/api/tags/quick');
        assertTrue(quickTags.element.matches('.quick-tags-sidebar'));
        assertEqual(
            [...quickTags.element.querySelectorAll('.quick-tags-group-title')].map((heading) => heading.textContent).join('|'),
            'Most used|Your most used|Your recent',
        );
        assertEqual(quickTags.element.querySelectorAll('.quick-tags-group--global .quick-tag-button').length, 20);
        assertEqual(quickTags.element.querySelectorAll('.quick-tags-group--user-most-used .quick-tag-button').length, 10);
        assertEqual(quickTags.element.querySelectorAll('.quick-tags-group--user-recent .quick-tag-button').length, 10);
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="global-0"]')?.textContent, 'global-0');
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="used-0"]')?.getAttribute('aria-pressed'), 'false');
    } finally {
        restore();
    }
}

export async function testQuickTagButtonsToggleCurrentImageTagsWithoutConfirmation(fixture) {
    const added = [];
    const removed = [];
    const requests = [];
    const status = createMessageBox();
    const quickTags = createQuickTagsView({
        status,
        onTagAdded: (tagName) => added.push(tagName),
        onTagRemoved: (tagName) => removed.push(tagName),
    });
    quickTags.element.hidden = false;
    const fetchMock = mockFetch((url, options) => {
        if (url === '/api/tags/quick') {
            return {status: 200, body: {
                global: [{name: 'nature', category: 'General'}, {name: 'sky', category: 'General'}],
                userMostUsed: [],
                userRecent: [],
            }};
        }
        requests.push({url, options});
        if (url === '/api/image/tags') {
            return {status: 200, body: {applied: ['sky'], unknown: []}};
        }
        return {status: 204, body: null};
    });
    const restore = useFetch(fetchMock);
    fixture.append(status, quickTags.element);
    try {
        quickTags.updateImage(7, ['nature']);
        await quickTags.load();

        const nature = quickTags.element.querySelector('.quick-tag-button[data-tag-name="nature"]');
        const sky = quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]');
        assertEqual(nature.getAttribute('aria-pressed'), 'true');
        assertEqual(sky.getAttribute('aria-pressed'), 'false');

        sky.click();
        await settle();
        assertEqual(JSON.stringify(requests[0]), JSON.stringify({
            url: '/api/image/tags',
            options: {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({imageId: 7, tags: 'sky'})},
        }));
        const skyAfterAdd = quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]');
        assertEqual(skyAfterAdd.getAttribute('aria-pressed'), 'true');
        assertEqual(added.join('|'), 'sky');
        assertTrue(document.querySelector('.modal-backdrop') === null, 'quick tags should not ask for confirmation');

        skyAfterAdd.click();
        await settle();
        assertEqual(requests[1].url, '/api/image/tags/remove');
        assertEqual(JSON.stringify(JSON.parse(requests[1].options.body)), JSON.stringify({imageId: 7, tag: 'sky'}));
        assertEqual(
            quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]')?.getAttribute('aria-pressed'),
            'false',
        );
        assertEqual(removed.join('|'), 'sky');
    } finally {
        restore();
    }
}

export async function testQuickTagsFollowChangedImageTagsWithoutReloadingLists(fixture) {
    const fetchMock = mockFetch(() => ({status: 200, body: {
        global: [{name: 'nature', category: 'General'}, {name: 'sky', category: 'General'}],
        userMostUsed: [],
        userRecent: [],
    }}));
    const restore = useFetch(fetchMock);
    const quickTags = createQuickTagsView();
    fixture.append(quickTags.element);
    try {
        await quickTags.load();
        quickTags.updateImage(7, ['nature']);
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="nature"]')?.getAttribute('aria-pressed'), 'true');
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]')?.getAttribute('aria-pressed'), 'false');

        quickTags.updateImage(8, ['sky']);
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="nature"]')?.getAttribute('aria-pressed'), 'false');
        assertEqual(quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]')?.getAttribute('aria-pressed'), 'true');
        assertEqual(fetchMock.calls.length, 1);
    } finally {
        restore();
    }
}

export async function testQuickTagTogglesRefreshTheListsWithoutClearingTheSuccessMessage(fixture) {
    let quickTagsRequests = 0;
    const status = createMessageBox();
    const quickTags = createQuickTagsView({status});
    quickTags.element.hidden = false;
    fixture.append(status, quickTags.element);
    const fetchMock = mockFetch((url) => {
        if (url === '/api/tags/quick') {
            quickTagsRequests++;
            return {status: 200, body: {
                global: [{name: 'sky', category: 'General'}],
                userMostUsed: [],
                userRecent: quickTagsRequests === 2 ? [{name: 'sky', category: 'General'}] : [],
            }};
        }
        if (url === '/api/image/tags') {
            return {status: 200, body: {applied: ['sky'], unknown: []}};
        }
        return {status: 204, body: null};
    });
    const restore = useFetch(fetchMock);
    try {
        quickTags.updateImage(7, []);
        await quickTags.load();
        assertEqual(quickTagsRequests, 1);
        assertEqual(quickTags.element.querySelectorAll('.quick-tags-group--user-recent .quick-tag-button').length, 0);

        quickTags.element.querySelector('.quick-tags-group--global .quick-tag-button[data-tag-name="sky"]').click();
        await settle();

        assertEqual(quickTagsRequests, 2);
        const recentSky = quickTags.element.querySelector('.quick-tags-group--user-recent .quick-tag-button[data-tag-name="sky"]');
        assertTrue(recentSky !== null, 'the recent list should refresh after an add');
        assertEqual(recentSky.getAttribute('aria-pressed'), 'true');
        assertFalse(status.hidden);
        assertEqual(status.textContent, 'Added tag: sky.');
        assertTrue(status.classList.contains('form-message--success'));

        recentSky.click();
        await settle();

        assertEqual(quickTagsRequests, 3);
        assertEqual(quickTags.element.querySelectorAll('.quick-tags-group--user-recent .quick-tag-button').length, 0);
        assertFalse(status.hidden);
        assertEqual(status.textContent, 'Removed tag: sky.');
    } finally {
        restore();
    }
}

export async function testQuickTagRefreshFailureKeepsExistingListsAndSuccessMessage(fixture) {
    let quickTagsRequests = 0;
    const status = createMessageBox();
    const quickTags = createQuickTagsView({status});
    quickTags.element.hidden = false;
    fixture.append(status, quickTags.element);
    const fetchMock = mockFetch((url) => {
        if (url === '/api/tags/quick') {
            quickTagsRequests++;
            return quickTagsRequests === 1
                ? {status: 200, body: {
                    global: [{name: 'sky', category: 'General'}],
                    userMostUsed: [],
                    userRecent: [],
                }}
                : {status: 500, body: null};
        }
        if (url === '/api/image/tags') {
            return {status: 200, body: {applied: ['sky'], unknown: []}};
        }
        return {status: 204, body: null};
    });
    const restore = useFetch(fetchMock);
    try {
        quickTags.updateImage(7, []);
        await quickTags.load();

        quickTags.element.querySelector('.quick-tags-group--global .quick-tag-button[data-tag-name="sky"]').click();
        await settle();

        assertEqual(quickTagsRequests, 2);
        assertEqual(
            quickTags.element.querySelectorAll('.quick-tags-group--global .quick-tag-button[data-tag-name="sky"]').length,
            1,
            'a failed refresh should keep the existing lists',
        );
        assertFalse(status.hidden);
        assertEqual(status.textContent, 'Added tag: sky.');
    } finally {
        restore();
    }
}

export async function testQuickTagButtonsPreventDuplicateRequestsAndShowErrors(fixture) {
    let resolveAdd;
    const status = createMessageBox();
    const fetchMock = mockFetch((url) => {
        if (url === '/api/tags/quick') {
            return {status: 200, body: {global: [{name: 'sky', category: 'General'}], userMostUsed: [], userRecent: []}};
        }
        return new Promise((resolve) => { resolveAdd = resolve; });
    });
    const restore = useFetch(fetchMock);
    const quickTags = createQuickTagsView({status});
    quickTags.element.hidden = false;
    fixture.append(status, quickTags.element);
    try {
        quickTags.updateImage(7, []);
        await quickTags.load();
        const sky = quickTags.element.querySelector('.quick-tag-button[data-tag-name="sky"]');
        sky.click();
        assertTrue(sky.disabled, 'buttons should lock while a quick-tag request is pending');
        sky.click();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags').length, 1);

        resolveAdd({status: 500, body: null});
        await settle();
        assertFalse(sky.disabled);
        assertEqual(sky.getAttribute('aria-pressed'), 'false');
        assertFalse(status.hidden);
        assertTrue(status.textContent.includes('went wrong'));
    } finally {
        resolveAdd?.({status: 500, body: null});
        restore();
    }
}
