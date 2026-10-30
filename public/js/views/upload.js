/**
 * Upload view: each selected image is sent sequentially as its own multipart
 * request (field "image[]"). On 401 the user is sent to login. Other failures
 * do not stop the batch; every failed file is kept in a persistent error list
 * so later status updates cannot hide which uploads failed and why.
 * Importing this module has no side effects.
 */
import {postFormWithProgress} from '../api.js';
import {el} from '../dom.js';
import {
    clearMessage,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../forms.js';

const FILE_COUNT_PROGRESS_ID = 'upload-file-count-progress';
const FILE_BYTE_PROGRESS_ID = 'upload-file-byte-progress';
const DUPLICATE_IMAGE_MESSAGE = 'This image has already been uploaded.';

function imageWord(count) {
    return count === 1 ? 'image' : 'images';
}

function failureReason(error) {
    return messageForStatus(error?.status ?? 0, DUPLICATE_IMAGE_MESSAGE);
}

function buildFailureMessage(completed, total, failures) {
    const noun = imageWord(total);
    const summary = completed === 0
        ? `Could not upload ${failures.length} of ${total} ${noun}.`
        : `${completed} of ${total} ${noun} uploaded.`;
    const heading = failures.length === 1 ? 'Failed upload:' : 'Failed uploads:';
    const details = failures.map(({file, error}) => `- ${file.name}: ${failureReason(error)}`);
    return `${summary}\n${heading}\n${details.join('\n')}`;
}

function setFileCountProgress(progress, status, completed, total) {
    const noun = imageWord(total);
    progress.max = total;
    progress.value = completed;
    progress.setAttribute('aria-valuetext', `${completed} of ${total} ${noun} uploaded`);
    status.textContent = `${completed} of ${total} ${noun} uploaded.`;
}

function setCurrentFile(fileProgress, fileStatus, byteStatus, file, index, total) {
    fileProgress.max = 1;
    fileProgress.value = 0;
    fileProgress.setAttribute('aria-valuetext', 'Waiting for upload progress');
    fileStatus.textContent = `Uploading image ${index + 1} of ${total}: ${file.name}.`;
    byteStatus.textContent = 'Waiting for upload progress.';
}

function updateByteProgress(progress, status, file, event) {
    const loaded = Number.isFinite(event.loaded) ? Math.max(0, Math.trunc(event.loaded)) : 0;
    if (event.lengthComputable && Number.isFinite(event.total) && event.total > 0) {
        const total = Math.trunc(event.total);
        progress.max = total;
        progress.value = Math.min(loaded, total);
        progress.setAttribute('aria-valuetext', `${loaded} of ${total} bytes sent`);
        status.textContent = `${file.name}: ${loaded} of ${total} bytes sent.`;
        return;
    }

    progress.removeAttribute('value');
    progress.setAttribute('aria-valuetext', `${loaded} bytes sent; total unknown`);
    status.textContent = `${file.name}: ${loaded} bytes sent; total unknown.`;
}

function completeByteProgress(progress, status, file) {
    const currentMax = Number(progress.max);
    const max = currentMax > 1 ? currentMax : Math.max(file.size, 1);
    progress.max = max;
    progress.value = max;
    progress.setAttribute('aria-valuetext', 'Upload complete');
    status.textContent = `${file.name}: upload complete (${file.size} image bytes).`;
}

export function createUploadView({ navigate }) {
    const fields = {
        image: createField({
            label: 'Images',
            name: 'image[]',
            type: 'file',
            accept: 'image/*',
        }),
    };
    fields.image.input.multiple = true;

    const message = createMessageBox();
    const fileCountProgress = el('progress', {
        attributes: {id: FILE_COUNT_PROGRESS_ID, max: '1', value: '0'},
    });
    const fileCountStatus = el('p', {
        className: 'upload-progress-status',
        attributes: {role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true'},
        children: '0 of 0 files uploaded.',
    });
    const fileByteProgress = el('progress', {
        attributes: {id: FILE_BYTE_PROGRESS_ID, max: '1', value: '0'},
    });
    const currentFileStatus = el('p', {
        className: 'upload-progress-status upload-current-file-status',
        attributes: {role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true'},
        children: 'Waiting to upload.',
    });
    const currentByteStatus = el('p', {
        className: 'upload-progress-status upload-byte-status',
        children: 'Waiting for upload progress.',
    });
    const progress = el('div', {
        className: 'upload-progress',
        attributes: {hidden: 'hidden'},
        children: [
            el('div', {
                className: 'upload-progress-group',
                children: [
                    el('label', {
                        className: 'upload-progress-label',
                        attributes: {for: FILE_COUNT_PROGRESS_ID},
                        children: 'Images uploaded',
                    }),
                    fileCountProgress,
                    fileCountStatus,
                ],
            }),
            el('div', {
                className: 'upload-progress-group',
                children: [
                    el('label', {
                        className: 'upload-progress-label',
                        attributes: {for: FILE_BYTE_PROGRESS_ID},
                        children: 'Current image transfer',
                    }),
                    fileByteProgress,
                    currentFileStatus,
                    currentByteStatus,
                ],
            }),
        ],
    });
    const submit = el('button', {
        className: 'button button--primary',
        attributes: {type: 'submit'},
        children: 'Upload',
    });

    const form = el('form', {
        className: 'form',
        attributes: { action: '/upload-image', method: 'post' },
        children: [fields.image.wrapper, message, progress, submit],
    });

    let uploading = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (uploading) {
            return;
        }

        clearMessage(message);
        progress.hidden = true;
        const files = Array.from(fields.image.input.files ?? []);
        const errors = {
            image: files.length === 0 ? 'Please choose an image file.' : null,
        };
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        progress.hidden = false;
        setFileCountProgress(fileCountProgress, fileCountStatus, 0, files.length);
        uploading = true;
        fields.image.input.disabled = true;
        submit.disabled = true;
        submit.textContent = 'Uploading…';
        form.setAttribute('aria-busy', 'true');

        let completed = 0;
        const failures = [];
        try {
            for (const [index, file] of files.entries()) {
                setCurrentFile(fileByteProgress, currentFileStatus, currentByteStatus, file, index, files.length);

                try {
                    const formData = new FormData();
                    formData.append('image[]', file);
                    await postFormWithProgress('/upload-image', formData, (uploadEvent) => {
                        updateByteProgress(fileByteProgress, currentByteStatus, file, uploadEvent);
                    });

                    completed++;
                    setFileCountProgress(fileCountProgress, fileCountStatus, completed, files.length);
                    completeByteProgress(fileByteProgress, currentByteStatus, file);
                    currentFileStatus.textContent = `${file.name} uploaded.`;
                } catch (error) {
                    if (isAuthError(error)) {
                        navigate('/login');
                        return;
                    }

                    failures.push({file, error});
                    setFileCountProgress(fileCountProgress, fileCountStatus, completed, files.length);
                    currentFileStatus.textContent = `${file.name} failed.`;
                    currentByteStatus.textContent = `${file.name}: ${failureReason(error)}`;
                    showMessage(message, buildFailureMessage(completed, files.length, failures), 'error');
                }
            }

            const noun = imageWord(files.length);
            if (failures.length === 0) {
                showMessage(
                    message,
                    files.length === 1 ? 'Image uploaded successfully.' : `${files.length} images uploaded successfully.`,
                    'success',
                );
                currentFileStatus.textContent = files.length === 1
                    ? 'Image uploaded successfully.'
                    : `All ${files.length} ${noun} uploaded successfully.`;
                form.reset();
            } else {
                setFileCountProgress(fileCountProgress, fileCountStatus, completed, files.length);
                currentFileStatus.textContent =
                    `${completed} of ${files.length} ${noun} uploaded; ${failures.length} failed.`;
                showMessage(message, buildFailureMessage(completed, files.length, failures), 'error');
            }
        } finally {
            uploading = false;
            fields.image.input.disabled = false;
            submit.disabled = false;
            submit.textContent = 'Upload';
            form.setAttribute('aria-busy', 'false');
        }
    });

    return el('section', {
        className: 'page upload-page',
        children: [el('h1', {className: 'page-title', children: 'Upload images'}), form],
    });
}
