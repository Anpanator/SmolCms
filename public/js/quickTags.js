/**
 * Quick-tag sidebar and its session-scoped visibility control.
 * Importing this module has no side effects.
 */
import {get, postJson} from './api.js';
import {el} from './dom.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage} from './forms.js';

const QUICK_TAGS_URL = '/api/tags/quick';
const QUICK_TAGS_SETTING_URL = '/api/session/quick-tags';
const ADD_TAGS_URL = '/api/image/tags';
const REMOVE_TAG_URL = '/api/image/tags/remove';

let nextQuickTagsId = 1;

/**
 * @param {{
 *   status?: HTMLParagraphElement,
 *   navigate?: (path: string) => void,
 *   onTagAdded?: (tagName: string) => void,
 *   onTagRemoved?: (tagName: string) => void,
 * }} [options]
 * @returns {{element: HTMLElement, load: () => Promise<void>, updateImage: (imageId: number|null, tags: string[]) => void}}
 */
export function createQuickTagsView({
    status = createMessageBox(),
    navigate = () => {},
    onTagAdded = () => {},
    onTagRemoved = () => {},
} = {}) {
    const id = `quick-tags-${nextQuickTagsId++}`;
    const globalGroup = createTagGroup(id, 'global', 'Most used');
    const userMostUsedGroup = createTagGroup(id, 'user-most-used', 'Your most used');
    const userRecentGroup = createTagGroup(id, 'user-recent', 'Your recent');
    const groups = [globalGroup, userMostUsedGroup, userRecentGroup];
    const element = el('aside', {
        className: 'sidebar quick-tags-sidebar',
        attributes: {id},
        children: [
            el('h2', {className: 'sidebar-title', children: 'Quick tags'}),
            el('div', {
                className: 'quick-tags-columns',
                children: [
                    el('div', {className: 'quick-tags-column quick-tags-column--global', children: globalGroup.element}),
                    el('div', {
                        className: 'quick-tags-column quick-tags-column--user',
                        children: [userMostUsedGroup.element, userRecentGroup.element],
                    }),
                ],
            }),
        ],
    });
    element.hidden = true;

    let lists = {global: [], userMostUsed: [], userRecent: []};
    let imageId = null;
    let activeTags = new Set();
    let pending = false;
    let loading = false;
    let requestId = 0;
    let refreshPromise = null;

    render();
    return {element, load, updateImage};

    async function load() {
        const currentRequestId = ++requestId;
        loading = true;
        clearMessage(status);
        render();
        try {
            const data = await get(QUICK_TAGS_URL);
            if (currentRequestId !== requestId) {
                return;
            }
            lists = normalizeLists(data);
            loading = false;
            render();
        } catch (error) {
            if (currentRequestId !== requestId) {
                return;
            }
            lists = {global: [], userMostUsed: [], userRecent: []};
            loading = false;
            render();
            if (isAuthError(error)) {
                navigate('/login');
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        }
    }

    /**
     * Refreshes the lists without clearing the current message or blanking
     * existing tags. Concurrent refreshes share one request.
     */
    async function refreshLists() {
        if (refreshPromise !== null) {
            return refreshPromise;
        }
        const currentRequestId = ++requestId;
        refreshPromise = (async () => {
            try {
                const data = await get(QUICK_TAGS_URL);
                if (currentRequestId !== requestId) {
                    return;
                }
                lists = normalizeLists(data);
                loading = false;
                render();
            } catch {
                if (currentRequestId !== requestId) {
                    return;
                }
                loading = false;
                render();
            } finally {
                refreshPromise = null;
            }
        })();
        return refreshPromise;
    }

    function normalizeLists(data) {
        return {
            global: normalizeQuickTags(data?.global, 20),
            userMostUsed: normalizeQuickTags(data?.userMostUsed, 10),
            userRecent: normalizeQuickTags(data?.userRecent, 10),
        };
    }

    function updateImage(nextImageId, tags) {
        imageId = Number.isSafeInteger(nextImageId) && nextImageId > 0 ? nextImageId : null;
        activeTags = new Set(uniqueTagNames(tags));
        renderButtons();
    }

    function render() {
        globalGroup.heading.textContent = 'Most used';
        userMostUsedGroup.heading.textContent = 'Your most used';
        userRecentGroup.heading.textContent = 'Your recent';
        renderGroup(globalGroup, lists.global);
        renderGroup(userMostUsedGroup, lists.userMostUsed);
        renderGroup(userRecentGroup, lists.userRecent);
        renderButtons();
    }

    function renderGroup(group, tags) {
        if (loading && tags.length === 0) {
            group.list.replaceChildren(el('li', {className: 'quick-tags-empty', children: 'Loading tags…'}));
            return;
        }
        if (tags.length === 0) {
            group.list.replaceChildren(el('li', {className: 'quick-tags-empty', children: 'No tags yet.'}));
            return;
        }
        group.list.replaceChildren(...tags.map(createTagButton));
    }

    function renderButtons() {
        for (const group of groups) {
            for (const button of group.list.querySelectorAll('.quick-tag-button')) {
                const tagName = button.dataset.tagName;
                const isActive = activeTags.has(tagName);
                button.classList.toggle('quick-tag-button--active', isActive);
                button.setAttribute('aria-pressed', String(isActive));
                button.setAttribute('aria-label', `${isActive ? 'Remove' : 'Add'} tag ${tagName}`);
                button.disabled = imageId === null || pending;
            }
        }
    }

    function createTagButton(tag) {
        const button = el('button', {
            className: 'quick-tag-button',
            attributes: {
                type: 'button',
                title: tag.name,
                'data-tag-name': tag.name,
                'aria-pressed': 'false',
            },
            children: tag.name,
        });
        button.addEventListener('click', () => void toggleTag(tag.name));
        return el('li', {className: 'quick-tag-item', children: button});
    }

    async function toggleTag(tagName) {
        const requestedImageId = imageId;
        if (requestedImageId === null || pending) {
            return;
        }

        const removing = activeTags.has(tagName);
        pending = true;
        clearMessage(status);
        renderButtons();
        try {
            if (removing) {
                await postJson(REMOVE_TAG_URL, {imageId: requestedImageId, tag: tagName});
            } else {
                const data = await postJson(ADD_TAGS_URL, {imageId: requestedImageId, tags: tagName});
                const applied = uniqueTagNames(data?.applied);
                if (!applied.includes(tagName)) {
                    showMessage(status, `Tag "${tagName}" is no longer available.`);
                    return;
                }
            }

            if (imageId !== requestedImageId) {
                return;
            }
            if (removing) {
                activeTags.delete(tagName);
                onTagRemoved(tagName);
                showMessage(status, `Removed tag: ${tagName}.`, 'success');
            } else {
                activeTags.add(tagName);
                onTagAdded(tagName);
                showMessage(status, `Added tag: ${tagName}.`, 'success');
            }
            renderButtons();
            await refreshLists();
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        } finally {
            pending = false;
            renderButtons();
        }
    }
}

/**
 * @param {{
 *   session: *,
 *   quickTags: ReturnType<typeof createQuickTagsView>,
 *   layout: HTMLElement,
 *   status?: HTMLParagraphElement,
 *   navigate?: (path: string) => void,
 * }} options
 */
export function createQuickTagsToggle({session, quickTags, layout, status = createMessageBox(), navigate = () => {}}) {
    if (!session?.getUser?.()) {
        quickTags.element.hidden = true;
        layout.classList.remove('layout--with-quick-tags');
        return null;
    }

    let enabled = session.getQuickTagsEnabled?.() === true;
    let pending = false;
    quickTags.element.hidden = !enabled;
    layout.classList.toggle('layout--with-quick-tags', enabled);

    const button = el('button', {
        className: 'button quick-tags-toggle',
        attributes: {type: 'button', 'aria-controls': quickTags.element.id},
    });
    renderButton();
    if (enabled) {
        void quickTags.load();
    }
    button.addEventListener('click', () => void toggleVisibility());
    return button;

    async function toggleVisibility() {
        if (pending) {
            return;
        }
        pending = true;
        const requestedValue = !enabled;
        renderButton();
        clearMessage(status);
        try {
            const data = await postJson(QUICK_TAGS_SETTING_URL, {enabled: requestedValue});
            enabled = typeof data?.quickTagsEnabled === 'boolean' ? data.quickTagsEnabled : requestedValue;
            if (typeof session.setQuickTagsEnabled === 'function') {
                session.setQuickTagsEnabled(enabled);
            }
            quickTags.element.hidden = !enabled;
            layout.classList.toggle('layout--with-quick-tags', enabled);
            if (enabled) {
                await quickTags.load();
            }
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        } finally {
            pending = false;
            renderButton();
        }
    }

    function renderButton() {
        button.textContent = enabled ? 'Hide quick tags' : 'Show quick tags';
        button.setAttribute('aria-expanded', String(enabled));
        button.disabled = pending;
    }
}

function createTagGroup(id, key, title) {
    const headingId = `${id}-${key}-title`;
    const heading = el('h3', {
        className: 'quick-tags-group-title',
        attributes: {id: headingId},
        children: title,
    });
    const list = el('ul', {className: 'quick-tags-list'});
    return {
        heading,
        list,
        element: el('section', {
            className: `quick-tags-group quick-tags-group--${key}`,
            attributes: {'aria-labelledby': headingId},
            children: [heading, list],
        }),
    };
}

function normalizeQuickTags(values, limit) {
    if (!Array.isArray(values)) {
        return [];
    }
    const tags = [];
    const seen = new Set();
    for (const value of values) {
        if (typeof value?.name !== 'string' || value.name.trim() === '' || seen.has(value.name)) {
            continue;
        }
        seen.add(value.name);
        tags.push({name: value.name, category: typeof value.category === 'string' ? value.category : ''});
        if (tags.length === limit) {
            break;
        }
    }
    return tags;
}

function uniqueTagNames(values) {
    if (!Array.isArray(values)) {
        return [];
    }
    return [...new Set(values.filter((tag) => typeof tag === 'string' && tag.trim() !== ''))];
}
