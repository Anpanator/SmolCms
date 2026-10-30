/**
 * Tag Queue: moderation queue for tag suggestions from users with MOD access
 * or higher. Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createImageViewer} from '../imageViewer.js';
import {createModerationQueue} from '../moderationQueue.js';
import {clearMessage, createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {userProfileHref} from '../routes.js';
import {registerViewHotkeys} from '../hotkeys.js';

const QUEUE_URL = '/api/tag-suggestions/queue';
const IMAGE_DETAIL_URL = '/api/image/detail';
const APPROVE_URL = '/api/tag-suggestions/approve';
const REJECT_URL = '/api/tag-suggestions/reject';

export function createTagQueueView({session, navigate, query}) {
    if (!hasAccessLevel(session.getUser(), AccessLevel.MOD)) {
        return el('section', {
            className: 'page review-page review-denied-page',
            attributes: {tabindex: '-1'},
            children: [
                el('h1', {className: 'page-title', children: 'Tag Queue'}),
                el('p', {
                    className: 'notice notice--warning review-denied',
                    children: 'You do not have permission to moderate tag suggestions.',
                }),
            ],
        });
    }

    const status = createMessageBox();
    const imageViewer = createImageViewer();
    imageViewer.showStatus('Loading tag suggestion queue…');
    const imageHolder = imageViewer.element;
    const metadataList = imageViewer.metadata;
    const queueList = el('ul', {
        className: 'review-list tag-queue-list',
        attributes: {role: 'list', 'aria-label': 'Tag suggestions waiting for moderation'},
        children: el('li', {className: 'review-empty tag-queue-empty', children: 'Loading tag suggestions…'}),
    });
    const currentTagName = el('p', {
        className: 'tag-queue-current-name',
        children: 'Select a tag suggestion to review.',
    });
    const currentSuggestedBy = el('p', {className: 'tag-queue-current-byline'});
    const approveButton = el('button', {
        className: 'button button--primary',
        attributes: {type: 'button', 'data-action': 'approve', disabled: 'disabled'},
        children: 'Approve',
    });
    const rejectButton = el('button', {
        className: 'button button--danger',
        attributes: {type: 'button', 'data-action': 'reject', disabled: 'disabled'},
        children: 'Reject',
    });

    const page = el('section', {
        className: 'review-page',
        attributes: {tabindex: '0', 'aria-label': 'Tag Queue'},
        children: el('div', {
            className: 'review-workspace',
            children: [
                el('section', {
                    className: 'review-image-panel',
                    attributes: {'aria-label': 'Full-size image for tag review'},
                    children: imageHolder,
                }),
                el('aside', {
                    className: 'review-sidebar',
                    children: [
                        el('h1', {className: 'review-title', children: 'Tag Queue'}),
                        status,
                        el('section', {
                            className: 'review-metadata-panel',
                            children: [
                                el('h2', {className: 'review-panel-title', children: 'Image details'}),
                                metadataList,
                            ],
                        }),
                        el('section', {
                            className: 'review-metadata-panel tag-queue-current-panel',
                            attributes: {'aria-live': 'polite'},
                            children: [
                                el('h2', {className: 'review-panel-title', children: 'Tag suggestion under review'}),
                                currentTagName,
                                currentSuggestedBy,
                            ],
                        }),
                        el('div', {
                            className: 'review-actions',
                            children: [approveButton, rejectButton],
                        }),
                        el('p', {
                            className: 'review-hotkeys',
                            attributes: {'aria-label': 'Keyboard shortcuts'},
                            children: [
                                'Keyboard shortcuts: ',
                                el('kbd', {children: '← / ↑'}), ' previous · ',
                                el('kbd', {children: '→ / ↓'}), ' next · ',
                                el('kbd', {children: 'Enter'}), ' approve · ',
                                el('kbd', {children: 'Delete / Backspace'}), ' reject',
                            ],
                        }),
                        el('section', {
                            className: 'review-queue-panel tag-queue-panel',
                            children: [
                                el('h2', {className: 'review-panel-title', children: 'Queue'}),
                                queueList,
                            ],
                        }),
                    ],
                }),
            ],
        }),
    });

    // The queue currently has no URL-backed filters; accept query to mirror the other view factories.
    void query;
    function buildQueueItem(suggestion, selectedSuggestion, selectById) {
        const tagName = suggestion.tagName ?? 'Unknown tag';
        const suggestedBy = suggestion.suggestedBy?.displayName ?? 'Unknown user';
        const created = suggestion.created ?? '';
        const imageId = suggestion.imageId ?? '—';
        const selectButton = el('button', {
            className: 'review-select tag-queue-select',
            attributes: {
                type: 'button',
                'data-suggestion-id': suggestion.id,
                'data-image-id': suggestion.imageId,
                'aria-pressed': suggestion.id === selectedSuggestion?.id ? 'true' : 'false',
                'aria-label': `Select tag suggestion ${tagName}, suggested by ${suggestedBy}${created ? ` on ${created}` : ''}, for image #${imageId}`,
            },
            children: el('span', {className: 'tag-queue-name', children: tagName}),
        });
        const suggestionInfo = el('span', {
            className: 'tag-queue-info',
            children: [
                el('span', {
                    className: 'tag-queue-suggested-by',
                    children: ['Suggested by ', createSuggestedByName(suggestion.suggestedBy)],
                }),
                el('span', {className: 'tag-queue-created', children: `Created ${created}`}),
                el('span', {className: 'tag-queue-image-id', children: `Image #${imageId}`}),
            ],
        });
        suggestionInfo.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                return;
            }
            selectById(suggestion.id);
        });
        const item = el('li', {
            className: 'review-item tag-queue-item',
            attributes: {'data-suggestion-id': suggestion.id, 'data-image-id': suggestion.imageId},
            children: [selectButton, suggestionInfo],
        });
        selectButton.addEventListener('click', () => selectById(suggestion.id));
        return item;
    }

    function setActionButtonsDisabled(disabled) {
        approveButton.disabled = disabled;
        rejectButton.disabled = disabled;
    }

    const moderationQueue = createModerationQueue({
        fetchQueue: async () => {
            const data = await get(QUEUE_URL);
            return Array.isArray(data?.suggestions) ? data.suggestions : [];
        },
        fetchDetails: async (_suggestion, imageId) => {
            const data = await get(`${IMAGE_DETAIL_URL}?id=${imageId}`);
            return data?.image ?? null;
        },
        submitAction: (action, suggestion) => postJson(
            `${action === 'approve' ? APPROVE_URL : REJECT_URL}?id=${suggestion.id}`,
        ),
        getImageId: (suggestion) => suggestion?.imageId,
        renderQueue({items, selected, selectById}) {
            queueList.replaceChildren(...items.map((suggestion) => buildQueueItem(suggestion, selected, selectById)));
        },
        onQueueError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
            queueList.replaceChildren(el('li', {
                className: 'review-empty tag-queue-empty',
                children: 'Could not load the tag suggestion queue.',
            }));
            imageViewer.showStatus('The tag suggestion queue could not be loaded.');
        },
        onEmpty() {
            currentTagName.textContent = '';
            currentSuggestedBy.textContent = '';
            queueList.replaceChildren(el('li', {
                className: 'review-empty tag-queue-empty',
                children: 'No tag suggestions are waiting for moderation.',
            }));
            imageViewer.clear('No tag suggestions are waiting for moderation.');
        },
        onSelect(suggestion) {
            currentTagName.textContent = suggestion.tagName ?? 'Unknown tag';
            currentSuggestedBy.replaceChildren(
                'Suggested by ',
                createSuggestedByName(suggestion.suggestedBy),
            );
            for (const button of queueList.querySelectorAll('.tag-queue-select')) {
                const selected = button.getAttribute('data-suggestion-id') === String(suggestion.id);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            }
        },
        onDetailsLoading(suggestion) {
            imageViewer.update({id: suggestion.imageId}, {status: 'Loading full-size image…'});
        },
        onDetailsLoaded(suggestion, details) {
            imageViewer.update({...details, id: details.id ?? suggestion.imageId});
        },
        onDetailsUnavailable(suggestion) {
            imageViewer.update({id: suggestion.imageId}, {status: 'Full-size image details are unavailable.'});
        },
        onDetailsError(error) {
            imageViewer.showStatus('Full-size image could not be loaded.');
            showMessage(status, messageForStatus(error?.status ?? 0));
        },
        onUnauthorized: () => navigate('/login'),
        onClearStatus: () => clearMessage(status),
        onActionSuccess(action) {
            showMessage(status, action === 'approve'
                ? 'Tag suggestion approved.'
                : 'Tag suggestion rejected.', 'success');
        },
        onActionError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
        },
        setActionsDisabled: setActionButtonsDisabled,
        shouldCacheDetails: (details) => Boolean(details?.downloadSrc),
        shouldEvictDetails: (suggestion, remainingSuggestions) => (
            !remainingSuggestions.some((item) => item.imageId === suggestion.imageId)
        ),
    });

    approveButton.addEventListener('click', () => moderationQueue.act('approve'));
    rejectButton.addEventListener('click', () => moderationQueue.act('reject'));
    registerViewHotkeys(page, {
        ArrowLeft: moderationQueue.handleKeyboard,
        ArrowUp: moderationQueue.handleKeyboard,
        ArrowRight: moderationQueue.handleKeyboard,
        ArrowDown: moderationQueue.handleKeyboard,
        Enter: moderationQueue.handleKeyboard,
        Delete: moderationQueue.handleKeyboard,
        Backspace: moderationQueue.handleKeyboard,
    });
    moderationQueue.load();
    return page;
}

function createSuggestedByName(user) {
    const displayName = user?.displayName ?? 'Unknown user';
    const profileHref = userProfileHref(user?.id);
    return profileHref === null
        ? displayName
        : el('a', {
            className: 'user-profile-link',
            attributes: {href: profileHref},
            children: displayName,
        });
}
