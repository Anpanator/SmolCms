import {assertEqual, assertFalse, assertTrue, settle} from './assert.js';
import {mockFetch, testUser, useFetch} from './mocks.js';
import {createImageView, formatFileSize} from '../../public/js/views/image.js';

const DETAIL = {
    id: 7,
    status: 'accepted',
    uploadDate: '2026-10-19 10:30:00',
    uploader: 'Upload User',
    width: 100,
    height: 50,
    format: 'jpeg',
    fileSize: 1234,
    sampleSrc: '/api/image/sample?name=ab89c0d0ef.webp',
    downloadSrc: '/api/image/download?name=ab89c0d0ef.jpeg',
    tags: ['nature', 'sky'],
};

function detailResponse(overrides = {}) {
    return { status: 200, body: { image: { ...DETAIL, ...overrides } } };
}

async function createView(handler, query = 'id=7', session = {getUser: () => null}) {
    const fetchMock = mockFetch(handler);
    const restore = useFetch(fetchMock);
    const navigateCalls = [];
    const navigateOptions = [];
    const view = createImageView({
        session,
        navigate: (path, options = {}) => {
            navigateCalls.push(path);
            navigateOptions.push(options);
        },
        query: new URLSearchParams(query),
    });
    await settle();
    return { view, fetchMock, navigateCalls, navigateOptions, restore };
}

function pressKey(target, key, options = {}) {
    const event = new KeyboardEvent('keydown', {
        key,
        bubbles: true,
        cancelable: true,
        ...options,
    });
    target.dispatchEvent(event);
    return event;
}

function metaValue(view, term) {
    const item = [...view.querySelectorAll('.image-meta .image-meta-item')]
        .find((node) => node.querySelector('dt')?.textContent === term);
    return item?.querySelector('dd')?.textContent ?? null;
}

export async function testImageViewRendersSampleImageInLayout() {
    const { view, fetchMock, restore } = await createView(() => detailResponse());
    try {
        assertTrue(view.matches('.layout.image-layout'), 'image view should use the .layout grid');
        const toolbar = view.querySelector('.image-toolbar');
        assertTrue(toolbar !== null, 'image view should have a toolbar above its content');
        assertTrue(toolbar.querySelector('.search-view') !== null, 'tag search should live in the image toolbar');
        assertEqual(toolbar.querySelector('.image-nav'), null, 'previous/next controls should not share the toolbar');
        assertTrue(view.querySelector('.image-sidebar-column .image-toolbar') === toolbar,
            'the search toolbar should share the sidebar column');
        assertTrue(view.querySelector('.image-sidebar-column .sidebar') !== null,
            'the sidebar should sit in the same column as the search');
        assertTrue(view.querySelector('.sidebar .image-nav') !== null, 'previous/next controls should live in the sidebar');
        assertEqual(view.querySelector('.sidebar .search-view'), null, 'the image sidebar should not contain tag search');
        assertTrue(view.querySelector('.sidebar .tags') !== null, 'sidebar tag list should exist');
        assertTrue(view.querySelector('.quick-tags-sidebar')?.hidden, 'quick tags should be hidden by default');
        assertEqual(view.querySelector('.quick-tags-toggle'), null, 'anonymous users cannot enable quick tags');
        assertEqual(view.querySelector('.tag-add-row'), null, 'anonymous users should not see tag controls');
        assertEqual(view.querySelector('.tag-remove-button'), null, 'anonymous users should not see remove controls');
        assertEqual(view.querySelectorAll('.sidebar .tags-item').length, 2, 'detail tags should replace static tags');
        assertEqual(view.querySelector('.tags-link[data-tag-name="nature"]')?.textContent, 'nature');
        assertEqual(view.querySelector('.tags-link[data-tag-name="sky"]')?.textContent, 'sky');
        assertTrue(view.querySelector('.sidebar .image-meta') !== null, 'metadata section should live in the sidebar');
        assertTrue(view.querySelector('.content-title') === null, 'the image view should not have a title header');
        assertTrue(view.querySelector('.image-meta-section .sidebar-title') === null, 'image details should not have a title header');
        assertTrue(view.querySelector('.image-meta-separator') !== null, 'image details should be separated by a horizontal rule');

        assertEqual(fetchMock.calls.length, 1);
        assertEqual(fetchMock.calls[0].url, '/api/image/detail?id=7');
        assertEqual(fetchMock.calls[0].options.method, 'GET');

        const image = view.querySelector('.content img.image-view-image');
        assertTrue(image !== null, 'the viewing image should be rendered');
        assertEqual(image.getAttribute('src'), '/api/image/sample?name=ab89c0d0ef.webp', 'the sample variant must be used');
    } finally {
        restore();
    }
}

