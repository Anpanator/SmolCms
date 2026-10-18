/**
 * Home view: static tag sidebar plus a placeholder content tile grid.
 * Importing this module has no side effects.
 */
import { el } from '../dom.js';

const TAGS = [
    'landscape', 'portrait', 'anime', 'cityscape', 'nature', 'monochrome',
    'food', 'architecture', 'night', 'sky', 'water', 'cats',
];

const TILE_COUNT = 12;

export function createHomeView() {
    const tags = el('ul', {
        className: 'tags',
        children: TAGS.map((tag) => el('li', {
            className: 'tags-item',
            children: el('a', { className: 'tags-link', attributes: { href: '#' }, children: tag }),
        })),
    });

    const sidebar = el('aside', {
        className: 'sidebar',
        children: [el('h2', { className: 'sidebar-title', children: 'Tags' }), tags],
    });

    const tiles = Array.from({ length: TILE_COUNT }, (_, index) => el('div', {
        className: 'tile',
        attributes: { 'data-tile': String(index + 1) },
        children: el('span', { className: 'tile-placeholder', children: `Image ${index + 1}` }),
    }));

    const grid = el('div', { className: 'grid', children: tiles });

    return el('div', {
        className: 'layout',
        children: [
            sidebar,
            el('section', {
                className: 'content',
                children: [el('h1', { className: 'content-title', children: 'Latest uploads' }), grid],
            }),
        ],
    });
}
