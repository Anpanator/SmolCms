/**
 * Internal user management for moderators and administrators.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, accessLevelName} from '../accessLevel.js';
import {el} from '../dom.js';
import {createModal} from '../modal.js';
import {createPagination} from '../pagination.js';
import {createSearchBox} from '../searchBox.js';
import {
    clearMessage,
    createField,
    createMessageBox,
    isAuthError,
    messageForStatus,
    setFieldErrors,
    showMessage,
} from '../forms.js';
import {validateDisplayName, validateLoginName} from '../validation.js';

const USERS_URL = '/api/users';
const UPDATE_USER_URL = '/api/users/update';
const PER_PAGE = 30;
const ACCESS_LEVELS = [
    AccessLevel.NOVICE,
    AccessLevel.FELLOW,
    AccessLevel.MASTER,
    AccessLevel.WIZARD,
    AccessLevel.MOD,
    AccessLevel.ADMIN,
];

/**
 * @param {{session: *, navigate?: (path: string) => void, query?: URLSearchParams|string}} [context]
 */
export function createUsersView({
    session,
    navigate = () => {},
    query = new URLSearchParams(window.location.search),
} = {}) {
    const currentUser = session?.getUser?.() ?? null;
    if (!hasUsersAccess(currentUser)) {
        return el('section', {
            className: 'internal-admin-denied-page users-denied-page',
            attributes: {tabindex: '-1', 'aria-label': 'User management'},
            children: el('p', {
                className: 'notice notice--warning internal-admin-denied',
                children: 'You do not have permission to manage users.',
            }),
        });
    }

    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query);
    let currentPage = parsePositiveInteger(params.get('page'), 1);
    let currentQuery = (params.get('q') ?? '').trim();
    let requestId = 0;
    let users = [];

    const listMessage = createMessageBox();
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading users…'),
    });
    const table = el('table', {
        className: 'internal-admin-table users-table',
        attributes: {'aria-label': 'Users'},
        children: [
            el('thead', {
                children: el('tr', {
                    children: [
                        'ID',
                        'Login name',
                        'Display name',
                        'Access level',
                        'Register date',
                        'Actions',
                    ].map((label) => el('th', {attributes: {scope: 'col'}, children: label})),
                }),
            }),
            tableBody,
        ],
    });
    const pagination = createPagination(currentPage, (pageNumber) => {
        currentPage = pageNumber;
        loadUsers(currentPage, currentQuery);
    });
    pagination.element.setAttribute('aria-label', 'User pages');

    const search = createSearchBox({
        placeholder: 'Search users',
        buttonText: 'Search',
        onSearch(queryText) {
            currentQuery = String(queryText ?? '').trim();
            search.setValue(currentQuery);
            currentPage = 1;
            loadUsers(currentPage, currentQuery);
        },
        onReset() {
            currentQuery = '';
            currentPage = 1;
            loadUsers(currentPage, currentQuery);
        },
    });
    search.setValue(currentQuery);

    const page = el('section', {
        className: 'internal-admin-page users-page',
        attributes: {'aria-label': 'User management'},
        children: el('div', {
            className: 'internal-admin-workspace',
            children: [
                el('section', {
                    className: 'internal-admin-list-panel users-list-panel',
                    attributes: {'aria-label': 'User accounts'},
                    children: [
                        listMessage,
                        el('div', {className: 'internal-admin-table-wrap', children: table}),
                        pagination.element,
                    ],
                }),
                el('aside', {
                    className: 'internal-admin-sidebar users-sidebar',
                    attributes: {'aria-label': 'Search users'},
                    children: search.element,
                }),
            ],
        }),
    });

    loadUsers(currentPage, currentQuery);
    return page;

    async function loadUsers(pageNumber, queryText) {
        const request = ++requestId;
        currentPage = pageNumber;
        clearMessage(listMessage);
        tableBody.replaceChildren(createTableStatusRow('Loading users…'));
        pagination.loading(pageNumber);

        const queryParams = new URLSearchParams();
        queryParams.set('page', String(pageNumber));
        if (queryText !== '') {
            queryParams.set('q', queryText);
        }

        try {
            const data = await get(`${USERS_URL}?${queryParams}`);
            if (request !== requestId) {
                return;
            }

            const loadedUsers = Array.isArray(data?.users) ? data.users : [];
            const total = Number.isSafeInteger(data?.total) && data.total >= 0 ? data.total : loadedUsers.length;
            const perPage = parsePositiveInteger(data?.perPage, PER_PAGE);
            const responsePage = parsePositiveInteger(data?.page, pageNumber);
            const pageCount = Math.max(1, Math.ceil(total / perPage));

            if (responsePage > pageCount) {
                currentPage = pageCount;
                loadUsers(currentPage, queryText);
                return;
            }

            currentPage = responsePage;
            users = loadedUsers;
            pagination.update({page: responsePage, total, perPage});
            if (users.length === 0) {
                tableBody.replaceChildren(createTableStatusRow(
                    queryText === '' ? 'No users found.' : 'No users match your search.',
                ));
                return;
            }
            renderUsers();
        } catch (error) {
            if (request !== requestId) {
                return;
            }
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(listMessage, messageForStatus(error?.status ?? 0));
            tableBody.replaceChildren(createTableStatusRow('Could not load users.'));
            pagination.update({page: pageNumber, total: 0, perPage: PER_PAGE});
        }
    }

    function renderUsers() {
        tableBody.replaceChildren(...users.map(createUserRow));
    }

    function createUserRow(user) {
        const row = el('tr', {
            className: 'internal-admin-table-row users-table-row',
            attributes: {'data-user-id': user?.id ?? ''},
            children: [
                el('td', {className: 'users-id-cell', children: user?.id ?? ''}),
                el('td', {className: 'users-login-cell', children: user?.loginName ?? ''}),
                el('td', {className: 'users-display-name-cell', children: user?.displayName ?? ''}),
                el('td', {className: 'users-access-level-cell', children: accessLevelName(user?.accessLevel)}),
                el('td', {className: 'users-register-date-cell', children: user?.registerDate ?? ''}),
            ],
        });
        const actions = el('td', {className: 'users-actions-cell'});
        if (Number(user?.accessLevel ?? 0) <= Number(currentUser?.accessLevel ?? 0)) {
            const editButton = el('button', {
                className: 'button users-edit-button',
                attributes: {
                    type: 'button',
                    'aria-label': `Edit user #${user.id}`,
                    title: `Edit user #${user.id}`,
                },
                children: '✎',
            });
            editButton.addEventListener('click', () => openEditModal(user));
            actions.append(editButton);
        }
        row.append(actions);
        return row;
    }

    function openEditModal(user) {
        const titleId = `edit-user-title-${user.id}`;
        const loginNameField = createField({
            label: 'Login name',
            name: 'loginName',
            value: user.loginName ?? '',
        });
        const displayNameField = createField({
            label: 'Display name',
            name: 'displayName',
            value: user.displayName ?? '',
        });
        const accessLevelField = createAccessLevelField(user.accessLevel);
        const modalMessage = createMessageBox();
        const saveButton = el('button', {
            className: 'button button--primary',
            attributes: {type: 'submit'},
            children: 'Save',
        });
        const cancelButton = el('button', {
            className: 'button modal-cancel',
            attributes: {type: 'button'},
            children: 'Cancel',
        });
        const form = el('form', {
            className: 'form user-edit-form',
            attributes: {novalidate: 'novalidate'},
            children: [
                createReadOnlyField('ID', 'id', user.id),
                loginNameField.wrapper,
                createReadOnlyField('Register date', 'registerDate', user.registerDate),
                displayNameField.wrapper,
                accessLevelField.wrapper,
                modalMessage,
                el('div', {className: 'modal-actions', children: [cancelButton, saveButton]}),
            ],
        });
        const modal = createModal({
            content: el('div', {
                className: 'user-edit-panel',
                children: [
                    el('h2', {
                        className: 'modal-title',
                        attributes: {id: titleId},
                        children: `Edit user #${user.id}`,
                    }),
                    form,
                ],
            }),
            labelledBy: titleId,
        });
        let savePending = false;

        loginNameField.input.focus();
        cancelButton.addEventListener('click', () => modal.close());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (savePending) {
                return;
            }
            clearMessage(modalMessage);
            const loginName = loginNameField.input.value.trim();
            const displayName = displayNameField.input.value.trim();
            const errors = {
                loginName: validateLoginName(loginName),
                displayName: validateDisplayName(displayName),
            };
            setFieldErrors({loginName: loginNameField, displayName: displayNameField}, errors);
            const validationError = errors.loginName ?? errors.displayName;
            if (validationError) {
                showMessage(modalMessage, validationError);
                return;
            }

            const accessLevel = Number(accessLevelField.input.value);
            if (!ACCESS_LEVELS.includes(accessLevel)) {
                showMessage(modalMessage, messageForStatus(400));
                return;
            }

            savePending = true;
            saveButton.disabled = true;
            try {
                const data = await postJson(`${UPDATE_USER_URL}?id=${user.id}`, {loginName, displayName, accessLevel});
                const savedUser = data?.user ?? {};
                const updatedUser = {
                    ...user,
                    ...savedUser,
                    id: user.id,
                    loginName: savedUser.loginName ?? loginName,
                    displayName: savedUser.displayName ?? displayName,
                    accessLevel: savedUser.accessLevel ?? accessLevel,
                };
                const userIndex = users.findIndex((item) => String(item.id) === String(user.id));
                if (userIndex >= 0) {
                    users[userIndex] = updatedUser;
                    renderUsers();
                }
                showMessage(listMessage, `User #${user.id} updated.`, 'success');
                modal.close();
            } catch (error) {
                if (isAuthError(error)) {
                    modal.close();
                    navigate('/login');
                    return;
                }
                if (error?.status === 409) {
                    showMessage(modalMessage, 'This login name is already taken.');
                    return;
                }
                if (error?.status === 400) {
                    const currentLoginNameError = validateLoginName(loginNameField.input.value.trim());
                    const currentDisplayNameError = validateDisplayName(displayNameField.input.value.trim());
                    const currentErrors = {
                        loginName: currentLoginNameError,
                        displayName: currentDisplayNameError,
                    };
                    setFieldErrors({loginName: loginNameField, displayName: displayNameField}, currentErrors);
                    showMessage(modalMessage, currentLoginNameError ?? currentDisplayNameError ?? messageForStatus(400));
                    return;
                }
                showMessage(modalMessage, messageForStatus(error?.status ?? 0));
            } finally {
                savePending = false;
                saveButton.disabled = false;
            }
        });
    }
}