export async function testImageViewShowsMetadataAndDisabledDownloadForAnonymousUser() {
    const { view, restore } = await createView(() => detailResponse());
    try {
        assertEqual(metaValue(view, 'Uploaded'), '2026-10-19 10:30:00');
        assertEqual(metaValue(view, 'Uploader'), 'Upload User');
        assertEqual(metaValue(view, 'Dimensions'), '100 × 50');
        assertEqual(metaValue(view, 'Format'), 'jpeg');
        assertEqual(metaValue(view, 'File size'), '1.2 KB');

        const download = view.querySelector('.sidebar button.image-download');
        assertTrue(download !== null, 'disabled download control should exist in the sidebar');
        assertTrue(download.disabled, 'anonymous users should not be able to download');
        assertEqual(download.getAttribute('href'), null, 'disabled download control should have no href');
        assertTrue(!download.hasAttribute('download'), 'disabled download control should have no download action');
        assertEqual(download.textContent, 'Download');
        assertEqual(
            view.querySelector('.image-download-disabled-wrapper')?.getAttribute('title'),
            'Log in to download images',
        );
    } finally {
        restore();
    }
}

export async function testImageViewShowsDownloadLinkForLoggedInUser() {
    const { view, restore } = await createView(
        () => detailResponse(),
        'id=7',
        {getUser: () => testUser()},
    );
    try {
        const download = view.querySelector('.sidebar a.image-download');
        assertTrue(download !== null, 'logged-in users should have a working download link');
        assertEqual(download.getAttribute('href'), '/api/image/download?name=ab89c0d0ef.jpeg');
        assertTrue(download.hasAttribute('download'), 'download link should carry the download attribute');
        assertEqual(download.textContent, 'Download');
        assertEqual(view.querySelector('.quick-tags-toggle')?.textContent, 'Show quick tags');
        assertTrue(view.querySelector('.quick-tags-sidebar').hidden, 'logged-in users start with quick tags off');
    } finally {
        restore();
    }
}

export async function testQuickTagsToggleLoadsAndHidesTheRightSidebarWithoutRefetchingOnDisable() {
    let quickTagsEnabled = false;
    const session = {
        getUser: () => testUser(),
        getQuickTagsEnabled: () => quickTagsEnabled,
        setQuickTagsEnabled: (enabled) => { quickTagsEnabled = enabled; },
    };
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/tags/quick') {
            return {status: 200, body: {
                global: [{name: 'nature', category: 'General'}, {name: 'clouds', category: 'General'}],
                userMostUsed: [],
                userRecent: [],
            }};
        }
        if (url === '/api/session/quick-tags' && options.method === 'POST') {
            return {status: 200, body: {quickTagsEnabled: JSON.parse(options.body).enabled}};
        }
        return {status: 200, body: null};
    }, 'id=7', session);
    try {
        const toggle = view.querySelector('.quick-tags-toggle');
        const quickTags = view.querySelector('.quick-tags-sidebar');
        assertTrue(toggle !== null);
        assertTrue(quickTags.hidden);
        assertFalse(view.classList.contains('layout--with-quick-tags'));
        assertTrue(
            (view.querySelector('.tag-editor').compareDocumentPosition(toggle) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
            'the toggle should follow the tag list in the left sidebar',
        );
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tags/quick').length, 0);

        toggle.click();
        await settle();
        assertFalse(quickTags.hidden);
        assertTrue(view.classList.contains('layout--with-quick-tags'));
        assertEqual(session.getQuickTagsEnabled(), true);
        assertEqual(toggle.textContent, 'Hide quick tags');
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tags/quick').length, 1);
        assertEqual(quickTags.querySelector('.quick-tag-button[data-tag-name="nature"]')?.getAttribute('aria-pressed'), 'true');

        toggle.click();
        await settle();
        assertTrue(quickTags.hidden);
        assertFalse(view.classList.contains('layout--with-quick-tags'));
        assertEqual(session.getQuickTagsEnabled(), false);
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tags/quick').length, 1,
            'hiding the view should not refetch its lists');
    } finally {
        restore();
    }
}

