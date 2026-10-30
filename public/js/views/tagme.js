/**
 * Tagme queue view: one random image awaiting tags with the shared image
 * details and tag-editing controls. Importing this module has no side effects.
 */
import {get} from '../api.js';
import {el} from '../dom.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage} from '../forms.js';
import {createImageDetails} from '../imageDetails.js';
import {createQuickTagsToggle, createQuickTagsView} from '../quickTags.js';
import {createTagList} from '../tagList.js';

const RANDOM_IMAGE_URL = '/api/tagme/random';
const EMPTY_QUEUE_MESSAGE = 'No images are waiting for tags right now. Try again later.';

/** @param {{session?: *, navigate?: (path: string) => void, query?: URLSearchParams}} [context] */
export function createTagmeView({session, navigate = () => {}, query: _query} = {}) {
    const status = createMessageBox();
    const imageDetails = createImageDetails();
    const nextButton = el('button', {
        className: 'button button--primary tagme-next-button',
        attributes: {type: 'button', disabled: ''},
        children: 'Next random image',
    });
    let isLoading = false;
    let quickTags = null;
    const tagList = createTagList({
        session,
        navigate,
        status,
        onRefresh: loadRandomImage,
        onChange: (imageId, tags) => quickTags?.updateImage(imageId, tags),
    });
    quickTags = createQuickTagsView({
        status,
        navigate,
        onTagAdded: (tagName) => tagList.addTag(tagName),
        onTagRemoved: (tagName) => tagList.removeTag(tagName),
    });
    const view = el('div', {className: 'layout tagme-layout'});
    const quickTagsToggle = createQuickTagsToggle({
        session,
        quickTags,
        layout: view,
        status,
        navigate,
        defaultEnabled: true,
    });
    const sidebar = el('aside', {
        className: 'sidebar',
        children: [
            el('h2', {className: 'sidebar-title', children: 'Tags'}),
            tagList.element,
            quickTagsToggle,
            imageDetails.metaSection,
        ],
    });
    const content = el('section', {
        className: 'content tagme-content',
        children: [
            el('div', {
                className: 'tagme-toolbar',
                children: [
                    el('h1', {className: 'content-title', children: 'Tagme queue'}),
                    nextButton,
                ],
            }),
            status,
            imageDetails.imageHolder,
        ],
    });
    view.append(sidebar, content, quickTags.element);

    nextButton.addEventListener('click', loadRandomImage);
    loadRandomImage();

    return view;

    async function loadRandomImage() {
        if (isLoading) {
            return;
        }
        isLoading = true;
        nextButton.disabled = true;
        clearMessage(status);
        try {
            const data = await get(RANDOM_IMAGE_URL);
            const image = data?.image ?? null;
            if (!image) {
                imageDetails.update(null);
                tagList.update(null);
                showMessage(status, EMPTY_QUEUE_MESSAGE, 'info');
                return;
            }
            imageDetails.update(image);
            tagList.update(image);
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            if (error?.status === 404) {
                imageDetails.update(null);
                tagList.update(null);
                showMessage(status, EMPTY_QUEUE_MESSAGE, 'info');
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        } finally {
            isLoading = false;
            nextButton.disabled = false;
        }
    }
}
