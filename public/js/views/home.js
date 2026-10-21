/**
 * Home view: paginated image grid with page-specific tags, tag search,
 * skeleton tiles, an empty state and a non-blocking error message.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {el} from '../dom.js';
import {createPagination} from '../pagination.js';
import {createSearchView, normalizeTags} from '../search.js';
import {messageForStatus} from '../forms.js';
import {createTagLinkList, normalizeActiveTags} from '../tagLinks.js';

const IMAGES_URL = '/api/images';
const DEFAULT_PER_PAGE = 25;
const LOADING_TILE_COUNT = 6;

/** Build the shared list of tag links used by the home and image sidebars. */
export function createTagList(tags = [], activeTags = []) {
    return createTagLinkList({
        tags,
        activeTags,
        hrefForTag(tagName, {active: isActive, activeTags: active}) {
            const nextActive = new Set(active);
            if (isActive) {
                nextActive.delete(tagName);
            } else {
                nextActive.add(tagName);
            }
            return buildHomePath(1, [...nextActive].join(','));
        },
    });
}

/** Render the tag list and an affordance to clear any active filters. */
export function createTagView(tags = [], activeTags = []) {
    const active = normalizeActiveTags(activeTags);
    return el('div', {
        className: 'tag-view',
        children: [
            active.size > 0 ? el('a', {
                className: 'tags-clear',
                attributes: { href: '/' },
                children: 'Clear filters',
            }) : null,
            createTagList(tags, active),
        ],
    });
}

/**
 * @param {{ navigate?: (path: string, options?: object) => void, query?: URLSearchParams|string }} [context]
 */
export function createHomeView({navigate = () => {}, query = new URLSearchParams(window.location.search)} = {}) {
    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query);
    const requestedPage = parsePositiveInteger(params.get('page'), 1);
    const tags = normalizeTags(params.get('tags') ?? '');
    const activeTags = tags === '' ? [] : tags.split(',');

    const search = createSearchView({
        initialTags: tags,
        onSearch: (searchTags) => navigate(buildHomePath(1, searchTags)),
    });
    const toolbar = el('div', {
        className: 'layout-toolbar home-toolbar',
        children: search.element,
    });
    const tagView = createTagView([], activeTags);
    const sidebar = el('aside', {
        className: 'sidebar',
        children: [
            el('h2', { className: 'sidebar-title', children: 'Tags' }),
            tagView,
        ],
    });

    const grid = el('div', { className: 'grid' });
    showLoadingTiles(grid);
    const pagination = createPagination(requestedPage, (page) => {
        navigate(buildHomePath(page, tags));
    });
    pagination.element.setAttribute('aria-label', 'Image pages');

    const content = el('section', {
        className: 'content',
        children: [grid, pagination.element],
    });

    loadImages({ grid, tagView, pagination, page: requestedPage, tags, navigate });

    return el('div', { className: 'layout home-layout', children: [toolbar, sidebar, content] });
}

function showLoadingTiles(grid) {
    grid.replaceChildren(...Array.from({ length: LOADING_TILE_COUNT }, () => el('div', {
        className: 'tile tile--skeleton',
        attributes: { 'aria-hidden': 'true' },
    })));
}

async function loadImages({grid, tagView, pagination, page, tags, navigate}) {
    try {
        const data = await get(buildImagesUrl(page, tags));
        const images = Array.isArray(data?.images) ? data.images : [];
        const total = Number.isSafeInteger(data?.total) && data.total >= 0 ? data.total : images.length;
        const perPage = parsePositiveInteger(data?.perPage, DEFAULT_PER_PAGE);
        const responsePage = parsePositiveInteger(data?.page, page);
        const totalPages = Math.max(1, Math.ceil(total / perPage));

        if (responsePage > totalPages) {
            navigate(buildHomePath(totalPages, tags), { replace: true });
            return;
        }

        if (responsePage !== page) {
            navigate(buildHomePath(responsePage, tags), { replace: true });
        }

        tagView.replaceWith(createTagView(Array.isArray(data?.tags) ? data.tags : [], tags.split(',').filter(Boolean)));
        pagination.update({ page: responsePage, total, perPage });

        if (images.length === 0) {
            grid.replaceChildren(createGridStatus('No images uploaded yet.', 'empty'));
            return;
        }
        grid.replaceChildren(...images.map(createTile));
    } catch (error) {
        pagination.update({ page, total: 0, perPage: DEFAULT_PER_PAGE });
        grid.replaceChildren(createGridStatus(messageForStatus(error?.status ?? 0), 'error'));
    }
}

function buildImagesUrl(page, tags) {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (tags !== '') {
        params.set('tags', tags);
    }
    return `${IMAGES_URL}?${params}`;
}

function buildHomePath(page, tags) {
    const params = new URLSearchParams();
    if (page > 1) {
        params.set('page', String(page));
    }
    const normalizedTags = normalizeTags(tags);
    if (normalizedTags !== '') {
        params.set('tags', normalizedTags);
    }
    const query = params.toString();
    return query === '' ? '/' : `/?${query}`;
}

function parsePositiveInteger(value, fallback) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : fallback;
    }
    if (!/^[1-9][0-9]*$/.test(value ?? '')) {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : fallback;
}

function createGridStatus(text, kind) {
    return el('p', { className: `grid-status grid-status--${kind}`, children: text });
}

function createTile(image) {
    const child = image.thumbSrc
        ? el('img', { className: 'tile-image', attributes: { src: image.thumbSrc, alt: '', loading: 'lazy' } })
        : el('span', { className: 'tile-placeholder', children: 'No preview' });
    return el('a', {
        className: `tile tile--link${image.status === 'pending_review' ? ' tile-unapproved' : ''}`,
        attributes: {href: `/image?id=${image.id}`, 'data-image-id': image.id},
        children: child,
    });
}