export async function testImageViewHotkeysKeepWorkingAfterQuickTagsAreShown(fixture) {
    let quickTagsEnabled = false;
    let resolveSetting;
    const session = {
        getUser: () => testUser(),
        getQuickTagsEnabled: () => quickTagsEnabled,
        setQuickTagsEnabled: (enabled) => { quickTagsEnabled = enabled; },
    };
    const {view, fetchMock, navigateCalls, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/tags/quick') {
            return {status: 200, body: {
                global: [{name: 'nature', category: 'General'}],
                userMostUsed: [],
                userRecent: [],
            }};
        }
        if (url === '/api/session/quick-tags' && options.method === 'POST') {
            return new Promise((resolve) => { resolveSetting = resolve; });
        }
        if (url === '/api/image/neighbor?id=7&direction=next') {
            return {status: 200, body: {id: 12}};
        }
        return {status: 200, body: null};
    }, 'id=7', session);
    fixture.append(view);
    try {
        const toggle = view.querySelector('.quick-tags-toggle');
        toggle.focus();
        toggle.click();
        assertEqual(document.activeElement, toggle, 'the toggle should keep focus while pending');

        resolveSetting({status: 200, body: {quickTagsEnabled: true}});
        await settle();
        assertFalse(view.querySelector('.quick-tags-sidebar').hidden);
        assertEqual(document.activeElement, toggle, 'showing quick tags must not drop page focus');
        assertFalse(toggle.disabled, 'the toggle should remain focusable after loading');

        assertTrue(pressKey(toggle, 'ArrowLeft').defaultPrevented, 'hotkeys should still reach the image view');
        await settle();
        assertTrue(
            fetchMock.calls.some((call) => call.url === '/api/image/neighbor?id=7&direction=next'),
            'ArrowLeft should still request the next image',
        );
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=12']));
    } finally {
        resolveSetting?.({status: 200, body: {quickTagsEnabled: false}});
        view.remove();
        restore();
    }
}

export async function testImageViewShowsPendingApprovalWarningAboveUploadDate() {
    const {view, restore} = await createView(() => detailResponse({status: 'pending_review'}));
    try {
        const uploaded = [...view.querySelectorAll('.image-meta-item')]
            .find((node) => node.querySelector('dt')?.textContent === 'Uploaded');
        const warning = view.querySelector('.image-pending-warning');
        assertTrue(uploaded !== undefined, 'uploaded date should be displayed');
        assertTrue(warning !== null && !warning.hidden, 'pending-review images should show the warning');
        assertEqual(warning.textContent, 'This image is pending approval.');
        assertTrue(
            (warning.compareDocumentPosition(uploaded) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
            'warning should precede the upload date',
        );
    } finally {
        restore();
    }
}

export async function testImageTagSearchNavigatesToHomeFilter() {
    const { view, navigateCalls, restore } = await createView(() => detailResponse());
    try {
        const input = view.querySelector('.search-input');
        input.value = 'cats, dogs';
        view.querySelector('.search-view').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        assertEqual(navigateCalls[0], '/?tags=cats%2Cdogs');
    } finally {
        restore();
    }
}

export async function testImageViewHandlesProcessingPlaceholders() {
    const { view, restore } = await createView(() => detailResponse({
        width: null,
        height: null,
        format: null,
        fileSize: null,
        sampleSrc: null,
        downloadSrc: null,
    }));
    try {
        const processing = view.querySelector('.content .image-processing');
        assertTrue(processing !== null, 'a null sampleSrc should show the processing notice');
        assertTrue(processing.textContent.includes('still being processed'));
        assertTrue(view.querySelector('img.image-view-image') === null, 'no image should be rendered while processing');
        assertTrue(view.querySelector('a.image-download') === null, 'download link should be hidden while processing');
        assertEqual(metaValue(view, 'Dimensions'), '—');
        assertEqual(metaValue(view, 'Format'), '—');
        assertEqual(metaValue(view, 'File size'), '—');
    } finally {
        restore();
    }
}

export async function testImageViewUnknownIdShowsNotFound() {
    const { view, navigateCalls, restore } = await createView(() => ({ status: 404, body: null }));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '404 should show a message');
        assertTrue(message.textContent.includes('Image not found'), '404 should show the not-found message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), '404 should not navigate');
    } finally {
        restore();
    }
}

export async function testImageViewShowsForbiddenMessageFor401And403() {
    for (const statusCode of [401, 403]) {
        const {view, navigateCalls, restore} = await createView(() => ({status: statusCode, body: null}));
        try {
            const message = view.querySelector('.form-message');
            assertTrue(message !== null && !message.hidden, `${statusCode} should show a forbidden message`);
            assertEqual(message.textContent, 'You do not have permission to view this image.');
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), `${statusCode} should not redirect to login`);
        } finally {
            restore();
        }
    }
}

export async function testImageViewServerErrorShowsMessage() {
    const { view, navigateCalls, restore } = await createView(() => ({ status: 500, body: null }));
    try {
        const message = view.querySelector('.form-message');
        assertTrue(message !== null && !message.hidden, '500 should show a message');
        assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));
    } finally {
        restore();
    }
}

export async function testImageViewWithoutValidIdDoesNotFetch() {
    for (const query of ['', 'id=abc', 'id=0', 'id=-1']) {
        const { view, fetchMock, restore } = await createView(
            () => detailResponse(),
            query,
        );
        try {
            assertEqual(fetchMock.calls.length, 0, `a malformed query (${query || 'empty'}) must not fetch`);
            const message = view.querySelector('.form-message');
            assertTrue(message !== null && !message.hidden, 'a malformed id should show a message');
            assertTrue(message.textContent.includes('Image not found'));
            assertTrue(view.querySelector('img.image-view-image') === null);
        } finally {
            restore();
        }
    }
}