function createReadOnlyField(label, name, value) {
    const id = `field-${name}`;
    const input = el('input', {
        className: 'field-input',
        attributes: {id, name, type: 'text', readonly: 'readonly', 'aria-readonly': 'true'},
    });
    input.value = String(value ?? '');
    return el('div', {
        className: 'field user-edit-readonly-field',
        children: [
            el('label', {className: 'field-label', attributes: {for: id}, children: label}),
            input,
        ],
    });
}

function createAccessLevelField(value) {
    const id = 'field-accessLevel';
    const input = el('select', {
        className: 'field-input',
        attributes: {id, name: 'accessLevel'},
        children: ACCESS_LEVELS.map((accessLevel) => el('option', {
            attributes: {value: String(accessLevel)},
            children: accessLevelName(accessLevel),
        })),
    });
    input.value = String(value);
    return {
        wrapper: el('div', {
            className: 'field',
            children: [
                el('label', {className: 'field-label', attributes: {for: id}, children: 'Access level'}),
                input,
            ],
        }),
        input,
    };
}

function createTableStatusRow(text) {
    return el('tr', {
        className: 'internal-admin-table-status',
        children: el('td', {attributes: {colspan: '6'}, children: text}),
    });
}

function parsePositiveInteger(value, fallback) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : fallback;
    }
    if (!/^[1-9][0-9]*$/.test(value ?? '')) {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) ? parsed : fallback;
}

function hasUsersAccess(user) {
    return Number(user?.accessLevel ?? 0) >= AccessLevel.MOD;
}
