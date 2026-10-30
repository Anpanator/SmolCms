/**
 * Delete Queue: moderation queue for images that moderators removed from the
 * regular collection. Deleted images stay in the database (and hidden) until a
 * moderator deletes them for real here; "Restore" makes an image visible again.
 *
 * The page reuses the Mod Queue layout and interactions. Importing this module
 * has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createImageViewer} from '../imageViewer.js';
import {createConfirmDialog} from '../modal.js';
import {createModerationQueue} from '../moderationQueue.js';
import {clearMessage, createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {RoutePath, userProfileHref} from '../routes.js';
import {registerViewHotkeys} from '../hotkeys.js';

const QUEUE_URL = RoutePath.API_DELETE_QUEUE;
const IMAGE_DETAIL_URL = RoutePath.API_IMAGE_DETAIL;
const RESTORE_URL = RoutePath.API_DELETE_QUEUE_RESTORE;
const DELETE_URL = RoutePath.API_DELETE_QUEUE_DELETE;
const DELETE_ALL_URL = RoutePath.API_DELETE_QUEUE_DELETE_ALL;

export function createDeleteQueueView({session, navigate = () => {}} = {}) {
    if (!hasAccessLevel(session.getUser(), AccessLevel.MOD)) {
        return el('section', {
            className: 'page review-page review-denied-page',
            attributes: {tabindex: '-1'},
            children: [
                el('h1', {className: 'page-title', children: 'Delete Queue'}),
                el('p', {
                    className: 'notice notice--warning review-denied',
                    children: 'You do not have permission to manage deleted images.',
                }),
            ],
        });
    }

    const status = createMessageBox();
    const imageViewer = createImageViewer();
    imageViewer.showStatus('Loading delete queue…');
    const imageHolder = imageViewer.element;
    const metadataList = imageViewer.metadata;
    const queueList = el('ul', {
        className: 'review-list',
        children: el('li', {className: 'review-empty', children: 'Loading queue…'}),
    });
    const restoreButton = el('button', {
        className: 'button button--primary',
        attributes: {type: 'button', 'data-action': 'restore', disabled: 'disabled'},
        children: 'Restore',
    });
    const deleteButton = el('button', {
        className: 'button button--danger',
        attributes: {type: 'button', 'data-action': 'delete', disabled: 'disabled'},
        children: 'Delete permanently',
    });
    const deleteAllButton = el('button', {
        className: 'button button--danger',
        attributes: {type: 'button', 'data-action': 'delete-all', disabled: 'disabled'},
        children: 'Delete All',
    });
    let deleteAllPending = false;

    const page = el('section', {
        className: 'review-page',
        attributes: {tabindex: '0', 'aria-label': 'Delete Queue'},
        children: el('div', {
            className: 'review-workspace',
            children: [
                el('section', {
                    className: 'review-image-panel',
                    attributes: {'aria-label': 'Full-size deleted image'},
                    children: imageHolder,
                }),
                el('aside', {
                    className: 'review-sidebar',
                    children: [
                        el('h1', {className: 'review-title', children: 'Delete Queue'}),
                        status,
                        el('section', {
                            className: 'review-metadata-panel',
                            children: [
                                el('h2', {className: 'review-panel-title', children: 'Image details'}),
                                metadataList,
                            ],
                        }),
                        el('div', {
                            className: 'review-actions',
                            children: [restoreButton, deleteButton, deleteAllButton],
                        }),
                        el('p', {
                            className: 'review-hotkeys',
                            attributes: {'aria-label': 'Keyboard shortcuts'},
                            children: [
                                'Keyboard shortcuts: ',
                                el('kbd', {children: '← / ↑'}), ' previous · ',
                                el('kbd', {children: '→ / ↓'}), ' next · ',
                                el('kbd', {children: 'Enter'}), ' restore · ',
                                el('kbd', {children: 'Delete / Backspace'}), ' delete permanently',
                            ],
                        }),
                        el('section', {
                            className: 'review-queue-panel',
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

    function buildQueueItem(image, selectedImage, selectById) {
        const uploader = image.uploader ?? 'Unknown uploader';
        const uploadDate = image.uploadDate ?? '';
        const uploaderHref = userProfileHref(image.uploaderId);
        const uploaderValue = uploaderHref === null
            ? el('span', {className: 'review-uploader', children: uploader})
            : el('a', {
                className: 'review-uploader user-profile-link',
                attributes: {href: uploaderHref},
                children: uploader,
            });
        const thumb = image.thumbSrc
            ? el('img', { className: 'review-thumb', attributes: { src: image.thumbSrc, alt: '' } })
            : el('span', {
                className: 'review-thumb review-thumb--empty',
                attributes: { 'aria-hidden': 'true' },
                children: 'No preview',
            });
        const selectButton = el('button', {
            className: 'review-select',
            attributes: {
                type: 'button',
                'data-image-id': image.id,
                'aria-pressed': image.id === selectedImage?.id ? 'true' : 'false',
                'aria-label': `Select deleted image ${image.id}, uploaded by ${uploader}${uploadDate ? ` on ${uploadDate}` : ''}`,
            },
            children: [
                thumb,
                el('span', {
                    className: 'review-info',
                    children: el('span', {className: 'review-date', children: uploadDate}),
                }),
            ],
        });
        const item = el('li', {
            className: 'review-item',
            attributes: {'data-image-id': image.id},
            children: [selectButton, el('span', {className: 'review-info', children: uploaderValue})],
        });
        selectButton.addEventListener('click', () => selectById(image.id));
        return item;
    }

    function setActionButtonsDisabled(disabled) {
        const actionsDisabled = disabled || deleteAllPending;
        restoreButton.disabled = actionsDisabled;
        deleteButton.disabled = actionsDisabled;
        deleteAllButton.disabled = actionsDisabled;
    }

    const deleteQueue = createModerationQueue({
        primaryAction: 'restore',
        secondaryAction: 'delete',
        fetchQueue: async () => {
            const data = await get(QUEUE_URL);
            return Array.isArray(data?.images) ? data.images : [];
        },
        fetchDetails: async (_image, imageId) => {
            const data = await get(`${IMAGE_DETAIL_URL}?id=${imageId}`);
            return data?.image ?? null;
        },
        submitAction: (action, image) => postJson(
            `${action === 'restore' ? RESTORE_URL : DELETE_URL}?id=${image.id}`,
        ),
        getImageId: (image) => image?.id,
        renderQueue({items, selected, selectById}) {
            queueList.replaceChildren(...items.map((image) => buildQueueItem(image, selected, selectById)));
        },
        onQueueError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'Could not load the delete queue.',
            }));
            imageViewer.showStatus('The delete queue could not be loaded.');
        },
        onEmpty() {
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'No images are waiting for deletion.',
            }));
            imageViewer.clear('No images are waiting for deletion.');
        },
        onSelect(image) {
            for (const button of queueList.querySelectorAll('.review-select')) {
                const selected = button.getAttribute('data-image-id') === String(image.id);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            }
        },
        onDetailsLoading(image) {
            imageViewer.update(image, {status: 'Loading full-size image…'});
        },
        onDetailsLoaded(image, details) {
            imageViewer.update({...image, ...details});
        },
        onDetailsUnavailable(image) {
            imageViewer.update(image, {status: 'Full-size image details are unavailable.'});
        },
        onDetailsError(error) {
            imageViewer.showStatus('Full-size image could not be loaded.');
            showMessage(status, messageForStatus(error?.status ?? 0));
        },
        onUnauthorized: () => navigate(RoutePath.LOGIN),
        onClearStatus: () => clearMessage(status),
        onActionSuccess(action, image) {
            showMessage(status, action === 'restore'
                ? `Image #${image.id} restored.`
                : `Image #${image.id} deleted permanently.`, 'success');
        },
        onActionError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
        },
        setActionsDisabled: setActionButtonsDisabled,
        shouldCacheDetails: (details) => Boolean(details?.downloadSrc),
    });

    async function deleteAllQueued() {
        const queuedCount = deleteQueue.getItems().length;
        if (deleteAllPending || queuedCount === 0) {
            return;
        }

        deleteAllPending = true;
        try {
            const dialog = createConfirmDialog({
                message: `Permanently delete ${queuedCount} queued image${queuedCount === 1 ? '' : 's'}? This cannot be undone.`,
                confirmLabel: 'Delete all permanently',
                cancelLabel: 'Cancel',
            });
            if (!(await dialog.result)) {
                return;
            }

            clearMessage(status);
            setActionButtonsDisabled(true);
            const result = await postJson(DELETE_ALL_URL);
            await deleteQueue.load();
            const deletedCount = Number.isSafeInteger(result?.deletedCount)
                ? result.deletedCount
                : queuedCount;
            showMessage(status, deletedCount === 0
                ? 'No images were waiting for deletion.'
                : `${deletedCount} image${deletedCount === 1 ? '' : 's'} deleted permanently.`, 'success');
        } catch (error) {
            if ((error?.status ?? 0) === 401) {
                navigate(RoutePath.LOGIN);
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
            if ((error?.status ?? 0) !== 403) {
                await deleteQueue.load();
            }
        } finally {
            deleteAllPending = false;
            setActionButtonsDisabled(deleteQueue.getItems().length === 0);
        }
    }

    restoreButton.addEventListener('click', () => {
        if (!deleteAllPending) {
            void deleteQueue.act('restore');
        }
    });
    deleteButton.addEventListener('click', () => {
        if (!deleteAllPending) {
            void deleteQueue.act('delete');
        }
    });
    deleteAllButton.addEventListener('click', () => {
        void deleteAllQueued();
    });
    const handleKeyboard = (event) => {
        if (!deleteAllPending) {
            deleteQueue.handleKeyboard(event);
        }
    };
    registerViewHotkeys(page, {
        ArrowLeft: handleKeyboard,
        ArrowUp: handleKeyboard,
        ArrowRight: handleKeyboard,
        ArrowDown: handleKeyboard,
        Enter: handleKeyboard,
        Delete: handleKeyboard,
        Backspace: handleKeyboard,
    });
    deleteQueue.load();
    return page;
}