export function testFormatFileSizeFormatsBytesHumanReadable() {
    assertEqual(formatFileSize(null), '—');
    assertEqual(formatFileSize(undefined), '—');
    assertEqual(formatFileSize(-1), '—');
    assertEqual(formatFileSize(0), '0 B');
    assertEqual(formatFileSize(512), '512 B');
    assertEqual(formatFileSize(1024), '1.0 KB');
    assertEqual(formatFileSize(1234), '1.2 KB');
    assertEqual(formatFileSize(1048576), '1.0 MB');
    assertEqual(formatFileSize(5 * 1024 * 1024), '5.0 MB');
}

export async function testImageTagAddRequiresButtonAndMergesAppliedTags() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: ['bird', 'nature'], unknown: []}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        const input = view.querySelector('.tag-add-input');
        const addButton = view.querySelector('.tag-add-submit');
        assertTrue(input !== null, 'logged-in users should see the tag input');
        assertEqual(addButton.textContent, 'Add');
        assertEqual(view.querySelector('.sidebar .search-view'), null, 'tag search should stay outside the sidebar');
        assertTrue(view.querySelector('.sidebar .tag-add-row') !== null, 'tag editing controls should remain in the sidebar');

        addButton.click();
        input.value = 'bird, nature';
        input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true}));
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags').length, 0, 'Enter must not submit');

        addButton.click();
        await settle();
        const post = fetchMock.calls.find((call) => call.url === '/api/image/tags');
        assertEqual(post.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({imageId: 7, tags: 'bird, nature'}));
        assertEqual([...view.querySelectorAll('.tags-link')].map((link) => link.textContent).join('|'), 'bird|nature|sky');
        assertEqual(input.value, '', 'successful submission should clear the input');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.classList.contains('form-message--success'));
        assertTrue(message.textContent.includes('bird'));
    } finally {
        restore();
    }
}

export async function testImageTagAddIgnoresEmptyInput() {
    const {view, fetchMock, restore} = await createView(
        () => detailResponse(),
        'id=7',
        {getUser: () => testUser()},
    );
    try {
        view.querySelector('.tag-add-submit').click();
        view.querySelector('.tag-add-input').dispatchEvent(
            new KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true}),
        );
        await settle();
        assertEqual(fetchMock.calls.length, 1, 'only the initial detail request should run for empty input');
    } finally {
        restore();
    }
}

export async function testFellowCanSuggestUnknownImageTagsFromConfirmation() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: ['nature'], unknown: ['new-a', 'new-b']}};
        }
        if (url === '/api/tag-suggestions' && options.method === 'POST') {
            return {status: 201, body: {suggested: ['new-a', 'new-b']}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser({accessLevel: 200})});
    try {
        view.querySelector('.tag-add-input').value = 'nature, new-a, new-b';
        view.querySelector('.tag-add-submit').click();
        await settle();

        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'a fellow should be asked about unknown tags');
        assertTrue(dialog.textContent.includes('Unknown tags: new-a, new-b. Suggest them as new tags?'));
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/tag-suggestions').length, 0);
        dialog.querySelector('.button--primary').click();
        await settle();

        const suggest = fetchMock.calls.find((call) => call.url === '/api/tag-suggestions');
        assertEqual(suggest.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(suggest.options.body)), JSON.stringify({imageId: 7, tags: 'new-a, new-b'}));
        assertEqual(view.querySelector('.modal-backdrop'), null);
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.textContent.includes('submitted for review'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testNoviceSeesUnknownTagsWereNotAddedWithoutDialog() {
    const {view, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/tags' && options.method === 'POST') {
            return {status: 200, body: {applied: [], unknown: ['not-known']}};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser({accessLevel: 100})});
    try {
        view.querySelector('.tag-add-input').value = 'not-known';
        view.querySelector('.tag-add-submit').click();
        await settle();

        assertEqual(document.querySelector('.modal-backdrop'), null, 'below FELLOW should not see a suggestion dialog');
        const message = view.querySelector('.form-message:not([hidden])');
        assertEqual(message.textContent, 'Unknown tags were not added: not-known');
        assertTrue(message.classList.contains('form-message--info'));
    } finally {
        restore();
    }
}

