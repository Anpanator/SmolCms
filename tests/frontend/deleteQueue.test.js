import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, sessionResponse, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createDeleteQueueView} from '../../public/js/views/deleteQueue.js';

const QUEUE_URL = '/api/delete-queue';
const RESTORE_URL = '/api/delete-queue/restore';
const DELETE_URL = '/api/delete-queue/delete';
const DELETE_ALL_URL = '/api/delete-queue/delete-all';
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
    const view = createDeleteQueueView({
        session,
        navigate: (path) => navigateCalls.push(path),
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

export async function testDeleteQueueIsDeniedBelowMod() {
    for (const accessLevel of [null, 100, 400]) {
        const {view, fetchMock, navigateCalls, restore} = await createViewWith(accessLevel, () => {
            throw new Error('the queue must not be fetched without moderator access');
        });
        try {
            const denied = view.querySelector('.review-denied');
            assertTrue(denied !== null, 'denied access should show a message');
            assertTrue(denied.textContent.includes('permission'), 'denied message should mention permissions');
            assertEqual(view.querySelector('.page-title')?.textContent, 'Delete Queue');
            assertTrue(view.querySelector('.review-item') === null, 'no queue items for non-moderators');
            assertEqual(queueCalls(fetchMock).length, 0, 'the queue must not be fetched');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'denied view should not navigate');
        } finally {
            restore();
        }
    }
}

export async function testDeleteQueueShowsDownloadImageAndActiveMetadata() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        assertEqual(queueCalls(fetchMock).length, 1, 'the delete queue should be fetched once');
        assertEqual(queueCalls(fetchMock)[0].options.method, 'GET');
        assertEqual(view.querySelector('.review-title')?.textContent, 'Delete Queue');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 2, 'each queued image should become a thumbnail');

        const itemWithThumb = view.querySelector('.review-item[data-image-id="7"]');
        assertEqual(itemWithThumb.querySelector('img.review-thumb')?.getAttribute('src'),
            '/public/img/thumb/ab/ab89c0.avif');
        assertTrue(itemWithThumb.textContent.includes('Upload User'));
        assertEqual(itemWithThumb.querySelector('.review-uploader')?.getAttribute('href'), '/user?id=8');
        assertTrue(itemWithThumb.textContent.includes('2026-10-19 10:30:00'));
        assertEqual(itemWithThumb.querySelectorAll('button[data-action]').length, 0,
            'thumbnails must not have action buttons');

        const itemWithoutThumb = view.querySelector('.review-item[data-image-id="3"]');
        assertTrue(itemWithoutThumb.querySelector('img') === null);
        assertTrue(itemWithoutThumb.querySelector('.review-thumb--empty') !== null);

        const displayedImage = view.querySelector('.review-image');
        assertTrue(displayedImage !== null, 'the active image should be displayed');
        assertEqual(displayedImage.getAttribute('src'), DETAILS[7].downloadSrc,
            'the full-size download image should be used');
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1920 × 1080'));
        assertTrue(view.querySelector('.review-metadata').textContent.includes('1.2 MB'));
        assertEqual(view.querySelector('.review-select[data-image-id="7"]')?.getAttribute('aria-pressed'), 'true');

        assertEqual(view.querySelector('.review-actions [data-action="restore"]')?.textContent, 'Restore');
        assertEqual(view.querySelector('.review-actions [data-action="delete"]')?.textContent, 'Delete permanently');
        assertEqual(view.querySelector('.review-actions [data-action="delete-all"]')?.textContent, 'Delete All');
        assertEqual(view.querySelector('.review-actions [data-action="delete-all"]')?.disabled, false);
        assertTrue(view.querySelector('.review-hotkeys').textContent.includes('Enter'));
        assertTrue(view.querySelector('.review-hotkeys').textContent.includes('delete permanently'));
    } finally {
        restore();
    }
}

export async function testRestorePostsForActiveImageAndRemovesItFromTheQueue() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === `${RESTORE_URL}?id=7` ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="restore"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith(RESTORE_URL));
        assertEqual(actionCall.url, `${RESTORE_URL}?id=7`, 'restore should post the active image id');
        assertEqual(actionCall.options.method, 'POST');

        assertEqual(view.querySelectorAll('.review-item').length, 1, 'the restored item should be removed');
        assertTrue(view.querySelector('.review-item[data-image-id="3"]').textContent.includes('Other User'));
        assertEqual(view.querySelector('.review-image')?.getAttribute('src'), DETAILS[3].downloadSrc);

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'restoring should show a message');
        assertTrue(message.textContent.includes('restored'), 'the success message should mention the restore');
        assertTrue(message.classList.contains('form-message--success'));
    } finally {
        restore();
    }
}

export async function testDeletePermanentlyPostsForSelectedImageAndRemovesIt() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url === `${DELETE_URL}?id=3` ? {status: 204, body: null} : undefined
    ));
    try {
        view.querySelector('.review-select[data-image-id="3"]').dispatchEvent(
            new MouseEvent('click', {bubbles: true}),
        );
        await settle();
        view.querySelector('.review-actions button[data-action="delete"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith(DELETE_URL));
        assertEqual(actionCall.url, `${DELETE_URL}?id=3`, 'delete should post the selected image id');
        assertEqual(actionCall.options.method, 'POST');
        assertEqual(view.querySelectorAll('.review-item').length, 1);
        assertTrue(view.querySelector('.review-select[data-image-id="7"]') !== null);
        assertTrue(view.querySelector('.form-message').textContent.includes('deleted permanently'));
    } finally {
        restore();
    }
}

