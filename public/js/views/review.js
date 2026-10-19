/**
 * Review view: moderation queue for users with MOD access or higher.
 * Lists pending images with approve/reject actions backed by
 * /api/review/*; the server enforces the real access rules.
 * Importing this module has no side effects.
 */
import { get, postJson } from '../api.js';
import { el } from '../dom.js';
import {
    clearMessage,
    createMessageBox,
    isAuthError,
    messageForStatus,
    showMessage,
} from '../forms.js';

const QUEUE_URL = '/api/review/queue';
const APPROVE_URL = '/api/review/approve';
const REJECT_URL = '/api/review/reject';
/** AccessLevel::MOD — keep aligned with src/Data/Constant/AccessLevel.php. */
const MIN_REVIEW_ACCESS_LEVEL = 500;

export function createReviewView({ session, navigate }) {
    const status = createMessageBox();
    const queueBox = el('div', { className: 'review-queue' });

    async function loadQueue() {
        try {
            const data = await get(QUEUE_URL);
            renderQueue(Array.isArray(data?.images) ? data.images : []);
        } catch (error) {
            if (isAuthError(error)) {
                navigate('/login');
                return;
            }
            showMessage(status, messageForStatus(error?.status ?? 0));
        }
    }

    function renderQueue(images) {
        if (images.length === 0) {
            queueBox.replaceChildren(el('p', {
                className: 'review-empty',
                children: 'No images are waiting for review.',
            }));
            return;
        }
        queueBox.replaceChildren(...images.map(buildQueueItem));
    }

    function buildQueueItem(image) {
        const thumb = image.thumbSrc
            ? el('img', { className: 'review-thumb', attributes: { src: image.thumbSrc, alt: '' } })
            : el('span', {
                className: 'review-thumb review-thumb--empty',
                attributes: { 'aria-hidden': 'true' },
                children: 'No preview',
            });
        const approveButton = el('button', {
            className: 'button button--primary',
            attributes: { type: 'button', 'data-action': 'approve' },
            children: 'Approve',
        });
        const rejectButton = el('button', {
            className: 'button button--danger',
            attributes: { type: 'button', 'data-action': 'reject' },
            children: 'Reject',
        });

        const item = el('li', {
            className: 'review-item',
            children: [
                thumb,
                el('div', {
                    className: 'review-info',
                    children: [
                        el('span', { className: 'review-uploader', children: image.uploader ?? 'Unknown uploader' }),
                        el('span', { className: 'review-date', children: image.uploadDate ?? '' }),
                    ],
                }),
                el('div', { className: 'review-actions', children: [approveButton, rejectButton] }),
            ],
        });

        async function act(action) {
            clearMessage(status);
            approveButton.disabled = true;
            rejectButton.disabled = true;
            try {
                await postJson(`${action === 'approve' ? APPROVE_URL : REJECT_URL}?id=${image.id}`);
                item.remove();
                if (queueBox.querySelector('.review-item') === null) {
                    renderQueue([]);
                }
                showMessage(status, action === 'approve'
                    ? `Image #${image.id} approved.`
                    : `Image #${image.id} rejected.`, 'success');
            } catch (error) {
                approveButton.disabled = false;
                rejectButton.disabled = false;
                if (isAuthError(error)) {
                    navigate('/login');
                    return;
                }
                showMessage(status, messageForStatus(error?.status ?? 0));
                if ((error?.status ?? 0) !== 403) {
                    await loadQueue();
                }
            }
        }

        approveButton.addEventListener('click', () => act('approve'));
        rejectButton.addEventListener('click', () => act('reject'));
        return item;
    }

    if (!hasReviewAccess(session.getUser())) {
        return el('section', {
            className: 'page review-page',
            children: [
                el('h1', { className: 'page-title', children: 'Review' }),
                el('p', {
                    className: 'notice notice--warning review-denied',
                    children: 'You do not have permission to review images.',
                }),
            ],
        });
    }

    loadQueue();
    return el('section', {
        className: 'page review-page',
        children: [el('h1', { className: 'page-title', children: 'Review' }), status, queueBox],
    });
}

function hasReviewAccess(user) {
    return (user?.accessLevel ?? 0) >= MIN_REVIEW_ACCESS_LEVEL;
}
