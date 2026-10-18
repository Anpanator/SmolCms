import { assertEqual, assertTrue } from './assert.js';
import { createHomeView } from '../../public/js/views/home.js';

export function testHomeViewRendersSidebarTagsAndTileGrid() {
    const view = createHomeView();
    assertTrue(view.matches('.layout'), 'home should use the .layout grid');
    assertTrue(view.querySelector('.sidebar .tags') !== null, 'sidebar tag list should exist');
    assertTrue(view.querySelectorAll('.tags .tags-item').length >= 5, 'several tags should be listed');
    assertTrue(view.querySelector('.content .grid') !== null, 'content tile grid should exist');
    assertTrue(view.querySelectorAll('.grid .tile').length >= 6, 'placeholder tiles should exist');
}

export function testHomeTagLinksAreStatic() {
    const view = createHomeView();
    const firstTag = view.querySelector('.tags .tags-link');
    assertEqual(firstTag?.tagName, 'A');
    assertTrue(firstTag?.textContent.length > 0, 'tags should have labels');
}
