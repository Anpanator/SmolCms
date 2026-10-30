/**
 * Mod Queue: moderation queue for users with MOD access or higher.
 * The queue endpoint provides thumbnails; image details provide the full-size
 * download variant used for review. The server enforces the real access rules.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel, hasAccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createImageViewer} from '../imageViewer.js';
import {createModerationQueue} from '../moderationQueue.js';
import {clearMessage, createMessageBox, messageForStatus, showMessage} from '../forms.js';
import {userProfileHref} from '../routes.js';
import {registerViewHotkeys} from '../hotkeys.js';

const QUEUE_URL = '/api/review/queue';
const IMAGE_DETAIL_URL = '/api/image/detail';
const APPROVE_URL = '/api/review/approve';
const REJECT_URL = '/api/review/reject';

export function createReviewView({ session, navigate }) {
    if (!hasAccessLevel(session.getUser(), AccessLevel.MOD)) {
        return el('section', {
            className: 'page review-page review-denied-page',
            attributes: {tabindex: '-1'},
            children: [
                el('h1', {className: 'page-title', children: 'Mod Queue'}),
                el('p', {
                    className: 'notice notice--warning review-denied',
                    children: 'You do not have permission to moderate images.',
                }),
            ],
        });
    }

    const status = createMessageBox();
    const imageViewer = createImageViewer();
    imageViewer.showStatus('Loading moderation queue…');
    const imageHolder = imageViewer.element;
    const metadataList = imageViewer.metadata;
    const queueList = el('ul', {
        className: 'review-list',
        children: el('li', {className: 'review-empty', children: 'Loading queue…'}),
    });
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
        attributes: {tabindex: '0', 'aria-label': 'Mod Queue'},
        children: el('div', {
            className: 'review-workspace',
            children: [
                el('section', {
                    className: 'review-image-panel',
                    attributes: {'aria-label': 'Full-size image for review'},
                    children: imageHolder,
                }),
                el('aside', {
                    className: 'review-sidebar',
                    children: [
                        el('h1', {className: 'review-title', children: 'Mod Queue'}),
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
                'aria-label': `Select image ${image.id}, uploaded by ${uploader}${uploadDate ? ` on ${uploadDate}` : ''}`,
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
        approveButton.disabled = disabled;
        rejectButton.disabled = disabled;
    }

    const moderationQueue = createModerationQueue({
        fetchQueue: async () => {
            const data = await get(QUEUE_URL);
            return Array.isArray(data?.images) ? data.images : [];
        },
        fetchDetails: async (_image, imageId) => {
            const data = await get(`${IMAGE_DETAIL_URL}?id=${imageId}`);
            return data?.image ?? null;
        },
        submitAction: (action, image) => postJson(
            `${action === 'approve' ? APPROVE_URL : REJECT_URL}?id=${image.id}`,
        ),
        getImageId: (image) => image?.id,
        renderQueue({items, selected, selectById}) {
            queueList.replaceChildren(...items.map((image) => buildQueueItem(image, selected, selectById)));
        },
        onQueueError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'Could not load the moderation queue.',
            }));
            imageViewer.showStatus('The moderation queue could not be loaded.');
        },
        onEmpty() {
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'No images are waiting for moderation.',
            }));
            imageViewer.clear('No images are waiting for moderation.');
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
        onUnauthorized: () => navigate('/login'),
        onClearStatus: () => clearMessage(status),
        onActionSuccess(action, image) {
            showMessage(status, action === 'approve'
                ? `Image #${image.id} approved.`
                : `Image #${image.id} rejected.`, 'success');
        },
        onActionError(error) {
            showMessage(status, messageForStatus(error?.status ?? 0));
        },
        setActionsDisabled: setActionButtonsDisabled,
        shouldCacheDetails: (details) => Boolean(details?.downloadSrc),
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
