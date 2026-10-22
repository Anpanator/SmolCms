/**
 * Shared image-tag list and editing controls for image detail pages.
 * Importing this module has no side effects.
 */
import {postJson} from './api.js';
import {AccessLevel, hasAccessLevel} from './accessLevel.js';
import {el} from './dom.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage} from './forms.js';
import {createConfirmDialog} from './modal.js';
import {attachTagSuggestions} from './search.js';
import {createTagLinkList} from './tagLinks.js';

const ADD_TAGS_URL = '/api/image/tags';
const REMOVE_TAG_URL = '/api/image/tags/remove';
const SUGGEST_TAGS_URL = '/api/tag-suggestions';
const TAGME_CONFLICT_MESSAGE = "The 'tagme' tag can only be removed when the image has at least 10 other tags.";
const REMOVE_NOT_FOUND_MESSAGE = 'The image or tag attachment was not found. Image details were refreshed.';

let nextTagListId = 1;

/**
 * @param {{
 *   session?: *,
 *   navigate?: (path: string) => void,
 *   status?: HTMLParagraphElement,
 *   onRefresh?: () => (void|Promise<void>),
 *   onChange?: (imageId: number|null, tags: string[]) => void,
 * }} [options]
 * @returns {{
 *   element: HTMLDivElement,
 *   status: HTMLParagraphElement,
 *   update: (image: object|null) => void,
 *   getTags: () => string[],
 *   addTag: (tagName: string) => void,
 *   removeTag: (tagName: string) => void,
 * }}
 */
