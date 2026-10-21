import {assertEqual} from './assert.js';
import {
    createImageMetadataItems,
    formatDimensions,
    formatFileSize,
} from '../../public/js/imageMetadata.js';

export function testImageMetadataFormattersHandleKnownAndMissingValues() {
    assertEqual(formatDimensions(1920, 1080), '1920 × 1080');
    assertEqual(formatDimensions(null, 1080), '—');
    assertEqual(formatFileSize(50), '50 B');
    assertEqual(formatFileSize(2048), '2.0 KB');
    assertEqual(formatFileSize(1234567), '1.2 MB');
    assertEqual(formatFileSize(Number.NaN), '—');
}

export function testImageMetadataItemsSupportClassVariantsAndOptionalId() {
    const image = {
        id: 7,
        uploadDate: '2026-10-19',
        uploader: 'Upload User',
        uploaderId: 11,
        width: 1920,
        height: 1080,
        format: 'WEBP',
        fileSize: 1234567,
    };
    const imageDetails = createImageMetadataItems(image);
    assertEqual(imageDetails.length, 5);
    assertEqual(imageDetails[0].className, 'image-meta-item');
    assertEqual(imageDetails[0].querySelector('dt')?.textContent, 'Uploaded');

    const reviewDetails = createImageMetadataItems(image, {className: 'review-meta', includeId: true});
    assertEqual(reviewDetails.length, 6);
    assertEqual(reviewDetails[0].className, 'review-meta-item');
    assertEqual(reviewDetails[0].querySelector('.review-meta-term')?.textContent, 'Image ID');
    assertEqual(reviewDetails[0].querySelector('.review-meta-value')?.textContent, '7');
    assertEqual(reviewDetails[1].querySelector('.review-meta-value')?.textContent, '2026-10-19');
    assertEqual(imageDetails[1].querySelector('.image-meta-value a')?.getAttribute('href'), '/user?id=11');
    assertEqual(reviewDetails[2].querySelector('.review-meta-value a')?.getAttribute('href'), '/user?id=11');
    assertEqual(reviewDetails[2].querySelector('.review-meta-value')?.textContent, 'Upload User');

    const invalidUploader = createImageMetadataItems({...image, uploaderId: 0})[1];
    assertEqual(invalidUploader.querySelector('.image-meta-value a'), null);
    assertEqual(invalidUploader.querySelector('.image-meta-value')?.textContent, 'Upload User');
}
