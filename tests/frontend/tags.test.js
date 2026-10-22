import {assertEqual, assertTrue, settle} from './assert.js';
import {mockFetch, submitForm, testUser, useFetch} from './mocks.js';
import {createTagsView} from '../../public/js/views/tags.js';

const CATEGORIES = ['Artist', 'General', 'Copyright'];
const TAGS = [
    {name: 'popular tag', category: 'Artist', usageCount: 12},
    {name: 'unused tag', category: 'General', usageCount: 0},
];

function moderatorSession(accessLevel = 500) {
    return {getUser: () => testUser({accessLevel})};
}

function defaultResponse(url, options = {}) {
    const parsed = new URL(url, 'http://localhost');
    if (parsed.pathname === '/api/tag-categories' && (options.method ?? 'GET') === 'GET') {
        return {status: 200, body: {categories: CATEGORIES}};
    }
    if (parsed.pathname === '/api/tags/suggest') {
        return {status: 200, body: {tags: ['thighhighs', 'black thighhighs']}};
    }
    if (parsed.pathname === '/api/tags' && (options.method ?? 'GET') === 'GET') {
        return {status: 200, body: {tags: TAGS, page: 1, perPage: 100, total: TAGS.length}};
    }
    if (parsed.pathname === '/api/tags' && options.method === 'POST') {
        const body = JSON.parse(options.body);
        return {
            status: 201,
            body: {tag: {name: body.name, category: body.category, usageCount: 0}},
        };
    }
    return {status: 200, body: null};
}

async function createLoadedView(handler = () => undefined, {accessLevel = 500, query = ''} = {}) {
    const fetchMock = mockFetch(async (url, options) => {
        const response = await handler(url, options);
        return response === undefined ? defaultResponse(url, options) : response;
    });
    const restore = useFetch(fetchMock);
    const view = createTagsView({
        session: accessLevel === null ? {getUser: () => null} : moderatorSession(accessLevel),
        query: new URLSearchParams(query),
    });
    await settle();
    return {view, fetchMock, restore};
}

function tagListCalls(fetchMock) {
    return fetchMock.calls.filter((call) => (
        new URL(call.url, 'http://localhost').pathname === '/api/tags'
        && (call.options.method ?? 'GET') === 'GET'
    ));
}

function queryParams(call) {
    return new URL(call.url, 'http://localhost').searchParams;
}

function tagForm(view) {
    return view.querySelector('input[name="tagName"]').closest('form');
}

function categoryForm(view) {
    return view.querySelector('input[name="categoryName"]').closest('form');
}

