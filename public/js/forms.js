/**
 * Shared form-building and status-mapping helpers for views.
 * Importing this module has no side effects.
 */
import { ApiError } from './api.js';
import { el } from './dom.js';

/**
 * Maps an API error status code to a user-facing message
 * (error responses have empty bodies, the frontend owns the wording).
 */
export function messageForStatus(status) {
    switch (status) {
        case 400:
            return 'Please check your input.';
        case 401:
            return 'Please log in to continue.';
        case 403:
            return 'You do not have permission to do that.';
        case 409:
            return 'This login name is already taken.';
        case 413:
            return 'The file is too large. The maximum upload size is 100 MB.';
        case 500:
            return 'Something went wrong on the server. Please try again later.';
        default:
            return 'Something went wrong. Please try again.';
    }
}

/** @returns {boolean} true when the error means "not authenticated" */
export function isAuthError(error) {
    return error instanceof ApiError && error.status === 401;
}

/**
 * Builds a labelled form field: wrapper div with label, input and a
 * field-level error message placeholder.
 */
export function createField({ label, name, type = 'text', value = '', accept = undefined }) {
    const errorElement = el('p', { className: 'field-error', attributes: { hidden: 'hidden' } });
    const attributes = { name, id: `field-${name}`, type };
    if (accept) {
        attributes.accept = accept;
    }
    const input = el('input', { className: 'field-input', attributes });
    input.value = value;
    const wrapper = el('div', {
        className: 'field',
        children: [
            el('label', { className: 'field-label', attributes: { for: `field-${name}` }, children: label }),
            input,
            errorElement,
        ],
    });
    return { wrapper, input, errorElement };
}

/** Shows per-field validation error messages (value = error string). */
export function setFieldErrors(fields, errors) {
    for (const [name, message] of Object.entries(errors)) {
        const field = fields[name];
        if (!field) {
            continue;
        }
        if (message) {
            field.errorElement.textContent = message;
            field.errorElement.hidden = false;
            field.input.setAttribute('aria-invalid', 'true');
        } else {
            field.errorElement.textContent = '';
            field.errorElement.hidden = true;
            field.input.removeAttribute('aria-invalid');
        }
    }
}

/** Form-level message box (error or success). */
export function createMessageBox() {
    return el('p', {
        className: 'form-message',
        attributes: { role: 'alert', hidden: 'hidden' },
    });
}

export function showMessage(box, text, kind = 'error') {
    box.className = `form-message form-message--${kind}`;
    box.textContent = text;
    box.hidden = false;
}

export function clearMessage(box) {
    box.className = 'form-message';
    box.textContent = '';
    box.hidden = true;
}
