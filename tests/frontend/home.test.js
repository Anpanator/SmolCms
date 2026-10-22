import {assertEqual, assertFalse, assertTrue, settle} from './assert.js';
import {mockFetch, useFetch} from './mocks.js';
import {createHomeView} from '../../public/js/views/home.js';

const IMAGES = {
    images: [
        { id: 7, thumbSrc: '/public/img/thumb/ab/ab89c0.avif', status: 'accepted' },
        { id: 3, thumbSrc: '/public/img/thumb/cd/cd1234.avif', status: 'accepted' },
    ],
    page: 1,
    perPage: 15,
    total: 27,
    tags: [
        { name: 'nature', imageCount: 2 },
        { name: 'sky', imageCount: 1 },
    ],
};

async function createLoadedView(handler, query = '', navigate = () => {}) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const view = createHomeView({ navigate, query: new URLSearchParams(query) });
    await settle();
    return { view, fetchMock, restore };
}

export async function testHomeViewRendersPageTagsAndImageGrid() {
    const { view, fetchMock, restore } = await createLoadedView(() => ({ status: 200, body: IMAGES }));
    try {
        assertTrue(view.matches('.layout.home-layout'), 'home should use the .layout grid');
        assertTrue(view.querySelector('.home-toolbar .search-view') !== null, 'home should have a tag search above the layout content');
        const sidebarColumn = view.querySelector('.home-sidebar-column');
        assertTrue(sidebarColumn !== null, 'search and sidebar should share a column');
        assertTrue(sidebarColumn.querySelector(':scope > .home-toolbar .search-view') !== null,
            'the tag search should stay above the sidebar');
        assertTrue(sidebarColumn.querySelector(':scope > .sidebar') !== null,
            'the sidebar should sit below the tag search');
        assertEqual(sidebarColumn.querySelector('.sidebar .search-view'), null,
            'home sidebar should not contain tag search');
        assertTrue(sidebarColumn.querySelector('.sidebar .tags') !== null, 'sidebar tag list should exist');
        assertTrue(view.querySelector(':scope > .content .grid') !== null,
            'the grid should be a direct layout column so it can start at the top');
        assertEqual(view.querySelector('.quick-tags-sidebar'), null, 'home does not have a quick-tag sidebar');
        assertFalse(view.classList.contains('layout--with-quick-tags'));
        assertEqual(view.querySelectorAll('.tags .tags-item').length, 2, 'the page tags should replace static tags');
        assertEqual(view.querySelector('.tags-link[data-tag-name="nature"]')?.textContent, 'nature');
        assertEqual(view.querySelector('.tags-link[data-tag-name="sky"]')?.textContent, 'sky');
        assertTrue(view.querySelector('.content .grid') !== null, 'content tile grid should exist');
        assertTrue(view.querySelector('.content-title') === null, 'home should not have a Latest uploads header');

        assertEqual(fetchMock.calls.length, 1, 'home should fetch the image list once');
        assertEqual(fetchMock.calls[0].url, '/api/images?page=1');
        assertEqual(fetchMock.calls[0].options.method, 'GET');

        const tiles = view.querySelectorAll('.grid a.tile');
        assertEqual(tiles.length, 2, 'each image should become a tile');
        const image = view.querySelector('.grid a.tile[data-image-id="7"] img.tile-image');
        assertTrue(image !== null, 'tiles should contain images');
        assertEqual(image.getAttribute('src'), '/public/img/thumb/ab/ab89c0.avif');
        assertEqual(image.getAttribute('alt'), '');
        assertEqual(
            view.querySelector('.grid a.tile[data-image-id="7"]')?.getAttribute('href'),
            '/image?id=7',
            'tiles should link to the image display view',
        );
        assertEqual(view.querySelector('.grid a.tile[data-image-id="3"]')?.getAttribute('href'), '/image?id=3');
    } finally {
        restore();
    }
}

export async function testHomeTagLinksAreDynamicAndClearable() {
    const { view, restore } = await createLoadedView(
        () => ({ status: 200, body: IMAGES }),
        'tags=nature',
    );
    try {
        const links = view.querySelectorAll('.tags .tags-link');
        assertEqual(links.length, 2);
        const natureLink = view.querySelector('.tags .tags-link[data-tag-name="nature"]');
        const skyLink = view.querySelector('.tags .tags-link[data-tag-name="sky"]');
        assertEqual(natureLink.getAttribute('href'), '/', 'clicking an active tag should remove that filter');
        assertTrue(natureLink.classList.contains('tags-link--active'), 'the selected tag should be marked active');
        assertEqual(natureLink.getAttribute('aria-current'), 'page');
        assertTrue(!skyLink.classList.contains('tags-link--active'), 'other tags should not be marked active');
        assertEqual(skyLink.getAttribute('href'), '/?tags=nature%2Csky', 'adding a tag should preserve active filters');
        assertEqual(view.querySelector('.tags-clear')?.getAttribute('href'), '/', 'active filters should be clearable');
        assertEqual(view.querySelector('.search-input')?.value, 'nature');
    } finally {
        restore();
    }
}