export async function testRemovingTagRequiresConfirmationAndExplainsTagmeConflict() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse({tags: ['nature', 'tagme']});
        }
        if (url === '/api/image/tags/remove' && options.method === 'POST') {
            return {status: 409, body: null};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        view.querySelector('.tag-remove-button[data-tag-name="tagme"]').click();
        const dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'removing a tag should open the shared confirmation dialog');
        assertEqual(dialog.querySelector('.modal-message')?.textContent, 'Remove the tag "tagme" from this image?');
        dialog.querySelector('.button--primary').click();
        await settle();

        const post = fetchMock.calls.find((call) => call.url === '/api/image/tags/remove');
        assertEqual(post.options.method, 'POST');
        assertEqual(JSON.stringify(JSON.parse(post.options.body)), JSON.stringify({imageId: 7, tag: 'tagme'}));
        assertTrue(view.querySelector('.tags-link[data-tag-name="tagme"]') !== null, 'a conflict must keep the tag displayed');
        assertTrue(view.querySelector('.form-message').textContent.includes('at least 10 other tags'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testRemovingTagCanBeCancelledOrApplied() {
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse({tags: ['nature', 'sky']});
        }
        return url === '/api/image/tags/remove' && options.method === 'POST'
            ? {status: 204, body: null}
            : {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        const removeButton = view.querySelector('.tag-remove-button[data-tag-name="nature"]');
        removeButton.click();
        let dialog = document.querySelector('.modal-backdrop');
        assertTrue(dialog !== null, 'removing a tag should use the shared confirmation dialog');
        dialog.querySelector('.modal-cancel').click();
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags/remove').length, 0, 'cancel should not make a request');

        removeButton.click();
        dialog = document.querySelector('.modal-backdrop');
        dialog.querySelector('.button--primary').click();
        await settle();
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/tags/remove').length, 1);
        assertEqual(view.querySelector('.tags-link[data-tag-name="nature"]'), null, '204 should remove the tag');
        assertTrue(view.querySelector('.form-message').textContent.includes('Removed tag'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

export async function testRemovingMissingTagRefreshesImageDetails() {
    let detailRequests = 0;
    const {view, fetchMock, restore} = await createView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            detailRequests++;
            return detailResponse();
        }
        if (url === '/api/image/tags/remove' && options.method === 'POST') {
            return {status: 404, body: null};
        }
        return {status: 200, body: null};
    }, 'id=7', {getUser: () => testUser()});
    try {
        view.querySelector('.tag-remove-button[data-tag-name="nature"]').click();
        document.querySelector('.modal-backdrop .button--primary').click();
        await settle();

        assertEqual(detailRequests, 2, 'a missing image/attachment should refresh image detail');
        assertTrue(view.querySelector('.form-message').textContent.includes('attachment was not found'));
    } finally {
        document.querySelector('.modal-backdrop .modal-cancel')?.click();
        restore();
    }
}

const MOD_SESSION = {getUser: () => testUser({accessLevel: 500})};

function modView(handler, query = 'id=7') {
    return createView(handler, query, MOD_SESSION);
}

export async function testImageViewModActionsOnlyVisibleFromModLevel() {
    for (const [label, session] of [
        ['anonymous', {getUser: () => null}],
        ['novice', {getUser: () => testUser()}],
        ['fellow', {getUser: () => testUser({accessLevel: 200})}],
    ]) {
        const {view, restore} = await createView(() => detailResponse(), 'id=7', session);
        try {
            assertEqual(view.querySelector('.mod-actions'), null, `${label} users should not see the mod actions section`);
        } finally {
            restore();
        }
    }
}

export async function testImageViewShowsModActionsSectionWithDeleteForMods() {
    const {view, fetchMock, restore} = await modView(() => detailResponse());
    try {
        const sidebar = view.querySelector('.sidebar');
        const section = sidebar.querySelector('.mod-actions');
        assertTrue(section !== null, 'mods should see the mod actions section');
        assertEqual(section.querySelector('.sidebar-title')?.textContent, 'Mod Actions');
        assertTrue(
            (view.querySelector('.image-meta-section').compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
            'the mod actions section should follow the metadata section',
        );

        const deleteButton = section.querySelector('.mod-action-delete');
        assertTrue(deleteButton !== null, 'the mod actions section should contain a delete button');
        assertEqual(deleteButton.textContent, 'Delete image');
        assertFalse(deleteButton.disabled, 'a loaded image should enable the delete button');
        assertTrue(deleteButton.matches('.button--danger'), 'the delete button should use the danger style');
        assertEqual(fetchMock.calls.length, 1, 'the mod actions section should not trigger extra requests');
    } finally {
        restore();
    }
}

export async function testImageViewDeleteSendsRequestImmediatelyWithoutConfirmation() {
    const {view, fetchMock, navigateCalls, navigateOptions, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: null}};
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 204, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        const deleteButton = view.querySelector('.mod-action-delete');
        deleteButton.click();
        await settle();

        assertEqual(document.querySelector('.modal-backdrop'), null,
            'deleting an image must not ask for confirmation anymore');
        const post = fetchMock.calls.find((call) => call.url === '/api/image/delete?id=7');
        assertTrue(post !== undefined, 'deleting should send the delete request immediately');
        assertEqual(post.options.method, 'POST');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/']), 'no previous neighbor should fall back to home');
        assertEqual(JSON.stringify(navigateOptions), JSON.stringify([{replace: true}]),
            'the deleted image should not stay in browser history');
    } finally {
        restore();
    }
}

export async function testImageViewDeleteNavigatesToPreviousNeighborWithReplace() {
    const {view, fetchMock, navigateCalls, navigateOptions, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: 3}};
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 204, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.mod-action-delete').click();
        await settle();

        const neighborIndex = fetchMock.calls.findIndex(
            (call) => call.url === '/api/image/neighbor?id=7&direction=previous',
        );
        const deleteIndex = fetchMock.calls.findIndex((call) => call.url === '/api/image/delete?id=7');
        assertTrue(neighborIndex >= 0, 'the previous neighbor should be resolved before deleting');
        assertTrue(neighborIndex < deleteIndex, 'the neighbor lookup must happen before the delete request');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=3']),
            'deletion should stay in the image view on the previous neighbor');
        assertEqual(JSON.stringify(navigateOptions), JSON.stringify([{replace: true}]),
            'the deleted image should not stay in browser history');
    } finally {
        restore();
    }
}

