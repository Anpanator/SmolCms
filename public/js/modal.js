/**
 * Small dependency-free modal and confirmation dialog helpers.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';

let nextConfirmDialogId = 1;
const activeModals = [];

/**
 * @param {{content?: Node|string|number|Array<*>|null, labelledBy?: string}} [options]
 * @returns {{element: HTMLDivElement, close: () => void}}
 */
export function createModal({content = null, labelledBy} = {}) {
    const previouslyFocused = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const attributes = {
        role: 'dialog',
        'aria-modal': 'true',
        tabindex: '-1',
    };
    if (typeof labelledBy === 'string' && labelledBy !== '') {
        attributes['aria-labelledby'] = labelledBy;
    }

    const element = el('div', {
        className: 'modal-backdrop',
        attributes,
        children: el('div', {className: 'modal-panel', children: content}),
    });
    let isOpen = true;

    function close() {
        if (!isOpen) {
            return;
        }
        isOpen = false;
        document.removeEventListener('keydown', handleKeyDown);
        element.removeEventListener('click', handleBackdropClick);
        const index = activeModals.indexOf(element);
        if (index >= 0) {
            activeModals.splice(index, 1);
        }
        element.dispatchEvent(new Event('modalclose'));
        element.remove();
        if (previouslyFocused?.isConnected) {
            previouslyFocused.focus();
        }
    }

    function handleBackdropClick(event) {
        if (event.target === element) {
            close();
        }
    }

    function handleKeyDown(event) {
        if (event.key === 'Escape' && activeModals[activeModals.length - 1] === element) {
            event.preventDefault();
            close();
        }
    }

    element.addEventListener('click', handleBackdropClick);
    document.addEventListener('keydown', handleKeyDown);
    activeModals.push(element);
    document.body.append(element);
    element.focus();

    return {element, close};
}

/**
 * @param {{message: string|Node, confirmLabel?: string, cancelLabel?: string}} [options]
 * @returns {{element: HTMLDivElement, close: () => void, result: Promise<boolean>}}
 */
export function createConfirmDialog({message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel'} = {}) {
    const titleId = `confirm-dialog-title-${nextConfirmDialogId++}`;
    let settleResult;
    let settled = false;
    const result = new Promise((resolve) => {
        settleResult = resolve;
    });
    const cancelButton = el('button', {
        className: 'button modal-cancel',
        attributes: {type: 'button'},
        children: cancelLabel,
    });
    const confirmButton = el('button', {
        className: 'button button--primary',
        attributes: {type: 'button'},
        children: confirmLabel,
    });
    const content = el('div', {
        className: 'confirm-dialog',
        children: [
            el('h2', {
                className: 'modal-title',
                attributes: {id: titleId},
                children: 'Confirm action',
            }),
            el('p', {className: 'modal-message', children: message}),
            el('div', {className: 'modal-actions', children: [cancelButton, confirmButton]}),
        ],
    });
    const modal = createModal({content, labelledBy: titleId});

    function finish(confirmed) {
        if (settled) {
            return;
        }
        settled = true;
        settleResult(confirmed);
        modal.close();
    }

    modal.element.addEventListener('modalclose', () => {
        if (!settled) {
            settled = true;
            settleResult(false);
        }
    }, {once: true});
    cancelButton.addEventListener('click', () => finish(false));
    confirmButton.addEventListener('click', () => finish(true));

    return {element: modal.element, close: modal.close, result};
}
