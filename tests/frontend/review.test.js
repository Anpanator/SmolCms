import {assertEqual, assertTrue, settle} from './assert.js';
import {attachHotkeyHandler, mockFetch, sessionResponse, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createReviewView} from '../../public/js/views/review.js';

const QUEUE_URL = '/api/review/queue';
const QUEUE = {
    images: [
        {
            id: 7,
            thumbSrc: '/public/img/thumb/ab/ab89c0.avif',
            sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
            uploadDate: '2026-10-19 10:30:00',
            uploader: 'Upload User',
            uploaderId: 8,
        },
        {
            id: 3,
            thumbSrc: null,
            sampleSrc: null,
            uploadDate: '2026-10-18 09:00:00',
            uploader: 'Other User',
            uploaderId: 9,
        },
    ],
};
const DETAILS = {
    7: {
        id: 7,
        uploadDate: '2026-10-19 10:30:00',
        uploader: 'Upload User',
        uploaderId: 8,
        width: 1920,
        height: 1080,
        format: 'WEBP',
        fileSize: 1234567,
        downloadSrc: '/api/image/download?name=ab89c0d0ef.webp',
    },
    3: {
        id: 3,
        uploadDate: '2026-10-18 09:00:00',
        uploader: 'Other User',
        uploaderId: 9,
        width: 800,
        height: 600,
        format: 'PNG',
        fileSize: 2048,
        downloadSrc: '/api/image/download?name=cd89c0d0ef.png',
    },
};

/**
 * Creates a session (mocked /api/session) and a mod queue view for the given
 * access level (null = anonymous). The optional handler overrides API calls.
 */
async function createViewWith(accessLevel, handler = () => undefined) {
    const fetchMock = mockFetch(async (url, options) => {
        if (url === '/api/session') {
            return sessionResponse(accessLevel === null ? null : testUser({ accessLevel }));
        }
        const response = await handler(url, options);
        return response === undefined ? defaultResponse(url) : response;
    });
    const restoreFetch = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    const navigateCalls = [];
    const view = createReviewView({ session, navigate: (path) => navigateCalls.push(path) });
    const hotkeyHandler = attachHotkeyHandler(view);
    await settle();
    return {
        view,
        fetchMock,
        navigateCalls,
        restore() {
            hotkeyHandler.destroy();
            restoreFetch();
        },
    };
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

export async function testReviewIsDeniedBelowMod() {
    for (const accessLevel of [null, 100, 400]) {
        const { view, fetchMock, navigateCalls, restore } = await createViewWith(accessLevel, () => {
            throw new Error('the queue must not be fetched without moderator access');
        });
        try {
            const denied = view.querySelector('.review-denied');
            assertTrue(denied !== null, 'denied access should show a message');
            assertTrue(denied.textContent.includes('permission'), 'denied message should mention permissions');
            assertEqual(view.querySelector('.page-title')?.textContent, 'Mod Queue');
            assertTrue(view.querySelector('.review-item') === null, 'no queue items for non-moderators');
            assertEqual(queueCalls(fetchMock).length, 0, 'the queue must not be fetched');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'denied view should not navigate');
        } finally {
            restore();
        }
    }
}

export async function testModQueueShowsDownloadImageAndActiveMetadata() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        assertEqual(fetchMock.calls[0].url, '/api/session');
        assertEqual(queueCalls(fetchMock).length, 1, 'the queue should be fetched once for MOD');
        assertEqual(queueCalls(fetchMock)[0].options.method, 'GET');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 2, 'each queued image should become a thumbnail');
        assertEqual(view.querySelector('.review-title')?.textContent, 'Mod Queue');

        const itemWithThumb = view.querySelector('.review-item[data-image-id="7"]');
        const thumb = itemWithThumb.querySelector('img.review-thumb');
        assertTrue(thumb !== null, 'a queued image with a thumb should render an image');
        assertEqual(thumb.getAttribute('src'), '/public/img/thumb/ab/ab89c0.avif');
        assertTrue(itemWithThumb.textContent.includes('Upload User'), 'uploader should be shown in compact metadata');
        assertEqual(itemWithThumb.querySelector('.review-uploader')?.getAttribute('href'), '/user?id=8');
        assertEqual(itemWithThumb.querySelector('button a'), null, 'profile links must not be nested inside the selection button');
        assertTrue(itemWithThumb.textContent.includes('2026-10-19 10:30:00'), 'upload date should be shown');
        assertEqual(itemWithThumb.querySelectorAll('button[data-action]').length, 0, 'thumbnails must not have action buttons');

        const itemWithoutThumb = view.querySelector('.review-item[data-image-id="3"]');
        assertTrue(itemWithoutThumb.querySelector('img') === null, 'a queued image without a thumb renders no image');
        assertTrue(itemWithoutThumb.querySelector('.review-thumb--empty') !== null, 'a missing thumb should show fallback text');

        const displayedImage = view.querySelector('.review-image');
        assertTrue(displayedImage !== null, 'the active image should be displayed');
        assertEqual(displayedImage.getAttribute('src'), DETAILS[7].downloadSrc, 'the full-size download image should be used');
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1920 × 1080'));
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1.2 MB'));
        assertEqual(view.querySelector('.review-metadata a')?.getAttribute('href'), '/user?id=8');
        assertEqual(itemWithThumb.querySelector('.review-select')?.getAttribute('aria-pressed'), 'true');

        assertEqual(view.querySelector('.review-actions [data-action="approve"]')?.textContent, 'Approve');
        assertEqual(view.querySelector('.review-actions [data-action="reject"]')?.textContent, 'Reject');
        assertTrue(view.querySelector('.review-hotkeys').textContent.includes('Delete / Backspace'));
    } finally {
        restore();
    }
}

