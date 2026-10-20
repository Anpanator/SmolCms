/**
 * Full-size image viewer and metadata display used by moderation views.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';
import {createImageMetadataItems} from './imageMetadata.js';

/**
 * @param {{image?: object|null}} [options]
 * @returns {{
 *   element: HTMLDivElement,
 *   metadata: HTMLDListElement,
 *   update: (image: object, options?: {status?: string|null}) => void,
 *   showStatus: (message: string) => void,
 *   clear: (message?: string|null) => void
 * }}
 */
export function createImageViewer({image = null} = {}) {
    const element = el('div', {
        className: 'review-image-frame',
        attributes: {'aria-live': 'polite'},
    });
    const metadata = el('dl', {className: 'review-metadata'});
    let imagePan = null;
    let suppressImageClick = false;

    if (image) {
        update(image);
    }

    return {element, metadata, update, showStatus, clear};

    function update(nextImage, {status = null} = {}) {
        renderMetadata(nextImage);
        resetImageFrame();

        if (status !== null) {
            renderStatus(status);
            return;
        }
        if (!nextImage?.downloadSrc) {
            renderStatus('The full-size image is still being processed.');
            return;
        }

        let fullSize = false;
        const imageElement = el('img', {
            className: 'review-image',
            attributes: {
                src: nextImage.downloadSrc,
                alt: `Full-size image ${nextImage.id} for moderation`,
                role: 'button',
                tabindex: '0',
                'aria-label': 'View image at full size; drag to pan',
                'aria-pressed': 'false',
                draggable: 'false',
            },
        });
        imageElement.addEventListener('click', (event) => {
            if (suppressImageClick) {
                suppressImageClick = false;
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            toggleImageSize();
        });
        imageElement.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            suppressImageClick = false;
            toggleImageSize();
        });
        imageElement.addEventListener('pointerdown', (event) => {
            if (imagePan || !fullSize || !event.isPrimary || event.button !== 0 || !imageOverflows()) {
                return;
            }
            suppressImageClick = false;
            imagePan = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                scrollLeft: element.scrollLeft,
                scrollTop: element.scrollTop,
                moved: false,
            };
            element.classList.add('review-image-frame--panning');
            document.addEventListener('pointermove', moveImage, {passive: false});
            document.addEventListener('pointerup', finishImagePan);
            document.addEventListener('pointercancel', cancelImagePan);
        });
        imageElement.addEventListener('dragstart', (event) => event.preventDefault());
        element.replaceChildren(imageElement);

        function toggleImageSize() {
            fullSize = !fullSize;
            setImageSize(fullSize);
        }

        function setImageSize(isFullSize) {
            cancelImagePan(null);
            element.classList.toggle('review-image-frame--full-size', isFullSize);
            element.scrollLeft = 0;
            element.scrollTop = 0;
            imageElement.classList.toggle('review-image--full-size', isFullSize);
            imageElement.setAttribute('aria-pressed', isFullSize ? 'true' : 'false');
            imageElement.setAttribute('aria-label', isFullSize
                ? 'Return to fitted image; drag to pan'
                : 'View image at full size; drag to pan');
        }
    }

    function showStatus(message) {
        resetImageFrame();
        renderStatus(message);
    }

    function clear(message = null) {
        resetImageFrame();
        metadata.replaceChildren();
        element.replaceChildren();
        if (message !== null) {
            renderStatus(message);
        }
    }

    function renderStatus(message) {
        element.replaceChildren(el('p', {className: 'image-processing', children: message}));
    }

    function imageOverflows() {
        return element.scrollWidth > element.clientWidth
            || element.scrollHeight > element.clientHeight;
    }

    function moveImage(event) {
        if (!imagePan || event.pointerId !== imagePan.pointerId) {
            return;
        }
        const deltaX = event.clientX - imagePan.startX;
        const deltaY = event.clientY - imagePan.startY;
        if (!imagePan.moved && Math.hypot(deltaX, deltaY) < 3) {
            return;
        }
        imagePan.moved = true;
        element.scrollLeft = imagePan.scrollLeft - deltaX;
        element.scrollTop = imagePan.scrollTop - deltaY;
        event.preventDefault();
    }

    function finishImagePan(event) {
        if (!imagePan || event.pointerId !== imagePan.pointerId) {
            return;
        }
        suppressImageClick = imagePan.moved;
        imagePan = null;
        element.classList.remove('review-image-frame--panning');
        removeImagePanListeners();
    }

    function cancelImagePan(event) {
        if (!imagePan || (event && event.pointerId !== imagePan.pointerId)) {
            return;
        }
        imagePan = null;
        suppressImageClick = false;
        element.classList.remove('review-image-frame--panning');
        removeImagePanListeners();
    }

    function removeImagePanListeners() {
        document.removeEventListener('pointermove', moveImage);
        document.removeEventListener('pointerup', finishImagePan);
        document.removeEventListener('pointercancel', cancelImagePan);
    }

    function resetImageFrame() {
        cancelImagePan(null);
        suppressImageClick = false;
        element.classList.remove('review-image-frame--full-size');
        element.scrollLeft = 0;
        element.scrollTop = 0;
    }

    function renderMetadata(nextImage) {
        metadata.replaceChildren(...createImageMetadataItems(nextImage, {
            className: 'review-meta',
            includeId: true,
        }));
    }
}
