import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, testUser, useFetch} from './mocks.js';
import {createImageView, formatFileSize} from '../../public/js/views/image.js';

const DETAIL = {
    id: 7,
    uploadDate: '2026-10-19 10:30:00',
    uploader: 'Upload User',
    width: 100,
    height: 50,
    format: 'jpeg',
    fileSize: 1234,
    sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
    downloadSrc: '/api/image/download?name=ab89c0d0ef.jpeg',
    tags: ['nature', 'sky'],
};

function detailResponse(overrides = {}) {
    return { status: 200, body: { image: { ...DETAIL, ...overrides } } };
}

async function createView(handler, query = 'id=7', session = {getUser: () => null}) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const navigateCalls = [];
    const view = createImageView({
        session,
        navigate: (path) => navigateCalls.push(path),
        query: new URLSearchParams(query),
    });
    await settle();
    return { view, fetchMock, navigateCalls, restore };
}

function metaValue(view, term) {
    const item = [...view.querySelectorAll('.image-meta .image-meta-item')]
        .find((node) => node.querySelector('dt')?.textContent === term);
    return item?.querySelector('dd')?.textContent ?? null;
}

export async function testImageViewRendersSampleImageInLayout() {
    const { view, fetchMock, restore } = await createView(() => detailResponse());
    try {
        assertTrue(view.matches('.layout'), 'image view should use the .layout grid');
        assertTrue(view.querySelector('.sidebar .tags') !== null, 'sidebar tag list should exist');
        assertTrue(view.querySelector('.sidebar .search-view') !== null, 'image sidebar should have tag search');
        assertEqual(view.querySelector('.tag-add-row'), null, 'anonymous users should not see tag controls');
        assertEqual(view.querySelector('.tag-remove-button'), null, 'anonymous users should not see remove controls');
        assertEqual(view.querySelectorAll('.sidebar .tags-item').length, 2, 'detail tags should replace static tags');
        assertEqual(view.querySelector('.tags-link[data-tag-name="nature"]')?.textContent, 'nature');
        assertEqual(view.querySelector('.tags-link[data-tag-name="sky"]')?.textContent, 'sky');
        assertTrue(view.querySelector('.sidebar .image-meta') !== null, 'metadata section should live in the sidebar');
        assertTrue(view.querySelector('.content-title') === null, 'the image view should not have a title header');
        assertTrue(view.querySelector('.image-meta-section .sidebar-title') === null, 'image details should not have a title header');
        assertTrue(view.querySelector('.image-meta-separator') !== null, 'image details should be separated by a horizontal rule');

        assertEqual(fetchMock.calls.length, 1);
        assertEqual(fetchMock.calls[0].url, '/api/image/detail?id=7');
        assertEqual(fetchMock.calls[0].options.method, 'GET');

        const image = view.querySelector('.content img.image-view-image');
        assertTrue(image !== null, 'the viewing image should be rendered');
        assertEqual(image.getAttribute('src'), '/api/image/sample?name=ab89c0d0ef.webp', 'the sample variant must be used');
    } finally {
        restore();
    }
}

export async function testImageViewShowsMetadataAndDownloadLink() {
    const { view, restore } = await createView(() => detailResponse());
    try {
        assertEqual(metaValue(view, 'Uploaded'), '2026-10-19 10:30:00');
        assertEqual(metaValue(view, 'Uploader'), 'Upload User');
        assertEqual(metaValue(view, 'Dimensions'), '100 × 50');
        assertEqual(metaValue(view, 'Format'), 'jpeg');
        assertEqual(metaValue(view, 'File size'), '1.2 KB');

        const download = view.querySelector('.sidebar a.image-download');
        assertTrue(download !== null, 'download link should exist in the sidebar');
        assertEqual(download.getAttribute('href'), '/api/image/download?name=ab89c0d0ef.jpeg');
        assertTrue(download.hasAttribute('download'), 'download link should carry the download attribute');
        assertEqual(download.textContent, 'Download');
    } finally {
        restore();
    }
}

export async function testImageTagSearchNavigatesToHomeFilter() {
    const { view, navigateCalls, restore } = await createView(() => detailResponse());
    try {
        const input = view.querySelector('.search-input');
        input.value = 'cats, dogs';
        view.querySelector('.search-view').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        assertEqual(navigateCalls[0], '/?tags=cats%2Cdogs');
    } finally {
        restore();
    }
}