export async function testImageViewDeleteFallsBackHomeWhenPreviousNeighborLookupFails() {
    const {view, fetchMock, navigateCalls, navigateOptions, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 500, body: null};
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 204, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.mod-action-delete').click();
        await settle();

        assertTrue(fetchMock.calls.some((call) => call.url === '/api/image/delete?id=7'),
            'a failed neighbor lookup must still attempt the deletion');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/']),
            'a failed neighbor lookup should fall back to home');
        assertEqual(JSON.stringify(navigateOptions), JSON.stringify([{replace: true}]));
    } finally {
        restore();
    }
}

export async function testImageViewDeleteFailureShowsMessageAndStays() {
    const {view, fetchMock, navigateCalls, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: 3}};
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 500, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.mod-action-delete').click();
        await settle();

        assertTrue(fetchMock.calls.some((call) => call.url === '/api/image/neighbor?id=7&direction=previous'),
            'the previous neighbor should be resolved before the failing delete');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message !== null, 'a failed deletion should show a message');
        assertTrue(message.textContent.includes('went wrong'), 'a server error should map to a generic message');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'a failed deletion should not navigate');
        assertFalse(view.querySelector('.mod-action-delete').disabled, 'a failed deletion should re-enable the button');
    } finally {
        restore();
    }
}

export async function testImageViewDeleteHotkeySendsRequestImmediatelyWithoutConfirmation() {
    const {view, fetchMock, navigateCalls, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: 3}};
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 204, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        assertTrue(pressKey(view, 'Delete').defaultPrevented, 'Delete should trigger the delete action');
        await settle();

        assertEqual(document.querySelector('.modal-backdrop'), null,
            'the Delete hotkey must not open a confirmation dialog');
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/image/delete?id=7'),
            'Delete should trigger the delete request immediately');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=3']));
    } finally {
        restore();
    }
}

export async function testImageViewControlsAreDisabledWithoutALoadedImage() {
    for (const statusCode of [404, 403]) {
        const {view, restore} = await modView(() => ({status: statusCode, body: null}));
        try {
            const deleteButton = view.querySelector('.mod-action-delete');
            assertTrue(deleteButton !== null, 'mod controls should stay visible without an image');
            assertTrue(deleteButton.disabled, 'deletion needs a loaded image');
            assertEqual(deleteButton.getAttribute('aria-disabled'), 'true', 'unavailable deletion should be aria-disabled');
            deleteButton.click();
            await settle();
            assertEqual(document.querySelector('.modal-backdrop'), null, 'a disabled delete button must not start a delete');

            const previousButton = view.querySelector('.image-nav-previous');
            const nextButton = view.querySelector('.image-nav-next');
            assertTrue(previousButton !== null && nextButton !== null, 'navigation should exist without an image');
            assertTrue(previousButton.disabled && nextButton.disabled, 'navigation needs a loaded image');
        } finally {
            restore();
        }
    }
}

