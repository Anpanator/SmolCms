import { assertEqual, assertTrue, settle } from './assert.js';
import { mockFetch, sessionResponse, testUser, useFetch } from './mocks.js';
import { createSession } from '../../public/js/session.js';
import { createReviewView } from '../../public/js/views/review.js';

const QUEUE = {
    images: [
        {
            id: 7,
            thumbSrc: '/public/img/thumb/ab/ab89c0.avif',
            sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
            uploadDate: '2026-10-19 10:30:00',
            uploader: 'Upload User',
        },
        {
            id: 3,
            thumbSrc: null,
            sampleSrc: null,
            uploadDate: '2026-10-18 09:00:00',
            uploader: 'Other User',
        },
    ],
};

/**
 * Creates a session (mocked /api/session) and a review view for the given
 * access level (null = anonymous). `handler` serves every non-session URL.
 */
async function createViewWith(accessLevel, handler) {
    const fetchMock = mockFetch((url, options) => {
        if (url === '/api/session') {
            return sessionResponse(accessLevel === null ? null : testUser({ accessLevel }));
        }
        return handler(url, options);
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    const navigateCalls = [];
    const view = createReviewView({ session, navigate: (path) => navigateCalls.push(path) });
    await settle();
    return { view, fetchMock, navigateCalls, restore };
}

function queueCalls(fetchMock) {
    return fetchMock.calls.filter((call) => call.url === '/api/review/queue');
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
            assertTrue(view.querySelector('.review-item') === null, 'no queue items for non-moderators');
            assertEqual(queueCalls(fetchMock).length, 0, 'the queue must not be fetched');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'denied view should not navigate');
        } finally {
            restore();
        }
    }
}

export async function testReviewQueueRendersItemsForModerators() {
    const { view, fetchMock, restore } = await createViewWith(500, () => ({ status: 200, body: QUEUE }));
    try {
        assertEqual(fetchMock.calls[0].url, '/api/session');
        assertEqual(queueCalls(fetchMock).length, 1, 'the queue should be fetched once for MOD');
        assertEqual(queueCalls(fetchMock)[0].options.method, 'GET');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 2, 'each queued image should become a list item');

        const thumb = items[0].querySelector('img.review-thumb');
        assertTrue(thumb !== null, 'a queued image with a thumb should render an image');
        assertEqual(thumb.getAttribute('src'), '/public/img/thumb/ab/ab89c0.avif');
        assertTrue(items[0].textContent.includes('Upload User'), 'uploader should be shown');
        assertTrue(items[0].textContent.includes('2026-10-19 10:30:00'), 'upload date should be shown');

        assertTrue(items[1].querySelector('img') === null, 'a queued image without a thumb renders no image');
        assertTrue(items[1].querySelector('.review-thumb--empty') !== null, 'a missing thumb should show fallback text');

        for (const item of items) {
            assertEqual(item.querySelector('button[data-action="approve"]')?.textContent, 'Approve');
            assertEqual(item.querySelector('button[data-action="reject"]')?.textContent, 'Reject');
        }
    } finally {
        restore();
    }
}

export async function testReviewQueueEmptyState() {
    const { view, restore } = await createViewWith(600, () => ({ status: 200, body: { images: [] } }));
    try {
        const empty = view.querySelector('.review-empty');
        assertTrue(empty !== null, 'an empty queue should show a message');
        assertTrue(empty.textContent.includes('No images are waiting for review'));
    } finally {
        restore();
    }
}

