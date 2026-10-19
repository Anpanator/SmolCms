/**
 * Reusable comma-separated tag search with debounced backend suggestions.
 * Importing this module has no side effects.
 */
import {get} from './api.js';
import {el} from './dom.js';

const TAG_SUGGESTIONS_URL = '/api/tags/suggest';
const DEBOUNCE_MS = 250;
const MAX_SUGGESTIONS = 15;

let nextSearchViewId = 1;

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
 * @returns {{ element: HTMLFormElement, input: HTMLInputElement, suggestions: HTMLUListElement }}
 */
export function createSearchView({onSearch = () => {}, initialTags = ''} = {}) {
    const id = nextSearchViewId++;
    const inputId = `tag-search-input-${id}`;
    const suggestionsId = `tag-search-suggestions-${id}`;

    const input = el('input', {
        className: 'search-input',
        attributes: {
            id: inputId,
            type: 'text',
            value: initialTags,
            placeholder: 'Search tags',
            autocomplete: 'off',
            role: 'combobox',
            'aria-autocomplete': 'list',
            'aria-expanded': 'false',
            'aria-controls': suggestionsId,
        },
    });
    const label = el('label', {
        className: 'search-label',
        attributes: { for: inputId },
        children: 'Search tags',
    });
    const submitButton = el('button', {
        className: 'search-submit',
        attributes: { type: 'submit', 'aria-label': 'Search tags' },
        children: el('span', {
            className: 'search-icon',
            attributes: { 'aria-hidden': 'true' },
        }),
    });
    const suggestions = el('ul', {
        className: 'search-suggestions',
        attributes: { id: suggestionsId, role: 'listbox', hidden: '' },
    });
    const form = el('form', {
        className: 'search-view',
        attributes: { role: 'search' },
        children: [
            label,
            el('div', { className: 'search-control', children: [input, submitButton] }),
            suggestions,
        ],
    });

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
        if (!form.contains(event.target)) {
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
        const prefix = input.value.slice(commaIndex + 1).trim();
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
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        closeSuggestions();
        onSearch(normalizeTags(input.value));
    });

    return { element: form, input, suggestions };
}
