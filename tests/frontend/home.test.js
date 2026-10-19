import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, useFetch} from './mocks.js';
import {createHomeView} from '../../public/js/views/home.js';

const IMAGES = {
    images: [
        { id: 7, thumbSrc: '/public/img/thumb/ab/ab89c0.avif' },
        { id: 3, thumbSrc: '/public/img/thumb/cd/cd1234.avif' },
    ],
};

async function createLoadedView(handler) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const view = createHomeView();
    await settle();
    return { view, fetchMock, restore };
}

export async function testHomeViewRendersSidebarTagsAndImageGrid() {
    const { view, fetchMock, restore } = await createLoadedView(() => ({ status: 200, body: IMAGES }));
    try {
        assertTrue(view.matches('.layout'), 'home should use the .layout grid');
        assertTrue(view.querySelector('.sidebar .tags') !== null, 'sidebar tag list should exist');
        assertTrue(view.querySelectorAll('.tags .tags-item').length >= 5, 'several tags should be listed');
        assertTrue(view.querySelector('.content .grid') !== null, 'content tile grid should exist');
        assertTrue(view.querySelector('.content-title') === null, 'home should not have a Latest uploads header');

        assertEqual(fetchMock.calls.length, 1, 'home should fetch the image list once');
        assertEqual(fetchMock.calls[0].url, '/api/images');
        assertEqual(fetchMock.calls[0].options.method, 'GET');

        const tiles = view.querySelectorAll('.grid a.tile');
        assertEqual(tiles.length, 2, 'each image should become a tile');
        const image = tiles[0].querySelector('img.tile-image');
        assertTrue(image !== null, 'tiles should contain images');
        assertEqual(image.getAttribute('src'), '/public/img/thumb/ab/ab89c0.avif');
        assertEqual(image.getAttribute('alt'), '');
        assertEqual(tiles[0].getAttribute('href'), '/image?id=7', 'tiles should link to the image display view');
        assertEqual(tiles[1].getAttribute('href'), '/image?id=3');
    } finally {
        restore();
    }
}

export function testHomeTagLinksAreStatic() {
    const fetchMock = mockFetch(() => ({ status: 200, body: IMAGES }));
    const restore = useFetch(fetchMock);
    const view = createHomeView();
    restore();

    const firstTag = view.querySelector('.tags .tags-link');
    assertEqual(firstTag?.tagName, 'A');
    assertTrue(firstTag?.textContent.length > 0, 'tags should have labels');
}

export function testHomeGridShowsLoadingStateFirst() {
    const fetchMock = mockFetch(() => ({ status: 200, body: IMAGES }));
    const restore = useFetch(fetchMock);
    try {
        const view = createHomeView();
        assertTrue(
            view.querySelector('.grid .tile--skeleton') !== null,
            'grid should show a loading state before the fetch resolves',
        );
        return settle().then(() => {
            assertEqual(view.querySelectorAll('.grid a.tile').length, 2, 'tiles should replace the loading state');
            assertEqual(view.querySelectorAll('.grid .tile--skeleton').length, 0);
        });
    } finally {
        restore();
    }
}

export async function testHomeGridShowsEmptyState() {
    const { view, restore } = await createLoadedView(() => ({ status: 200, body: { images: [] } }));
    try {
        const status = view.querySelector('.grid .grid-status');
        assertTrue(status !== null, 'an empty grid should show a status message');
        assertTrue(status.classList.contains('grid-status--empty'), 'empty state should be marked as such');
        assertTrue(status.textContent.includes('No images uploaded yet'), 'empty state should explain the empty grid');
        assertEqual(view.querySelectorAll('.grid .tile').length, 0, 'an empty grid has no tiles');
    } finally {
        restore();
    }
}

export async function testHomeGridShowsErrorMessageOnFailure() {
    const { view, restore } = await createLoadedView(() => ({ status: 500, body: null }));
    try {
        const status = view.querySelector('.grid .grid-status');
        assertTrue(status !== null, 'a fetch failure should show a message');
        assertTrue(status.classList.contains('grid-status--error'), 'failure message should be marked as an error');
        assertTrue(view.querySelector('.sidebar .tags') !== null, 'sidebar should stay usable on grid failure');
    } finally {
        restore();
    }
}

export async function testHomeTileWithoutThumbShowsPlaceholder() {
    const { view, restore } = await createLoadedView(
        () => ({ status: 200, body: { images: [{ id: 9, thumbSrc: null }] } }),
    );
    try {
        const tile = view.querySelector('.grid a.tile');
        assertTrue(tile !== null, 'the image should still become a tile');
        assertEqual(tile.getAttribute('href'), '/image?id=9');
        assertTrue(tile.querySelector('img') === null, 'a tile without a thumb renders no image');
        assertTrue(tile.querySelector('.tile-placeholder') !== null, 'a tile without a thumb shows placeholder text');
    } finally {
        restore();
    }
}
