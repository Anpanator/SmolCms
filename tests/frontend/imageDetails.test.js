import {assertEqual, assertTrue} from './assert.js';
import {createImageDetails} from '../../public/js/imageDetails.js';

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

export function testImageDetailsRenderSampleMetadataAndDownload() {
    const details = createImageDetails();
    details.update(IMAGE);

    assertEqual(details.imageHolder.querySelector('.image-view-image')?.getAttribute('src'), IMAGE.sampleSrc);
    assertEqual(details.metadata.querySelectorAll('.image-meta-item').length, 5);
    assertTrue(details.metadata.textContent.includes('Upload User'));
    assertTrue(details.metadata.textContent.includes('1920 × 1080'));
    assertTrue(details.metadata.textContent.includes('1.2 MB'));
    assertEqual(details.metaSection.querySelector('.image-download')?.getAttribute('href'), IMAGE.downloadSrc);
}

export function testImageDetailsUpdatesProcessingStateAndCanClear() {
    const details = createImageDetails();
    details.update({...IMAGE, sampleSrc: null, downloadSrc: null, width: null, height: null});

    assertTrue(details.imageHolder.textContent.includes('still being processed'));
    assertEqual(details.metaSection.querySelector('.image-download'), null);
    assertEqual(details.metadata.querySelector('.image-meta-value')?.textContent, '2026-10-19 10:30:00');

    details.update(null);
    assertEqual(details.imageHolder.childElementCount, 0);
    assertEqual(details.metadata.childElementCount, 0);
    assertEqual(details.metaSection.querySelector('.image-download'), null);
}