export async function testDownloadImageTogglesBetweenFittedAndFullSize() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        const image = view.querySelector('.review-image');
        assertEqual(image.getAttribute('role'), 'button', 'the image size control should be keyboard accessible');

        image.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        assertTrue(image.classList.contains('review-image--full-size'), 'click should show the image at natural size');
        assertEqual(image.getAttribute('aria-pressed'), 'true');
        assertTrue(view.querySelector('.review-image-frame').classList.contains('review-image-frame--full-size'));

        image.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        assertTrue(!image.classList.contains('review-image--full-size'), 'a second click should restore the fitted view');
        assertEqual(image.getAttribute('aria-pressed'), 'false');

        image.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter',
            bubbles: true,
            cancelable: true,
        }));
        assertTrue(image.classList.contains('review-image--full-size'), 'Enter should activate the size control');
        assertTrue(
            !fetchMock.calls.some((call) => call.url.startsWith('/api/review/approve')),
            'activating the image control must not approve the image',
        );
    } finally {
        restore();
    }
}

export async function testDraggingFullSizeImagePansWithoutTogglingIt(fixture) {
    const {view, restore} = await createViewWith(500);
    try {
        fixture.append(view);
        const image = view.querySelector('.review-image');
        const frame = view.querySelector('.review-image-frame');
        image.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));

        let scrollLeft = 0;
        let scrollTop = 0;
        Object.defineProperties(frame, {
            clientWidth: {configurable: true, value: 100},
            clientHeight: {configurable: true, value: 100},
            scrollWidth: {configurable: true, value: 500},
            scrollHeight: {configurable: true, value: 400},
            scrollLeft: {
                configurable: true,
                get: () => scrollLeft,
                set: (value) => {
                    scrollLeft = value;
                },
            },
            scrollTop: {
                configurable: true,
                get: () => scrollTop,
                set: (value) => {
                    scrollTop = value;
                },
            },
        });

        image.dispatchEvent(new PointerEvent('pointerdown', {
            bubbles: true,
            cancelable: true,
            pointerId: 9,
            isPrimary: true,
            button: 0,
            clientX: 100,
            clientY: 100,
        }));
        image.dispatchEvent(new PointerEvent('pointermove', {
            bubbles: true,
            cancelable: true,
            pointerId: 9,
            isPrimary: true,
            clientX: 60,
            clientY: 70,
        }));
        image.dispatchEvent(new PointerEvent('pointerup', {
            bubbles: true,
            pointerId: 9,
            isPrimary: true,
            button: 0,
        }));
        image.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        assertEqual(scrollLeft, 40, 'dragging right-to-left should pan horizontally');
        assertEqual(scrollTop, 30, 'dragging bottom-to-top should pan vertically');
        assertTrue(image.classList.contains('review-image--full-size'), 'the drag click must not restore fitted size');
    } finally {
        restore();
    }
}

export async function testSelectingThumbnailUpdatesImageAndMetadata() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        view.querySelector('.review-select[data-image-id="3"]').dispatchEvent(new MouseEvent('click', {bubbles: true}));
        await settle();

        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);
        assertTrue(view.querySelector('.review-metadata').textContent.includes('Other User'));
        assertTrue(view.querySelector('.review-metadata').textContent.includes('800 × 600'));
        assertEqual(view.querySelectorAll('.review-select[aria-pressed="true"]').length, 1);
        assertEqual(view.querySelector('.review-select[aria-pressed="true"]')?.getAttribute('data-image-id'), '3');
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/image/detail?id=3'));
    } finally {
        restore();
    }
}

