/**
 * Tag management page for moderators: browse, search and create tags/categories.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createPagination} from '../pagination.js';
import {createPaginatedTable, parsePositiveInteger} from '../paginatedTable.js';
import {createSearchView, normalizeTags} from '../search.js';
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
import {normalizeTagName, validateTagCategoryName, validateTagName} from '../validation.js';

const TAGS_URL = '/api/tags';
const CATEGORIES_URL = '/api/tag-categories';
const PER_PAGE = 25;

/**
 * @param {{ session: *, navigate?: (path: string) => void, query?: URLSearchParams|string }} [context]
 */
export function createTagsView({
    session,
    navigate = () => {},
    query = new URLSearchParams(window.location.search),
} = {}) {
    if (!hasAccessLevel(session?.getUser?.(), AccessLevel.MOD)) {
        return el('section', {
            className: 'page tags-denied-page',
            attributes: {tabindex: '-1'},
            children: [
                el('h1', {className: 'page-title', children: 'Tags'}),
                el('p', {
                    className: 'notice notice--warning tags-denied',
                    children: 'You do not have permission to manage tags.',
                }),
            ],
        });
    }

    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query);
    const initialPage = parsePositiveInteger(params.get('page'), 1);
    const initialQuery = firstSearchSegment(params.get('q') ?? '');
    let categoryRequestId = 0;

    const tagNameField = createField({label: 'Tag name', name: 'tagName'});
    const categorySelectField = createSelectField({
        label: 'Category',
        name: 'tagCategory',
        inputName: 'category',
        options: ['General'],
    });
    const categoryNameField = createField({label: 'Category name', name: 'categoryName'});
    const tagMessage = createMessageBox();
    const categoryMessage = createMessageBox();
    const categoryLoadMessage = createMessageBox();
    const listMessage = createMessageBox();
    const tagSubmit = el('button', {
        className: 'button button--primary',
        attributes: {type: 'submit'},
        children: 'Create tag',
    });
    const categorySubmit = el('button', {
        className: 'button button--primary',
        attributes: {type: 'submit'},
        children: 'Create category',
    });

    const tagForm = el('form', {
        className: 'form tags-form',
        attributes: {novalidate: 'novalidate'},
        children: [
            tagNameField.wrapper,
            categorySelectField.wrapper,
            categoryLoadMessage,
            tagMessage,
            tagSubmit,
        ],
    });
    const categoryForm = el('form', {
        className: 'form tags-form',
        attributes: {novalidate: 'novalidate'},
        children: [categoryNameField.wrapper, categoryMessage, categorySubmit],
    });

    const search = createSearchView({
        initialTags: initialQuery,
        onSearch(searchTags) {
            paginatedTable.setQuery(firstSearchSegment(searchTags));
        },
    });
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading tags…'),
    });
    const table = el('table', {
        className: 'data-table tags-table',
        children: [
            el('thead', {
                children: el('tr', {
                    children: [
                        el('th', {attributes: {scope: 'col'}, children: 'Tag name'}),
                        el('th', {attributes: {scope: 'col'}, children: 'Category'}),
                        el('th', {className: 'tags-count-cell', attributes: {scope: 'col'}, children: 'Usage count'}),
                    ],
                }),
            }),
            tableBody,
        ],
    });
    let paginatedTable;
    const pagination = createPagination(initialPage, (pageNumber) => paginatedTable.setPage(pageNumber));
    pagination.element.setAttribute('aria-label', 'Tag pages');

    const page = el('section', {
        className: 'tags-page',
        children: [
            el('div', {
                className: 'tags-management',
                children: [
                    el('section', {
                        className: 'tags-form-panel',
                        attributes: {'aria-labelledby': 'create-category-title'},
                        children: [
                            el('h2', {
                                className: 'tags-section-title',
                                attributes: {id: 'create-category-title'},
                                children: 'Create category'
                            }),
                            categoryForm,
                            search.element,
                        ],
                    }),
                    el('section', {
                        className: 'tags-form-panel',
                        attributes: {'aria-labelledby': 'create-tag-title'},
                        children: [
                            el('h2', {className: 'tags-section-title', attributes: {id: 'create-tag-title'}, children: 'Create tag'}),
                            tagForm,
                        ],
                    }),
                ],
            }),
            el('section', {
                className: 'tags-browser',
                attributes: {'aria-labelledby': 'tags-browser-title'},
                children: [
                    el('h2', {
                        className: 'tags-section-title',
                        attributes: {id: 'tags-browser-title'},
                        children: 'Browse tags'
                    }),
                    listMessage,
                    el('div', {
                        className: 'data-table-wrap tags-table-wrap',
                        children: table,
                    }),
                    pagination.element,
                ],
            }),
        ],
    });

    tagForm.addEventListener('submit', createTag);
    categoryForm.addEventListener('submit', createCategory);

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
            return get(`${TAGS_URL}?${queryParams}`);
        },
        getItems: (data) => data?.tags,
        renderRow: createTagRow,
        renderStatusRow: createTableStatusRow,
        messages: {
            loading: 'Loading tags…',
            empty: 'No tags found.',
            emptySearch: 'No tags match your search.',
            error: 'Could not load tags.',
        },
        defaultPerPage: PER_PAGE,
        initialPage,
        initialQuery,
        navigate,
    });

    refreshCategories();
    paginatedTable.load();
    return page;

    async function refreshCategories(preferredCategory = categorySelectField.input.value) {
        const requestId = ++categoryRequestId;
        try {
            const data = await get(CATEGORIES_URL);
            if (requestId !== categoryRequestId) {
                return;
            }
            const categories = Array.isArray(data?.categories)
                ? data.categories.filter((category) => typeof category === 'string' && category !== '')
                : [];
            updateCategoryOptions(categories, preferredCategory);
            clearMessage(categoryLoadMessage);
        } catch (error) {
            if (requestId !== categoryRequestId) {
                return;
            }
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(categoryLoadMessage, messageForStatus(error?.status ?? 0));
        }
    }

    function updateCategoryOptions(categories, preferredCategory) {
        if (categories.length === 0) {
            categorySelectField.input.replaceChildren(el('option', {
                attributes: {value: '', disabled: 'disabled'},
                children: 'No categories available',
            }));
            categorySelectField.input.value = '';
            categorySelectField.input.disabled = true;
            return;
        }

        categorySelectField.input.replaceChildren(...categories.map((category) => el('option', {
            attributes: {value: category},
            children: category,
        })));
        categorySelectField.input.disabled = false;
        const desiredCategory = categories.includes(preferredCategory)
            ? preferredCategory
            : categories.includes('General') ? 'General' : categories[0];
        categorySelectField.input.value = desiredCategory;
    }

    async function createTag(event) {
        event.preventDefault();
        clearMessage(tagMessage);

        const name = normalizeTagName(tagNameField.input.value);
        tagNameField.input.value = name;
        const errors = {
            tagName: validateTagName(name),
            tagCategory: categorySelectField.input.value === '' ? 'Choose a category.' : null,
        };
        setFieldErrors({tagName: tagNameField, tagCategory: categorySelectField}, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }
        if (tagSubmit.disabled) {
            return;
        }

        tagSubmit.disabled = true;
        try {
            const data = await postJson(TAGS_URL, {
                name,
                category: categorySelectField.input.value,
            });
            tagNameField.input.value = '';
            setFieldErrors({tagName: tagNameField, tagCategory: categorySelectField}, {
                tagName: null,
                tagCategory: null,
            });
            if ([...categorySelectField.input.options].some((option) => option.value === 'General')) {
                categorySelectField.input.value = 'General';
            }
            const createdName = typeof data?.tag?.name === 'string' ? data.tag.name : name;
            showMessage(tagMessage, `Tag "${createdName}" created.`, 'success');
            await paginatedTable.reload();
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(tagMessage, messageForStatus(
                error?.status ?? 0,
                'A tag with that name already exists.',
            ));
        } finally {
            tagSubmit.disabled = false;
        }
    }

    async function createCategory(event) {
        event.preventDefault();
        clearMessage(categoryMessage);

        const name = normalizeTagName(categoryNameField.input.value);
        categoryNameField.input.value = name;
        const errors = {categoryName: validateTagCategoryName(name)};
        setFieldErrors({categoryName: categoryNameField}, errors);
        if (Object.values(errors).some(Boolean)) {
            return;
        }
        if (categorySubmit.disabled) {
            return;
        }

        categorySubmit.disabled = true;
        try {
            const data = await postJson(CATEGORIES_URL, {name});
            categoryNameField.input.value = '';
            setFieldErrors({categoryName: categoryNameField}, {categoryName: null});
            const createdCategory = typeof data?.category === 'string' ? data.category : name;
            showMessage(categoryMessage, `Category "${createdCategory}" created.`, 'success');
            await refreshCategories(createdCategory);
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(categoryMessage, messageForStatus(
                error?.status ?? 0,
                'A category with that name already exists.',
            ));
        } finally {
            categorySubmit.disabled = false;
        }
    }
}

function createTagRow(tag) {
    const usageCount = Number.isSafeInteger(tag?.usageCount) && tag.usageCount >= 0 ? tag.usageCount : 0;
    return el('tr', {
        className: 'tags-table-row',
        attributes: {'data-tag-name': tag?.name ?? ''},
        children: [
            el('td', {className: 'tags-table-name', children: tag?.name ?? ''}),
            el('td', {className: 'tags-table-category', children: tag?.category ?? ''}),
            el('td', {className: 'tags-count-cell', children: usageCount}),
        ],
    });
}

function createTableStatusRow(text) {
    return el('tr', {
        className: 'tags-table-status data-table-status',
        children: el('td', {attributes: {colspan: '3'}, children: text}),
    });
}

function createSelectField({label, name, inputName, options}) {
    const input = el('select', {
        className: 'field-input',
        attributes: {name: inputName},
        children: options.map((option) => el('option', {
            attributes: {value: option},
            children: option,
        })),
    });
    const errorElement = el('p', {className: 'field-error', attributes: {hidden: 'hidden'}});
    return createControlField({label, name, control: input, errorElement});
}

function firstSearchSegment(value) {
    return normalizeTags(value).split(',')[0]?.trim() ?? '';
}
