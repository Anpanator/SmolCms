/**
 * Tag Queue: moderation queue for tag suggestions from users with MOD access
 * or higher. Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createImageViewer} from '../imageViewer.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage} from '../forms.js';

const QUEUE_URL = '/api/tag-suggestions/queue';
const IMAGE_DETAIL_URL = '/api/image/detail';
const APPROVE_URL = '/api/tag-suggestions/approve';
const REJECT_URL = '/api/tag-suggestions/reject';

export function createTagQueueView({session, navigate, query}) {
    if (!hasTagQueueAccess(session.getUser())) {
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

    let queueSuggestions = [];
    let activeSuggestion = null;
    let activeIndex = -1;
    let detailRequestId = 0;
    let loadingImageId = null;
    let actionPending = false;
    const imageDetails = new Map();

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

    approveButton.addEventListener('click', () => act('approve'));
    rejectButton.addEventListener('click', () => act('reject'));
    page.addEventListener('keydown', handleKeyboard);

    // The queue currently has no URL-backed filters; accept query to mirror the other view factories.
    void query;
    loadQueue();
    return page;

    async function loadQueue() {
        try {
            const data = await get(QUEUE_URL);
            queueSuggestions = Array.isArray(data?.suggestions) ? data.suggestions : [];
            renderQueue();
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
            queueList.replaceChildren(el('li', {
                className: 'review-empty tag-queue-empty',
                children: 'Could not load the tag suggestion queue.',
            }));
            imageViewer.showStatus('The tag suggestion queue could not be loaded.');
        }
    }

    function renderQueue(preferredIndex = null) {
        if (queueSuggestions.length === 0) {
            activeSuggestion = null;
            activeIndex = -1;
            detailRequestId++;
            loadingImageId = null;
            currentTagName.textContent = '';
            currentSuggestedBy.textContent = '';
            queueList.replaceChildren(el('li', {
                className: 'review-empty tag-queue-empty',
                children: 'No tag suggestions are waiting for moderation.',
            }));
            imageViewer.clear('No tag suggestions are waiting for moderation.');
            setActionButtonsDisabled(true);
            return;
        }

        const previousSuggestionId = activeSuggestion?.id;
        queueList.replaceChildren(...queueSuggestions.map(buildQueueItem));

        let nextIndex = Number.isInteger(preferredIndex)
            ? preferredIndex
            : queueSuggestions.findIndex((suggestion) => suggestion.id === previousSuggestionId);
        if (nextIndex < 0 || nextIndex >= queueSuggestions.length) {
            nextIndex = 0;
        }
        selectSuggestion(nextIndex);
    }

    function buildQueueItem(suggestion) {
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
                'aria-pressed': suggestion.id === activeSuggestion?.id ? 'true' : 'false',
                'aria-label': `Select tag suggestion ${tagName}, suggested by ${suggestedBy}${created ? ` on ${created}` : ''}, for image #${imageId}`,
            },
            children: [
                el('span', {className: 'tag-queue-name', children: tagName}),
                el('span', {
                    className: 'tag-queue-info',
                    children: [
                        el('span', {className: 'tag-queue-suggested-by', children: `Suggested by ${suggestedBy}`}),
                        el('span', {className: 'tag-queue-created', children: `Created ${created}`}),
                        el('span', {className: 'tag-queue-image-id', children: `Image #${imageId}`}),
                    ],
                }),
            ],
        });
        const item = el('li', {
            className: 'review-item tag-queue-item',
            attributes: {'data-suggestion-id': suggestion.id, 'data-image-id': suggestion.imageId},
            children: selectButton,
        });
        selectButton.addEventListener('click', () => selectSuggestionById(suggestion.id));
        return item;
    }

    function selectSuggestionById(id) {
        const index = queueSuggestions.findIndex((suggestion) => suggestion.id === id);
        if (index >= 0) {
            selectSuggestion(index);
        }
    }

    function selectSuggestion(index) {
        const suggestion = queueSuggestions[index];
        if (!suggestion) {
            return;
        }

        const sameSuggestion = activeSuggestion?.id === suggestion.id;
        activeSuggestion = suggestion;
        activeIndex = index;
        currentTagName.textContent = suggestion.tagName ?? 'Unknown tag';
        currentSuggestedBy.textContent = `Suggested by ${suggestion.suggestedBy?.displayName ?? 'Unknown user'}`;
        for (const button of queueList.querySelectorAll('.tag-queue-select')) {
            const selected = button.getAttribute('data-suggestion-id') === String(suggestion.id);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        }
        setActionButtonsDisabled(actionPending);

        const cachedDetails = imageDetails.get(suggestion.imageId);
        if (cachedDetails) {
            renderActiveImage(suggestion, cachedDetails);
            return;
        }
        if (sameSuggestion && loadingImageId === suggestion.imageId) {
            return;
        }

        imageViewer.update({id: suggestion.imageId}, {status: 'Loading full-size image…'});
        const requestId = ++detailRequestId;
        loadingImageId = suggestion.imageId;
        get(`${IMAGE_DETAIL_URL}?id=${suggestion.imageId}`)
            .then((data) => {
                if (requestId !== detailRequestId || activeSuggestion?.id !== suggestion.id) {
                    return;
                }
                loadingImageId = null;
                const details = data?.image;
                if (!details) {
                    imageViewer.update({id: suggestion.imageId}, {status: 'Full-size image details are unavailable.'});
                    return;
                }
                if (details.downloadSrc) {
                    imageDetails.set(suggestion.imageId, details);
                }
                renderActiveImage(suggestion, details);
            })
            .catch((error) => {
                if (requestId !== detailRequestId || activeSuggestion?.id !== suggestion.id) {
                    return;
                }
                loadingImageId = null;
                imageViewer.showStatus('Full-size image could not be loaded.');
                if (isAuthError(error)) {
                    navigate('/login');
                    return;
                }
                showMessage(status, messageForStatus(error?.status ?? 0));
            });
    }

    function renderActiveImage(suggestion, details) {
        imageViewer.update({...details, id: details.id ?? suggestion.imageId});
    }

    async function act(action) {
        const suggestion = activeSuggestion;
        if (!suggestion || actionPending) {
            return;
        }

        clearMessage(status);
        actionPending = true;
        setActionButtonsDisabled(true);
        try {
            await postJson(`${action === 'approve' ? APPROVE_URL : REJECT_URL}?id=${suggestion.id}`);
            const removedIndex = queueSuggestions.findIndex((item) => item.id === suggestion.id);
            if (removedIndex < 0) {
                return;
            }

            const previousActiveId = activeSuggestion?.id;
            queueSuggestions.splice(removedIndex, 1);
            if (!queueSuggestions.some((item) => item.imageId === suggestion.imageId)) {
                imageDetails.delete(suggestion.imageId);
            }
            const nextIndex = previousActiveId === suggestion.id
                ? Math.min(removedIndex, queueSuggestions.length - 1)
                : queueSuggestions.findIndex((item) => item.id === previousActiveId);
            if (previousActiveId === suggestion.id) {
                activeSuggestion = null;
            }
            showMessage(status, action === 'approve'
                ? 'Tag suggestion approved.'
                : 'Tag suggestion rejected.', 'success');
            renderQueue(nextIndex);
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
            if ((error?.status ?? 0) !== 403) {
                await loadQueue();
            }
        } finally {
            actionPending = false;
            setActionButtonsDisabled(!activeSuggestion);
        }
    }

    function handleKeyboard(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]'))) {
            return;
        }
        if (!activeSuggestion) {
            return;
        }

        switch (event.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                selectSuggestion(Math.max(0, activeIndex - 1));
                break;
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                selectSuggestion(Math.min(queueSuggestions.length - 1, activeIndex + 1));
                break;
            case 'Enter':
                event.preventDefault();
                if (!event.repeat) {
                    act('approve');
                }
                break;
            case 'Delete':
            case 'Backspace':
                event.preventDefault();
                if (!event.repeat) {
                    act('reject');
                }
                break;
        }
    }

    function setActionButtonsDisabled(disabled) {
        approveButton.disabled = disabled;
        rejectButton.disabled = disabled;
    }
}

function hasTagQueueAccess(user) {
    return (user?.accessLevel ?? 0) >= AccessLevel.MOD;
}
