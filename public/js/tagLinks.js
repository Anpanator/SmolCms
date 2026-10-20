/** Shared tag-link list renderer for the gallery and image detail views. */
import {el} from './dom.js';

/**
 * @param {{
 *   tags?: Array<string|{name?: string}>,
 *   activeTags?: Set<string>|string|Array<string>,
 *   hrefForTag?: (tag: string, state: {active: boolean, activeTags: Set<string>}) => string,
 *   removeControlForTag?: (tag: string) => Node|null,
 * }} [options]
 */
export function createTagLinkList({
    tags = [],
    activeTags = [],
    hrefForTag = (tag) => `/?${new URLSearchParams({tags: tag})}`,
    removeControlForTag = null,
} = {}) {
    const active = normalizeActiveTags(activeTags);
    return el('ul', {
        className: 'tags',
        children: normalizeTagEntries(tags).map((tag) => {
            const isActive = active.has(tag.name);
            const removeControl = typeof removeControlForTag === 'function'
                ? removeControlForTag(tag.name)
                : null;
            return el('li', {
                className: 'tags-item',
                attributes: {'data-tag-name': tag.name},
                children: [
                    removeControl,
                    el('a', {
                        className: `tags-link${isActive ? ' tags-link--active' : ''}`,
                        attributes: {
                            href: hrefForTag(tag.name, {active: isActive, activeTags: active}),
                            'data-tag-name': tag.name,
                            ...(isActive ? {'aria-current': 'page'} : {}),
                        },
                        children: tag.name,
                    }),
                ],
            });
        }),
    });
}

function normalizeTagEntries(tags) {
    if (!Array.isArray(tags)) {
        return [];
    }
    return tags
        .map((tag) => typeof tag === 'string' ? {name: tag} : tag)
        .filter((tag) => typeof tag?.name === 'string' && tag.name !== '');
}

export function normalizeActiveTags(activeTags) {
    if (activeTags instanceof Set) {
        return activeTags;
    }
    const tags = Array.isArray(activeTags)
        ? activeTags
        : typeof activeTags === 'string' ? activeTags.split(',') : [activeTags];
    return new Set(tags.map((tag) => String(tag ?? '').trim()).filter(Boolean));
}