export async function testImageViewSidebarPlacesNavigationBeforeTheLoadedImage() {
    const {view, restore} = await createView(() => detailResponse());
    try {
        const sidebar = view.querySelector('.sidebar');
        const nav = sidebar?.querySelector('.image-nav');
        const image = view.querySelector('.content img.image-view-image');
        assertTrue(sidebar !== null, 'the image sidebar should exist');
        assertTrue(nav !== null, 'image navigation should live in the sidebar');
        assertTrue(view.querySelector('.image-toolbar .image-nav') === null,
            'navigation should be moved out of the toolbar');
        assertTrue(
            (nav.compareDocumentPosition(image) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
            'previous/next controls should precede the image',
        );

        const buttons = [...nav.querySelectorAll('.button')];
        assertEqual(buttons.length, 2, 'navigation should have two controls');
        assertTrue(buttons[0].classList.contains('image-nav-next'), 'next should come first');
        assertTrue(buttons[1].classList.contains('image-nav-previous'), 'previous should come second');
        assertEqual(buttons[0].textContent, 'Next');
        assertEqual(buttons[1].textContent, 'Prev', 'the previous label should be abbreviated');
        assertFalse(view.querySelector('.image-nav-next').disabled, 'next should be available for a loaded image');
        assertFalse(view.querySelector('.image-nav-previous').disabled, 'previous should be available for a loaded image');
    } finally {
        restore();
    }
}

export async function testImageViewNextButtonNavigatesToNextVisibleImage() {
    const {view, fetchMock, navigateCalls, restore} = await createView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=next') {
            return {status: 200, body: {id: 12}};
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.image-nav-next').click();
        await settle();

        const call = fetchMock.calls.find((entry) => entry.url === '/api/image/neighbor?id=7&direction=next');
        assertTrue(call !== undefined, 'next should ask the server for the adjacent image');
        assertEqual(call.options.method, 'GET');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=12']), 'next should update the view');
    } finally {
        restore();
    }
}

export async function testImageViewPreviousButtonWithoutNeighborShowsInfo() {
    const {view, fetchMock, navigateCalls, restore} = await createView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: null}};
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.image-nav-previous').click();
        await settle();

        const call = fetchMock.calls.find((entry) => entry.url === '/api/image/neighbor?id=7&direction=previous');
        assertTrue(call !== undefined, 'previous should ask the server for the adjacent image');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'a missing neighbor should not navigate');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.classList.contains('form-message--info'), 'a boundary should be informational');
        assertEqual(message.textContent, 'There is no previous image.');
        assertFalse(view.querySelector('.image-nav-previous').disabled, 'buttons should be usable again');
    } finally {
        restore();
    }
}

export async function testImageViewNeighborFailureShowsMessage() {
    const {view, fetchMock, navigateCalls, restore} = await createView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        return url === '/api/image/neighbor?id=7&direction=next'
            ? {status: 500, body: null}
            : {status: 200, body: null};
    });
    try {
        view.querySelector('.image-nav-next').click();
        await settle();

        assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor')).length, 1);
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]), 'a failed lookup should not navigate');
        const message = view.querySelector('.form-message:not([hidden])');
        assertTrue(message.textContent.includes('went wrong'), 'a server error should map to a generic message');
        assertFalse(view.querySelector('.image-nav-next').disabled, 'buttons should be usable again');
    } finally {
        restore();
    }
}

export async function testImageViewNavigationLocksWhileNeighbourRequestIsPending() {
    let resolveNeighbour;
    const {view, navigateCalls, restore} = await createView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=next') {
            return new Promise((resolve) => {
                resolveNeighbour = resolve;
            });
        }
        return {status: 200, body: null};
    });
    try {
        view.querySelector('.image-nav-next').click();
        assertTrue(view.querySelector('.image-nav-next').disabled, 'the pending lookup should disable next');
        assertTrue(view.querySelector('.image-nav-previous').disabled, 'the pending lookup should disable previous');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify([]));

        resolveNeighbour({status: 200, body: {id: 12}});
        await settle();
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=12']));
    } finally {
        resolveNeighbour?.({status: 200, body: {id: null}});
        restore();
    }
}

export async function testImageViewArrowKeysNavigateToPreviousAndNextImages() {
    const {view, fetchMock, navigateCalls, restore} = await createView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=previous') {
            return {status: 200, body: {id: 3}};
        }
        if (url === '/api/image/neighbor?id=7&direction=next') {
            return {status: 200, body: {id: 12}};
        }
        return {status: 200, body: null};
    });
    try {
        assertTrue(pressKey(view, 'ArrowRight', {repeat: true}).defaultPrevented, 'repeated arrows should still suppress scrolling');
        assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor')).length, 0,
            'a repeated arrow should not start navigation');
        assertTrue(pressKey(view, 'ArrowRight').defaultPrevented, 'right should be handled as previous');
        await settle();
        assertTrue(pressKey(view, 'ArrowLeft').defaultPrevented, 'left should be handled as next');
        await settle();

        assertEqual(
            JSON.stringify(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor')).map((call) => call.url)),
            JSON.stringify([
                '/api/image/neighbor?id=7&direction=previous',
                '/api/image/neighbor?id=7&direction=next',
            ]),
        );
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=3', '/image?id=12']));
    } finally {
        restore();
    }
}