export async function testImageViewHandlesProcessingPlaceholders() {
    const { view, restore } = await createView(() => detailResponse({
        width: null,
        height: null,
        format: null,
        fileSize: null,
        sampleSrc: null,
        downloadSrc: null,
    }));
    try {
        const processing = view.querySelector('.content .image-processing');
        assertTrue(processing !== null, 'a null sampleSrc should show the processing notice');
        assertTrue(processing.textContent.includes('still being processed'));
        assertTrue(view.querySelector('img.image-view-image') === null, 'no image should be rendered while processing');
        assertTrue(view.querySelector('a.image-download') === null, 'download link should be hidden while processing');
        assertEqual(metaValue(view, 'Dimensions'), '—');
        assertEqual(metaValue(view, 'Format'), '—');
        assertEqual(metaValue(view, 'File size'), '—');
    } finally {
        restore();
    }
}

export async function testImageViewUnknownIdShowsNotFound() {
    const { view, navigateCalls, restore } = await createView(() => ({ status: 404, body: null }));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '404 should show a message');
        assertTrue(message.textContent.includes('Image not found'), '404 should show the not-found message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), '404 should not navigate');
    } finally {
        restore();
    }
}

export async function testImageViewUnauthorizedNavigatesToLogin() {
    const { view, navigateCalls, restore } = await createView(() => ({ status: 401, body: null }));
    try {
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
    } finally {
        restore();
    }
}

export async function testImageViewServerErrorShowsMessage() {
    const { view, navigateCalls, restore } = await createView(() => ({ status: 500, body: null }));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '500 should show a message');
        assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
    } finally {
        restore();
    }
}

export async function testImageViewWithoutValidIdDoesNotFetch() {
    for (const query of ['', 'id=abc', 'id=0', 'id=-1']) {
        const { view, fetchMock, restore } = await createView(
            () => detailResponse(),
            query,
        );
        try {
            assertEqual(fetchMock.calls.length, 0, `a malformed query (${query || 'empty'}) must not fetch`);
            const message = view.querySelector('.form-message');
            assertTrue(message !== null && !message.hidden, 'a malformed id should show a message');
            assertTrue(message.textContent.includes('Image not found'));
            assertTrue(view.querySelector('img.image-view-image') === null);
        } finally {
            restore();
        }
    }
}

export function testFormatFileSizeFormatsBytesHumanReadable() {
    assertEqual(formatFileSize(null), '—');
    assertEqual(formatFileSize(undefined), '—');
    assertEqual(formatFileSize(-1), '—');
    assertEqual(formatFileSize(0), '0 B');
    assertEqual(formatFileSize(512), '512 B');
    assertEqual(formatFileSize(1024), '1.0 KB');
    assertEqual(formatFileSize(1234), '1.2 KB');
    assertEqual(formatFileSize(1048576), '1.0 MB');
    assertEqual(formatFileSize(5 * 1024 * 1024), '5.0 MB');
}

export async function testImageTagAddRequiresButtonAndMergesAppliedTags() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: ['bird', 'nature'], unknown: []}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        const input = view.querySelector('.tag-add-input');
        const addButton = view.querySelector('.tag-add-submit');
        assertTrue(input !== null, 'logged-in users should see the tag input');
        assertEqual(addButton.textContent, 'Add');

        addButton.click();
        input.value = 'bird, nature';
        input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true}));
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags').length, 0, 'Enter must not submit');

        addButton.click();
        await settle();
        const post = fetchMock.calls.find((call) => call.url === '/api/image/tags');
        assertEqual(post.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({imageId: 7, tags: 'bird, nature'}));
        assertEqual([...view.querySelectorAll('.tags-link')].map((link) => link.textContent).join('|'), 'bird|nature|sky');
        assertEqual(input.value, '', 'successful submission should clear the input');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.classList.contains('form-message--success'));
        assertTrue(message.textContent.includes('bird'));
    } finally {
        restore();
    }
}

export async function testImageTagAddIgnoresEmptyInput() {
    const {view, fetchMock, restore} = await createView(
        () => detailResponse(),
        'id=7',
        {getUser: () => testUser()},
    );
    try {
        view.querySelector('.tag-add-submit').click();
        view.querySelector('.tag-add-input').dispatchEvent(
            new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true}),
        );
        await settle();
        assertEqual(fetchMock.calls.length, 1, 'only the initial detail request should run for empty input');
    } finally {
        restore();
    }
}

