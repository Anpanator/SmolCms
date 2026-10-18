/**
 * Deterministic checks against the real stylesheet: CSSOM rule inspection
 * plus computed styles of a mounted fixture. The runner page links
 * ../../public/css/main.css.
 */
import { assertEqual, assertTrue, assertMatches } from './assert.js';

function findMainStylesheet() {
    for (const sheet of document.styleSheets) {
        if ((sheet.href ?? '').endsWith('/public/css/main.css')) {
            return sheet;
        }
    }
    throw new Error('main.css stylesheet not found on the runner page');
}

function collectRules(sheet) {
    const rules = [];
    for (const rule of sheet.cssRules) {
        if (rule instanceof CSSStyleRule) {
            rules.push({
                selector: rule.selectorText.replace(/\s+/g, ' '),
                text: rule.cssText.replace(/\s+/g, '').toLowerCase(),
            });
        }
    }
    return rules;
}

function findRule(rules, selectorPart, textPart = '') {
    return rules.find((rule) => rule.selector.includes(selectorPart) && rule.text.includes(textPart));
}

export function testStylesheetIsLinkedAndParsed() {
    const sheet = findMainStylesheet();
    assertTrue(sheet.cssRules.length > 40, `expected a full stylesheet, got ${sheet.cssRules.length} rules`);
}

export function testDropdownHiddenByDefaultAndRevealedOnHover() {
    const rules = collectRules(findMainStylesheet());

    const hiddenRule = findRule(rules, '.submenu', 'visibility:hidden');
    assertTrue(hiddenRule !== undefined, 'dropdowns should be hidden by default (visibility: hidden)');

    const hoverRule = findRule(rules, ':hover', 'visibility:visible');
    assertTrue(hoverRule !== undefined, 'a :hover rule must reveal the dropdown');
    assertTrue(hoverRule.selector.includes('.submenu'), 'the :hover rule must target the submenu');

    const focusRule = findRule(rules, ':focus-within', 'visibility:visible');
    assertTrue(focusRule !== undefined, 'a :focus-within rule must reveal the dropdown for keyboard users');
    assertTrue(focusRule.selector.includes('.submenu'), 'the :focus-within rule must target the submenu');
}

export function testTileHoverRuleExists() {
    const rules = collectRules(findMainStylesheet());
    assertTrue(findRule(rules, '.tile:hover') !== undefined, 'tiles should have a hover style');
}

export function testLayoutAndGridUseCssGrid() {
    const fixture = document.getElementById('test-fixture');
    const layout = document.createElement('div');
    layout.className = 'layout';
    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';
    const content = document.createElement('section');
    content.className = 'content';
    layout.append(sidebar, content);
    fixture.append(layout);
    try {
        const layoutStyle = getComputedStyle(layout);
        assertEqual(layoutStyle.display, 'grid', '.layout should be a CSS grid');
        assertEqual(layoutStyle.gridTemplateColumns.split(' ').length, 2, '.layout should have two columns');

        const grid = document.createElement('div');
        grid.className = 'grid';
        content.append(grid);
        assertEqual(getComputedStyle(grid).display, 'grid', '.grid should be a CSS grid');

        // auto-fill responsive columns declared in the stylesheet
        const rules = collectRules(findMainStylesheet());
        const gridRule = findRule(rules, '.grid', 'repeat(auto-fill');
        assertTrue(gridRule !== undefined, '.grid should use repeat(auto-fill, minmax(...)) columns');
    } finally {
        layout.remove();
    }
}

export function testFormElementsAreStyled() {
    const fixture = document.getElementById('test-fixture');
    const field = document.createElement('div');
    field.className = 'field';
    const input = document.createElement('input');
    input.className = 'field-input';
    const button = document.createElement('button');
    button.className = 'button';
    field.append(input);
    fixture.append(field, button);
    try {
        const inputStyle = getComputedStyle(input);
        assertNotEqualString(inputStyle.borderTopWidth, '0px', 'inputs should have a visible border');
        assertEqual(getComputedStyle(button).cursor, 'pointer', 'buttons should show a pointer cursor');
    } finally {
        field.remove();
        button.remove();
    }
}

function assertNotEqualString(actual, expected, message) {
    if (actual === expected) {
        throw new Error(`${message} (got ${expected})`);
    }
}

export function testErrorColorVariableAndFieldErrorUsage() {
    const rootStyle = getComputedStyle(document.documentElement);
    const danger = rootStyle.getPropertyValue('--color-danger').trim();
    assertMatches(danger, /^#[0-9a-fA-F]{6}$/, '--color-danger should be defined as a hex color');

    const fixture = document.getElementById('test-fixture');
    const fieldError = document.createElement('p');
    fieldError.className = 'field-error';
    fixture.append(fieldError);
    try {
        const rgb = hexToRgbString(danger);
        assertEqual(getComputedStyle(fieldError).color, rgb, '.field-error should use the danger color');
    } finally {
        fieldError.remove();
    }
}

function hexToRgbString(hex) {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return `rgb(${r}, ${g}, ${b})`;
}

export function testShellUsesFlexColumn() {
    const bodyStyle = getComputedStyle(document.body);
    assertEqual(bodyStyle.display, 'flex');
    assertEqual(bodyStyle.flexDirection, 'column');
}
