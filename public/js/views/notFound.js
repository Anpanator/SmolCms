/**
 * 404 view for unknown client-side routes.
 * Importing this module has no side effects.
 */
import { el } from '../dom.js';

export function createNotFoundView() {
    return el('section', {
        className: 'page not-found-page',
        children: [
            el('h1', { className: 'page-title', children: '404 – Page not found' }),
            el('p', {
                className: 'not-found-text',
                children: [
                    'The page you are looking for does not exist. ',
                    el('a', { className: 'notice-link', attributes: { href: '/' }, children: 'Go back home' }),
                    '.',
                ],
            }),
        ],
    });
}
