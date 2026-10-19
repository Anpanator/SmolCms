/**
 * Upload view: file input submitted as multipart FormData (field "image[]").
 * On 401 the user is sent to the login view; on 403/500 mapped messages
 * are shown. Importing this module has no side effects.
 */
import {postForm} from '../api.js';
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

const MAX_IMAGES = 10;

export function createUploadView({ navigate }) {
    const fields = {
        image: createField({
            label: 'Images (up to 10)',
            name: 'image[]',
            type: 'file',
            accept: 'image/*',
        }),
    };
    fields.image.input.multiple = true;
    const message = createMessageBox();
    const submit = el('button', { className: 'button button--primary', attributes: { type: 'submit' }, children: 'Upload' });

    const form = el('form', {
        className: 'form',
        attributes: { action: '/upload-image', method: 'post' },
        children: [fields.image.wrapper, message, submit],
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage(message);
        const files = Array.from(fields.image.input.files ?? []);
        const errors = {
            image: files.length === 0
                ? 'Please choose an image file.'
                : files.length > MAX_IMAGES
                    ? `Please choose no more than ${MAX_IMAGES} images.`
                    : null,
        };
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        const formData = new FormData();
        for (const file of files) {
            formData.append('image[]', file);
        }
        try {
            await postForm('/upload-image', formData);
            showMessage(message, 'Image uploaded successfully.', 'success');
            form.reset();
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(message, messageForStatus(error?.status ?? 0));
        }
    });

    return el('section', {
        className: 'page upload-page',
        children: [el('h1', {className: 'page-title', children: 'Upload images'}), form],
    });
}
