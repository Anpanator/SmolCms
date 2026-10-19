/**
 * Tag management page for moderators: browse, search and create tags/categories.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createSearchView, normalizeTags} from '../search.js';
import {
    clearMessage,
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
    if (!hasTagsAccess(session?.getUser?.())) {
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
    let currentPage = parsePositiveInteger(params.get('page'), 1);
    let currentQuery = firstSearchSegment(params.get('q') ?? '');
    let tagRequestId = 0;
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
        initialTags: currentQuery,
        onSearch(searchTags) {
            currentQuery = firstSearchSegment(searchTags);
            currentPage = 1;
            loadTags(currentPage, currentQuery);
        },
    });
    const tableBody = el('tbody', {
        children: createTableStatusRow('Loading tags…'),
    });
    const table = el('table', {
        className: 'tags-table',
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
    const pagination = createPagination(currentPage, (page) => {
        currentPage = page;
        loadTags(currentPage, currentQuery);
    });

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
                        className: 'tags-table-wrap',
                        children: table,
                    }),
                    pagination.element,
                ],
            }),
        ],
    });

    tagForm.addEventListener('submit', createTag);
    categoryForm.addEventListener('submit', createCategory);

    refreshCategories();
    loadTags(currentPage, currentQuery);
    return page;

    async function loadTags(pageNumber, queryText) {
        const requestId = ++tagRequestId;
        currentPage = pageNumber;
        clearMessage(listMessage);
        tableBody.replaceChildren(createTableStatusRow('Loading tags…'));
        pagination.loading(pageNumber);

        const queryParams = new URLSearchParams();
        queryParams.set('page', String(pageNumber));
        if (queryText !== '') {
            queryParams.set('q', queryText);
        }

        try {
            const data = await get(`${TAGS_URL}?${queryParams}`);
            if (requestId !== tagRequestId) {
                return;
            }

            const tags = Array.isArray(data?.tags) ? data.tags : [];
            const total = Number.isSafeInteger(data?.total) && data.total >= 0 ? data.total : tags.length;
            const perPage = parsePositiveInteger(data?.perPage, PER_PAGE);
            const responsePage = parsePositiveInteger(data?.page, pageNumber);
            const pageCount = Math.max(1, Math.ceil(total / perPage));

            if (responsePage > pageCount) {
                currentPage = pageCount;
                loadTags(currentPage, queryText);
                return;
            }

            currentPage = responsePage;
            pagination.update({page: responsePage, total, perPage});
            if (tags.length === 0) {
                tableBody.replaceChildren(createTableStatusRow(
                    queryText === '' ? 'No tags found.' : 'No tags match your search.',
                ));
                return;
            }

            tableBody.replaceChildren(...tags.map(createTagRow));
        } catch (error) {
            if (requestId !== tagRequestId) {
                return;
            }
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(listMessage, messageForStatus(error?.status ?? 0));
            tableBody.replaceChildren(createTableStatusRow('Could not load tags.'));
            pagination.update({page: pageNumber, total: 0, perPage: PER_PAGE});
        }
    }

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
            await loadTags(currentPage, currentQuery);
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

function createPagination(initialPage, onPageChange) {
    let currentPage = initialPage;
    const previous = el('button', {
        className: 'pagination-button pagination-button--previous',
        attributes: {type: 'button', disabled: ''},
        children: 'Previous',
    });
    const label = el('span', {className: 'pagination-label', children: 'Loading…'});
    const next = el('button', {
        className: 'pagination-button pagination-button--next',
        attributes: {type: 'button', disabled: ''},
        children: 'Next',
    });

    previous.addEventListener('click', () => {
        if (!previous.disabled) {
            onPageChange(currentPage - 1);
        }
    });
    next.addEventListener('click', () => {
        if (!next.disabled) {
            onPageChange(currentPage + 1);
        }
    });

    return {
        element: el('nav', {
            className: 'pagination',
            attributes: {'aria-label': 'Tag pages'},
            children: [previous, label, next],
        }),
        loading(page) {
            currentPage = page;
            label.textContent = 'Loading…';
            previous.disabled = true;
            next.disabled = true;
        },
        update({page, total, perPage}) {
            currentPage = page;
            const pageCount = Math.max(1, Math.ceil(total / perPage));
            label.textContent = `Page ${page} of ${pageCount}`;
            previous.disabled = page <= 1;
            next.disabled = page >= pageCount;
        },
    };
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
        className: 'tags-table-status',
        children: el('td', {attributes: {colspan: '3'}, children: text}),
    });
}

function createSelectField({label, name, inputName, options}) {
    const id = `field-${name}`;
    const input = el('select', {
        className: 'field-input',
        attributes: {id, name: inputName},
        children: options.map((option) => el('option', {
            attributes: {value: option},
            children: option,
        })),
    });
    const errorElement = el('p', {className: 'field-error', attributes: {hidden: 'hidden'}});
    return {
        wrapper: el('div', {
            className: 'field',
            children: [
                el('label', {className: 'field-label', attributes: {for: id}, children: label}),
                input,
                errorElement,
            ],
        }),
        input,
        errorElement,
    };
}

function firstSearchSegment(value) {
    return normalizeTags(value).split(',')[0]?.trim() ?? '';
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

function hasTagsAccess(user) {
    return (user?.accessLevel ?? 0) >= AccessLevel.MOD;
}
