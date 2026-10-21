/**
 * Image display view: shows the viewing image for a single image together
 * with its metadata and editable tags. Deep-linkable via /image?id=<id>.
 * Navigation between adjacent images and the Mod Actions (delete) section
 * are enforced by the server; inaccessible images are skipped.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {clearMessage, createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {createImageDetails, formatFileSize} from '../imageDetails.js';
import {createConfirmDialog} from '../modal.js';
import {createSearchView, normalizeTags} from '../search.js';
import {RoutePath} from '../routes.js';
import {createTagList} from '../tagList.js';

export {formatFileSize};

const DETAIL_URL = '/api/image/detail';
const NEIGHBOR_URL = '/api/image/neighbor';
const DELETE_URL = '/api/image/delete';
const NOT_FOUND_MESSAGE = 'Image not found.';
const FORBIDDEN_MESSAGE = 'You do not have permission to view this image.';
const NO_PREVIOUS_MESSAGE = 'There is no previous image.';
const NO_NEXT_MESSAGE = 'There is no next image.';

export function createImageView({session, navigate = () => {}, query = new URLSearchParams(window.location.search)} = {}) {
    const imageId = parseImageId(query.get('id'));
    const search = createSearchView({
        onSearch: (tags) => navigate(buildHomePath(tags)),
    });
    const status = createMessageBox();
    const imageDetails = createImageDetails({canDownload: Boolean(session.getUser())});
    const tagList = createTagList({
        session,
        navigate,
        status,
        onRefresh: () => loadImage(imageId, state, applyImage),
    });

    let currentImage = null;
    let navigationPending = false;
    let deletePending = false;

    const previousButton = createNavButton('image-nav-previous', 'Previous', 'previous');
    const nextButton = createNavButton('image-nav-next', 'Next', 'next');
    const imageNav = el('nav', {
        className: 'image-nav',
        attributes: {'aria-label': 'Adjacent images'},
        children: [previousButton, nextButton],
    });
    const deleteButton = hasAccessLevel(session.getUser(), AccessLevel.MOD) ? createDeleteButton() : null;
    const modActions = deleteButton === null ? null : el('section', {
        className: 'mod-actions',
        children: [
            el('h2', {className: 'sidebar-title', children: 'Mod Actions'}),
            deleteButton,
        ],
    });

    const sidebar = el('aside', {
        className: 'sidebar',
        children: [
            search.element,
            el('h2', {className: 'sidebar-title', children: 'Tags'}),
            tagList.element,
            imageDetails.metaSection,
            ...(modActions !== null ? [modActions] : []),
        ],
    });
    const content = el('section', {
        className: 'content',
        children: [status, imageDetails.imageHolder, imageNav],
    });
    const state = {navigate, imageDetails, tagList, status};

    refreshControls();
    loadImage(imageId, state, applyImage);

    return el('div', {className: 'layout image-layout', children: [sidebar, content]});

    function createNavButton(className, label, direction) {
        const button = el('button', {
            className: `button ${className}`,
            attributes: {type: 'button'},
            children: label,
        });
        button.addEventListener('click', () => void goToNeighbor(direction));
        return button;
    }

    function createDeleteButton() {
        const button = el('button', {
            className: 'button button--danger mod-action-delete',
            attributes: {type: 'button'},
            children: 'Delete image',
        });
        button.addEventListener('click', () => void deleteCurrentImage());
        return button;
    }

    /** Applies a freshly loaded image and enables/disables the view controls. */
    function applyImage(image) {
        currentImage = image;
        imageDetails.update(image);
        tagList.update(image);
        refreshControls();
    }

    function refreshControls() {
        setNavigationDisabled(currentImage === null || navigationPending);
        if (deleteButton !== null) {
            deleteButton.disabled = currentImage === null || deletePending;
        }
    }

    function setNavigationDisabled(disabled) {
        previousButton.disabled = disabled;
        nextButton.disabled = disabled;
    }

    async function goToNeighbor(direction) {
        const fromId = currentImage?.id ?? null;
        if (fromId === null || navigationPending) {
            return;
        }
        navigationPending = true;
        setNavigationDisabled(true);
        try {
            const data = await get(`${NEIGHBOR_URL}?id=${fromId}&direction=${direction}`);
            const neighborId = data?.id ?? null;
            if (!Number.isSafeInteger(neighborId) || neighborId <= 0) {
                showMessage(status, direction === 'next' ? NO_NEXT_MESSAGE : NO_PREVIOUS_MESSAGE, 'info');
                return;
            }
            navigate(`${RoutePath.IMAGE_DISPLAY}?id=${neighborId}`);
        } catch (error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
        } finally {
            navigationPending = false;
            refreshControls();
        }
    }

    async function deleteCurrentImage() {
        const image = currentImage;
        if (image === null || deletePending) {
            return;
        }
        deletePending = true;
        deleteButton.disabled = true;
        try {
            const dialog = createConfirmDialog({
                message: `Delete image #${image.id}? This action cannot be undone.`,
                confirmLabel: 'Delete image',
            });
            if (!(await dialog.result)) {
                return;
            }
            clearMessage(status);
            await postJson(`${DELETE_URL}?id=${image.id}`);
            navigate(RoutePath.START_PAGE);
        } catch (error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
        } finally {
            deletePending = false;
            refreshControls();
        }
    }
}

/** Positive-integer string or null (anything else is rejected server-side with 400). */
function parseImageId(raw) {
    return /^[1-9][0-9]*$/.test(raw ?? '') ? Number(raw) : null;
}

function buildHomePath(tags) {
    const normalizedTags = normalizeTags(tags);
    return normalizedTags === ''
        ? '/'
        : `/?${new URLSearchParams({tags: normalizedTags})}`;
}

async function loadImage(id, {imageDetails, tagList, status}, onImage) {
    if (id === null) {
        onImage(null);
        showMessage(status, NOT_FOUND_MESSAGE);
        return;
    }
    try {
        const data = await get(`${DETAIL_URL}?id=${id}`);
        const image = data?.image ?? null;
        if (!image) {
            onImage(null);
            showMessage(status, NOT_FOUND_MESSAGE);
            return;
        }
        onImage(image);
    } catch (error) {
        onImage(null);
        if (error?.status === 401 || error?.status === 403) {
            showMessage(status, FORBIDDEN_MESSAGE);
            return;
        }
        showMessage(status, error?.status === 404 ? NOT_FOUND_MESSAGE : messageForStatus(error?.status ?? 0));
    }
}
