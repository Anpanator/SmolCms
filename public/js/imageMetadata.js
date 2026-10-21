/** Shared image metadata formatting and presentation. */
import {el} from './dom.js';
import {userProfileHref} from './routes.js';

/** Render metadata entries using a page-specific CSS class prefix. */
export function createImageMetadataItems(image, {className = 'image-meta', includeId = false} = {}) {
    const details = image ?? {};
    const uploaderHref = userProfileHref(details.uploaderId);
    const uploader = details.uploader ?? '—';
    const entries = [
        ...(includeId ? [['Image ID', details.id ?? '—']] : []),
        ['Uploaded', details.uploadDate ?? '—'],
        ['Uploader', uploaderHref === null
            ? uploader
            : el('a', {className: 'user-profile-link', attributes: {href: uploaderHref}, children: uploader})],
        ['Dimensions', formatDimensions(details.width, details.height)],
        ['Format', details.format ?? '—'],
        ['File size', formatFileSize(details.fileSize)],
    ];

    return entries.map(([term, value]) => el('div', {
        className: `${className}-item`,
        children: [
            el('dt', {className: `${className}-term`, children: term}),
            el('dd', {className: `${className}-value`, children: value}),
        ],
    }));
}

export function formatDimensions(width, height) {
    return width === null || width === undefined || height === null || height === undefined
        ? '—'
        : `${width} × ${height}`;
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
