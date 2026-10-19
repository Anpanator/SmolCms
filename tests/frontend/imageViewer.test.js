import {assertEqual, assertTrue} from './assert.js';
import {createImageViewer} from '../../public/js/imageViewer.js';

const IMAGE = {
    id: 7,
    uploadDate: '2026-10-19 10:30:00',
    uploader: 'Upload User',
    width: 1920,
    height: 1080,
    format: 'WEBP',
    fileSize: 1234567,
    sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
    downloadSrc: '/api/image/download?name=ab89c0d0ef.webp',
};

export function testImageViewerRendersTheImageAndMetadata() {
    const viewer = createImageViewer({image: IMAGE});
    const image = viewer.element.querySelector('.review-image');

    assertTrue(viewer.element.matches('.review-image-frame'));
    assertEqual(viewer.metadata.className, 'review-metadata');
    assertEqual(image?.getAttribute('src'), IMAGE.downloadSrc);
    assertEqual(image?.getAttribute('alt'), 'Full-size image 7 for moderation');
    assertEqual(image?.getAttribute('role'), 'button');
    assertEqual(viewer.metadata.querySelectorAll('.review-meta-item').length, 6);
    assertTrue(viewer.metadata.textContent.includes('Upload User'));
    assertTrue(viewer.metadata.textContent.includes('1920 × 1080'));
    assertTrue(viewer.metadata.textContent.includes('1.2 MB'));
}

export function testImageViewerUpdatesAndClearsItsStatusAndMetadata() {
    const viewer = createImageViewer({image: IMAGE});
    viewer.update({...IMAGE, id: 8, downloadSrc: null}, {status: 'Loading full-size image…'});

    assertEqual(viewer.element.querySelector('.image-processing')?.textContent, 'Loading full-size image…');
    assertEqual(viewer.element.querySelector('.review-image'), null);
    assertTrue(viewer.metadata.textContent.includes('8'));

    viewer.showStatus('Full-size image could not be loaded.');
    assertEqual(viewer.element.querySelector('.image-processing')?.textContent, 'Full-size image could not be loaded.');
    assertTrue(viewer.metadata.textContent.includes('8'), 'showStatus should preserve the current metadata');

    viewer.clear('No image selected.');
    assertEqual(viewer.metadata.childElementCount, 0);
    assertEqual(viewer.element.querySelector('.image-processing')?.textContent, 'No image selected.');
}

export function testDraggingFullSizeImagePansWithoutTogglingIt(fixture) {
    const viewer = createImageViewer({image: IMAGE});
    fixture.append(viewer.element);
    const image = viewer.element.querySelector('.review-image');
    const frame = viewer.element;
    image.click();
    assertTrue(image.classList.contains('review-image--full-size'));

    let scrollLeft = 0;
    let scrollTop = 0;
    Object.defineProperties(frame, {
        clientWidth: {configurable: true, value: 100},
        clientHeight: {configurable: true, value: 100},
        scrollWidth: {configurable: true, value: 500},
        scrollHeight: {configurable: true, value: 400},
        scrollLeft: {
            configurable: true,
            get: () => scrollLeft,
            set: (value) => {
                scrollLeft = value;
            },
        },
        scrollTop: {
            configurable: true,
            get: () => scrollTop,
            set: (value) => {
                scrollTop = value;
            },
        },
    });

    image.dispatchEvent(new PointerEvent('pointerdown', {
        bubbles: true,
        cancelable: true,
        pointerId: 9,
        isPrimary: true,
        button: 0,
        clientX: 100,
        clientY: 100,
    }));
    image.dispatchEvent(new PointerEvent('pointermove', {
        bubbles: true,
        cancelable: true,
        pointerId: 9,
        isPrimary: true,
        clientX: 60,
        clientY: 70,
    }));
    image.dispatchEvent(new PointerEvent('pointerup', {
        bubbles: true,
        pointerId: 9,
        isPrimary: true,
        button: 0,
    }));
    image.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));

    assertEqual(scrollLeft, 40);
    assertEqual(scrollTop, 30);
    assertTrue(image.classList.contains('review-image--full-size'), 'the drag click must not toggle the image size');

    viewer.update({...IMAGE, id: 8});
    assertTrue(!viewer.element.classList.contains('review-image-frame--full-size'));
    assertTrue(viewer.element.querySelector('.review-image') !== image);
}
