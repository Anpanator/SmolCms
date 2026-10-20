/** Shared accessible shell for all role="search" forms. */
import {el} from './dom.js';

let nextSearchFormId = 1;

/**
 * @param {{
 *   className?: string,
 *   inputClassName?: string,
 *   inputType?: string,
 *   inputLabel?: string,
 *   placeholder?: string,
 *   initialValue?: string,
 *   inputAttributes?: Record<string, string>,
 *   submitText?: string,
 *   submitLabel?: string,
 *   submitClassName?: string,
 *   submitContent?: Node|string,
 *   controlsClassName?: string,
 *   hasSuggestions?: boolean,
 *   suggestionsClassName?: string,
 *   onSearch?: (value: string) => void,
 *   onReset?: () => void,
 *   resetText?: string,
 *   resetClassName?: string,
 * }} [options]
 */
export function createSearchForm({
    className = 'search-form',
    inputClassName = 'search-input',
    inputType = 'search',
    inputLabel = 'Search',
    placeholder = '',
    initialValue = '',
    inputAttributes = {},
    submitText = 'Search',
    submitLabel = submitText,
    submitClassName = 'button',
    submitContent = submitText,
    controlsClassName = '',
    hasSuggestions = false,
    suggestionsClassName = 'search-suggestions',
    onSearch = () => {},
    onReset,
    resetText = 'Reset',
    resetClassName = 'button search-box-reset',
} = {}) {
    const id = nextSearchFormId++;
    const suggestionsId = `search-form-suggestions-${id}`;
    const input = el('input', {
        className: inputClassName,
        attributes: {
            ...inputAttributes,
            id: `search-form-input-${id}`,
            type: inputType,
            ...(placeholder === '' ? {} : {placeholder}),
            ...(hasSuggestions ? {
                autocomplete: 'off',
                role: 'combobox',
                'aria-autocomplete': 'list',
                'aria-expanded': 'false',
                'aria-controls': suggestionsId,
            } : {}),
        },
    });
    input.value = String(initialValue ?? '');

    const submitButton = el('button', {
        className: submitClassName,
        attributes: {
            type: 'submit',
            ...(submitLabel === null ? {} : {'aria-label': submitLabel}),
        },
        children: submitContent,
    });
    const controls = controlsClassName
        ? el('div', {className: controlsClassName, children: [input, submitButton]})
        : [input, submitButton];
    const suggestions = hasSuggestions ? el('ul', {
        className: suggestionsClassName,
        attributes: {id: suggestionsId, role: 'listbox', hidden: ''},
    }) : null;
    const resetButton = typeof onReset === 'function' ? el('button', {
        className: resetClassName,
        attributes: {type: 'button'},
        children: resetText,
    }) : null;

    const element = el('form', {
        className,
        attributes: {role: 'search'},
        children: [
            el('label', {
                className: 'search-label',
                attributes: {for: input.id},
                children: inputLabel || placeholder || 'Search',
            }),
            controls,
            suggestions,
            resetButton,
        ],
    });

    function clear() {
        input.value = '';
    }

    element.addEventListener('submit', (event) => {
        event.preventDefault();
        onSearch(input.value);
    });
    resetButton?.addEventListener('click', () => {
        clear();
        onReset();
    });

    return {
        element,
        input,
        suggestions,
        getValue: () => input.value,
        setValue: (value) => {
            input.value = String(value ?? '');
        },
        clear,
    };
}
