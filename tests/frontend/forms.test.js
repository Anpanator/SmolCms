import { ApiError } from '../../public/js/api.js';
import {
    clearMessage,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../../public/js/forms.js';
import { assertEqual, assertTrue } from './assert.js';

export function testMessageForStatusMapping() {
    assertEqual(messageForStatus(400), 'Please check your input.');
    assertEqual(messageForStatus(401), 'Please log in to continue.');
    assertEqual(messageForStatus(403), 'You do not have permission to do that.');
    assertEqual(messageForStatus(409), 'This login name is already taken.');
    assertEqual(messageForStatus(413), 'The file is too large. The maximum upload size is 100 MB.');
    assertEqual(messageForStatus(500), 'Something went wrong on the server. Please try again later.');
    assertEqual(messageForStatus(503), 'Something went wrong. Please try again.');
    assertEqual(messageForStatus(0), 'Something went wrong. Please try again.');
}

export function testIsAuthError() {
    assertTrue(isAuthError(new ApiError(401, null)), '401 should be an auth error');
    assertTrue(!isAuthError(new ApiError(403, null)), '403 is not an auth error');
    assertTrue(!isAuthError(new Error('nope')));
}

export function testCreateFieldBuildsLabelInputAndError() {
    const field = createField({ label: 'Login name', name: 'loginName' });
    const input = field.wrapper.querySelector('input');
    assertEqual(input?.name, 'loginName');
    assertEqual(field.wrapper.querySelector('label')?.textContent, 'Login name');
    assertEqual(field.wrapper.querySelector('label')?.getAttribute('for'), 'field-loginName');
    assertTrue(field.errorElement.hidden, 'field error should start hidden');
}

export function testSetFieldErrorsTogglesMessages() {
    const field = createField({ label: 'Login name', name: 'loginName' });
    setFieldErrors({ loginName: field }, { loginName: 'Login name is required.' });
    assertEqual(field.errorElement.textContent, 'Login name is required.');
    assertTrue(!field.errorElement.hidden);
    assertEqual(field.input.getAttribute('aria-invalid'), 'true');

    setFieldErrors({ loginName: field }, { loginName: null });
    assertTrue(field.errorElement.hidden, 'clearing should hide the error');
    assertEqual(field.input.hasAttribute('aria-invalid'), false);
}

export function testMessageBoxShowAndClear() {
    const box = createMessageBox();
    assertTrue(box.hidden);
    showMessage(box, 'Oops.', 'error');
    assertTrue(!box.hidden);
    assertEqual(box.textContent, 'Oops.');
    assertTrue(box.className.includes('form-message--error'));
    showMessage(box, 'Yay.', 'success');
    assertTrue(box.className.includes('form-message--success'));
    clearMessage(box);
    assertTrue(box.hidden);
}
