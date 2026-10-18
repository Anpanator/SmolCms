/**
 * Register view: validates the registration form client-side, POSTs JSON to
 * /register and navigates to the login view with a success notice on 201.
 * Importing this module has no side effects.
 */
import { postJson } from '../api.js';
import { el } from '../dom.js';
import { clearMessage, createField, createMessageBox, messageForStatus, setFieldErrors, showMessage } from '../forms.js';
import {
    validateDisplayName,
    validateEmail,
    validateLoginName,
    validatePassword,
    validatePasswordMatch,
} from '../validation.js';

export function createRegisterView({ navigate }) {
    const fields = {
        email: createField({ label: 'Email', name: 'email', type: 'email' }),
        loginName: createField({ label: 'Login name', name: 'loginName' }),
        displayName: createField({ label: 'Display name', name: 'displayName' }),
        password: createField({ label: 'Password', name: 'password', type: 'password' }),
        passwordRepeat: createField({ label: 'Repeat password', name: 'passwordRepeat', type: 'password' }),
    };
    const message = createMessageBox();
    const submit = el('button', { className: 'button button--primary', attributes: { type: 'submit' }, children: 'Register' });

    const form = el('form', {
        className: 'form',
        attributes: { action: '/register', method: 'post', novalidate: 'novalidate' },
        children: [
            fields.email.wrapper,
            fields.loginName.wrapper,
            fields.displayName.wrapper,
            fields.password.wrapper,
            fields.passwordRepeat.wrapper,
            message,
            submit,
        ],
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage(message);
        const values = {
            email: fields.email.input.value.trim(),
            loginName: fields.loginName.input.value.trim(),
            displayName: fields.displayName.input.value.trim(),
            password: fields.password.input.value,
        };
        const errors = {
            email: validateEmail(values.email),
            loginName: validateLoginName(values.loginName),
            displayName: validateDisplayName(values.displayName),
            password: validatePassword(values.password),
            passwordRepeat: validatePasswordMatch(values.password, fields.passwordRepeat.input.value),
        };
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        try {
            await postJson('/register', { ...values, passwordRepeat: fields.passwordRepeat.input.value });
            navigate('/login?registered=1');
        } catch (error) {
            showMessage(message, messageForStatus(error?.status ?? 0));
        }
    });

    return el('section', {
        className: 'page auth-page',
        children: [el('h1', { className: 'page-title', children: 'Register' }), form],
    });
}
