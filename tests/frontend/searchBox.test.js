import {assertEqual, assertTrue} from './assert.js';
import {createSearchBox} from '../../public/js/searchBox.js';

export function testSearchBoxSubmitsItsUnmodifiedQueryAndExposesValueMethods() {
    const queries = [];
    const search = createSearchBox({
        placeholder: 'Search users',
        buttonText: 'Find users',
        onSearch: (query) => queries.push(query),
    });
    const input = search.element.querySelector('input');
    const submit = search.element.querySelector('button[type="submit"]');
    const submitEvent = new Event('submit', {bubbles: true, cancelable: true});

    assertEqual(search.element.className, 'search-box');
    assertTrue(search.input instanceof HTMLInputElement, 'search boxes should expose the shared input property');
    assertEqual(search.suggestions, null, 'plain search boxes do not create suggestions');
    assertEqual(search.element.querySelectorAll('input').length, 1);
    assertEqual(input.type, 'search');
    assertEqual(input.placeholder, 'Search users');
    assertEqual(search.element.querySelector('label').textContent, 'Search users');
    assertEqual(submit.textContent, 'Find users');
    assertEqual(search.element.querySelector('.search-suggestions'), null);

    search.setValue('  Ada, Admin  ');
    assertEqual(search.getValue(), '  Ada, Admin  ');
    search.element.dispatchEvent(submitEvent);
    assertTrue(submitEvent.defaultPrevented);
    assertEqual(queries[0], '  Ada, Admin  ', 'search should not normalize or split the query');

    search.clear();
    assertEqual(search.getValue(), '');
}

export function testSearchBoxResetButtonClearsAndCallsOnReset() {
    let resetCount = 0;
    const search = createSearchBox({
        placeholder: 'Search audit log',
        onReset: () => resetCount++,
    });
    const reset = search.element.querySelector('.search-box-reset');

    assertTrue(reset !== null, 'a reset button should render when onReset is provided');
    search.setValue('admin action');
    reset.click();
    assertEqual(search.getValue(), '');
    assertEqual(resetCount, 1);

    const withoutReset = createSearchBox({placeholder: 'Search users'});
    assertEqual(withoutReset.element.querySelector('.search-box-reset'), null);
}
