/**
 * Shared sample-image and metadata presentation for image detail pages.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';
import {createImageMetadataItems} from './imageMetadata.js';

export {formatDimensions, formatFileSize} from './imageMetadata.js';

const DOWNLOAD_DISABLED_NOTE = 'Log in to download images';

/**
 * @returns {{
 *   imageHolder: HTMLDivElement,
 *   metaSection: HTMLDivElement,
 *   metadata: HTMLDivElement,
 *   pendingWarning: HTMLParagraphElement,
 *   update: (image: object|null) => void,
 * }}
 */
export function createImageDetails({canDownload = true} = {}) {
    const imageHolder = el('div', {className: 'image-view'});
    const uploadMetadata = el('dl', {className: 'image-meta-list'});
    const metadata = el('dl', {className: 'image-meta-list'});
    const pendingWarning = el('p', {
        className: 'image-pending-warning',
        attributes: {role: 'status', hidden: 'hidden'},
        children: 'This image is pending approval.',
    });
    const metadataContent = el('div', {
        className: 'image-meta image-meta-content',
        children: [pendingWarning, uploadMetadata, metadata],
    });
    const metaSection = el('div', {
        className: 'image-meta-section',
        children: [el('hr', {className: 'image-meta-separator'}), metadataContent],
    });
    let downloadControl = null;

    return {imageHolder, metaSection, metadata: metadataContent, pendingWarning, update};

    function update(image) {
        imageHolder.replaceChildren();
        uploadMetadata.replaceChildren();
        metadata.replaceChildren();
        downloadControl?.remove();
        downloadControl = null;
        pendingWarning.hidden = image?.status !== 'pending_review';

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

        const metadataItems = createImageMetadataItems(image);
        uploadMetadata.append(metadataItems[0]);
        metadata.append(...metadataItems.slice(1));

        if (image.downloadSrc) {
            downloadControl = canDownload
                ? el('a', {
                    className: 'button button--primary image-download',
                    attributes: {href: image.downloadSrc, download: 'download'},
                    children: 'Download',
                })
                : el('span', {
                    className: 'image-download-disabled-wrapper',
                    attributes: {title: DOWNLOAD_DISABLED_NOTE},
                    children: el('button', {
                        className: 'button button--primary image-download image-download--disabled',
                        attributes: {type: 'button', disabled: 'disabled'},
                        children: 'Download',
                    }),
                });
            metaSection.append(downloadControl);
        }
    }
}