export async function testDeleteAllConfirmsBeforeBulkActionAndEmptiesTheQueue() {
    let queueRequestCount = 0;
    const {view, fetchMock, restore} = await createViewWith(500, (url) => {
        if (url === QUEUE_URL) {
            queueRequestCount++;
            return {
                status: 200,
                body: {images: queueRequestCount === 1 ? QUEUE.images : []},
            };
        }
        return url === DELETE_ALL_URL
            ? {status: 200, body: {deletedCount: 2}}
            : undefined;
    });
    try {
        view.querySelector('.review-actions [data-action="delete-all"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));

        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'Delete All should ask for confirmation');
        assertTrue(
            dialog.querySelector('.modal-message')?.textContent.includes('2 queued images')
                && dialog.querySelector('.modal-message')?.textContent.includes('cannot be undone'),
            'the confirmation should warn that the bulk delete is permanent',
        );
        assertTrue(
            !fetchMock.calls.some((call) => call.url === DELETE_ALL_URL),
            'the bulk delete should wait for confirmation',
        );

        dialog.querySelector('.modal-actions .button--primary').click();
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url === DELETE_ALL_URL);
        assertTrue(actionCall !== undefined, 'Delete All should call the bulk endpoint');
        assertEqual(actionCall.options.method, 'POST');
        assertEqual(queueCalls(fetchMock).length, 2, 'the queue should refresh after the bulk delete');
        assertEqual(view.querySelectorAll('.review-item').length, 0);
        assertTrue(view.querySelector('.review-empty')?.textContent.includes('No images are waiting for deletion'));

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'bulk deletion should show a success message');
        assertTrue(message.textContent.includes('2 images deleted permanently'));
        assertTrue(message.classList.contains('form-message--success'));
        assertTrue(view.querySelector('.review-actions [data-action="delete-all"]').disabled,
            'Delete All should be disabled when the queue is empty');
    } finally {
        restore();
    }
}

export async function testCancelingDeleteAllKeepsTheQueueUntouched() {
    const {view, fetchMock, restore} = await createViewWith(500);
    try {
        view.querySelector('.review-actions [data-action="delete-all"]').click();
        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'Delete All should ask for confirmation');

        dialog.querySelector('.modal-cancel').click();
        await settle();

        assertTrue(
            !fetchMock.calls.some((call) => call.url === DELETE_ALL_URL),
            'canceling should not call the bulk delete endpoint',
        );
        assertEqual(view.querySelectorAll('.review-item').length, 2, 'canceling should leave all queued images visible');
        assertEqual(view.querySelector('.review-actions [data-action="delete-all"]').disabled, false,
            'Delete All should be enabled after the dialog is canceled');
    } finally {
        restore();
    }
}

export async function testEnterRestoresAndDeleteKeyPermanentlyDeletes() {
    const {view, fetchMock, restore} = await createViewWith(500, (url) => (
        url.startsWith(RESTORE_URL) || url.startsWith(DELETE_URL) ? {status: 204, body: null} : undefined
    ));
    try {
        const enter = pressKey(view, 'Enter');
        assertTrue(enter.defaultPrevented, 'Enter should restore the active image');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === `${RESTORE_URL}?id=7`));

        pressKey(view, 'ArrowRight');
        await settle();
        const deleteKey = pressKey(view, 'Delete');
        assertTrue(deleteKey.defaultPrevented, 'Delete should permanently delete the active image');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === `${DELETE_URL}?id=3`));
        assertTrue(view.querySelector('.review-empty') !== null, 'the emptied queue should show its empty state');
    } finally {
        restore();
    }
}

export async function testDeleteQueueUnauthorizedFetchNavigatesToLogin() {
    const {view, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === QUEUE_URL ? {status: 401, body: null} : undefined
    ));
    try {
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']));
        assertTrue(view.querySelector('.review-item') === null);
    } finally {
        restore();
    }
}

export async function testDeleteQueueActionUnauthorizedNavigatesToLogin() {
    const {view, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === `${RESTORE_URL}?id=7` ? {status: 401, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions button[data-action="restore"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        await settle();

        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']));
        assertEqual(view.querySelectorAll('.review-item').length, 2, 'a failed action must not remove the item');
    } finally {
        restore();
    }
}

export async function testDeleteAllUnauthorizedNavigatesToLogin() {
    const {view, navigateCalls, restore} = await createViewWith(500, (url) => (
        url === DELETE_ALL_URL ? {status: 401, body: null} : undefined
    ));
    try {
        view.querySelector('.review-actions [data-action="delete-all"]')
            .dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        document.querySelector('.modal-backdrop .modal-actions .button--primary').click();
        await settle();

        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']));
        assertEqual(view.querySelectorAll('.review-item').length, 2,
            'an unauthorized bulk delete should leave the displayed queue unchanged');
    } finally {
        restore();
    }
}

export async function testDeleteQueueEmptyState() {
    const {view, restore} = await createViewWith(600, (url) => (
        url === QUEUE_URL ? {status: 200, body: {images: []}} : undefined
    ));
    try {
        const empty = view.querySelector('.review-empty');
        assertTrue(empty !== null, 'an empty queue should show a message');
        assertTrue(empty.textContent.includes('No images are waiting for deletion'));
        assertTrue(view.querySelector('.review-image') === null);
        assertTrue(view.querySelector('.review-actions [data-action="restore"]').disabled);
        assertTrue(view.querySelector('.review-actions [data-action="delete-all"]').disabled);
    } finally {
        restore();
    }
}

export async function testDeleteQueueFetchFailureShowsMessage() {
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
