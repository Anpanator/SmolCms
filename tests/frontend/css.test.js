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

    assertTrue(findRule(rules, '.tile-unapproved', 'border:2pxsolid') !== undefined, 'unapproved thumbnails should have an orange border');
    assertTrue(findRule(rules, '.image-pending-warning', 'color:var(--color-danger)') !== undefined, 'pending warnings should use the danger color');
    assertTrue(findRule(rules, '.profile-api-key', 'display:flex') !== undefined, 'the profile API key section should use the page layout');
    assertTrue(findRule(rules, '.profile-api-key-value', 'overflow-wrap:anywhere') !== undefined, 'generated API keys should wrap on narrow screens');
    assertTrue(findRule(rules, '.profile-api-key-value', 'background:var(--color-bg)') !== undefined, 'generated API keys should use the page background');
    const profileGridRule = findRule(rules, '.profile-recent-uploads-grid', 'display:grid');
    assertTrue(
        /grid-template-columns:repeat\(5,minmax\(0(?:px)?,1fr\)\)/.test(profileGridRule?.text ?? ''),
        'recent uploads should use a five-column grid',
    );

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

export function testProfileApiKeyActionRowStylesExist() {
    const rules = collectRules(findMainStylesheet());
    const actionsRule = findRule(rules, '.profile-api-key-actions', 'display:flex');
    assertTrue(actionsRule !== undefined, 'API key buttons should share a flex row');
    assertTrue(actionsRule.text.includes('flex-wrap:wrap'), 'the API key action row should wrap on narrow screens');
    assertTrue(actionsRule.text.includes('gap:var(--space-2)'), 'the API key action row should use the shared spacing');
    assertTrue(
        findRule(rules, '.profile-api-key-actions [hidden]', 'display:none') !== undefined,
        'hidden API key actions should not be displayed even when they carry the button class',
    );
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

export function testImageToolbarAlignsWithSidebarAndImageColumns() {
    const fixture = document.getElementById('test-fixture');
    const layout = document.createElement('div');
    layout.className = 'layout image-layout';
    const toolbar = document.createElement('div');
    toolbar.className = 'layout-toolbar image-toolbar';
    const search = document.createElement('form');
    search.className = 'search-view';
    const navigation = document.createElement('nav');
    navigation.className = 'image-nav';
    const previous = document.createElement('button');
    previous.className = 'button';
    previous.textContent = 'Previous';
    const next = document.createElement('button');
    next.className = 'button';
    next.textContent = 'Next';
    navigation.append(previous, next);
    toolbar.append(search, navigation);

    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';
    const content = document.createElement('section');
    content.className = 'content';
    const image = document.createElement('img');
    image.className = 'image-view-image';
    image.style.width = '2rem';
    image.style.height = '2rem';
    content.append(image);
    layout.append(toolbar, sidebar, content);
    fixture.append(layout);

    try {
        const toolbarRect = toolbar.getBoundingClientRect();
        const searchRect = search.getBoundingClientRect();
        const navigationRect = navigation.getBoundingClientRect();
        const sidebarRect = sidebar.getBoundingClientRect();
        const imageRect = image.getBoundingClientRect();
        const contentRect = content.getBoundingClientRect();

        assertEqual(searchRect.left, sidebarRect.left, 'search should align with the sidebar below it');
        assertEqual(navigationRect.left, contentRect.left, 'navigation should align with the image below it');
        assertEqual(sidebarRect.top, imageRect.top, 'the sidebar should start at the same height as the image');
        assertTrue(toolbarRect.bottom < sidebarRect.top, 'the search/navigation row should be above the image and sidebar');
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
    const tagsWorkspaceRule = findRule(rules, '.internal-admin-workspace', 'grid-template-columns');
    assertTrue(tagsWorkspaceRule !== undefined, 'the tag page should use the internal admin workspace split');
    assertTrue(tagsWorkspaceRule.text.includes('3fr') && tagsWorkspaceRule.text.includes('1fr'));
    assertTrue(findRule(rules, '.data-table', 'border-collapse:collapse') !== undefined, 'shared data tables should use collapsed borders');
    assertTrue(findRule(rules, '.data-table th', 'border-bottom') !== undefined, 'shared table headings and rows should be separated');
    assertTrue(findRule(rules, '.data-table-wrap', 'overflow-x:auto') !== undefined, 'shared table wrappers should scroll horizontally');
    assertTrue(findRule(rules, '.data-table-status td', 'text-align:center') !== undefined, 'shared status rows should be centered');
    assertTrue(findRule(rules, '.tags-search-panel', 'display:flex') !== undefined, 'tag search should have a dedicated sidebar panel');
    assertTrue(findRule(rules, '.tags-form-panel', 'min-width:0') !== undefined, 'form panels should be allowed to shrink');
}

export function testGalleryWidthAndQuickTagsLayoutStylesExist() {
    const rules = collectRules(findMainStylesheet());
    assertTrue(
        findRule(rules, '.view-container:has(> .home-layout)', 'max-width:var(--gallery-max-width)') !== undefined,
        'home, image, and tagme pages should receive the wider content cap',
    );
    const quickLayout = findRule(rules, '.layout--with-quick-tags', 'grid-template-columns');
    assertTrue(quickLayout?.text.includes('var(--sidebar-width)'));
    assertTrue(quickLayout?.text.includes('var(--quick-tags-sidebar-width)'));
    assertTrue(findRule(rules, '.quick-tags-columns', 'grid-template-columns') !== undefined);
    const buttonRule = findRule(rules, '.quick-tag-button', 'text-overflow:ellipsis');
    assertTrue(buttonRule?.text.includes('overflow:hidden'), 'quick-tag names should not overflow their buttons');
    assertTrue(findRule(rules, '.quick-tag-button--active', 'background:var(--color-accent)') !== undefined);
    assertTrue(findRule(rules, '.quick-tags-sidebar[hidden]', 'display:none') !== undefined);
    assertTrue(
        findRule(rules, '.quick-tag-button[aria-disabled="true"]', 'cursor:wait') !== undefined,
        'pending quick-tag buttons should look disabled without losing focus',
    );
    assertTrue(
        findRule(rules, '.button[aria-disabled="true"]', 'cursor:wait') !== undefined,
        'aria-disabled buttons such as the quick-tags toggle should look disabled',
    );
}

export function testImageTagEditingAndTagmeControlsHaveStyles() {
    const rules = collectRules(findMainStylesheet());
    assertTrue(findRule(rules, '.tag-add-row', 'display:flex') !== undefined, 'tag input and Add button should share a row');
    assertTrue(findRule(rules, '.tag-remove-button', 'color:var(--color-danger)') !== undefined, 'remove controls should use the danger color');
    assertTrue(findRule(rules, '.tagme-toolbar', 'display:flex') !== undefined, 'the Tagme title and refresh action should share a toolbar');
    assertTrue(findRule(rules, '.form-message--info', 'color:var(--color-accent-strong)') !== undefined, 'informational tag messages should be styled');
}

export function testImageNavigationAndModActionStylesExist() {
    const rules = collectRules(findMainStylesheet());
    assertTrue(findRule(rules, '.layout-toolbar', 'grid-column:1/-1') !== undefined, 'the toolbars should span above the layout columns');
    assertTrue(findRule(rules, '.layout-toolbar', 'grid-template-columns:subgrid') !== undefined, 'toolbar controls should align with the sidebar and content columns');
    assertTrue(findRule(rules, '.layout-toolbar .search-view', 'margin-bottom:0') !== undefined, 'toolbar search should align with adjacent controls');
    assertTrue(findRule(rules, '.image-nav', 'display:flex') !== undefined, 'previous/next controls should share a row above the image');
    assertTrue(findRule(rules, '.image-nav', 'margin-top:') === undefined, 'navigation should not retain the old below-image spacing');
    assertTrue(findRule(rules, '.image-nav .button') !== undefined, 'previous/next controls should share the row width');
    assertTrue(findRule(rules, '.mod-actions', 'display:flex') !== undefined, 'the mod actions section should have a styled layout');
    assertTrue(findRule(rules, '.button--danger') !== undefined, 'the delete action should reuse the danger button style');
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
