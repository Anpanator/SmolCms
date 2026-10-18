/**
 * Profile view: prefills the current display name from the session and
 * POSTs JSON to /settings. On 401 the user is sent to the login view.
 * Importing this module has no side effects.
 */
import {postJson} from '../api.js';
import {el} from '../dom.js';
import {accessLevelName} from '../accessLevel.js';
import {
    clearMessage,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../forms.js';
import {validateDisplayName} from '../validation.js';

export function createProfileView({session, navigate}) {
    const user = session.getUser();
    const fields = {
        displayName: createField({
            label: 'Display name',
            name: 'displayName',
            value: user?.displayName ?? '',
        }),
    };
    const message = createMessageBox();
    const submit = el('button', {className: 'button button--primary', attributes: {type: 'submit'}, children: 'Save'});
    const accessLevelField = user
        ? createReadOnlyField('Access level', 'accessLevel', accessLevelName(user.accessLevel))
        : null;

    const form = el('form', {
        className: 'form',
        attributes: {action: '/settings', method: 'post', novalidate: 'novalidate'},
        children: [fields.displayName.wrapper, accessLevelField, message, submit],
    });

    const children = [el('h1', {className: 'page-title', children: 'Profile'})];
    if (!user) {
        children.push(el('p', {
            className: 'notice notice--warning',
            children: [
                'You are not logged in. ',
                el('a', {className: 'notice-link', attributes: {href: '/login'}, children: 'Log in'}),
                ' to view your profile.',
            ],
        }));
    }
    children.push(form);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage(message);
        const displayName = fields.displayName.input.value.trim();
        const errors = {displayName: validateDisplayName(displayName)};
        setFieldErrors(fields, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }

        try {
            const data = await postJson('/settings', {displayName});
            session.setUser(data?.user ?? user);
            showMessage(message, 'Display name updated.', 'success');
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(message, messageForStatus(error?.status ?? 0));
        }
    });

    return el('section', {className: 'page profile-page', children});
}

function createReadOnlyField(label, name, value) {
    const id = `field-${name}`;
    const input = el('input', {
        className: 'field-input',
        attributes: {id, name, type: 'text', readonly: 'readonly', 'aria-readonly': 'true'},
    });
    input.value = value;
    return el('div', {
        className: 'field',
        children: [
            el('label', {className: 'field-label', attributes: {for: id}, children: label}),
            input,
        ],
    });
}
