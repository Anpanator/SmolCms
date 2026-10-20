/**
 * Reusable comma-separated tag search with debounced backend suggestions.
 * Importing this module has no side effects.
 */
import {get} from './api.js';
import {el} from './dom.js';
import {createSearchForm} from './searchForm.js';

const TAG_SUGGESTIONS_URL = '/api/tags/suggest';
const DEBOUNCE_MS = 250;
const MAX_SUGGESTIONS = 15;

let nextSuggestionsId = 1;

/** Normalize comma-separated tag input for API and URL use. */
export function normalizeTags(value) {
    return String(value ?? '')
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean)
        .join(',');
}

/**
 * @param {{ onSearch?: (tags: string) => void, initialTags?: string }} [options]
 * @returns {{
 *   element: HTMLFormElement,
 *   input: HTMLInputElement,
 *   suggestions: HTMLUListElement|null,
 *   getValue: () => string,
 *   setValue: (value: string) => void,
 *   clear: () => void,
 * }}
 */
export function createSearchView({onSearch = () => {}, initialTags = ''} = {}) {
    let autocomplete = {close: () => {}};
    const search = createSearchForm({
        className: 'search-view',
        inputClassName: 'search-input',
        inputType: 'text',
        inputLabel: 'Search tags',
        placeholder: 'Search tags',
        initialValue: initialTags,
        submitText: 'Search tags',
        submitClassName: 'search-submit',
        submitContent: el('span', {
            className: 'search-icon',
            attributes: { 'aria-hidden': 'true' },
        }),
        controlsClassName: 'search-control',
        hasSuggestions: true,
        onSearch(value) {
            autocomplete.close();
            onSearch(normalizeTags(value));
        },
    });
    autocomplete = attachTagSuggestions({input: search.input, suggestions: search.suggestions, container: search.element});
    return search;
}

/**
 * Attach the shared comma-segment completion behavior to a text input.
 *
 * @param {{input: HTMLInputElement, suggestions: HTMLUListElement, container?: HTMLElement}} options
 * @returns {{close: () => void}}
 */
export function attachTagSuggestions({input, suggestions, container = input.parentElement} = {}) {
    const suggestionsId = suggestions.id || input.getAttribute('aria-controls') || `tag-suggestions-${nextSuggestionsId++}`;
    suggestions.id = suggestionsId;
    input.setAttribute('aria-controls', suggestionsId);

    let debounceTimer = null;
    let requestVersion = 0;
    let resultTags = [];
    let activeIndex = -1;
    let outsideListenerAttached = false;

    function setSuggestionsVisible(visible) {
        suggestions.hidden = !visible;
        input.setAttribute('aria-expanded', String(visible));
        if (!visible) {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function detachOutsideListener() {
        if (!outsideListenerAttached) {
            return;
        }
        document.removeEventListener('pointerdown', handleOutsidePointerDown);
        outsideListenerAttached = false;
    }

    function attachOutsideListener() {
        if (outsideListenerAttached) {
            return;
        }
        document.addEventListener('pointerdown', handleOutsidePointerDown);
        outsideListenerAttached = true;
    }

    function clearPendingRequest() {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        requestVersion++;
    }

    function closeSuggestions() {
        clearPendingRequest();
        resultTags = [];
        activeIndex = -1;
        suggestions.replaceChildren();
        setSuggestionsVisible(false);
        detachOutsideListener();
    }

    function handleOutsidePointerDown(event) {
        if (!container?.contains(event.target)) {
            closeSuggestions();
        }
    }

    function updateActiveSuggestion() {
        const options = suggestions.querySelectorAll('[role="option"]');
        options.forEach((option, index) => {
            const active = index === activeIndex;
            option.classList.toggle('search-suggestion--active', active);
            option.setAttribute('aria-selected', String(active));
        });
        if (activeIndex >= 0) {
            input.setAttribute('aria-activedescendant', `${suggestionsId}-option-${activeIndex}`);
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function renderSuggestions() {
        suggestions.replaceChildren(...resultTags.map((tag, index) => {
            const option = el('li', {
                className: 'search-suggestion',
                attributes: {
                    id: `${suggestionsId}-option-${index}`,
                    role: 'option',
                    'aria-selected': 'false',
                    'data-tag': tag,
                },
                children: tag,
            });
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => selectSuggestion(tag));
            return option;
        }));

        const visible = resultTags.length > 0;
        setSuggestionsVisible(visible);
        if (visible) {
            attachOutsideListener();
            updateActiveSuggestion();
        } else {
            detachOutsideListener();
        }
    }

    function selectSuggestion(tag) {
        const commaIndex = input.value.lastIndexOf(',');
        const prefix = commaIndex >= 0 ? `${input.value.slice(0, commaIndex + 1)} ` : '';
        input.value = `${prefix}${tag}, `;
        closeSuggestions();
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }

    function scheduleSuggestions() {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        requestVersion++;
        const version = requestVersion;
        resultTags = [];
        activeIndex = -1;
        suggestions.replaceChildren();
        setSuggestionsVisible(false);
        detachOutsideListener();

        const commaIndex = input.value.lastIndexOf(',');
        const prefix = input.value.slice(commaIndex + 1).replace(/^\s+/, '');
        if (prefix.length < 2) {
            return;
        }

        debounceTimer = setTimeout(async () => {
            debounceTimer = null;
            try {
                const data = await get(`${TAG_SUGGESTIONS_URL}?q=${encodeURIComponent(prefix)}`);
                if (version !== requestVersion) {
                    return;
                }
                resultTags = (Array.isArray(data?.tags) ? data.tags : [])
                    .filter((tag) => typeof tag === 'string' && tag.length > 0)
                    .slice(0, MAX_SUGGESTIONS);
                renderSuggestions();
            } catch {
                if (version === requestVersion) {
                    resultTags = [];
                    renderSuggestions();
                }
            }
        }, DEBOUNCE_MS);
    }

    input.addEventListener('input', scheduleSuggestions);
    input.addEventListener('blur', closeSuggestions);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && resultTags.length > 0) {
            event.preventDefault();
            activeIndex = (activeIndex + 1) % resultTags.length;
            updateActiveSuggestion();
            return;
        }
        if (event.key === 'ArrowUp' && resultTags.length > 0) {
            event.preventDefault();
            activeIndex = activeIndex <= 0 ? resultTags.length - 1 : activeIndex - 1;
            updateActiveSuggestion();
            return;
        }
        if (event.key === 'Enter' && !suggestions.hidden && activeIndex >= 0) {
            event.preventDefault();
            selectSuggestion(resultTags[activeIndex]);
            return;
        }
        if (event.key === 'Escape' && !suggestions.hidden) {
            event.preventDefault();
            closeSuggestions();
        }
    });

    return {close: closeSuggestions};
}
