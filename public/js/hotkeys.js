/** Central keyboard shortcuts for the active page view. */

export const HOTKEY_KEYS = Object.freeze([
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Enter',
    'Delete',
    'Backspace',
]);

const registeredHotkeys = new Set(HOTKEY_KEYS);
const viewHandlers = new WeakMap();
const keyboardEventTypes = ['keydown', 'keypress', 'keyup'];

/**
 * Associate this view's supported page shortcuts with their handlers.
 *
 * @param {HTMLElement} view
 * @param {Record<string, (event: KeyboardEvent) => void>} handlers
 * @returns {() => void} unregister function
 */
export function registerViewHotkeys(view, handlers) {
    const hotkeys = new Map();
    for (const [key, handler] of Object.entries(handlers)) {
        if (!registeredHotkeys.has(key)) {
            throw new TypeError(`Unsupported page hotkey: ${key}`);
        }
        if (typeof handler !== 'function') {
            throw new TypeError(`The handler for ${key} must be a function`);
        }
        hotkeys.set(key, handler);
    }

    viewHandlers.set(view, hotkeys);
    return () => {
        if (viewHandlers.get(view) === hotkeys) {
            viewHandlers.delete(view);
        }
    };
}

/**
 * Delegate page shortcuts from a shell container to its active view. The
 * container can include elements outside the view (such as the site header),
 * while only the active view's registered shortcuts are invoked. A document
 * fallback also handles keys when focus has fallen outside the app container.
 *
 * @param {HTMLElement} container
 * @param {() => HTMLElement|null} getActiveView
 * @returns {{destroy: () => void}}
 */
export function createHotkeyHandler(container, getActiveView = () => container.firstElementChild) {
    function stopEditableKeyEvent(event) {
        if (!isEditableTarget(event.target) || !(event.target instanceof HTMLElement)) {
            return;
        }

        // Install this at the event target during capture. Target-local input
        // handlers still run, but the key event cannot reach page hotkeys or
        // bubble further up the document tree.
        event.target.addEventListener(event.type, stopPropagation, {once: true});
    }

    function handleKeyDown(event) {
        if (isEditableTarget(event.target)) {
            event.stopPropagation();
            return;
        }
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || document.querySelector('.modal-backdrop') !== null || !registeredHotkeys.has(event.key)) {
            return;
        }

        const activeView = getActiveView();
        const handler = activeView instanceof HTMLElement
            ? viewHandlers.get(activeView)?.get(event.key)
            : null;
        handler?.(event);
    }

    function handleOutsideKeyDown(event) {
        if (event.target instanceof Node && container.contains(event.target)) {
            return;
        }
        handleKeyDown(event);
    }

    for (const eventType of keyboardEventTypes) {
        container.addEventListener(eventType, stopEditableKeyEvent, true);
    }
    container.addEventListener('keydown', handleKeyDown);
    document.addEventListener('keydown', handleOutsideKeyDown);

    return {
        destroy() {
            for (const eventType of keyboardEventTypes) {
                container.removeEventListener(eventType, stopEditableKeyEvent, true);
            }
            container.removeEventListener('keydown', handleKeyDown);
            document.removeEventListener('keydown', handleOutsideKeyDown);
        },
    };
}

function isEditableTarget(target) {
    return target instanceof Element && (
        target.closest('input, textarea, select, [contenteditable="true"]') !== null
        || (target instanceof HTMLElement && target.isContentEditable)
    );
}

function stopPropagation(event) {
    event.stopPropagation();
}
