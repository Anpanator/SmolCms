import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createTagQueueView} from '../../public/js/views/tagQueue.js';

const QUEUE_URL = '/api/tag-suggestions/queue';
const QUEUE = {
    suggestions: [
        {
            id: 21,
            tagName: 'landscape',
            imageId: 7,
            suggestedBy: {id: 2, displayName: 'Tagger One'},
            created: '2026-10-19 10:30:00',
        },
        {
            id: 22,
            tagName: 'coastline',
            imageId: 3,
            suggestedBy: {id: 3, displayName: 'Tagger Two'},
            created: '2026-10-20 09:00:00',
        },
    ],
};
const DETAILS = {
    7: {
        id: 7,
        uploadDate: '2026-10-19 10:30:00',
        uploader: 'Upload User',
        uploaderId: 4,
        width: 1920,
        height: 1080,
        format: 'WEBP',
        fileSize: 1234567,
        downloadSrc: '/api/image/download?name=ab89c0d0ef.webp',
        tags: ['sunset'],
    },
    3: {
        id: 3,
        uploadDate: '2026-10-18 09:00:00',
        uploader: 'Other User',
        uploaderId: 5,
        width: 800,
        height: 600,
        format: 'PNG',
        fileSize: 2048,
        downloadSrc: '/api/image/download?name=cd89c0d0ef.png',
        tags: [],
    },
};

async function createViewWith(accessLevel, handler = () => undefined) {
    const fetchMock = mockFetch(async (url, options) => {
        if (url === '/api/session') {
            return sessionResponse(accessLevel === null ? null : testUser({accessLevel}));
        }
        const response = await handler(url, options);
        return response === undefined ? defaultResponse(url) : response;
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    const navigateCalls = [];
    const view = createTagQueueView({
        session,
        navigate: (path) => navigateCalls.push(path),
        query: new URLSearchParams(),
    });
    await settle();
    return {view, fetchMock, navigateCalls, restore};
}

function defaultResponse(url) {
    if (url === QUEUE_URL) {
        return {status: 200, body: QUEUE};
    }
    const detailMatch = url.match(/^\/api\/image\/detail\?id=(\d+)$/);
    if (detailMatch) {
        const image = DETAILS[detailMatch[1]];
        return image ? {status: 200, body: {image}} : {status: 404, body: null};
    }
    return {status: 200, body: null};
}

function queueCalls(fetchMock) {
    return fetchMock.calls.filter((call) => call.url === QUEUE_URL);
}

function pressKey(view, key, options = {}) {
    const event = new KeyboardEvent('keydown', {
        key,
        bubbles: true,
        cancelable: true,
        ...options,
    });
    view.dispatchEvent(event);
    return event;
}

export async function testTagQueueIsDeniedBelowModWithoutFetchingQueue() {
    for (const accessLevel of [null, 100, 400]) {
        const {view, fetchMock, navigateCalls, restore} = await createViewWith(accessLevel, () => {
            throw new Error('the queue must not be fetched without moderator access');
        });
        try {
            const denied = view.querySelector('.review-denied');
            assertTrue(denied !== null, 'denied access should show a message');
            assertTrue(denied.textContent.includes('permission'));
            assertEqual(view.querySelector('.page-title')?.textContent, 'Tag Queue');
            assertTrue(view.querySelector('.tag-queue-item') === null);
            assertEqual(queueCalls(fetchMock).length, 0, 'the queue must not be fetched');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
        } finally {
            restore();
        }
    }
}

export async function testQueueRendersTagSuggestionsAndInitialImageViewer() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        assertEqual(queueCalls(fetchMock).length, 1, 'the tag suggestion queue should load once');
        assertEqual(queueCalls(fetchMock)[0].options.method, 'GET');
        assertEqual(view.querySelector('.review-title')?.textContent, 'Tag Queue');

        const list = view.querySelector('.tag-queue-list');
        assertEqual(list?.getAttribute('role'), 'list', 'the suggestion queue should expose list semantics');
        const items = view.querySelectorAll('.tag-queue-item');
        assertEqual(items.length, 2);

        const first = view.querySelector('.tag-queue-item[data-suggestion-id="21"]');
        assertEqual(first?.getAttribute('data-image-id'), '7');
        assertEqual(first.querySelector('.tag-queue-name')?.textContent, 'landscape');
        assertTrue(first.textContent.includes('Suggested by Tagger One'));
        assertTrue(first.textContent.includes('2026-10-19 10:30:00'));
        assertTrue(first.textContent.includes('Image #7'));
        assertEqual(first.querySelector('.tag-queue-select')?.getAttribute('data-suggestion-id'), '21');
        assertEqual(first.querySelector('.tag-queue-select')?.getAttribute('aria-pressed'), 'true');
        assertEqual(first.querySelector('.tag-queue-suggested-by a')?.getAttribute('href'), '/user?id=2');
        assertEqual(first.querySelector('button a'), null, 'profile links must not be nested inside the selection button');
        assertEqual(view.querySelectorAll('.tag-queue-select[aria-pressed="true"]').length, 1);

        assertEqual(view.querySelector('.tag-queue-current-name')?.textContent, 'landscape');
        assertEqual(view.querySelector('.tag-queue-current-byline')?.textContent, 'Suggested by Tagger One');
        assertEqual(view.querySelector('.tag-queue-current-byline a')?.getAttribute('href'), '/user?id=2');
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[7].downloadSrc);
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1920 × 1080'));
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1.2 MB'));
        assertEqual(view.querySelector('.review-actions [data-action="approve"]')?.textContent, 'Approve');
        assertEqual(view.querySelector('.review-actions [data-action="reject"]')?.textContent, 'Reject');
        assertTrue(view.querySelector('.review-hotkeys').textContent.includes('Delete / Backspace'));
    } finally {
        restore();
    }
}

