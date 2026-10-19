/**
 * Image display view: shows the viewing image for a single image together
 * with its metadata in the sidebar (GET /api/image/detail). Deep-linkable
 * via /image?id=<id>; the id is read from the URL query string.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {el} from '../dom.js';
import {createMessageBox, isAuthError, messageForStatus, showMessage} from '../forms.js';
import {createSearchView, normalizeTags} from '../search.js';
import {createTagView} from './home.js';

const DETAIL_URL = '/api/image/detail';
const NOT_FOUND_MESSAGE = 'Image not found.';

export function createImageView({ navigate, query = new URLSearchParams(window.location.search) } = {}) {
    const search = createSearchView({
        onSearch: (tags) => navigate?.(buildHomePath(tags)),
    });
    const tagView = createTagView();
    const metaList = el('dl', { className: 'image-meta' });
    const metaSection = el('div', {
        className: 'image-meta-section',
        children: [el('hr', {className: 'image-meta-separator'}), metaList],
    });
    const sidebar = el('aside', {
        className: 'sidebar',
        children: [
            search.element,
            el('h2', { className: 'sidebar-title', children: 'Tags' }),
            tagView,
            metaSection,
        ],
    });

    const status = createMessageBox();
    const imageHolder = el('div', { className: 'image-view' });
    const content = el('section', {
        className: 'content',
        children: [status, imageHolder],
    });

    loadImage(parseImageId(query.get('id')), { navigate, imageHolder, metaList, metaSection, status, tagView });

    return el('div', { className: 'layout image-layout', children: [sidebar, content] });
}

/** Positive-integer string or null (anything else is rejected server-side with 400). */
function parseImageId(raw) {
    return /^[1-9][0-9]*$/.test(raw ?? '') ? Number(raw) : null;
}

async function loadImage(id, { navigate, imageHolder, metaList, metaSection, status, tagView }) {
    if (id === null) {
        showMessage(status, NOT_FOUND_MESSAGE);
        return;
    }
    try {
        const data = await get(`${DETAIL_URL}?id=${id}`);
        renderImage(data?.image ?? null, { imageHolder, metaList, metaSection, status, tagView });
    } catch (error) {
        if (isAuthError(error)) {
            navigate('/login');
            return;
        }
        showMessage(status, error?.status === 404 ? NOT_FOUND_MESSAGE : messageForStatus(error?.status ?? 0));
    }
}

function renderImage(image, { imageHolder, metaList, metaSection, status, tagView }) {
    if (!image) {
        showMessage(status, NOT_FOUND_MESSAGE);
        return;
    }

    tagView.replaceWith(createTagView(Array.isArray(image.tags) ? image.tags : []));

    if (image.sampleSrc) {
        imageHolder.append(el('img', {
            className: 'image-view-image',
            attributes: { src: image.sampleSrc, alt: 'Uploaded image' },
        }));
    } else {
        imageHolder.append(el('p', {
            className: 'image-processing',
            children: 'Image is still being processed.',
        }));
    }

    metaList.append(
        createMetaItem('Uploaded', image.uploadDate ?? '—'),
        createMetaItem('Uploader', image.uploader ?? '—'),
        createMetaItem('Dimensions', formatDimensions(image.width, image.height)),
        createMetaItem('Format', image.format ?? '—'),
        createMetaItem('File size', formatFileSize(image.fileSize)),
    );

    if (image.downloadSrc) {
        metaSection.append(el('a', {
            className: 'button button--primary image-download',
            attributes: { href: image.downloadSrc, download: 'download' },
            children: 'Download',
        }));
    }
}

function createMetaItem(term, value) {
    return el('div', { className: 'image-meta-item', children: [
        el('dt', { className: 'image-meta-term', children: term }),
        el('dd', { className: 'image-meta-value', children: value }),
    ]});
}

function formatDimensions(width, height) {
    return width === null || width === undefined || height === null || height === undefined
        ? '—'
        : `${width} × ${height}`;
}

function buildHomePath(tags) {
    const normalizedTags = normalizeTags(tags);
    return normalizedTags === ''
        ? '/'
        : `/?${new URLSearchParams({ tags: normalizedTags })}`;
}

/** Human-readable file size in B/KB/MB; "—" when unknown. */
export function formatFileSize(bytes) {
    if (typeof bytes !== 'number' || !Number.isFinite(bytes) || bytes < 0) {
        return '—';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 ** 2) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}