export async function testApprovePostsAndRemovesItem() {
    const { view, fetchMock, restore } = await createViewWith(500, (url) => {
        if (url === '/api/review/approve?id=7') {
            return { status: 204, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        const approve = view.querySelector('.review-item button[data-action="approve"]');
        approve.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/review/approve'));
        assertEqual(actionCall.url, '/api/review/approve?id=7', 'approve should post the image id');
        assertEqual(actionCall.options.method, 'POST');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 1, 'the approved item should be removed from the queue');
        assertEqual(items[0].querySelector('.review-uploader')?.textContent, 'Other User');

        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'approval should show a message');
        assertTrue(message.textContent.includes('approved'), 'the success message should mention the approval');
        assertTrue(message.classList.contains('form-message--success'), 'the success message should be marked as such');
    } finally {
        restore();
    }
}

export async function testRejectPostsAndRemovesItem() {
    const { view, fetchMock, restore } = await createViewWith(500, (url) => {
        if (url === '/api/review/reject?id=3') {
            return { status: 204, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        const reject = view.querySelectorAll('.review-item')[1].querySelector('button[data-action="reject"]');
        reject.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        const actionCall = fetchMock.calls.find((call) => call.url.startsWith('/api/review/reject'));
        assertEqual(actionCall.url, '/api/review/reject?id=3', 'reject should post the image id');
        assertEqual(actionCall.options.method, 'POST');

        const items = view.querySelectorAll('.review-item');
        assertEqual(items.length, 1, 'the rejected item should be removed from the queue');
        assertEqual(items[0].querySelector('.review-uploader')?.textContent, 'Upload User');
        assertTrue(view.querySelector('.form-message').textContent.includes('rejected'));
    } finally {
        restore();
    }
}

export async function testRejectLastItemShowsEmptyState() {
    const { view, restore } = await createViewWith(500, () => ({ status: 200, body: { images: [QUEUE.images[1]] } }));
    try {
        const reject = view.querySelector('.review-item button[data-action="reject"]');
        reject.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(view.querySelectorAll('.review-item').length, 0);
        assertTrue(view.querySelector('.review-empty') !== null, 'an emptied queue should show the empty state');
    } finally {
        restore();
    }
}

export async function testApproveUnauthorizedNavigatesToLogin() {
    const { view, fetchMock, navigateCalls, restore } = await createViewWith(500, (url) => {
        if (url === '/api/review/approve?id=7') {
            return { status: 401, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        view.querySelector('.review-item button[data-action="approve"]')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
        assertEqual(view.querySelectorAll('.review-item').length, 2, 'a failed action must not remove the item');
    } finally {
        restore();
    }
}

export async function testApproveForbiddenShowsMessageWithoutRefresh() {
    const { view, fetchMock, navigateCalls, restore } = await createViewWith(500, (url) => {
        if (url === '/api/review/approve?id=7') {
            return { status: 403, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        view.querySelector('.review-item button[data-action="approve"]')
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
    const { view, fetchMock, restore } = await createViewWith(500, (url) => {
        if (url === '/api/review/approve?id=7') {
            return { status: 404, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        view.querySelector('.review-item button[data-action="approve"]')
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
    const { view, navigateCalls, restore } = await createViewWith(500, () => ({ status: 401, body: null }));
    try {
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), 'queue 401 should navigate to login');
        assertTrue(view.querySelector('.review-item') === null);
    } finally {
        restore();
    }
}

export async function testQueueFetchFailureShowsMessage() {
    const { view, restore } = await createViewWith(500, () => ({ status: 500, body: null }));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, 'a queue fetch failure should show a message');
        assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error message');
    } finally {
        restore();
    }
}

export async function testQueueButtonsAreDisabledWhileRequestIsRunning() {
    let release;
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    const { view, restore } = await createViewWith(500, async (url) => {
        if (url === '/api/review/approve?id=7') {
            await gate;
            return { status: 204, body: null };
        }
        return { status: 200, body: QUEUE };
    });
    try {
        const approve = view.querySelector('.review-item button[data-action="approve"]');
        const reject = view.querySelector('.review-item button[data-action="reject"]');
        approve.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await settle();

        assertEqual(view.querySelectorAll('.review-item').length, 2, 'the item should still be rendered while the request runs');
        assertTrue(approve.disabled, 'the approve button should be disabled while the request runs');
        assertTrue(reject.disabled, 'the sibling reject button should be disabled while the request runs');
        release();
        await settle();
        assertEqual(view.querySelectorAll('.review-item').length, 1, 'the item should be removed after 204');
    } finally {
        release();
        restore();
    }
}