export async function testSelectingSuggestionFetchesImageDetailAndUpdatesViewer() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        view.querySelector('.tag-queue-select[data-suggestion-id="22"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true}));
        await settle();

        const detailCall = fetchMock.calls.find((call) => call.url === '/api/image/detail?id=3');
        assertTrue(detailCall !== undefined, 'selecting a suggestion should fetch its image details');
        assertEqual(detailCall.options.method, 'GET');
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);
        assertTrue(view.querySelector('.review-metadata').textContent.includes('Other User'));
        assertTrue(view.querySelector('.review-metadata').textContent.includes('800 × 600'));
        assertEqual(view.querySelector('.tag-queue-current-name')?.textContent, 'coastline');
        assertEqual(view.querySelector('.tag-queue-current-byline')?.textContent, 'Suggested by Tagger Two');
        assertEqual(view.querySelectorAll('.tag-queue-select[aria-pressed="true"]').length, 1);
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '22');
    } finally {
        restore();
    }
}

export async function testTagQueueReusesImageDetailsWhileSuggestionsShareAnImage() {
    let detailCalls = 0;
    const sharedImageSuggestions = {
        suggestions: [
            {...QUEUE.suggestions[0]},
            {...QUEUE.suggestions[1], id: 23, imageId: QUEUE.suggestions[0].imageId},
        ],
    };
    const {view, fetchMock, restore} = await createViewWith(500, (url) => {
        if (url === QUEUE_URL) {
            return {status: 200, body: sharedImageSuggestions};
        }
        if (url === '/api/image/detail?id=7') {
            detailCalls++;
        }
        if (url === '/api/tag-suggestions/approve?id=21') {
            return {status: 204, body: null};
        }
        return undefined;
    });
    try {
        assertEqual(detailCalls, 1, 'initial selection should fetch image details once');
        view.querySelector('.review-actions [data-action="approve"]').click();
        await settle();

        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/detail?id=7').length, 1);
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '23');
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[7].downloadSrc);
    } finally {
        restore();
    }
}

export async function testApprovePostsSuggestionIdRemovesItAndSelectsNeighbor() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/tag-suggestions/approve?id=21' ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/tag-suggestions/approve'));
        assertEqual(actionCall.url, '/api/tag-suggestions/approve?id=21');
        assertEqual(actionCall.options.method, 'POST');
        assertEqual(view.querySelectorAll('.tag-queue-item').length, 1);
        assertTrue(view.querySelector('.tag-queue-item[data-suggestion-id="21"]') === null);
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '22');
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);

        const message = view.querySelector('.form-message');
        assertEqual(message?.textContent, 'Tag suggestion approved.');
        assertTrue(!message.hidden);
        assertTrue(message.classList.contains('form-message--success'));
    } finally {
        restore();
    }
}

