/**
 * Profile view: prefills the current display name from the session and
 * POSTs JSON to /settings. On 401 the user is sent to the login view.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {el} from '../dom.js';
import {accessLevelName} from '../accessLevel.js';
import {
    clearMessage,
    createControlField,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../forms.js';
import {validateDisplayName} from '../validation.js';

const RECENT_UPLOADS_LIMIT = 15;

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
    const approvedTagSuggestionsField = user
        ? createReadOnlyField('Approved tag suggestions', 'approvedTagSuggestions', 'Loading…')
        : null;
    const recentUploadsSection = user ? createRecentUploadsSection() : null;

    const form = el('form', {
        className: 'form',
        attributes: {action: '/settings', method: 'post', novalidate: 'novalidate'},
        children: [fields.displayName.wrapper, accessLevelField, approvedTagSuggestionsField, message, submit],
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
    if (recentUploadsSection !== null) {
        children.push(recentUploadsSection.element);
    }

    if (approvedTagSuggestionsField !== null && recentUploadsSection !== null) {
        const approvedTagSuggestionsInput = approvedTagSuggestionsField.querySelector('input');
        get('/api/profile')
            .then((data) => {
                const count = data?.approvedTagSuggestions;
                approvedTagSuggestionsInput.value = Number.isSafeInteger(count) && count >= 0
                    ? String(count)
                    : 'Unavailable';
                renderRecentUploads(recentUploadsSection.grid, data?.recentUploads);
            })
            .catch(() => {
                approvedTagSuggestionsInput.value = 'Unavailable';
                recentUploadsSection.grid.replaceChildren(
                    createRecentUploadsStatus('Recent uploads are unavailable.'),
                );
            });
    }

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
    const input = el('input', {
        className: 'field-input',
        attributes: {name, type: 'text', readonly: 'readonly', 'aria-readonly': 'true'},
    });
    input.value = value;
    return createControlField({label, name, control: input}).wrapper;
}

export function createRecentUploadsSection() {
    const grid = el('div', {
        className: 'profile-recent-uploads-grid',
        attributes: {'aria-live': 'polite'},
        children: [createRecentUploadsStatus('Loading recent uploads…')],
    });
    return {
        element: el('section', {
            className: 'profile-recent-uploads',
            children: [
                el('h2', {className: 'profile-recent-uploads-title', children: 'Recent Uploads'}),
                grid,
            ],
        }),
        grid,
    };
}

export function renderRecentUploads(grid, recentUploads) {
    const uploads = Array.isArray(recentUploads)
        ? recentUploads.filter((image) => Number.isSafeInteger(image?.id)
            && image.id > 0
            && typeof image.thumbSrc === 'string'
            && image.thumbSrc !== '').slice(0, RECENT_UPLOADS_LIMIT)
        : [];

    if (uploads.length === 0) {
        grid.replaceChildren(createRecentUploadsStatus('No uploads yet.'));
        return;
    }

    grid.replaceChildren(...uploads.map(createRecentUploadTile));
}

function createRecentUploadTile(image) {
    return el('a', {
        className: `tile tile--link${image.status === 'pending_review' ? ' tile-unapproved' : ''}`,
        attributes: {
            href: `/image?id=${image.id}`,
            'data-image-id': image.id,
            'aria-label': `View image ${image.id}`,
        },
        children: el('img', {
            className: 'tile-image',
            attributes: {src: image.thumbSrc, alt: '', loading: 'lazy'},
        }),
    });
}

function createRecentUploadsStatus(text) {
    return el('p', {className: 'profile-recent-uploads-status', children: text});
}