export async function testReviewIgnoresLateDetailsForAnInactiveThumbnail() {
    const pendingDetails = new Map();
    const {view, restore} = await createViewWith(500, (url) => {
        const match = url.match(/^\/api\/image\/detail\?id=(\d+)$/);
        if (!match) {
            return undefined;
        }
        const imageId = match[1];
        return new Promise((resolve) => {
            const requests = pendingDetails.get(imageId) ?? [];
            requests.push(resolve);
            pendingDetails.set(imageId, requests);
        });
    });
    try {
        view.querySelector('.review-select[data-image-id="3"]').click();
        view.querySelector('.review-select[data-image-id="7"]').click();
        const detailFor = (imageId, downloadSrc) => ({
            status: 200,
            body: {image: {...DETAILS[imageId], downloadSrc}},
        });
        pendingDetails.get('7')[1](detailFor(7, '/current-image-7'));
        await settle();
        pendingDetails.get('3')[0](detailFor(3, '/stale-image-3'));
        pendingDetails.get('7')[0](detailFor(7, '/stale-image-7'));
        await settle();

        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), '/current-image-7');
        assertEqual(view.querySelector('.review-select[aria-pressed="true"]')?.getAttribute('data-image-id'), '7');
    } finally {
        for (const requests of pendingDetails.values()) {
            requests.forEach((resolve) => resolve({status: 200, body: {image: null}}));
        }
        restore();
    }
}

export async function testReviewQueueEmptyState() {
    const {view, restore} = await createViewWith(600, (url) => (
        url === QUEUE_URL ? {status: 200, body: {images: []}} : undefined
    ));
    try {
        const empty = view.querySelector('.review-empty');
        assertTrue(empty !== null, 'an empty queue should show a message');
        assertTrue(empty.textContent.includes('No images are waiting for moderation'));
        assertTrue(view.querySelector('.review-image') === null);
        assertTrue(view.querySelector('.review-actions [data-action="approve"]').disabled);
    } finally {
        restore();
    }
}

export async function testApprovePostsForActiveImageAndSelectsNext() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/review/approve?id=7' ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/review/approve'));
        assertEqual(actionCall.url, '/api/review/approve?id=7', 'approve should post the active image id');
        assertEqual(actionCall.options.method, 'POST');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 1, 'the approved item should be removed from the queue');
        assertTrue(
            view.querySelector('.review-item[data-image-id="3"]').textContent.includes('Other User'),
            'the next queue image should remain',
        );
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'approval should show a message');
        assertTrue(message.textContent.includes('approved'), 'the success message should mention the approval');
        assertTrue(message.classList.contains('form-message--success'), 'the success message should be marked as such');
    } finally {
        restore();
    }
}

export async function testRejectPostsForSelectedThumbnailAndRemovesIt() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/review/reject?id=3' ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.review-select[data-image-id="3"]').dispatchEvent(new MouseEvent('click', {bubbles: true}));
        await settle();
        view.querySelector('.review-actions button[data-action="reject"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/review/reject'));
        assertEqual(actionCall.url, '/api/review/reject?id=3', 'reject should post the selected image id');
        assertEqual(actionCall.options.method, 'POST');
        assertEqual(view.querySelectorAll('.review-item').length, 1);
        assertTrue(
            view.querySelector('.review-select[data-image-id="7"]') !== null,
            'the remaining thumbnail should be image 7',
        );
        assertTrue(view.querySelector('.form-message').textContent.includes('rejected'));
    } finally {
        restore();
    }
}

export async function testRejectLastItemShowsEmptyState() {
    const {view, restore} = await createViewWith(500, (url) => (
        url === QUEUE_URL ? {status: 200, body: {images: [QUEUE.images[1]]}}
            : url === '/api/review/reject?id=3' ? {status: 204, body: null}
                : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="reject"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        assertEqual(view.querySelectorAll('.review-item').length, 0);
        assertTrue(view.querySelector('.review-empty') !== null, 'an emptied queue should show the empty state');
    } finally {
        restore();
    }
}

export async function testArrowKeysNavigateAndUpdateActiveImage() {
    const {view, restore} = await createViewWith(500);
    try {
        assertTrue(pressKey(view, 'ArrowDown').defaultPrevented, 'down should be handled as next');
        await settle();
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);

        pressKey(view, 'ArrowLeft');
        await settle();
        assertEqual(view.querySelector('.review-select[aria-pressed="true"]')?.getAttribute('data-image-id'), '7');

        pressKey(view, 'ArrowRight');
        await settle();
        assertEqual(view.querySelector('.review-select[aria-pressed="true"]')?.getAttribute('data-image-id'), '3');
        pressKey(view, 'ArrowUp');
        await settle();
        assertEqual(view.querySelector('.review-select[aria-pressed="true"]')?.getAttribute('data-image-id'), '7');
    } finally {
        restore();
    }
}

