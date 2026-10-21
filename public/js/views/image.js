/**
 * Image display view: shows the viewing image for a single image together
 * with its metadata and editable tags. Deep-linkable via /image?id=<id>.
 * Importing this module has no side effects.
 */
import {get} from '../api.js';
import {el} from '../dom.js';
import {createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {createImageDetails, formatFileSize} from '../imageDetails.js';
import {createSearchView, normalizeTags} from '../search.js';
import {createTagList} from '../tagList.js';

export {formatFileSize};

const DETAIL_URL = '/api/image/detail';
const NOT_FOUND_MESSAGE = 'Image not found.';
const FORBIDDEN_MESSAGE = 'You do not have permission to view this image.';

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
        onRefresh: () => loadImage(imageId, state),
    });
    const sidebar = el('aside', {
        className: 'sidebar',
        children: [
            search.element,
            el('h2', {className: 'sidebar-title', children: 'Tags'}),
            tagList.element,
            imageDetails.metaSection,
        ],
    });
    const content = el('section', {
        className: 'content',
        children: [status, imageDetails.imageHolder],
    });
    const state = {navigate, imageDetails, tagList, status};

    loadImage(imageId, state);

    return el('div', {className: 'layout image-layout', children: [sidebar, content]});
}

/** Positive-integer string or null (anything else is rejected server-side with 400). */
function parseImageId(raw) {
    return /^[1-9][0-9]*$/.test(raw ?? '') ? Number(raw) : null;
}

async function loadImage(id, {navigate, imageDetails, tagList, status}) {
    if (id === null) {
        imageDetails.update(null);
        tagList.update(null);
        showMessage(status, NOT_FOUND_MESSAGE);
        return;
    }
    try {
        const data = await get(`${DETAIL_URL}?id=${id}`);
        const image = data?.image ?? null;
        if (!image) {
            imageDetails.update(null);
            tagList.update(null);
            showMessage(status, NOT_FOUND_MESSAGE);
            return;
        }
        imageDetails.update(image);
        tagList.update(image);
    } catch (error) {
        if (error?.status === 401 || error?.status === 403) {
            showMessage(status, FORBIDDEN_MESSAGE);
            return;
        }
        showMessage(status, error?.status === 404 ? NOT_FOUND_MESSAGE : messageForStatus(error?.status ?? 0));
    }
}

function buildHomePath(tags) {
    const normalizedTags = normalizeTags(tags);
    return normalizedTags === ''
        ? '/'
        : `/?${new URLSearchParams({tags: normalizedTags})}`;
}
