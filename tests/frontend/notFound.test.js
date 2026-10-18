import { assertTrue } from './assert.js';
import { createNotFoundView } from '../../public/js/views/notFound.js';

export function testNotFoundViewRenders404AndHomeLink() {
    const view = createNotFoundView();
    assertTrue(view.textContent.includes('404'), 'view should mention 404');
    const homeLink = view.querySelector('a[href="/"]');
    assertTrue(homeLink !== null, 'view should link back home');
    assertTrue(homeLink.textContent.includes('home'), 'link should be labelled');
}
