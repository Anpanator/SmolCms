/**
 * Deterministic checks against the real stylesheet: CSSOM rule inspection
 * plus computed styles of a mounted fixture. The runner page links
 * ../../public/css/main.css.
 */
import {assertEqual, assertMatches, assertTrue} from './assert.js';

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

export function testImageTileAndReviewStylesExist() {
    const rules = collectRules(findMainStylesheet());

    const tileImageRule = findRule(rules, '.tile-image', 'object-fit:cover');
    assertTrue(tileImageRule !== undefined, 'tile images should be cropped with object-fit: cover');

    const skeletonRule = findRule(rules, '.tile--skeleton', 'animation');
    assertTrue(skeletonRule !== undefined, 'loading skeleton tiles should have an animation');

    const gridStatusRule = findRule(rules, '.grid-status');
    assertTrue(gridStatusRule !== undefined, 'grid status messages (loading/empty/error) should be styled');

    const reviewThumbRule = findRule(rules, '.review-thumb', 'object-fit:cover');
    assertTrue(reviewThumbRule !== undefined, 'review thumbnails should be cropped with object-fit: cover');

    const reviewSelectRule = findRule(rules, '.review-select', 'display:flex');
    assertTrue(reviewSelectRule !== undefined, 'queue thumbnails should have a selectable layout');

    const reviewColumnsRule = findRule(rules, '.review-workspace', 'grid-template-columns');
    assertTrue(
        reviewColumnsRule?.text.includes('3fr') && reviewColumnsRule.text.includes('1fr'),
        'the moderation workspace should split into 75/25 columns',
    );

    const reviewImageRule = findRule(rules, '.review-image', 'max-width:100%');
    assertTrue(
        reviewImageRule?.text.includes('max-height:100%'),
        'the default review image should fit within its frame without being enlarged',
    );
    const fullSizeImageRule = findRule(rules, '.review-image--full-size', 'max-width:none');
    assertTrue(
        fullSizeImageRule?.text.includes('max-height:none'),
        'the full-size review image should use its natural dimensions',
    );
    assertTrue(
        findRule(rules, '.review-image-panel', 'max-height:100dvh') !== undefined,
        'the review image area should not exceed the viewport height',
    );
    assertTrue(
        findRule(rules, '.review-image-frame', 'overflow:auto') !== undefined,
        'the review image frame should scroll when the natural-size image overflows',
    );

    assertTrue(findRule(rules, '.review-metadata-panel') !== undefined, 'active image metadata should have a panel');

    assertTrue(findRule(rules, '.button--danger') !== undefined, 'a danger button style should exist for Reject');
    assertTrue(findRule(rules, '.image-meta') !== undefined, 'the image metadata list should be styled');
    assertTrue(findRule(rules, '.image-download') !== undefined, 'the download link should be styled');
}

export function testImageMetadataSeparatorUsesBorderColorAndSidebarPadding() {
    const fixture = document.getElementById('test-fixture');
    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';
    const section = document.createElement('div');
    section.className = 'image-meta-section';
    const separator = document.createElement('hr');
    separator.className = 'image-meta-separator';
    section.append(separator);
    sidebar.append(section);
    fixture.append(sidebar);

    try {
        const sidebarStyle = getComputedStyle(sidebar);
        const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--color-border').trim();
        const expectedColor = hexToRgbString(borderColor);
        assertEqual(getComputedStyle(separator).borderTopColor, expectedColor, 'the separator should use the border color');

        const sidebarRect = sidebar.getBoundingClientRect();
        const separatorRect = separator.getBoundingClientRect();
        const contentLeft = sidebarRect.left
            + parseFloat(sidebarStyle.borderLeftWidth)
            + parseFloat(sidebarStyle.paddingLeft);
        const contentRight = sidebarRect.right
            - parseFloat(sidebarStyle.borderRightWidth)
            - parseFloat(sidebarStyle.paddingRight);
        assertEqual(separatorRect.left, contentLeft, 'the separator should start at the sidebar content edge');
        assertEqual(separatorRect.right, contentRight, 'the separator should end at the sidebar content edge');
    } finally {
        sidebar.remove();
    }
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

export function testTileImageCoversTheTile() {
    const fixture = document.getElementById('test-fixture');
    const tile = document.createElement('a');
    tile.className = 'tile tile--link';
    const image = document.createElement('img');
    image.className = 'tile-image';
    tile.append(image);
    fixture.append(tile);
    try {
        const style = getComputedStyle(image);
        assertEqual(style.objectFit, 'cover', 'tile images should use object-fit: cover');
        assertEqual(getComputedStyle(tile).aspectRatio, '1 / 1', 'tiles should stay square-ish');
    } finally {
        tile.remove();
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

export function testSearchInputAndPaginationStylesAreAvailable() {
    const rules = collectRules(findMainStylesheet());
    const searchInputRule = findRule(rules, '.search-input', 'overflow:hidden');
    assertTrue(searchInputRule?.text.includes('white-space:nowrap'), 'the search field should stay single-line without overflow');
    assertTrue(findRule(rules, '.search-submit', 'position:absolute') !== undefined, 'the search icon should sit inside the field');
    assertTrue(findRule(rules, '.search-suggestions', 'position:absolute') !== undefined, 'suggestions should overlay below the field');
    assertTrue(findRule(rules, '.pagination', 'display:flex') !== undefined, 'pagination controls should have a styled row');
    assertTrue(findRule(rules, '.tags-link--active', 'font-weight:600') !== undefined, 'active tags should be visually distinct');
}

export function testTagManagementTableAndFormsHaveStyles() {
    const rules = collectRules(findMainStylesheet());
    assertTrue(findRule(rules, '.tags-page', 'grid-template-columns') !== undefined, 'the tag page should use a single-column layout');
    assertTrue(findRule(rules, '.tags-table', 'border-collapse:collapse') !== undefined, 'the tag list should render as a table');
    assertTrue(findRule(rules, '.tags-table th', 'border-bottom') !== undefined, 'table headings and rows should be separated');
    assertTrue(findRule(rules, '.tags-management', 'grid-template-columns') !== undefined, 'tag creation forms should have a responsive layout');
    assertTrue(findRule(rules, '.tags-form-panel', 'min-width:0') !== undefined, 'form panels should be allowed to shrink');
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