export async function testRejectPostsSelectedSuggestionRemovesItAndSelectsNeighbor() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/tag-suggestions/reject?id=22' ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.tag-queue-select[data-suggestion-id="22"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true}));
        await settle();
        view.querySelector('.review-actions button[data-action="reject"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/tag-suggestions/reject'));
        assertEqual(actionCall.url, '/api/tag-suggestions/reject?id=22');
        assertEqual(actionCall.options.method, 'POST');
        assertEqual(view.querySelectorAll('.tag-queue-item').length, 1);
        assertTrue(view.querySelector('.tag-queue-item[data-suggestion-id="22"]') === null);
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '21');
        assertEqual(view.querySelector('.form-message')?.textContent, 'Tag suggestion rejected.');
    } finally {
        restore();
    }
}

export async function testRejectingLastSuggestionShowsEmptyState() {
    const {view, restore} = await createViewWith(500, (url) => (
        url === QUEUE_URL ? {status: 200, body: {suggestions: [QUEUE.suggestions[1]]}}
            : url === '/api/tag-suggestions/reject?id=22' ? {status: 204, body: null}
                : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="reject"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        assertEqual(view.querySelectorAll('.tag-queue-item').length, 0);
        assertTrue(view.querySelector('.tag-queue-empty')?.textContent.includes('No tag suggestions are waiting for moderation'));
        assertTrue(view.querySelector('.review-image') === null);
        assertTrue(view.querySelector('.review-actions [data-action="approve"]').disabled);
    } finally {
        restore();
    }
}

export async function testEmptySuggestionQueueShowsEmptyState() {
    const {view, restore} = await createViewWith(600, (url) => (
        url === QUEUE_URL ? {status: 200, body: {suggestions: []}} : undefined
    ));
    try {
        const empty = view.querySelector('.tag-queue-empty');
        assertTrue(empty !== null, 'an empty queue should show a message');
        assertTrue(empty.textContent.includes('No tag suggestions are waiting for moderation'));
        assertTrue(view.querySelector('.review-image') === null);
        assertTrue(view.querySelector('.review-actions [data-action="approve"]').disabled);
        assertTrue(view.querySelector('.review-actions [data-action="reject"]').disabled);
    } finally {
        restore();
    }
}

export async function testArrowKeysNavigateSuggestionsAndUpdateImage() {
    const {view, restore} = await createViewWith(500);
    try {
        assertTrue(pressKey(view, 'ArrowDown').defaultPrevented, 'down should be handled as next');
        await settle();
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '22');

        pressKey(view, 'ArrowLeft');
        await settle();
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '21');
        pressKey(view, 'ArrowRight');
        await settle();
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '22');
        pressKey(view, 'ArrowUp');
        await settle();
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '21');
    } finally {
        restore();
    }
}

export async function testEnterApprovesAndDeleteRejectsSelectedSuggestions() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/tag-suggestions/approve?id=22' || url === '/api/tag-suggestions/reject?id=21'
            ? {status: 204, body: null}
            : undefined
    ));
    try {
        pressKey(view, 'ArrowRight');
        await settle();
        assertTrue(pressKey(view, 'Enter').defaultPrevented, 'Enter should approve the selected suggestion');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/tag-suggestions/approve?id=22'));
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '21');

        assertTrue(pressKey(view, 'Delete').defaultPrevented, 'Delete should reject the selected suggestion');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/tag-suggestions/reject?id=21'));
        assertTrue(view.querySelector('.tag-queue-empty') !== null);
    } finally {
        restore();
    }
}

export async function testBackspaceRejectsSelectedSuggestion() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/tag-suggestions/reject?id=21' ? {status: 204, body: null} : undefined
    ));
    try {
        assertTrue(pressKey(view, 'Backspace').defaultPrevented, 'Backspace should reject the selected suggestion');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/tag-suggestions/reject?id=21'));
        assertEqual(view.querySelector('.tag-queue-select[aria-pressed="true"]')?.getAttribute('data-suggestion-id'), '22');
    } finally {
        restore();
    }
}

export async function testActionErrorsUseFormMessagesAndRefreshQueue() {
    for (const [errorStatus, expectedMessage] of [
        [400, 'Please check your input.'],
        [404, 'This item no longer exists.'],
    ]) {
        const {view, fetchMock, restore} = await createViewWith(500, (url) => (
            url === '/api/tag-suggestions/approve?id=21' ? {status: errorStatus, body: null} : undefined
        ));
        try {
            view.querySelector('.review-actions button[data-action="approve"]')
                .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
            await settle();

            assertEqual(view.querySelector('.form-message')?.textContent, expectedMessage);
            assertEqual(queueCalls(fetchMock).length, 2, `${errorStatus} should refresh the queue`);
            assertEqual(view.querySelectorAll('.tag-queue-item').length, QUEUE.suggestions.length);
        } finally {
            restore();
        }
    }
}
