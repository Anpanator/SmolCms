import { assertEqual, assertTrue, settle } from './assert.js';
import { mockFetch, useFetch } from './mocks.js';
import { createImageView, formatFileSize } from '../../public/js/views/image.js';

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
};

function detailResponse(overrides = {}) {
    return { status: 200, body: { image: { ...DETAIL, ...overrides } } };
}

async function createView(handler, query = 'id=7') {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const navigateCalls = [];
    const view = createImageView({
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
        assertTrue(view.querySelector('.sidebar .image-meta') !== null, 'metadata section should live in the sidebar');

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
        assertTrue(download.textContent.includes('Download full size'));
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