export async function testHomeTagLinksToggleIndividualFilters() {
    const {view, restore} = await createLoadedView(
        () => ({
            status: 200,
            body: {...IMAGES, tags: [...IMAGES.tags, {name: 'clouds', imageCount: 1}]},
        }),
        'tags=nature%2Csky',
    );
    try {
        assertEqual(
            view.querySelector('.tags-link[data-tag-name="nature"]')?.getAttribute('href'),
            '/?tags=sky',
            'clicking an active tag should remove only that tag',
        );
        assertEqual(
            view.querySelector('.tags-link[data-tag-name="sky"]')?.getAttribute('href'),
            '/?tags=nature',
            'the other active filter should remain selected',
        );
        assertEqual(
            view.querySelector('.tags-link[data-tag-name="clouds"]')?.getAttribute('href'),
            '/?tags=nature%2Csky%2Cclouds',
            'clicking an inactive tag should add it to the active filters',
        );
    } finally {
        restore();
    }
}

export async function testHomePaginationUsesUrlStateAndKeepsTagFilter() {
    const navigation = [];
    const { view, fetchMock, restore } = await createLoadedView(
        () => ({ status: 200, body: { ...IMAGES, page: 2, total: 45 } }),
        'page=2&tags=nature',
        (path) => navigation.push(path),
    );
    try {
        assertEqual(fetchMock.calls[0].url, '/api/images?page=2&tags=nature');
        assertEqual(view.querySelector('.pagination-label')?.textContent, 'Page 2 of 3');

        const previous = view.querySelector('.pagination-button--previous');
        const next = view.querySelector('.pagination-button--next');
        assertTrue(!previous.disabled, 'Previous should be enabled after page one');
        assertTrue(!next.disabled, 'Next should be enabled before the last page');
        next.click();
        previous.click();
        assertEqual(navigation[0], '/?page=3&tags=nature');
        assertEqual(navigation[1], '/?tags=nature');
    } finally {
        restore();
    }
}

export function testHomeGridShowsLoadingStateFirst() {
    const fetchMock = mockFetch(() => ({ status: 200, body: IMAGES }));
    const restore = useFetch(fetchMock);
    try {
        const view = createHomeView({ query: new URLSearchParams() });
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

export async function testHomeGridShowsEmptyStateAndNoTags() {
    const { view, restore } = await createLoadedView(
        () => ({ status: 200, body: { images: [], page: 1, perPage: 15, total: 0, tags: [] } }),
        'tags=missing',
    );
    try {
        const status = view.querySelector('.grid .grid-status');
        assertTrue(status !== null, 'an empty grid should show a status message');
        assertTrue(status.classList.contains('grid-status--empty'), 'empty state should be marked as such');
        assertTrue(status.textContent.includes('No images uploaded yet'), 'empty state should explain the empty grid');
        assertEqual(view.querySelectorAll('.grid .tile').length, 0, 'an empty grid has no tiles');
        assertEqual(view.querySelectorAll('.sidebar .tags-item').length, 0, 'an empty page has no tags');
        assertTrue(view.querySelector('.tags-clear') !== null, 'the active filter remains clearable');
        assertEqual(view.querySelector('.pagination-label')?.textContent, 'Page 1 of 1');
        assertTrue(view.querySelector('.pagination-button--previous').disabled);
        assertTrue(view.querySelector('.pagination-button--next').disabled);
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
        assertTrue(view.querySelector('.home-toolbar .search-view') !== null, 'search should stay usable on grid failure');
    } finally {
        restore();
    }
}

export async function testHomeTileWithoutThumbShowsPlaceholder() {
    const { view, restore } = await createLoadedView(
        () => ({ status: 200, body: {
            images: [{ id: 9, thumbSrc: null }],
            page: 1,
            perPage: 15,
            total: 1,
            tags: [],
        } }),
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

export async function testPendingReviewTilesGetUnapprovedClass() {
    const {view, restore} = await createLoadedView(
        () => ({status: 200, body: {
            images: [
                {id: 9, thumbSrc: '/public/img/thumb/ab/ab89c0.avif', status: 'pending_review'},
                {id: 10, thumbSrc: '/public/img/thumb/cd/cd1234.avif', status: 'accepted'},
            ],
            page: 1,
            perPage: 15,
            total: 2,
            tags: [],
        }}),
    );
    try {
        const pendingTile = view.querySelector('.grid a.tile[data-image-id="9"]');
        const acceptedTile = view.querySelector('.grid a.tile[data-image-id="10"]');
        assertTrue(pendingTile.classList.contains('tile-unapproved'), 'pending-review tiles should be marked unapproved');
        assertTrue(!acceptedTile.classList.contains('tile-unapproved'), 'accepted tiles should keep the normal style');
    } finally {
        restore();
    }
}

export async function testHomeSearchSubmitsEncodedFiltersAndClears() {
    const navigation = [];
    const { view, restore } = await createLoadedView(
        () => ({ status: 200, body: IMAGES }),
        '',
        (path) => navigation.push(path),
    );
    try {
        const form = view.querySelector('.search-view');
        const input = view.querySelector('.search-input');
        input.value = 'nature, sky';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        assertEqual(navigation[0], '/?tags=nature%2Csky');

        input.value = '  ';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        assertEqual(navigation[1], '/', 'submitting an empty search should clear the filter');
    } finally {
        restore();
    }
}
