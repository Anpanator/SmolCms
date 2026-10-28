/**
 * Header navigation, rendered from session state.
 *
 * Anonymous: Home / Login / Register.
 * Logged in: Home / Upload Image / Tagme / user display name (hover dropdown with
 * profile and logout). Dropdown submenus are plain nested lists whose
 * visibility is toggled purely by CSS (:hover / :focus-within).
 * Importing this module has no side effects.
 */
import {postJson} from './api.js';
import {AccessLevel, accessLevelName, hasAccessLevel} from './accessLevel.js';
import {el} from './dom.js';
import {RoutePath, userProfileHref} from './routes.js';

function navLink(path, label, activePath, isActive = activePath === path) {
    return el('a', {
        className: `nav-link${isActive ? ' active' : ''}`,
        attributes: { href: path },
        children: label,
    });
}

function navItem(children) {
    return el('li', { className: 'nav-item', children });
}

function internalNavItem(activePath) {
    const isActive = activePath === RoutePath.USER_MANAGEMENT || activePath === RoutePath.AUDIT_LOG;
    return el('li', {
        className: 'nav-item has-submenu internal-nav-item',
        children: [
            el('button', {
                className: `nav-link nav-submenu-trigger internal-nav-trigger${isActive ? ' active' : ''}`,
                attributes: {type: 'button', 'aria-haspopup': 'true'},
                children: 'Internal',
            }),
            el('ul', {
                className: 'submenu',
                children: [
                    el('li', {
                        className: 'submenu-item',
                        children: navLink(RoutePath.USER_MANAGEMENT, 'User Management', activePath),
                    }),
                    el('li', {
                        className: 'submenu-item',
                        children: navLink(RoutePath.AUDIT_LOG, 'Audit Log', activePath),
                    }),
                ],
            }),
        ],
    });
}

function sessionItems(user, activePath) {
    if (user) {
        const items = [
            navItem(navLink(RoutePath.START_PAGE, 'Home', activePath)),
            navItem(navLink(RoutePath.UPLOAD_IMAGE, 'Upload Image', activePath)),
            navItem(navLink(RoutePath.TAGME_QUEUE, 'Tagme', activePath)),
        ];
        if (hasAccessLevel(user, AccessLevel.MOD)) {
            items.push(navItem(navLink(RoutePath.IMAGE_REVIEW, 'Mod Queue', activePath)));
            items.push(navItem(navLink(RoutePath.TAG_QUEUE, 'Tag Queue', activePath)));
            items.push(navItem(navLink(RoutePath.DELETE_QUEUE, 'Delete Queue', activePath)));
            items.push(navItem(navLink(RoutePath.TAGS_PAGE, 'Tags', activePath)));
            items.push(internalNavItem(activePath));
        }
        return items;
    }
    return [
        navItem(navLink(RoutePath.START_PAGE, 'Home', activePath)),
        navItem(navLink(RoutePath.LOGIN, 'Login', activePath)),
        navItem(navLink(RoutePath.REGISTER, 'Register', activePath)),
    ];
}

/**
 * Header navigation with session-aware items and an account dropdown.
 *
 * @param {{ session: *, navigate: Function }} deps
 * @returns {{ element: HTMLElement, setActive: (path: string) => void, destroy: () => void }}
 */
export function createHeaderNav({ session, navigate }) {
    const nav = el('nav', { className: 'top-nav', attributes: { 'aria-label': 'Main navigation' } });
    const list = el('ul', { className: 'nav-list' });
    nav.append(list);

    let activePath = null;
    const unsubscribe = session.subscribe(() => render(session.getUser()));

    function buildLogoutButton() {
        const button = el('button', {
            className: 'nav-logout button button--link',
            attributes: { type: 'button' },
            children: 'Logout',
        });
        button.addEventListener('click', async () => {
            try {
                await postJson(RoutePath.LOGOUT);
            } catch {
                // logout is best effort: the session refresh below decides the UI state
            }
            await session.refresh();
            navigate(RoutePath.START_PAGE);
        });
        return button;
    }

    function buildAccountDropdown(user) {
        const profileHref = userProfileHref(user.id);
        const accountLinks = [];
        if (profileHref !== null) {
            accountLinks.push(el('li', {
                className: 'submenu-item',
                children: navLink(profileHref, 'Profile', activePath, activePath === RoutePath.USER_PROFILE),
            }));
        }
        accountLinks.push(el('li', {
            className: 'submenu-item',
            children: navLink(RoutePath.SETTINGS, 'Settings', activePath),
        }));
        accountLinks.push(el('li', {
            className: 'submenu-item submenu-label',
            children: `Access level: ${accessLevelName(user.accessLevel)}`,
        }));
        accountLinks.push(el('li', {className: 'submenu-item', children: buildLogoutButton()}));

        return el('li', {
            className: 'nav-item has-submenu',
            children: [
                el('button', {
                    className: 'nav-link nav-submenu-trigger',
                    attributes: {
                        type: 'button',
                        'aria-haspopup': 'true',
                    },
                    children: user.displayName,
                }),
                el('ul', {
                    className: 'submenu',
                    children: accountLinks,
                }),
            ],
        });
    }

    function render(user) {
        const items = sessionItems(user, activePath);
        if (user) {
            items.push(buildAccountDropdown(user));
        }
        list.replaceChildren(...items);
    }

    render(session.getUser());

    return {
        element: nav,
        setActive(path) {
            activePath = path;
            render(session.getUser());
        },
        destroy: unsubscribe,
    };
}
