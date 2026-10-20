import {assertEqual, assertTrue} from './assert.js';
import {createSearchForm} from '../../public/js/searchForm.js';

export function testSearchFormProvidesTheSharedReturnContract() {
    const queries = [];
    const search = createSearchForm({
        inputLabel: 'Search tags',
        placeholder: 'Search tags',
        hasSuggestions: true,
        onSearch: (query) => queries.push(query),
    });
    assertEqual(search.element.getAttribute('role'), 'search');
    assertEqual(search.input.id, search.element.querySelector('label')?.htmlFor);
    assertTrue(search.suggestions.matches('[role="listbox"]'));
    assertEqual(search.input.getAttribute('role'), 'combobox');
    assertEqual(typeof search.getValue, 'function');
    assertEqual(typeof search.setValue, 'function');
    assertEqual(typeof search.clear, 'function');

    search.setValue('red, blue');
    assertEqual(search.getValue(), 'red, blue');
    search.element.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    assertEqual(queries[0], 'red, blue');
    search.clear();
    assertEqual(search.getValue(), '');
}

export function testSearchFormResetUsesTheSameValueApi() {
    let resets = 0;
    const search = createSearchForm({onReset: () => resets++});
    assertEqual(search.suggestions, null);
    search.setValue('query');
    search.element.querySelector('button[type="button"]').click();
    assertEqual(search.getValue(), '');
    assertEqual(resets, 1);
}
