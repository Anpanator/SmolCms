import {assertEqual, assertTrue} from './assert.js';
import {createTagLinkList} from '../../public/js/tagLinks.js';

export function testTagLinksNormalizeEntriesAndRenderActiveState() {
    const list = createTagLinkList({
        tags: ['nature', {name: 'sky'}, {name: ''}, null],
        activeTags: 'nature',
        hrefForTag(tag, {active, activeTags}) {
            const next = new Set(activeTags);
            if (active) {
                next.delete(tag);
            } else {
                next.add(tag);
            }
            return `/?tags=${[...next].join(',')}`;
        },
    });
    assertEqual(list.className, 'tags');
    assertEqual(list.querySelectorAll('.tags-item').length, 2);
    assertEqual(list.querySelector('.tags-link[data-tag-name="nature"]')?.getAttribute('href'), '/?tags=');
    assertTrue(list.querySelector('.tags-link[data-tag-name="nature"]').classList.contains('tags-link--active'));
    assertEqual(list.querySelector('.tags-link[data-tag-name="nature"]')?.getAttribute('aria-current'), 'page');
    assertEqual(list.querySelector('.tags-link[data-tag-name="sky"]')?.getAttribute('href'), '/?tags=nature,sky');
    assertEqual(list.querySelector('.tags-link[data-tag-name="sky"]')?.getAttribute('aria-current'), null);
}

export function testTagLinksAllowOptionalRemoveControls() {
    const list = createTagLinkList({
        tags: ['nature'],
        removeControlForTag(tag) {
            const button = document.createElement('button');
            button.className = 'tag-remove-button';
            button.setAttribute('aria-label', `Remove ${tag}`);
            return button;
        },
    });
    const item = list.querySelector('.tags-item');
    assertEqual(item.firstElementChild.className, 'tag-remove-button');
    assertEqual(item.querySelector('.tags-link')?.getAttribute('href'), '/?tags=nature');
}
