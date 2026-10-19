/**
 * Plain single-input search form without tag completion or query parsing.
 * Importing this module has no side effects.
 */
import {el} from './dom.js';

let nextSearchBoxId = 1;

/**
 * @param {{
 *   placeholder?: string,
 *   buttonText?: string,
 *   onSearch?: (query: string) => void,
 *   onReset?: () => void
 * }} [options]
 * @returns {{
 *   element: HTMLFormElement,
 *   getValue: () => string,
 *   setValue: (value: string) => void,
 *   clear: () => void
 * }}
 */
export function createSearchBox({
    placeholder = '',
    buttonText = 'Search',
    onSearch = () => {},
    onReset,
} = {}) {
    const id = nextSearchBoxId++;
    const labelText = placeholder || 'Search';
    const input = el('input', {
        className: 'field-input search-box-input',
        attributes: {
            id: `search-box-input-${id}`,
            type: 'search',
            placeholder,
        },
    });
    const children = [
        el('label', {
            className: 'search-label',
            attributes: {for: input.id},
            children: labelText,
        }),
        input,
        el('button', {
            className: 'button button--primary search-box-submit',
            attributes: {type: 'submit'},
            children: buttonText,
        }),
    ];

    if (typeof onReset === 'function') {
        children.push(el('button', {
            className: 'button search-box-reset',
            attributes: {type: 'button'},
            children: 'Reset',
        }));
    }

    const element = el('form', {
        className: 'search-box',
        attributes: {role: 'search'},
        children,
    });

    function clear() {
        input.value = '';
    }

    element.addEventListener('submit', (event) => {
        event.preventDefault();
        onSearch(input.value);
    });

    if (typeof onReset === 'function') {
        element.querySelector('.search-box-reset').addEventListener('click', () => {
            clear();
            onReset();
        });
    }

    return {
        element,
        getValue: () => input.value,
        setValue: (value) => {
            input.value = String(value ?? '');
        },
        clear,
    };
}
