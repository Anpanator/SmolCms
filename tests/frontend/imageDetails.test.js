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
    const details = createImageDetails({canDownload: true});
    details.update(IMAGE);

    assertEqual(details.imageHolder.querySelector('.image-view-image')?.getAttribute('src'), IMAGE.sampleSrc);
    assertEqual(details.metadata.querySelectorAll('.image-meta-item').length, 5);
    assertTrue(details.metadata.textContent.includes('Upload User'));
    assertTrue(details.metadata.textContent.includes('1920 × 1080'));
    assertTrue(details.metadata.textContent.includes('1.2 MB'));
    const download = details.metaSection.querySelector('a.image-download');
    assertTrue(download !== null);
    assertEqual(download.getAttribute('href'), IMAGE.downloadSrc);
    assertTrue(download.hasAttribute('download'));
    assertEqual(download.textContent, 'Download');
}

export function testImageDetailsRendersDisabledDownloadWhenDownloadsAreForbidden() {
    const details = createImageDetails({canDownload: false});
    details.update(IMAGE);

    const download = details.metaSection.querySelector('button.image-download');
    assertTrue(download !== null);
    assertTrue(download.disabled);
    assertEqual(download.getAttribute('href'), null);
    assertTrue(!download.hasAttribute('download'));
    assertEqual(download.textContent, 'Download');
    assertEqual(
        details.metaSection.querySelector('.image-download-disabled-wrapper')?.getAttribute('title'),
        'Log in to download images',
    );
}

export function testImageDetailsUpdatesProcessingStateAndCanClear() {
    for (const canDownload of [true, false]) {
        const details = createImageDetails({canDownload});
        details.update({...IMAGE, sampleSrc: null, downloadSrc: null, width: null, height: null});

        assertTrue(details.imageHolder.textContent.includes('still being processed'));
        assertEqual(details.metaSection.querySelector('.image-download'), null);
        assertEqual(details.metadata.querySelector('.image-meta-value')?.textContent, '2026-10-19 10:30:00');

        details.update(null);
        assertEqual(details.imageHolder.childElementCount, 0);
        assertEqual(details.metadata.querySelectorAll('.image-meta-item').length, 0);
        assertEqual(details.metaSection.querySelector('.image-download'), null);
    }
}

export function testImageDetailsShowsPendingApprovalWarningOnlyForPendingReview() {
    const details = createImageDetails();
    assertTrue(details.pendingWarning.hidden, 'accepted details should not show the warning');

    details.update({...IMAGE, status: 'pending_review'});
    assertTrue(!details.pendingWarning.hidden, 'pending-review details should show the warning');
    assertEqual(details.pendingWarning.textContent, 'This image is pending approval.');
    const uploadedItem = [...details.metadata.querySelectorAll('.image-meta-item')]
        .find((node) => node.querySelector('dt')?.textContent === 'Uploaded');
    assertTrue(
        (details.pendingWarning.compareDocumentPosition(uploadedItem) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
        'pending warning should precede the upload date',
    );

    details.update(null);
    assertTrue(details.pendingWarning.hidden, 'clearing the image should hide the warning');
}