function wait(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

export async function testTagsPageIsDeniedWithoutModeratorAccess() {
    for (const accessLevel of [null, 100, 400]) {
        const fetchMock = mockFetch(() => {
            throw new Error('protected tag APIs must not be called without moderator access');
        });
        const restore = useFetch(fetchMock);
        try {
            const navigateCalls = [];
            const view = createTagsView({
                session: accessLevel === null ? {getUser: () => null} : moderatorSession(accessLevel),
                navigate: (path) => navigateCalls.push(path),
            });
            assertTrue(view.querySelector('.tags-denied') !== null, 'lower access should show the denied notice');
            assertTrue(view.querySelector('.tags-denied').textContent.includes('permission'));
            assertEqual(view.querySelector('.page-title')?.textContent, 'Tags');
            assertEqual(fetchMock.calls.length, 0, 'denied views must not call protected APIs');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
        } finally {
            restore();
        }
    }
}

export async function testModeratorSeesTagTableAndGeneralCategoryDefault() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        assertTrue(view.matches('.tags-page'), 'moderators should see the management page');
        assertTrue(view.querySelector('.tags-table')?.classList.contains('data-table'), 'tag tables should use the shared table component');
        assertTrue(view.querySelector('.tags-table-wrap')?.classList.contains('data-table-wrap'));
        assertTrue(view.querySelector('.sidebar') === null, 'tag management should not render a tags sidebar');
        assertTrue(view.querySelector('.tags-workspace')?.classList.contains('internal-admin-workspace'));
        assertTrue(view.querySelector('.tags-browser')?.classList.contains('internal-admin-list-panel'));
        assertTrue(view.querySelector('.tags-sidebar')?.classList.contains('internal-admin-sidebar'));
        assertEqual(
            [...view.querySelectorAll('.tags-sidebar > section')].map((section) => section.querySelector('h2')?.textContent).join('|'),
            'Search tags|Create tag|Create category',
            'the tag search and forms should appear in the requested sidebar order',
        );
        assertTrue(view.querySelector('.tags-sidebar .search-view') !== null);
        assertEqual(view.querySelector('.tags-form-panel .search-view'), null, 'search should not remain in a create form panel');
        assertTrue(view.querySelector('img') === null, 'tag management should not display images');
        assertEqual(view.querySelectorAll('.tags-table thead th').length, 3);
        assertEqual(
            [...view.querySelectorAll('.tags-table thead th')].map((cell) => cell.textContent).join('|'),
            'Tag name|Category|Usage count',
        );

        const rows = view.querySelectorAll('.tags-table tbody tr.tags-table-row');
        assertEqual(rows.length, 2);
        const popularRow = view.querySelector('.tags-table-row[data-tag-name="popular tag"]');
        const unusedRow = view.querySelector('.tags-table-row[data-tag-name="unused tag"]');
        assertEqual(popularRow.querySelector('.tags-table-name').textContent, 'popular tag', 'API order should be preserved');
        assertEqual(popularRow.querySelector('.tags-table-category').textContent, 'Artist');
        assertEqual(popularRow.querySelector('.tags-count-cell').textContent, '12');
        assertEqual(unusedRow.querySelector('.tags-table-name').textContent, 'unused tag');

        const categories = view.querySelectorAll('select[name="category"] option');
        assertEqual([...categories].map((option) => option.value).join('|'), 'Artist|General|Copyright');
        assertEqual(view.querySelector('select[name="category"]').value, 'General');

        const listCall = tagListCalls(fetchMock)[0];
        assertEqual(listCall.options.method ?? 'GET', 'GET');
        assertEqual(queryParams(listCall).get('page'), '1');
        assertEqual(queryParams(listCall).get('q'), null);
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/tag-categories'));
    } finally {
        restore();
    }
}

export async function testTagPaginationUsesOneHundredRowsAndLoadsAdjacentPages() {
    const {view, fetchMock, restore} = await createLoadedView((url) => {
        const parsed = new URL(url, 'http://localhost');
        if (parsed.pathname !== '/api/tags') {
            return undefined;
        }
        const page = Number(parsed.searchParams.get('page'));
        const count = page === 3 ? 1 : 100;
        return {
            status: 200,
            body: {
                tags: Array.from({length: count}, (_, index) => ({
                    name: `page-${page}-tag-${index}`,
                    category: 'General',
                    usageCount: 100 - index,
                })),
                page,
                perPage: 100,
                total: 201,
            },
        };
    });
    try {
        assertEqual(view.querySelectorAll('.tags-table-row').length, 100);
        assertEqual(view.querySelector('.pagination-label').textContent, 'Page 1 of 3');
        const previous = view.querySelector('.pagination-button--previous');
        const next = view.querySelector('.pagination-button--next');
        assertTrue(previous.disabled);
        assertTrue(!next.disabled);

        next.click();
        await settle();
        assertEqual(queryParams(tagListCalls(fetchMock).at(-1)).get('page'), '2');
        assertEqual(view.querySelector('.pagination-label').textContent, 'Page 2 of 3');
        assertEqual(
            view.querySelector('.tags-table-row[data-tag-name="page-2-tag-0"] .tags-table-name').textContent,
            'page-2-tag-0',
        );
        assertTrue(!previous.disabled);

        next.click();
        await settle();
        assertEqual(view.querySelectorAll('.tags-table-row').length, 1);
        assertEqual(view.querySelector('.pagination-label').textContent, 'Page 3 of 3');
        assertTrue(next.disabled);

        previous.click();
        await settle();
        assertEqual(queryParams(tagListCalls(fetchMock).at(-1)).get('page'), '2');
        assertEqual(view.querySelector('.pagination-label').textContent, 'Page 2 of 3');
    } finally {
        restore();
    }
}

