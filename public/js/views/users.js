/**
 * Internal user management for moderators and administrators.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, accessLevelName, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createModal} from '../modal.js';
import {createPagination} from '../pagination.js';
import {createPaginatedTable, parsePositiveInteger} from '../paginatedTable.js';
import {createSearchBox} from '../searchBox.js';
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
    if (!hasAccessLevel(currentUser, AccessLevel.MOD)) {
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
    const initialPage = parsePositiveInteger(params.get('page'), 1);
    const initialQuery = (params.get('q') ?? '').trim();
    let users = [];

    const listMessage = createMessageBox();
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading users…'),
    });
    const table = el('table', {
        className: 'data-table internal-admin-table users-table',
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
    let paginatedTable;
    const pagination = createPagination(initialPage, (pageNumber) => paginatedTable.setPage(pageNumber));
    pagination.element.setAttribute('aria-label', 'User pages');

    const search = createSearchBox({
        placeholder: 'Search users',
        buttonText: 'Search',
        onSearch(queryText) {
            const currentQuery = String(queryText ?? '').trim();
            search.setValue(currentQuery);
            paginatedTable.setQuery(currentQuery);
        },
        onReset() {
            paginatedTable.setQuery('');
        },
    });
    search.setValue(initialQuery);

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
                        el('div', {className: 'data-table-wrap internal-admin-table-wrap', children: table}),
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

    paginatedTable = createPaginatedTable({
        tableBody,
        listMessage,
        pagination,
        fetchPage({page: pageNumber, query: queryText}) {
            const queryParams = new URLSearchParams();
            queryParams.set('page', String(pageNumber));
            if (queryText !== '') {
                queryParams.set('q', queryText);
            }
            return get(`${USERS_URL}?${queryParams}`);
        },
        getItems: (data) => data?.users,
        renderRow: createUserRow,
        renderStatusRow: createTableStatusRow,
        messages: {
            loading: 'Loading users…',
            empty: 'No users found.',
            emptySearch: 'No users match your search.',
            error: 'Could not load users.',
        },
        defaultPerPage: PER_PAGE,
        initialPage,
        initialQuery,
        onItems: (loadedUsers) => {
            users = loadedUsers;
        },
        navigate,
    });
    paginatedTable.load();
    return page;

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
    const input = el('input', {
        className: 'field-input',
        attributes: {name, type: 'text', readonly: 'readonly', 'aria-readonly': 'true'},
    });
    input.value = String(value ?? '');
    return createControlField({
        label,
        name,
        control: input,
        className: 'field user-edit-readonly-field',
    }).wrapper;
}

function createAccessLevelField(value) {
    const input = el('select', {
        className: 'field-input',
        attributes: {name: 'accessLevel'},
        children: ACCESS_LEVELS.map((accessLevel) => el('option', {
            attributes: {value: String(accessLevel)},
            children: accessLevelName(accessLevel),
        })),
    });
    input.value = String(value);
    return createControlField({label: 'Access level', name: 'accessLevel', control: input});
}

function createTableStatusRow(text) {
    return el('tr', {
        className: 'internal-admin-table-status data-table-status',
        children: el('td', {attributes: {colspan: '6'}, children: text}),
    });
}
