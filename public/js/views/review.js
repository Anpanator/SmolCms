/**
 * Mod Queue: moderation queue for users with MOD access or higher.
 * The queue endpoint provides thumbnails; image details provide the full-size
 * download variant used for review. The server enforces the real access rules.
 * Importing this module has no side effects.
 */
import {get, postJson} from '../api.js';
import {AccessLevel} from '../accessLevel.js';
import {el} from '../dom.js';
import {createImageViewer} from '../imageViewer.js';
import {clearMessage, createMessageBox, isAuthError, messageForStatus, showMessage,} from '../forms.js';

const QUEUE_URL = '/api/review/queue';
const IMAGE_DETAIL_URL = '/api/image/detail';
const APPROVE_URL = '/api/review/approve';
const REJECT_URL = '/api/review/reject';

export function createReviewView({ session, navigate }) {
    if (!hasReviewAccess(session.getUser())) {
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

    let queueImages = [];
    let activeImage = null;
    let activeIndex = -1;
    let detailRequestId = 0;
    let loadingImageId = null;
    let actionPending = false;
    const imageDetails = new Map();

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

    approveButton.addEventListener('click', () => act('approve'));
    rejectButton.addEventListener('click', () => act('reject'));
    page.addEventListener('keydown', handleKeyboard);

    loadQueue();
    return page;

    async function loadQueue() {
        try {
            const data = await get(QUEUE_URL);
            queueImages = Array.isArray(data?.images) ? data.images : [];
            renderQueue();
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'Could not load the moderation queue.',
            }));
            imageViewer.showStatus('The moderation queue could not be loaded.');
        }
    }

    function renderQueue(preferredIndex = null) {
        if (queueImages.length === 0) {
            activeImage = null;
            activeIndex = -1;
            detailRequestId++;
            loadingImageId = null;
            queueList.replaceChildren(el('li', {
                className: 'review-empty',
                children: 'No images are waiting for moderation.',
            }));
            imageViewer.clear('No images are waiting for moderation.');
            setActionButtonsDisabled(true);
            return;
        }

        const previousImageId = activeImage?.id;
        queueList.replaceChildren(...queueImages.map(buildQueueItem));

        let nextIndex = Number.isInteger(preferredIndex)
            ? preferredIndex
            : queueImages.findIndex((image) => image.id === previousImageId);
        if (nextIndex < 0 || nextIndex >= queueImages.length) {
            nextIndex = 0;
        }
        selectImage(nextIndex);
    }

    function buildQueueItem(image) {
        const uploader = image.uploader ?? 'Unknown uploader';
        const uploadDate = image.uploadDate ?? '';
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
                'aria-pressed': image.id === activeImage?.id ? 'true' : 'false',
                'aria-label': `Select image ${image.id}, uploaded by ${uploader}${uploadDate ? ` on ${uploadDate}` : ''}`,
            },
            children: [
                thumb,
                el('span', {
                    className: 'review-info',
                    children: [
                        el('span', {className: 'review-uploader', children: uploader}),
                        el('span', {className: 'review-date', children: uploadDate}),
                    ],
                }),
            ],
        });
        const item = el('li', {
            className: 'review-item',
            attributes: {'data-image-id': image.id},
            children: selectButton,
        });
        selectButton.addEventListener('click', () => selectImageById(image.id));
        return item;
    }

    function selectImageById(id) {
        const index = queueImages.findIndex((image) => image.id === id);
        if (index >= 0) {
            selectImage(index);
        }
    }

    function selectImage(index) {
        const image = queueImages[index];
        if (!image) {
            return;
        }

        const sameImage = activeImage?.id === image.id;
        activeImage = image;
        activeIndex = index;
        for (const button of queueList.querySelectorAll('.review-select')) {
            const selected = button.getAttribute('data-image-id') === String(image.id);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        }
        setActionButtonsDisabled(actionPending);

        const cachedDetails = imageDetails.get(image.id);
        if (cachedDetails) {
            renderActiveImage(image, cachedDetails);
            return;
        }
        if (sameImage && loadingImageId === image.id) {
            return;
        }

        imageViewer.update(image, {status: 'Loading full-size image…'});
        const requestId = ++detailRequestId;
        loadingImageId = image.id;
        get(`${IMAGE_DETAIL_URL}?id=${image.id}`)
            .then((data) => {
                if (requestId !== detailRequestId || activeImage?.id !== image.id) {
                    return;
                }
                loadingImageId = null;
                const details = data?.image;
                if (!details) {
                    imageViewer.update(image, {status: 'Full-size image details are unavailable.'});
                    return;
                }
                if (details.downloadSrc) {
                    imageDetails.set(image.id, details);
                }
                renderActiveImage(image, details);
            })
            .catch((error) => {
                if (requestId !== detailRequestId || activeImage?.id !== image.id) {
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

    function renderActiveImage(queueImage, details) {
        imageViewer.update({...queueImage, ...details});
    }

    async function act(action) {
        const image = activeImage;
        if (!image || actionPending) {
            return;
        }

        clearMessage(status);
        actionPending = true;
        setActionButtonsDisabled(true);
        try {
            await postJson(`${action === 'approve' ? APPROVE_URL : REJECT_URL}?id=${image.id}`);
            const removedIndex = queueImages.findIndex((item) => item.id === image.id);
            if (removedIndex < 0) {
                return;
            }

            const previousActiveId = activeImage?.id;
            queueImages.splice(removedIndex, 1);
            imageDetails.delete(image.id);
            const nextIndex = previousActiveId === image.id
                ? Math.min(removedIndex, queueImages.length - 1)
                : queueImages.findIndex((item) => item.id === previousActiveId);
            if (previousActiveId === image.id) {
                activeImage = null;
            }
            showMessage(status, action === 'approve'
                ? `Image #${image.id} approved.`
                : `Image #${image.id} rejected.`, 'success');
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
            setActionButtonsDisabled(!activeImage);
        }
    }

    function handleKeyboard(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]'))) {
            return;
        }
        if (!activeImage) {
            return;
        }

        switch (event.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                selectImage(Math.max(0, activeIndex - 1));
                break;
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                selectImage(Math.min(queueImages.length - 1, activeIndex + 1));
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

function hasReviewAccess(user) {
    return (user?.accessLevel ?? 0) >= AccessLevel.MOD;
}