export async function testSearchFiltersTagNamesByFirstSegmentAndResetsPage() {
    const {view, fetchMock, restore} = await createLoadedView((url) => {
        const parsed = new URL(url, 'http://localhost');
        if (parsed.pathname !== '/api/tags') {
            return undefined;
        }
        const page = Number(parsed.searchParams.get('page'));
        const query = parsed.searchParams.get('q');
        return {
            status: 200,
            body: {
                tags: query
                    ? [{name: `${query} result`, category: 'General', usageCount: 3}]
                    : TAGS,
                page,
                perPage: 100,
                total: query ? 1 : 201,
            },
        };
    }, {query: 'page=2&q=old'});
    try {
        assertEqual(queryParams(tagListCalls(fetchMock)[0]).get('page'), '2');
        assertEqual(view.querySelector('.search-input').value, 'old');

        const searchForm = view.querySelector('.search-view');
        const input = view.querySelector('.search-input');
        input.value = 'black thighhighs, another segment';
        await submitForm(searchForm);
        const filteredCall = tagListCalls(fetchMock).at(-1);
        assertEqual(queryParams(filteredCall).get('q'), 'black thighhighs');
        assertEqual(queryParams(filteredCall).get('page'), '1', 'searching should return to page one');
        assertEqual(
            view.querySelector('.tags-table-row[data-tag-name="black thighhighs result"] .tags-table-name').textContent,
            'black thighhighs result',
        );
        assertEqual(view.querySelector('.pagination-label').textContent, 'Page 1 of 1');

        input.value = '   ';
        await submitForm(searchForm);
        const clearedCall = tagListCalls(fetchMock).at(-1);
        assertEqual(queryParams(clearedCall).get('q'), null, 'an empty search should clear q');
        assertEqual(queryParams(clearedCall).get('page'), '1');
        assertEqual(
            view.querySelector('.tags-table-row[data-tag-name="popular tag"] .tags-table-name').textContent,
            'popular tag',
        );
    } finally {
        restore();
    }
}

export async function testTagSearchUsesThePublicSuggestionEndpoint() {
    const {view, fetchMock, restore} = await createLoadedView();
    try {
        const input = view.querySelector('.search-input');
        input.value = 'thi';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        await wait(280);
        await settle();

        const suggestionCall = fetchMock.calls.find((call) => call.url === '/api/tags/suggest?q=thi');
        assertTrue(suggestionCall !== undefined, 'typing in tag search should use the public suggestion API');
        assertEqual(view.querySelector('.search-suggestions [role="option"]')?.textContent, 'thighhighs');
    } finally {
        restore();
    }
}

export async function testCreateTagPostsNormalizedValuesAndRefreshesTheTable() {
    let created = false;
    const {view, fetchMock, restore} = await createLoadedView((url, options) => {
        const parsed = new URL(url, 'http://localhost');
        if (parsed.pathname === '/api/tags' && options.method === 'POST') {
            created = true;
            return {status: 201, body: {tag: {name: 'new tag', category: 'Artist', usageCount: 0}}};
        }
        if (parsed.pathname === '/api/tags' && (options.method ?? 'GET') === 'GET') {
            return {
                status: 200,
                body: {
                    tags: created ? [...TAGS, {name: 'new tag', category: 'Artist', usageCount: 0}] : TAGS,
                    page: 1,
                    perPage: 100,
                    total: created ? 3 : 2,
                },
            };
        }
        return undefined;
    });
    try {
        const nameInput = view.querySelector('input[name="tagName"]');
        const categorySelect = view.querySelector('select[name="category"]');
        nameInput.value = '  new\t  tag  ';
        categorySelect.value = 'Artist';
        await submitForm(tagForm(view));

        const post = fetchMock.calls.find((call) => call.url === '/api/tags' && call.options.method === 'POST');
        assertTrue(post !== undefined);
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({name: 'new tag', category: 'Artist'}));
        assertEqual(tagListCalls(fetchMock).length, 2, 'success should reload the current table page');
        assertEqual(view.querySelectorAll('.tags-table-row').length, 3);
        assertEqual(nameInput.value, '', 'success should clear the tag name field');
        assertEqual(categorySelect.value, 'General', 'success should reset the category to General');
        const message = tagForm(view).querySelector('.form-message:not([hidden])');
        assertTrue(!message.hidden);
        assertTrue(message.textContent.includes('new tag'));
    } finally {
        restore();
    }
}