export async function testFellowCanSuggestUnknownImageTagsFromConfirmation() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: ['nature'], unknown: ['new-a', 'new-b']}};
        }
        if (url === '/api/tag-suggestions' && options.method === 'POST') {
            return {status: 201, body: {suggested: ['new-a', 'new-b']}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser({accessLevel: 200})});
    try {
        view.querySelector('.tag-add-input').value = 'nature, new-a, new-b';
        view.querySelector('.tag-add-submit').click();
        await settle();

        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'a fellow should be asked about unknown tags');
        assertTrue(dialog.textContent.includes('Unknown tags: new-a, new-b. Suggest them as new tags?'));
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tag-suggestions').length, 0);
        dialog.querySelector('.button--primary').click();
        await settle();

        const suggest = fetchMock.calls.find((call) => call.url === '/api/tag-suggestions');
        assertEqual(suggest.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(suggest.options.body)), JSON.stringify({imageId: 7, tags: 'new-a, new-b'}));
        assertEqual(view.querySelector('.modal-backdrop'), null);
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.textContent.includes('submitted for review'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testNoviceSeesUnknownTagsWereNotAddedWithoutDialog() {
    const {view, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: [], unknown: ['not-known']}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser({accessLevel: 100})});
    try {
        view.querySelector('.tag-add-input').value = 'not-known';
        view.querySelector('.tag-add-submit').click();
        await settle();

        assertEqual(document.querySelector('.modal-backdrop'), null, 'below FELLOW should not see a suggestion dialog');
        const message = view.querySelector('.form-message:not([hidden])');
        assertEqual(message.textContent, 'Unknown tags were not added: not-known');
        assertTrue(message.classList.contains('form-message--info'));
    } finally {
        restore();
    }
}

export async function testRemovingTagRequiresConfirmationAndExplainsTagmeConflict() {
    const originalConfirm = window.confirm;
    let confirmationMessage = '';
    window.confirm = (message) => {
        confirmationMessage = message;
        return true;
    };
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse({tags: ['nature', 'tagme']});
        }
        if (url === '/api/image/tags/remove' && options.method === 'POST') {
            return {status: 409, body: null};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        view.querySelector('.tag-remove-button[data-tag-name="tagme"]').click();
        await settle();

        assertEqual(confirmationMessage, 'Remove the tag "tagme" from this image?');
        const post = fetchMock.calls.find((call) => call.url === '/api/image/tags/remove');
        assertEqual(post.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({imageId: 7, tag: 'tagme'}));
        assertTrue(view.querySelector('.tags-link[data-tag-name="tagme"]') !== null, 'a conflict must keep the tag displayed');
        assertTrue(view.querySelector('.form-message').textContent.includes('at least 10 other tags'));
    } finally {
        window.confirm = originalConfirm;
        restore();
    }
}

export async function testRemovingTagCanBeCancelledOrApplied() {
    const originalConfirm = window.confirm;
    let confirm = false;
    window.confirm = () => confirm;
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse({tags: ['nature', 'sky']});
        }
        return url === '/api/image/tags/remove' && options.method === 'POST'
            ? {status: 204, body: null}
            : {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        const removeButton = view.querySelector('.tag-remove-button[data-tag-name="nature"]');
        removeButton.click();
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags/remove').length, 0, 'cancel should not make a request');

        confirm = true;
        removeButton.click();
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags/remove').length, 1);
        assertEqual(view.querySelector('.tags-link[data-tag-name="nature"]'), null, '204 should remove the tag');
        assertTrue(view.querySelector('.form-message').textContent.includes('Removed tag'));
    } finally {
        window.confirm = originalConfirm;
        restore();
    }
}

export async function testRemovingMissingTagRefreshesImageDetails() {
    let detailRequests = 0;
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            detailRequests++;
            return detailResponse();
        }
        if (url === '/api/image/tags/remove' && options.method === 'POST') {
            return {status: 404, body: null};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    const originalConfirm = window.confirm;
    window.confirm = () => true;
    try {
        view.querySelector('.tag-remove-button[data-tag-name="nature"]').click();
        await settle();

        assertEqual(detailRequests, 2, 'a missing image/attachment should refresh image detail');
        assertTrue(view.querySelector('.form-message').textContent.includes('attachment was not found'));
    } finally {
        window.confirm = originalConfirm;
        restore();
    }
}
