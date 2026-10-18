/**
 * Login view: validates credentials client-side, POSTs JSON to /login,
 * refreshes the session and navigates home on success.
 * Importing this module has no side effects.
 */
import { postJson } from '../api.js';
import { el } from '../dom.js';
import { clearMessage, createField, createMessageBox, messageForStatus, setFieldErrors, showMessage } from '../forms.js';
import { validateLoginName, validatePassword } from '../validation.js';

export function createLoginView({ session, navigate, query = new URLSearchParams(window.location.search) }) {
    const noticeText = query.get('registered');
    const notice = noticeText
        ? el('p', { className: 'notice notice--success', children: 'Registration successful. Please log in.' })
        : null;

    const fields = {
        loginName: createField({ label: 'Login name', name: 'loginName' }),
        password: createField({ label: 'Password', name: 'password', type: 'password' }),
    };
    const message = createMessageBox();
    const submit = el('button', { className: 'button button--primary', attributes: { type: 'submit' }, children: 'Log in' });

    const form = el('form', {
        className: 'form',
        attributes: { action: '/login', method: 'post', novalidate: 'novalidate' },
        children: [
            fields.loginName.wrapper,
            fields.password.wrapper,
            message,
            submit,
        ],
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage(message);
        const errors = {
            loginName: validateLoginName(fields.loginName.input.value),
            password: validatePassword(fields.password.input.value),
        };
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        try {
            await postJson('/login', {
                loginName: fields.loginName.input.value.trim(),
                password: fields.password.input.value,
            });
            await session.refresh();
            navigate('/');
        } catch (error) {
            showMessage(message, messageForStatus(error?.status ?? 0));
        }
    });

    return el('section', {
        className: 'page auth-page',
        children: [
            el('h1', { className: 'page-title', children: 'Log in' }),
            notice,
            form,
        ],
    });
}
