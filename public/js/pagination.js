/**
 * Shared previous/next pagination controls.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';

/**
 * @param {number} initialPage
 * @param {(page: number) => void} onPageChange
 * @param {{previousLabel?: string, nextLabel?: string}} [labels]
 * @returns {{
 *   element: HTMLElement,
 *   update: (state: {page: number, total: number, perPage: number}) => void,
 *   loading: (page: number) => void
 * }}
 */
export function createPagination(initialPage, onPageChange, {
    previousLabel = 'Previous',
    nextLabel = 'Next',
} = {}) {
    let currentPage = initialPage;
    const previous = el('button', {
        className: 'pagination-button pagination-button--previous',
        attributes: {type: 'button', disabled: '', 'aria-label': 'Previous page'},
        children: previousLabel,
    });
    const label = el('span', {className: 'pagination-label', children: 'Loading…'});
    const next = el('button', {
        className: 'pagination-button pagination-button--next',
        attributes: {type: 'button', disabled: '', 'aria-label': 'Next page'},
        children: nextLabel,
    });
    const element = el('nav', {
        className: 'pagination',
        attributes: {'aria-label': 'Pages'},
        children: [previous, label, next],
    });

    previous.addEventListener('click', () => {
        if (!previous.disabled) {
            onPageChange(currentPage - 1);
        }
    });
    next.addEventListener('click', () => {
        if (!next.disabled) {
            onPageChange(currentPage + 1);
        }
    });

    return {element, update, loading};

    function loading(page) {
        currentPage = page;
        label.textContent = 'Loading…';
        previous.disabled = true;
        next.disabled = true;
    }

    function update({page, total, perPage}) {
        currentPage = page;
        const pageCount = Math.max(1, Math.ceil(total / perPage));
        label.textContent = `Page ${page} of ${pageCount}`;
        previous.disabled = page <= 1;
        next.disabled = page >= pageCount;
    }
}