export async function testCreateTagShowsValidationAndConflictMessages() {
    let postCount = 0;
    const {view, fetchMock, restore} = await createLoadedView((url, options) => {
        if (url === '/api/tags' && options.method === 'POST') {
            postCount++;
            return {status: postCount === 1 ? 400 : 409, body: null};
        }
        return undefined;
    });
    try {
        const nameInput = view.querySelector('input[name="tagName"]');
        const form = tagForm(view);
        nameInput.value = 'invalid, tag';
        await submitForm(form);
        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 0);
        assertEqual(view.querySelector('.field-error:not([hidden])')?.textContent, 'Tag name must not contain a comma.');

        nameInput.value = 'valid tag';
        await submitForm(form);
        const tagMessage = tagForm(view).querySelector('.form-message:not([hidden])');
        assertEqual(tagMessage.textContent, 'Please check your input.');

        await submitForm(form);
        assertEqual(tagMessage.textContent, 'A tag with that name already exists.');
        assertEqual(fetchMock.calls.filter((call) => call.options.method === 'POST').length, 2);
    } finally {
        restore();
    }
}

export async function testCreateCategoryPostsAndRefreshesTheCategorySelect() {
    let created = false;
    const {view, fetchMock, restore} = await createLoadedView((url, options) => {
        if (url === '/api/tag-categories' && options.method === 'POST') {
            created = true;
            return {status: 201, body: {category: 'Characters'}};
        }
        if (url === '/api/tag-categories' && (options.method ?? 'GET') === 'GET') {
            return {status: 200, body: {categories: created ? [...CATEGORIES, 'Characters'] : CATEGORIES}};
        }
        return undefined;
    });
    try {
        const nameInput = view.querySelector('input[name="categoryName"]');
        nameInput.value = ' Characters ';
        await submitForm(categoryForm(view));

        const post = fetchMock.calls.find((call) => call.url === '/api/tag-categories' && call.options.method === 'POST');
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({name: 'Characters'}));
        assertEqual(nameInput.value, '');
        assertTrue([...view.querySelectorAll('select[name="category"] option')].some((option) => option.value === 'Characters'));
        assertEqual(view.querySelector('select[name="category"]').value, 'Characters');
        assertEqual(categoryForm(view).querySelector('.form-message').textContent, 'Category "Characters" created.');
    } finally {
        restore();
    }
}

export async function testCreateCategoryConflictUsesCategoryMessage() {
    const {view, restore} = await createLoadedView((url, options) => (
        url === '/api/tag-categories' && options.method === 'POST'
            ? {status: 409, body: null}
            : undefined
    ));
    try {
        view.querySelector('input[name="categoryName"]').value = 'Artist';
        await submitForm(categoryForm(view));
        assertEqual(
            categoryForm(view).querySelector('.form-message').textContent,
            'A category with that name already exists.',
        );
    } finally {
        restore();
    }
}

export async function testDeleteActionsAreOnlyVisibleToAdmins() {
    const moderator = await createLoadedView();
    try {
        assertEqual(moderator.view.querySelectorAll('.tags-table thead th').length, 3);
        assertTrue(
            moderator.view.querySelector('.tag-delete-button') === null,
            'moderators must not get delete buttons',
        );
    } finally {
        moderator.restore();
    }

    const admin = await createLoadedView(undefined, {accessLevel: 600});
    try {
        assertEqual(
            [...admin.view.querySelectorAll('.tags-table thead th')].map((cell) => cell.textContent).join('|'),
            'Tag name|Category|Usage count|Actions',
        );
        assertEqual(admin.view.querySelectorAll('.tag-delete-button').length, 2);
    } finally {
        admin.restore();
    }
}

