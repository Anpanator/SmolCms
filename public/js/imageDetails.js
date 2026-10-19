/**
 * Shared sample-image and metadata presentation for image detail pages.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';

/**
 * @returns {{
 *   imageHolder: HTMLDivElement,
 *   metaSection: HTMLDivElement,
 *   metadata: HTMLDListElement,
 *   update: (image: object|null) => void,
 * }}
 */
export function createImageDetails() {
    const imageHolder = el('div', {className: 'image-view'});
    const metadata = el('dl', {className: 'image-meta'});
    const metaSection = el('div', {
        className: 'image-meta-section',
        children: [el('hr', {className: 'image-meta-separator'}), metadata],
    });
    let downloadLink = null;

    return {imageHolder, metaSection, metadata, update};

    function update(image) {
        imageHolder.replaceChildren();
        metadata.replaceChildren();
        downloadLink?.remove();
        downloadLink = null;

        if (!image) {
            return;
        }

        if (image.sampleSrc) {
            imageHolder.append(el('img', {
                className: 'image-view-image',
                attributes: {src: image.sampleSrc, alt: 'Uploaded image'},
            }));
        } else {
            imageHolder.append(el('p', {
                className: 'image-processing',
                children: 'Image is still being processed.',
            }));
        }

        metadata.append(
            createMetaItem('Uploaded', image.uploadDate ?? '—'),
            createMetaItem('Uploader', image.uploader ?? '—'),
            createMetaItem('Dimensions', formatDimensions(image.width, image.height)),
            createMetaItem('Format', image.format ?? '—'),
            createMetaItem('File size', formatFileSize(image.fileSize)),
        );

        if (image.downloadSrc) {
            downloadLink = el('a', {
                className: 'button button--primary image-download',
                attributes: {href: image.downloadSrc, download: 'download'},
                children: 'Download',
            });
            metaSection.append(downloadLink);
        }
    }
}

function createMetaItem(term, value) {
    return el('div', {className: 'image-meta-item', children: [
        el('dt', {className: 'image-meta-term', children: term}),
        el('dd', {className: 'image-meta-value', children: value}),
    ]});
}

function formatDimensions(width, height) {
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
