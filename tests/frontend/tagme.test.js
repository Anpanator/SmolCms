import {assertEqual, assertFalse, assertTrue, settle} from './assert.js';
import {mockFetch, testUser, useFetch} from './mocks.js';
import {createTagmeView} from '../../public/js/views/tagme.js';

const IMAGE = {
    id: 7,
    uploadDate: '2026-10-19 10:30:00',
    uploader: 'Upload User',
    width: 100,
    height: 50,
    format: 'jpeg',
    fileSize: 1234,
    sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
    downloadSrc: '/api/image/download?name=ab89c0d0ef.jpeg',
    tags: ['nature', 'tagme'],
};

async function createView(handler, session = {getUser: () => testUser()}) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const navigateCalls = [];
    const view = createTagmeView({
        session,
        navigate: (path) => navigateCalls.push(path),
        query: new URLSearchParams(),
    });
    await settle();
    return {view, fetchMock, navigateCalls, restore};
}

export async function testTagmeRendersOneRandomImageAndSharedTagControls() {
    const {view, fetchMock, restore} = await createView(() => ({status: 200, body: {image: IMAGE}}));
    try {
        assertTrue(view.matches('.layout.tagme-layout'));
        assertEqual(view.querySelector('.tagme-toolbar .content-title')?.textContent, 'Tagme queue');
        assertEqual(view.querySelectorAll('.content img.image-view-image').length, 1);
        assertEqual(view.querySelector('.image-view-image')?.getAttribute('src'), IMAGE.sampleSrc);
        assertEqual(view.querySelectorAll('.tags-item').length, 2);
        assertTrue(view.querySelector('.tag-add-input') !== null, 'tagme should use the shared tag editor');
        assertTrue(view.querySelector('.tag-remove-button[data-tag-name="tagme"]') !== null);
        assertTrue(view.querySelector('.quick-tags-sidebar').hidden, 'quick tags should start hidden');
        assertEqual(view.querySelector('.quick-tags-toggle')?.textContent, 'Show quick tags');
        assertEqual(fetchMock.calls.length, 1);
        assertEqual(fetchMock.calls[0].url, '/api/tagme/random');
        assertEqual(fetchMock.calls[0].options.method, 'GET');
    } finally {
        restore();
    }
}

export async function testTagmeQuickTagButtonsFollowEachNewImageWithoutReloadingLists() {
    let imageNumber = 0;
    let quickTagsEnabled = false;
    const session = {
        getUser: () => testUser(),
        getQuickTagsEnabled: () => quickTagsEnabled,
        setQuickTagsEnabled: (enabled) => { quickTagsEnabled = enabled; },
    };
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/tagme/random') {
            imageNumber++;
            return {status: 200, body: {image: {
                ...IMAGE,
                id: imageNumber,
                sampleSrc: `/sample-${imageNumber}.webp`,
                tags: imageNumber === 1 ? ['nature', 'tagme'] : ['sky', 'tagme'],
            }}};
        }
        if (url === '/api/session/quick-tags' && options.method === 'POST') {
            return {status: 200, body: {quickTagsEnabled: JSON.parse(options.body).enabled}};
        }
        if (url === '/api/tags/quick') {
            return {status: 200, body: {
                global: [
                    {name: 'nature', category: 'General'},
                    {name: 'sky', category: 'General'},
                ],
                userMostUsed: [],
                userRecent: [],
            }};
        }
        return {status: 200, body: null};
    }, session);
    try {
        const toggle = view.querySelector('.quick-tags-toggle');
        toggle.click();
        await settle();
        const quickTags = view.querySelector('.quick-tags-sidebar');
        assertFalse(quickTags.hidden);
        assertEqual(quickTags.querySelector('.quick-tag-button[data-tag-name="nature"]')?.getAttribute('aria-pressed'), 'true');
        assertEqual(quickTags.querySelector('.quick-tag-button[data-tag-name="sky"]')?.getAttribute('aria-pressed'), 'false');

        view.querySelector('.tagme-next-button').click();
        await settle();

        assertEqual(view.querySelector('.image-view-image')?.getAttribute('src'), '/sample-2.webp');
        assertEqual(quickTags.querySelector('.quick-tag-button[data-tag-name="nature"]')?.getAttribute('aria-pressed'), 'false');
        assertEqual(quickTags.querySelector('.quick-tag-button[data-tag-name="sky"]')?.getAttribute('aria-pressed'), 'true');
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tags/quick').length, 1,
            'switching images should update active tags without refetching the lists');
    } finally {
        restore();
    }
}

export async function testTagmeNextButtonRefetchesAndRendersTheNewImage() {
    let requestNumber = 0;
    const {view, fetchMock, restore} = await createView(() => {
        requestNumber++;
        const image = {...IMAGE, id: requestNumber, sampleSrc: `/sample-${requestNumber}.webp`, uploader: `User ${requestNumber}`};
        return {status: 200, body: {image}};
    });
    try {
        const nextButton = view.querySelector('.tagme-next-button');
        assertTrue(!nextButton.disabled, 'the next-image button should be usable after the initial request');
        nextButton.click();
        await settle();

        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tagme/random').length, 2);
        assertEqual(view.querySelector('.image-view-image')?.getAttribute('src'), '/sample-2.webp');
        assertTrue(view.querySelector('.image-meta').textContent.includes('User 2'));
        assertEqual(view.querySelector('.tags-item')?.getAttribute('data-tag-name'), 'nature');
    } finally {
        restore();
    }
}

export async function testTagmeRefetchHandlesAnEmptyQueueWithFriendlyNotice() {
    let requestNumber = 0;
    const {view, fetchMock, restore} = await createView(() => {
        requestNumber++;
        return requestNumber === 1
            ? {status: 200, body: {image: IMAGE}}
            : {status: 404, body: null};
    });
    try {
        view.querySelector('.tagme-next-button').click();
        await settle();

        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tagme/random').length, 2);
        assertEqual(view.querySelector('.image-view-image'), null, 'the previous image should clear when the queue is empty');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.textContent.includes('No images are waiting for tags right now'));
        assertTrue(message.classList.contains('form-message--info'));
        assertTrue(!view.querySelector('.tagme-next-button').disabled, 'the queue can be retried after a 404');
    } finally {
        restore();
    }
}

export async function testTagmeInitial404ShowsFriendlyNotice() {
    const {view, restore} = await createView(() => ({status: 404, body: null}));
    try {
        assertTrue(view.querySelector('.form-message:not([hidden])').textContent.includes('Try again later'));
        assertEqual(view.querySelector('.image-view-image'), null);
        assertEqual(view.querySelectorAll('.tags-item').length, 0);
    } finally {
        restore();
    }
}