export async function testEnterApprovesSelectedImage() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/review/approve?id=3' ? {status: 204, body: null} : undefined
    ));
    try {
        pressKey(view, 'ArrowRight');
        await settle();
        const enter = pressKey(view, 'Enter');
        assertTrue(enter.defaultPrevented, 'Enter should be handled as approve');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/review/approve?id=3'));
    } finally {
        restore();
    }
}

export async function testDeleteAndBackspaceRejectSelectedImages() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url.startsWith('/api/review/reject?id=') ? {status: 204, body: null} : undefined
    ));
    try {
        assertTrue(pressKey(view, 'Delete').defaultPrevented, 'Delete should be handled as reject');
        await settle();
        assertTrue(
            view.querySelector('.review-select[data-image-id="3"]') !== null,
            'the rejected thumbnail should be removed from the queue',
        );

        assertTrue(pressKey(view, 'Backspace').defaultPrevented, 'Backspace should be handled as reject');
        await settle();
        const rejectedIds = fetchMock.calls
            .filter((call) => call.url.startsWith('/api/review/reject?id='))
            .map((call) => call.url);
        assertEqual(JSON.stringify(rejectedIds), JSON.stringify([
            '/api/review/reject?id=7',
            '/api/review/reject?id=3',
        ]));
        assertTrue(view.querySelector('.review-empty') !== null);
    } finally {
        restore();
    }
}

export async function testApproveUnauthorizedNavigatesToLogin() {
    const {view, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === '/api/review/approve?id=7' ? {status: 401, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
        assertEqual(view.querySelectorAll('.review-item').length, 2, 'a failed action must not remove the item');
    } finally {
        restore();
    }
}

export async function testApproveForbiddenShowsMessageWithoutRefresh() {
    const {view, fetchMock, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === '/api/review/approve?id=7' ? {status: 403, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '403 should show a message');
        assertTrue(message.textContent.includes('permission'), '403 should map to a permission message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), '403 should not navigate');
        assertEqual(queueCalls(fetchMock).length, 1, '403 should not refresh the queue');
        assertEqual(view.querySelectorAll('.review-item').length, 2, '403 should keep the item');
    } finally {
        restore();
    }
}

export async function testApproveNotFoundRefreshesQueue() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === '/api/review/approve?id=7' ? {status: 404, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '404 should show a message');
        assertEqual(queueCalls(fetchMock).length, 2, '404 should refresh the queue');
        assertEqual(view.querySelectorAll('.review-item').length, 2, 'the refreshed queue should be rendered');
    } finally {
        restore();
    }
}

export async function testQueueFetchUnauthorizedNavigatesToLogin() {
    const {view, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === QUEUE_URL ? {status: 401, body: null} : undefined
    ));
    try {
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), 'queue 401 should navigate to login');
        assertTrue(view.querySelector('.review-item') === null);
    } finally {
        restore();
    }
}

export async function testQueueFetchFailureShowsMessage() {
    const {view, restore} = await createViewWith(500, (url) => (
        url === QUEUE_URL ? {status: 500, body: null} : undefined
    ));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'a queue fetch failure should show a message');
        assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error message');
    } finally {
        restore();
    }
}

export async function testActionButtonsAreDisabledWhileRequestIsRunning() {
    let release;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    const { view, restore } = await createViewWith(500, async (url) => {
        if (url === '/api/review/approve?id=7') {
            await gate;
            return { status: 204, body: null };
        }
        return undefined;
    });
    try {
        const approve = view.querySelector('.review-actions button[data-action="approve"]');
        const reject = view.querySelector('.review-actions button[data-action="reject"]');
        approve.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(view.querySelectorAll('.review-item').length, 2, 'the item should remain while the request runs');
        assertTrue(approve.disabled, 'the approve button should be disabled while the request runs');
        assertTrue(reject.disabled, 'the reject button should be disabled while the request runs');
        release();
        await settle();
        assertEqual(view.querySelectorAll('.review-item').length, 1, 'the item should be removed after 204');
    } finally {
        release();
        restore();
    }
}
