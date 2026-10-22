/**
 * Profile view: prefills the current display name from the session and
 * POSTs JSON to /settings. On 401 the user is sent to the login view.
 * Importing this module has no side effects.
 */
import {get, postJson, request} from '../api.js';
import {el} from '../dom.js';
import {createConfirmDialog} from '../modal.js';
import {AccessLevel, accessLevelName, hasAccessLevel} from '../accessLevel.js';
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
import {RoutePath} from '../routes.js';

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
    const apiKeySection = hasAccessLevel(user, AccessLevel.WIZARD)
        ? createApiKeySection({navigate})
        : null;

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
    if (apiKeySection !== null) {
        children.push(apiKeySection.element);
    }
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
                if (typeof data?.hasApiKey === 'boolean') {
                    apiKeySection?.setHasApiKey(data.hasApiKey, true);
                }
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

function createApiKeySection({navigate}) {
    const message = createMessageBox();
    const key = el('code', {className: 'profile-api-key-value'});
    const keyOutput = el('p', {
        className: 'profile-api-key-output',
        attributes: {hidden: 'hidden', 'aria-live': 'polite'},
        children: key,
    });
    const generateButton = el('button', {
        className: 'button button--primary profile-api-key-generate-button',
        attributes: {type: 'button'},
        children: 'Generate API key',
    });
    const deleteButton = el('button', {
        className: 'button button--danger profile-api-key-delete-button',
        attributes: {type: 'button', hidden: 'hidden'},
        children: 'Delete API key',
    });
    const actions = el('div', {
        className: 'profile-api-key-actions',
        children: [generateButton, deleteButton],
    });
    let localKeyStateChanged = false;

    function setHasApiKey(hasApiKey, fromProfile = false) {
        if (fromProfile && localKeyStateChanged) {
            return;
        }
        if (!fromProfile) {
            localKeyStateChanged = true;
        }
        generateButton.textContent = hasApiKey ? 'Rotate API key' : 'Generate API key';
        deleteButton.hidden = !hasApiKey;
    }

    generateButton.addEventListener('click', async (event) => {
        event.preventDefault();
        generateButton.disabled = true;
        clearMessage(message);
        key.textContent = '';
        keyOutput.hidden = true;
        try {
            const data = await postJson(RoutePath.API_PROFILE_API_KEY, {});
            if (typeof data?.apiKey !== 'string' || data.apiKey === '') {
                showMessage(message, 'Could not generate an API key.');
                return;
            }
            key.textContent = data.apiKey;
            keyOutput.hidden = false;
            setHasApiKey(true);
            showMessage(message, 'API key generated. Copy it now; it will only be shown once.', 'success');
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(message, messageForStatus(error?.status ?? 0));
        } finally {
            generateButton.disabled = false;
        }
    });

    deleteButton.addEventListener('click', async (event) => {
        event.preventDefault();
        if (deleteButton.disabled) {
            return;
        }
        deleteButton.disabled = true;
        try {
            const dialog = createConfirmDialog({
                message: 'Delete your API key? Any application using it will no longer be able to authenticate.',
                confirmLabel: 'Delete API key',
                cancelLabel: 'Keep API key',
            });
            if (!(await dialog.result)) {
                return;
            }

            clearMessage(message);
            await request(RoutePath.API_PROFILE_API_KEY, {method: 'DELETE'});
            resetApiKey();
            showMessage(message, 'API key deleted.', 'success');
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            if (error?.status === 404) {
                resetApiKey();
                showMessage(message, 'API key deleted.', 'success');
                return;
            }
            showMessage(message, messageForStatus(error?.status ?? 0));
        } finally {
            deleteButton.disabled = false;
        }
    });

    function resetApiKey() {
        key.textContent = '';
        keyOutput.hidden = true;
        setHasApiKey(false);
    }

    const element = el('section', {
        className: 'profile-api-key',
        children: [
            el('h2', {className: 'profile-api-key-title', children: 'API key'}),
            el('p', {
                className: 'profile-api-key-note',
                children: 'The key is shown only once and grants API access to endpoints enabled for API keys.',
            }),
            actions,
            message,
            keyOutput,
        ],
    });
    return {element, setHasApiKey};
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
