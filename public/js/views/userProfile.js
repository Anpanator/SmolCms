/**
 * Public, view-only user profiles. The logged-in user's own profile also links
 * to the existing settings editor. Importing this module has no side effects.
 */
import {get} from '../api.js';
import {accessLevelName} from '../accessLevel.js';
import {el} from '../dom.js';
import {clearMessage, createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {RoutePath} from '../routes.js';
import {createRecentUploadsSection, renderRecentUploads} from './profile.js';

const NOT_FOUND_MESSAGE = 'User not found.';
const PERMISSION_MESSAGE = 'You do not have permission to view this profile.';
const MOD_ACTION_NAMES = new Map([
    [1, 'Tag added'],
    [2, 'Tag removed'],
]);

/**
 * @param {{session?: *, navigate?: Function, query?: URLSearchParams|string}} [context]
 */
export function createUserProfileView({
    session,
    query = new URLSearchParams(window.location.search),
} = {}) {
    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query);
    const profileId = parseProfileId(params.get('id'));
    const title = el('h1', {className: 'page-title user-profile-title', children: 'Profile'});
    const actions = el('div', {className: 'user-profile-actions'});
    const status = createMessageBox();
    const content = el('div', {className: 'user-profile-content'});
    const page = el('section', {
        className: 'page user-profile-page',
        children: [
            el('header', {className: 'user-profile-header', children: [title, actions]}),
            status,
            content,
        ],
    });

    if (profileId === null) {
        showMessage(status, NOT_FOUND_MESSAGE);
        return page;
    }

    showMessage(status, 'Loading profile…', 'info');
    void loadProfile();
    return page;

    async function loadProfile() {
        try {
            const data = await get(`${RoutePath.API_USER_PROFILE}?id=${profileId}`);
            const user = data?.user;
            if (!Number.isSafeInteger(user?.id) || user.id !== profileId) {
                showMessage(status, NOT_FOUND_MESSAGE);
                return;
            }

            title.textContent = user.displayName ?? 'Profile';
            if (session?.getUser?.()?.id === profileId) {
                actions.replaceChildren(el('a', {
                    className: 'button button--primary user-profile-edit',
                    attributes: {href: RoutePath.SETTINGS},
                    children: 'Edit profile',
                }));
            }

            const recentUploads = createRecentUploadsSection();
            renderRecentUploads(recentUploads.grid, data?.recentUploads);
            const children = [
                createProfileDetails(user, data?.approvedTagSuggestions),
                recentUploads.element,
            ];
            const modActions = createModActionsSection(data?.modActions);
            if (modActions !== null) {
                children.push(modActions);
            }
            content.replaceChildren(...children);
            clearMessage(status);
        } catch (error) {
            if (error?.status === 404) {
                showMessage(status, NOT_FOUND_MESSAGE);
                return;
            }
            if (error?.status === 401 || error?.status === 403) {
                showMessage(status, PERMISSION_MESSAGE);
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        }
    }
}

function parseProfileId(raw) {
    if (!/^[1-9][0-9]*$/.test(raw ?? '')) {
        return null;
    }
    const id = Number(raw);
    return Number.isSafeInteger(id) ? id : null;
}

function createProfileDetails(user, approvedTagSuggestions) {
    const suggestionCount = Number.isSafeInteger(approvedTagSuggestions) && approvedTagSuggestions >= 0
        ? String(approvedTagSuggestions)
        : 'Unavailable';
    return el('dl', {
        className: 'user-profile-details',
        children: [
            createProfileDetail('Display name', user.displayName ?? '—'),
            createProfileDetail('Access level', accessLevelName(user.accessLevel)),
            createProfileDetail('Registered', user.registerDate ?? '—'),
            createProfileDetail('Approved tag suggestions', suggestionCount),
        ],
    });
}

function createProfileDetail(label, value) {
    return el('div', {
        className: 'user-profile-detail',
        children: [
            el('dt', {className: 'user-profile-detail-term', children: label}),
            el('dd', {className: 'user-profile-detail-value', children: value}),
        ],
    });
}

function createModActionsSection(modActions) {
    if (!Array.isArray(modActions) || modActions.length === 0) {
        return null;
    }

    return el('section', {
        className: 'user-profile-mod-actions',
        children: [
            el('h2', {className: 'user-profile-section-title', children: 'Moderation actions'}),
            el('ol', {
                className: 'user-profile-mod-actions-list',
                children: modActions.map((action) => createModAction(action)),
            }),
        ],
    });
}

function createModAction(action) {
    const time = typeof action?.time === 'string' ? action.time : '';
    const actionName = MOD_ACTION_NAMES.get(action?.actionType) ?? `Action #${action?.actionType ?? 'unknown'}`;
    const heading = [actionName];
    if (time !== '') {
        heading.push(' · ', el('time', {attributes: {datetime: time}, children: time}));
    }

    return el('li', {
        className: 'user-profile-mod-action',
        children: [
            el('p', {className: 'user-profile-mod-action-heading', children: heading}),
            el('p', {className: 'user-profile-mod-action-context', children: action?.context ?? ''}),
        ],
    });
}
