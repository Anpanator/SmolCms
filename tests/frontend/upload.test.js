import {assertEqual, assertTrue} from './assert.js';
import {mockXhr, submitForm, useXhr} from './mocks.js';
import {createUploadView} from '../../public/js/views/upload.js';

function chooseFiles(view, names = ['cat.png']) {
    const input = view.querySelector('input[name="image[]"]');
    const transfer = new DataTransfer();
    for (const name of names) {
        transfer.items.add(new File(['image-bytes'], name, {type: 'image/png'}));
    }
    input.files = transfer.files;
    return input;
}

function chooseFile(view, name = 'cat.png') {
    return chooseFiles(view, [name]);
}

function dispatchSubmit(form) {
    form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
}

export function testUploadViewRendersAccessibleFileInputAndProgressBars() {
    const view = createUploadView({ navigate: () => {} });
    const input = view.querySelector('input[name="image[]"]');
    assertTrue(input !== null, 'image file input should exist');
    assertEqual(input.type, 'file');
    assertTrue(input.multiple, 'image file input should allow multiple files');
    assertEqual(view.querySelector(`label[for="${input.id}"]`).textContent, 'Images');
    assertTrue(view.querySelector('button[type="submit"]') !== null, 'submit button should exist');

    const countProgress = view.querySelector('#upload-file-count-progress');
    const byteProgress = view.querySelector('#upload-file-byte-progress');
    assertEqual(countProgress?.tagName, 'PROGRESS', 'file-count progress should be a progress bar');
    assertEqual(byteProgress?.tagName, 'PROGRESS', 'current-file transfer progress should be a progress bar');
    assertEqual(view.querySelector('label[for="upload-file-count-progress"]').textContent, 'Images uploaded');
    assertEqual(view.querySelector('label[for="upload-file-byte-progress"]').textContent, 'Current image transfer');
    assertEqual(view.querySelectorAll('[role="status"]').length, 2, 'progress should include accessible status text');
    assertTrue(view.querySelector('.upload-progress').hidden, 'progress should be hidden before a valid submission');
}

export async function testMissingFileShowsErrorWithoutSendingRequest() {
    const xhrMock = mockXhr(() => {
        throw new Error('XHR must not be created without a file');
    });
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({ navigate: () => {} });
        await submitForm(view.querySelector('form'));
        const error = view.querySelector('.field-error');
        assertTrue(!error.hidden, 'missing file should show an error');
        assertTrue(error.textContent.includes('choose an image'), 'error should ask for a file');
        assertEqual(xhrMock.calls.length, 0);
    } finally {
        restore();
    }
}

export async function testMoreThanTenFilesAreUploadedOnePerRequest() {
    const xhrMock = mockXhr(() => ({status: 201, body: {message: 'Image uploaded successfully.'}}));
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        const names = Array.from({length: 11}, (_, index) => `image-${index}.png`);
        const input = chooseFiles(view, names);
        await submitForm(view.querySelector('form'));

        assertEqual(xhrMock.calls.length, names.length, 'each selected file should have its own request');
        for (const [index, request] of xhrMock.calls.entries()) {
            assertEqual(request.method, 'POST');
            assertEqual(request.url, '/upload-image');
            assertTrue(request.body instanceof FormData, 'upload must use multipart FormData');
            const files = request.body.getAll('image[]');
            assertEqual(files.length, 1, 'each multipart request must contain exactly one image');
            assertTrue(files[0] instanceof File, 'the image must use the existing image[] field');
            assertEqual(files[0].name, names[index], 'files should be posted in selection order');
            assertEqual(request.headers.length, 0, 'multipart Content-Type must not be set manually');
        }

        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, 'success message should be visible');
        assertEqual(message.textContent, '11 images uploaded successfully.');
        assertEqual(view.querySelector('#upload-file-count-progress').value, 11);
        assertEqual(view.querySelector('#upload-file-count-progress').max, 11);
        assertEqual(input.files.length, 0, 'form should be reset after all uploads succeed');
    } finally {
        restore();
    }
}

