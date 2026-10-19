/**
 * Home view: static tag sidebar plus a live tile grid of the newest
 * accepted images (GET /api/images). The grid loads asynchronously with
 * skeleton tiles, an empty state and a non-blocking error message.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {el} from '../dom.js';
import {messageForStatus} from '../forms.js';

const IMAGES_URL = '/api/images';
const LOADING_TILE_COUNT = 6;

export const TAGS = [
    'landscape', 'portrait', 'anime', 'cityscape', 'nature', 'monochrome',
    'food', 'architecture', 'night', 'sky', 'water', 'cats',
];

/** Shared tag list, used by the home sidebar and the image display sidebar. */
export function createTagList() {
    return el('ul', {
        className: 'tags',
        children: TAGS.map((tag) => el('li', {
            className: 'tags-item',
            children: el('a', { className: 'tags-link', attributes: { href: '#' }, children: tag }),
        })),
    });
}

export function createHomeView() {
    const sidebar = el('aside', {
        className: 'sidebar',
        children: [el('h2', { className: 'sidebar-title', children: 'Tags' }), createTagList()],
    });

    const grid = el('div', { className: 'grid' });
    showLoadingTiles(grid);

    const content = el('section', {
        className: 'content',
        children: [grid],
    });

    loadImages(grid);

    return el('div', { className: 'layout', children: [sidebar, content] });
}

function showLoadingTiles(grid) {
    grid.replaceChildren(...Array.from({ length: LOADING_TILE_COUNT }, () => el('div', {
        className: 'tile tile--skeleton',
        attributes: { 'aria-hidden': 'true' },
    })));
}

async function loadImages(grid) {
    try {
        const data = await get(IMAGES_URL);
        const images = Array.isArray(data?.images) ? data.images : [];
        if (images.length === 0) {
            grid.replaceChildren(createGridStatus('No images uploaded yet.', 'empty'));
            return;
        }
        grid.replaceChildren(...images.map(createTile));
    } catch (error) {
        grid.replaceChildren(createGridStatus(messageForStatus(error?.status ?? 0), 'error'));
    }
}

function createGridStatus(text, kind) {
    return el('p', { className: `grid-status grid-status--${kind}`, children: text });
}

function createTile(image) {
    const child = image.thumbSrc
        ? el('img', { className: 'tile-image', attributes: { src: image.thumbSrc, alt: '', loading: 'lazy' } })
        : el('span', { className: 'tile-placeholder', children: 'No preview' });
    return el('a', {
        className: 'tile tile--link',
        attributes: { href: `/image?id=${image.id}` },
        children: child,
    });
}
