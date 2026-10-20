/**
 * Shared sample-image and metadata presentation for image detail pages.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';
import {createImageMetadataItems} from './imageMetadata.js';

export {formatDimensions, formatFileSize} from './imageMetadata.js';

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

        metadata.append(...createImageMetadataItems(image));

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