export async function testAdminDeleteAsksForConfirmationThenUnassignsAndReloads() {
    let deleted = false;
    const {view, fetchMock, restore} = await createLoadedView((url, options) => {
        const parsed = new URL(url, 'http://localhost');
        if (parsed.pathname === '/api/tags/delete' && options.method === 'POST') {
            deleted = true;
            return {status: 204, body: null};
        }
        if (parsed.pathname === '/api/tags' && (options.method ?? 'GET') === 'GET') {
            const tags = deleted ? TAGS.filter((tag) => tag.name !== 'unused tag') : TAGS;
            return {status: 200, body: {tags, page: 1, perPage: 100, total: tags.length}};
        }
        return undefined;
    }, {accessLevel: 600});
    try {
        const deleteCalls = () => fetchMock.calls.filter(
            (call) => call.options.method === 'POST' && call.url === '/api/tags/delete',
        );

        const button = view.querySelector('.tags-table-row[data-tag-name="unused tag"] .tag-delete-button');
        button.click();
        await settle();
        let dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'deleting a tag should open the confirmation dialog');
        assertTrue(dialog.querySelector('.modal-message').textContent.includes('unused tag'));
        assertTrue(
            dialog.querySelector('.modal-message').textContent.includes('images'),
            'the dialog should warn that tagged images lose the tag',
        );
        assertEqual(dialog.querySelector('.button--primary')?.textContent, 'Delete tag');

        dialog.querySelector('.modal-cancel').click();
        await settle();
        assertTrue(!dialog.isConnected, 'cancelling should close the dialog');
        assertEqual(deleteCalls().length, 0, 'cancelling must not send a delete request');
        assertTrue(view.querySelector('.tags-table-row[data-tag-name="unused tag"]') !== null);

        button.click();
        await settle();
        dialog = document.querySelector('.modal-backdrop');
        dialog.querySelector('.button--primary').click();
        await settle();

        assertEqual(deleteCalls().length, 1, 'confirming should send one delete request');
        assertEqual(JSON.stringify(JSON.parse(deleteCalls()[0].options.body)), JSON.stringify({name: 'unused tag'}));
        assertTrue(
            view.querySelector('.tags-table-row[data-tag-name="unused tag"]') === null,
            'the deleted tag should disappear from the table',
        );
        const message = view.querySelector('.tags-browser .form-message:not([hidden])');
        assertTrue(message !== null);
        assertTrue(message.textContent.includes('unused tag'));
    } finally {
        document.querySelectorAll('.modal-backdrop .modal-cancel').forEach((cancel) => cancel.click());
        restore();
    }
}

export async function testAdminDeleteShowsProtectedAndMissingTagMessages() {
    let calls = 0;
    const {view, restore} = await createLoadedView((url, options) => {
        if (url === '/api/tags/delete' && options.method === 'POST') {
            calls++;
            return {status: calls === 1 ? 409 : 404, body: null};
        }
        return undefined;
    }, {accessLevel: 600});
    try {
        const confirmDeletion = async () => {
            view.querySelector('.tag-delete-button').click();
            await settle();
            document.querySelector('.modal-backdrop .button--primary').click();
            await settle();
        };

        await confirmDeletion();
        assertEqual(
            view.querySelector('.tags-browser .form-message:not([hidden])').textContent,
            'Protected tags such as "tagme" cannot be deleted.',
        );

        await confirmDeletion();
        assertEqual(
            view.querySelector('.tags-browser .form-message:not([hidden])').textContent,
            'This item no longer exists.',
        );
    } finally {
        document.querySelectorAll('.modal-backdrop .modal-cancel').forEach((cancel) => cancel.click());
        restore();
    }
}