export function createTagList({
    session,
    navigate = () => {},
    status = createMessageBox(),
    onRefresh = () => {},
    onChange = () => {},
} = {}) {
    const list = el('ul', {className: 'tags'});
    const element = el('div', {className: 'tag-view tag-editor', children: list});
    const canEdit = Boolean(session?.getUser?.());
    let imageId = null;
    let tags = [];
    let isPending = false;
    let input = null;
    let addButton = null;
    let autocomplete = null;

    if (canEdit) {
        const controls = createEditControls();
        element.prepend(controls.row);
        input = controls.input;
        addButton = controls.addButton;
        autocomplete = controls.autocomplete;
    }

    return {element, status, update, getTags: () => [...tags], addTag, removeTag};

    function createEditControls() {
        const id = nextTagListId++;
        const inputId = `tag-add-input-${id}`;
        const suggestionsId = `tag-add-suggestions-${id}`;
        const tagInput = el('input', {
            className: 'field-input tag-add-input',
            attributes: {
                id: inputId,
                type: 'text',
                placeholder: 'Add tags',
                autocomplete: 'off',
                role: 'combobox',
                'aria-label': 'Add tags',
                'aria-autocomplete': 'list',
                'aria-expanded': 'false',
                'aria-controls': suggestionsId,
            },
        });
        const suggestions = el('ul', {
            className: 'search-suggestions tag-add-suggestions',
            attributes: {id: suggestionsId, role: 'listbox', hidden: ''},
        });
        const field = el('div', {
            className: 'tag-add-field',
            children: [
                el('label', {
                    className: 'search-label',
                    attributes: {for: inputId},
                    children: 'Add tags',
                }),
                tagInput,
                suggestions,
            ],
        });
        const button = el('button', {
            className: 'button button--primary tag-add-submit',
            attributes: {type: 'button'},
            children: 'Add',
        });
        const row = el('div', {className: 'tag-add-row', children: [field, button]});
        const tagSuggestions = attachTagSuggestions({input: tagInput, suggestions, container: element});

        tagInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
        button.addEventListener('click', addTags);

        return {row, input: tagInput, addButton: button, autocomplete: tagSuggestions};
    }

    function update(image) {
        imageId = parseImageId(image?.id);
        tags = sortedUniqueTags(image?.tags);
        render();
    }

    function addTag(tagName) {
        if (!canEdit || imageId === null || typeof tagName !== 'string' || tagName.trim() === '') {
            return;
        }
        const nextTags = sortedUniqueTags([...tags, tagName]);
        if (nextTags.length === tags.length) {
            return;
        }
        tags = nextTags;
        render();
    }

    function removeTag(tagName) {
        if (!canEdit || imageId === null) {
            return;
        }
        const nextTags = tags.filter((tag) => tag !== tagName);
        if (nextTags.length === tags.length) {
            return;
        }
        tags = nextTags;
        render();
    }

    function render() {
        const tagLinks = createTagLinkList({
            tags,
            hrefForTag: buildHomePath,
            removeControlForTag: canEdit ? (tag) => {
                const removeButton = el('button', {
                    className: 'tag-remove-button',
                    attributes: {
                        type: 'button',
                        'aria-label': `Remove tag ${tag}`,
                        'data-tag-name': tag,
                    },
                    children: '×',
                });
                removeButton.addEventListener('click', () => removeTagFromImage(tag));
                return removeButton;
            } : null,
        });
        list.replaceChildren(...tagLinks.childNodes);
        updateControls();
        onChange(imageId, [...tags]);
    }

    async function addTags() {
        const rawInput = input?.value ?? '';
        if (!canEdit || isPending || imageId === null || rawInput.trim() === '') {
            return;
        }

        autocomplete.close();
        clearMessage(status);
        const requestedImageId = imageId;
        isPending = true;
        updateControls();

        try {
            const data = await postJson(ADD_TAGS_URL, {imageId: requestedImageId, tags: rawInput});
            const applied = uniqueTagNames(data?.applied);
            const unknown = uniqueTagNames(data?.unknown);
            if (imageId === requestedImageId) {
                tags = sortedUniqueTags([...tags, ...applied]);
                render();
            }
            input.value = '';
            showMessage(status, applied.length > 0 ? `Tags added: ${applied.join(', ')}.` : 'Tags updated successfully.', 'success');

            if (unknown.length > 0) {
                await handleUnknownTags(requestedImageId, unknown);
            }
        } catch (error) {
            showApiError(error);
        } finally {
            isPending = false;
            updateControls();
        }
    }

    async function handleUnknownTags(requestedImageId, unknown) {
        const user = session?.getUser?.();
        if (!hasAccessLevel(user, AccessLevel.FELLOW)) {
            showMessage(status, `Unknown tags were not added: ${unknown.join(', ')}`, 'info');
            return;
        }

        const dialog = createConfirmDialog({
            message: `Unknown tags: ${unknown.join(', ')}. Suggest them as new tags?`,
            confirmLabel: 'Suggest tags',
            cancelLabel: 'Discard',
        });
        const confirmed = await dialog.result;
        if (!confirmed) {
            showMessage(status, `Unknown tags were discarded: ${unknown.join(', ')}.`, 'info');
            return;
        }

        try {
            await postJson(SUGGEST_TAGS_URL, {imageId: requestedImageId, tags: unknown.join(', ')});
            showMessage(status, `Tags submitted for review: ${unknown.join(', ')}.`, 'success');
        } catch (error) {
            showApiError(error);
        }
    }

    async function removeTagFromImage(tag) {
        if (!canEdit || isPending || imageId === null) {
            return;
        }

        const requestedImageId = imageId;
        isPending = true;
        updateControls();
        try {
            const dialog = createConfirmDialog({
                message: `Remove the tag "${tag}" from this image?`,
                confirmLabel: 'Remove tag',
                cancelLabel: 'Keep tag',
            });
            if (!(await dialog.result)) {
                return;
            }

            clearMessage(status);
            await postJson(REMOVE_TAG_URL, {imageId: requestedImageId, tag});
            if (imageId === requestedImageId) {
                tags = tags.filter((currentTag) => currentTag !== tag);
                render();
            }
            showMessage(status, `Removed tag: ${tag}.`, 'success');
        } catch (error) {
            if (error?.status === 409) {
                showMessage(status, TAGME_CONFLICT_MESSAGE);
                return;
            }
            if (error?.status === 404) {
                try {
                    await onRefresh();
                } catch {
                    // The page's refresh handler owns any display error.
                }
                showMessage(status, REMOVE_NOT_FOUND_MESSAGE);
                return;
            }
            showApiError(error);
        } finally {
            isPending = false;
            updateControls();
        }
    }

    function showApiError(error) {
        if (isAuthError(error)) {
            navigate('/login');
        }
        showMessage(status, error?.status === 404 ? 'Image not found.' : messageForStatus(error?.status ?? 0));
    }

    function updateControls() {
        if (addButton) {
            addButton.disabled = imageId === null || isPending;
            input.disabled = imageId === null || isPending;
        }
        list.querySelectorAll('.tag-remove-button').forEach((button) => {
            button.disabled = isPending;
        });
    }
}

function parseImageId(value) {
    return Number.isSafeInteger(value) && value > 0 ? value : null;
}

function uniqueTagNames(values) {
    if (!Array.isArray(values)) {
        return [];
    }
    return [...new Set(values.filter((tag) => typeof tag === 'string' && tag.trim() !== ''))];
}

function sortedUniqueTags(values) {
    return uniqueTagNames(values).sort((left, right) => left.localeCompare(right));
}

function buildHomePath(tag) {
    return `/?${new URLSearchParams({tags: tag})}`;
}
