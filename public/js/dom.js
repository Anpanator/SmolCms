/**
 * Tiny DOM builder shared by all view and nav modules.
 * Has no side effects at import time.
 */

/**
 * Create an element with class name, attributes and children in one call.
 * Children may be nested arrays, Nodes or primitives; null/false is skipped.
 */
export function el(tag, { className = '', attributes = {}, children = [] } = {}) {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    for (const [name, value] of Object.entries(attributes)) {
        node.setAttribute(name, value);
    }
    for (const child of [children].flat(Infinity)) {
        if (child === null || child === undefined || child === false) {
            continue;
        }
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
    return node;
}