export async function testFilesUploadSequentially() {
    const completions = [];
    let activeRequests = 0;
    let maximumActiveRequests = 0;
    const xhrMock = mockXhr(() => new Promise((resolve) => {
        activeRequests++;
        maximumActiveRequests = Math.max(maximumActiveRequests, activeRequests);
        completions.push((response) => {
            activeRequests--;
            resolve(response);
        });
    }));
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        const input = chooseFiles(view, ['cat.png', 'dog.png']);
        const form = view.querySelector('form');
        const submit = view.querySelector('button[type="submit"]');
        dispatchSubmit(form);
        await new Promise((resolve) => setTimeout(resolve, 0));

        assertEqual(xhrMock.calls.length, 1, 'the second request must wait for the first to finish');
        assertTrue(submit.disabled, 'submit should be disabled while uploading');
        assertTrue(input.disabled, 'file selection should be disabled while uploading');
        assertEqual(form.getAttribute('aria-busy'), 'true');

        dispatchSubmit(form);
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(xhrMock.calls.length, 1, 'duplicate submissions must not start another request');

        completions[0]({status: 201, body: null});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(xhrMock.calls.length, 2, 'the next request should start after the first succeeds');
        assertEqual(xhrMock.calls[1].body.get('image[]').name, 'dog.png');

        completions[1]({status: 201, body: null});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(maximumActiveRequests, 1, 'only one upload request should be active at a time');
        assertTrue(!submit.disabled, 'submit should be enabled after uploading');
        assertTrue(!input.disabled, 'file selection should be enabled after uploading');
        assertEqual(form.getAttribute('aria-busy'), 'false');
    } finally {
        restore();
    }
}

export async function testBothProgressBarsUpdateDuringUpload() {
    const completions = [];
    const xhrMock = mockXhr(() => new Promise((resolve) => completions.push(resolve)));
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        chooseFiles(view, ['cat.png', 'dog.png']);
        const countProgress = view.querySelector('#upload-file-count-progress');
        const byteProgress = view.querySelector('#upload-file-byte-progress');
        const countStatus = countProgress.parentElement.querySelector('[role="status"]');
        const byteStatus = view.querySelector('.upload-byte-status');

        dispatchSubmit(view.querySelector('form'));
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(xhrMock.calls.length, 1);
        xhrMock.calls[0].upload.emit('progress', {loaded: 7, total: 20, lengthComputable: true});
        assertEqual(byteProgress.max, 20);
        assertEqual(byteProgress.value, 7);
        assertTrue(byteStatus.textContent.includes('7 of 20 bytes sent'));
        assertEqual(countProgress.max, 2);
        assertEqual(countProgress.value, 0);
        assertEqual(countStatus.textContent, '0 of 2 images uploaded.');

        completions[0]({status: 201, body: null});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(xhrMock.calls.length, 2, 'the next file should start only after the first completes');
        assertEqual(countProgress.value, 1);
        assertEqual(countStatus.textContent, '1 of 2 images uploaded.');
        xhrMock.calls[1].upload.emit('progress', {loaded: 9, total: 30, lengthComputable: true});
        assertEqual(byteProgress.max, 30, 'byte progress should reset for the current file');
        assertEqual(byteProgress.value, 9);
        assertTrue(byteStatus.textContent.includes('9 of 30 bytes sent'));

        completions[1]({status: 201, body: null});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assertEqual(countProgress.value, 2);
        assertEqual(countStatus.textContent, '2 of 2 images uploaded.');
        assertEqual(byteProgress.value, byteProgress.max, 'the final file transfer bar should complete');
    } finally {
        restore();
    }
}

export async function testUnauthorizedNavigatesToLogin() {
    const xhrMock = mockXhr(() => ({status: 401, body: null}));
    const restore = useXhr(xhrMock);
    try {
        const navigateCalls = [];
        const view = createUploadView({navigate: (path) => navigateCalls.push(path)});
        chooseFile(view);
        await submitForm(view.querySelector('form'));
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to login');
        assertTrue(!view.querySelector('.form-message--success'), '401 must not show upload success');
    } finally {
        restore();
    }
}

