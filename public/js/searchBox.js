/**
 * Plain single-input search form without tag completion or query parsing.
 * Importing this module has no side effects.
 */
import {createSearchForm} from './searchForm.js';

/**
 * @param {{
 *   placeholder?: string,
 *   buttonText?: string,
 *   onSearch?: (query: string) => void,
 *   onReset?: () => void
 * }} [options]
 * @returns {{
 *   element: HTMLFormElement,
 *   input: HTMLInputElement,
 *   suggestions: null,
 *   getValue: () => string,
 *   setValue: (value: string) => void,
 *   clear: () => void,
 * }}
 */
export function createSearchBox({
    placeholder = '',
    buttonText = 'Search',
    onSearch = () => {},
    onReset,
} = {}) {
    return createSearchForm({
        className: 'search-box',
        inputClassName: 'field-input search-box-input',
        inputType: 'search',
        inputLabel: placeholder || 'Search',
        placeholder,
        submitText: buttonText,
        submitClassName: 'button button--primary search-box-submit',
        onSearch,
        onReset,
    });
}
