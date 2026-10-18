/**
 * Upload view: file input submitted as multipart FormData (field "image").
 * On 401 the user is sent to the login view; on 403/500 mapped messages
 * are shown. Importing this module has no side effects.
 */
import { postForm } from '../api.js';
import { el } from '../dom.js';
import {
    clearMessage,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../forms.js';

export function createUploadView({ navigate }) {
    const fields = {
        image: createField({
            label: 'Image',
            name: 'image',
            type: 'file',
            accept: 'image/*',
        }),
    };
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
        const file = fields.image.input.files?.[0];
        const errors = { image: file ? null : 'Please choose an image file.' };
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        try {
            const data = await postForm('/upload-image', formData);
            showMessage(message, `Image uploaded as "${data?.filename ?? 'unknown'}".`, 'success');
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
        children: [el('h1', { className: 'page-title', children: 'Upload image' }), form],
    });
}