export async function testForbiddenShowsPermissionMessage() {
    const xhrMock = mockXhr(() => ({status: 403, body: null}));
    const restore = useXhr(xhrMock);
    try {
        const navigateCalls = [];
        const view = createUploadView({navigate: (path) => navigateCalls.push(path)});
        chooseFile(view);
        await submitForm(view.querySelector('form'));

        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, '403 should show a message');
        assertTrue(message.textContent.includes('permission'), '403 should map to a permission message');
        assertEqual(navigateCalls.length, 0, '403 should not navigate');
    } finally {
        restore();
    }
}

export async function testServerErrorShowsGenericMessageWithoutSuccess() {
    const xhrMock = mockXhr(() => ({status: 500, body: null}));
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        chooseFile(view);
        await submitForm(view.querySelector('form'));

        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, '500 should show a message');
        assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error');
        assertTrue(!message.className.includes('--success'), 'failed upload must not be styled as success');
    } finally {
        restore();
    }
}

export async function testPartialFailureContinuesAndListsEveryFailedFile() {
    const xhrMock = mockXhr(() => xhrMock.calls.length === 1
        ? {status: 201, body: null}
        : {status: 500, body: null});
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        const input = chooseFiles(view, ['cat.png', 'dog.png', 'bird.png']);
        await submitForm(view.querySelector('form'));

        assertEqual(xhrMock.calls.length, 3, 'upload should continue after a failed file');
        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, 'partial failure should show a message');
        assertTrue(message.textContent.includes('1 of 3 images uploaded'));
        assertTrue(message.textContent.includes('dog.png'), 'the first failed file should stay listed');
        assertTrue(message.textContent.includes('bird.png'), 'later failures should be added to the list');
        assertTrue(message.textContent.includes('went wrong'), 'the failure reason should be shown');
        assertTrue(!message.textContent.includes('successfully'), 'partial failure must not claim success');
        assertTrue(!message.className.includes('--success'), 'partial failure must not be styled as success');
        assertEqual(view.querySelector('#upload-file-count-progress').value, 1);
        assertEqual(input.files.length, 3, 'failed uploads should keep the selection for a retry');
    } finally {
        restore();
    }
}

export async function testDuplicateUploadShowsPersistentErrorAndContinues() {
    const xhrMock = mockXhr(() => xhrMock.calls.length === 1
        ? {status: 409, body: null}
        : {status: 201, body: null});
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        chooseFiles(view, ['duplicate.png', 'new.png']);
        await submitForm(view.querySelector('form'));

        assertEqual(xhrMock.calls.length, 2, 'the next file should still be uploaded after a duplicate');
        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, 'duplicate should show an error');
        assertTrue(message.textContent.includes('duplicate.png'), 'the duplicate file should be named');
        assertTrue(message.textContent.includes('already been uploaded'), 'the duplicate reason should be shown');
        assertTrue(!message.textContent.includes('new.png'), 'successful files should not be listed as failures');
        assertTrue(!message.className.includes('--success'), 'duplicate must not be styled as success');
        assertEqual(view.querySelector('#upload-file-count-progress').value, 1);

        // Later per-file status updates must not replace the accumulated error.
        assertTrue(view.querySelector('.upload-current-file-status').textContent.includes('uploaded'));
        assertTrue(message.textContent.includes('already been uploaded'));
    } finally {
        restore();
    }
}

export async function testAllFailedUploadsKeepTheirReasonsInTheErrorList() {
    const xhrMock = mockXhr(() => xhrMock.calls.length === 1
        ? {status: 409, body: null}
        : {status: 413, body: null});
    const restore = useXhr(xhrMock);
    try {
        const view = createUploadView({
            navigate: () => {
            }
        });
        chooseFiles(view, ['duplicate.png', 'too-large.png']);
        await submitForm(view.querySelector('form'));

        assertEqual(xhrMock.calls.length, 2, 'all files should be attempted');
        const message = view.querySelector('.form-message');
        assertTrue(message.textContent.includes('Could not upload 2 of 2 images'));
        assertTrue(message.textContent.includes('duplicate.png: This image has already been uploaded.'));
        assertTrue(message.textContent.includes('too-large.png: The upload is too large.'));
        assertTrue(!message.className.includes('--success'));
        assertEqual(view.querySelector('#upload-file-count-progress').value, 0);
    } finally {
        restore();
    }
}