export async function testImageViewHotkeysIgnoreInputsContentEditableAndModifiers() {
    const {view, fetchMock, restore} = await createView((url) => (
        url.startsWith('/api/image/neighbor') ? {status: 200, body: {id: 12}} : detailResponse()
    ));
    try {
        const inputEvent = pressKey(view.querySelector('.search-input'), 'ArrowRight');
        assertFalse(inputEvent.defaultPrevented, 'arrow keys in tag search should keep their normal input behavior');
        assertFalse(pressKey(view, 'ArrowUp').defaultPrevented, 'up should remain available for page scrolling');
        assertFalse(pressKey(view, 'ArrowDown').defaultPrevented, 'down should remain available for page scrolling');
        pressKey(view, 'ArrowLeft', {ctrlKey: true});
        pressKey(view, 'ArrowRight', {shiftKey: true});

        const editable = document.createElement('div');
        editable.contentEditable = 'true';
        view.append(editable);
        pressKey(editable, 'ArrowRight');

        assertEqual(
            fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor')).length,
            0,
            'typing targets and modified keys should not navigate',
        );
    } finally {
        restore();
    }
}

export async function testImageViewDeleteHotkeyIgnoresRepeatsAndSendsOneRequest() {
    const {view, fetchMock, navigateCalls, navigateOptions, restore} = await modView((url, options) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/delete?id=7' && options.method === 'POST') {
            return {status: 204, body: null};
        }
        return {status: 200, body: null};
    });
    try {
        assertTrue(pressKey(view, 'Delete', {repeat: true}).defaultPrevented, 'repeated Delete should still be handled');
        assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/delete')).length, 0,
            'a repeated Delete event must not start a deletion');

        assertTrue(pressKey(view, 'Delete').defaultPrevented, 'Delete should trigger the delete action');
        await settle();
        assertTrue(fetchMock.calls.some((call) => call.url === '/api/image/delete?id=7'));
        assertEqual(fetchMock.calls.filter((call) => call.url === '/api/image/delete?id=7').length, 1);
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/']));
        assertEqual(JSON.stringify(navigateOptions), JSON.stringify([{replace: true}]),
            'the delete hotkey should also replace the deleted image');
    } finally {
        restore();
    }
}

export async function testImageViewDeleteHotkeyRequiresModeratorAndLoadedImage() {
    for (const [label, session, response] of [
        ['anonymous', {getUser: () => null}, detailResponse()],
        ['moderator without a loaded image', MOD_SESSION, {status: 404, body: null}],
    ]) {
        const {view, fetchMock, restore} = await createView(() => response, 'id=7', session);
        try {
            pressKey(view, 'Delete');
            await settle();
            assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/delete')).length, 0,
                `${label} must not trigger a deletion`);
        } finally {
            restore();
        }
    }
}

export async function testImageViewHotkeysDoNotRepeatWhileNavigationIsPending() {
    let resolveNeighbour;
    const {view, fetchMock, navigateCalls, restore} = await modView((url) => {
        if (url === '/api/image/detail?id=7') {
            return detailResponse();
        }
        if (url === '/api/image/neighbor?id=7&direction=next') {
            return new Promise((resolve) => {
                resolveNeighbour = resolve;
            });
        }
        return {status: 200, body: null};
    });
    try {
        assertTrue(pressKey(view, 'ArrowLeft').defaultPrevented);
        const pendingCalls = fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor'));
        assertEqual(pendingCalls.length, 1);
        assertTrue(pressKey(view, 'ArrowRight').defaultPrevented, 'pending arrows should not scroll the page');
        pressKey(view, 'Delete');
        assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/neighbor')).length, 1);
        assertEqual(fetchMock.calls.filter((call) => call.url.startsWith('/api/image/delete')).length, 0,
            'pending navigation should block delete hotkeys');

        resolveNeighbour({status: 200, body: {id: 12}});
        await settle();
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/image?id=12']));
    } finally {
        resolveNeighbour?.({status: 200, body: {id: null}});
        restore();
    }
}

export async function testImageViewFocusesItsPageForImmediateHotkeys() {
    const fetchMock = mockFetch(() => detailResponse());
    const restore = useFetch(fetchMock);
    const view = createImageView({
        session: {getUser: () => null},
        query: new URLSearchParams('id=7'),
    });
    const fixture = document.getElementById('test-fixture');
    fixture.append(view);
    try {
        await settle();
        assertEqual(document.activeElement, view, 'the image view should receive focus on navigation');
        assertEqual(view.getAttribute('tabindex'), '-1', 'the page should be programmatically focusable');
    } finally {
        view.remove();
        restore();
    }
}
