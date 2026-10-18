/**
 * History-API router.
 *
 * Maps client routes (URL pathnames) to view factory functions, renders the
 * active view into a container element, intercepts clicks on internal links
 * and handles popstate. Unknown paths render the notFound view.
 * Importing this module has no side effects; everything happens in factories.
 */

/**
 * @param {{
 *   container: HTMLElement,
 *   routes: Record<string, (ctx: { navigate: Function, path: string }) => Node>,
 *   notFound: (ctx: { navigate: Function, path: string }) => Node,
 *   onNavigate?: (path: string) => void,
 * }} config
 */
export function createRouter({ container, routes, notFound, onNavigate }) {
    let started = false;
    let currentPath = null;

    function resolve(path) {
        return Object.hasOwn(routes, path) ? routes[path] : notFound;
    }

    function render(path) {
        const pathname = normalizePath(path);
        const factory = resolve(pathname);
        currentPath = pathname;
        container.replaceChildren();
        container.append(factory({ navigate, path: pathname }));
        if (onNavigate) {
            onNavigate(pathname);
        }
        return pathname;
    }

    function normalizePath(path) {
        const url = new URL(path, 'http://localhost/');
        const pathname = url.pathname;
        return pathname === '' ? '/' : pathname;
    }

    function updateHistory(path, method) {
        try {
            history[method]({}, '', path);
        } catch {
            // history API is unavailable (e.g. file:// origins): keep rendering
        }
    }

    function navigate(path, { replace = false } = {}) {
        updateHistory(path, replace ? 'replaceState' : 'pushState');
        return render(path);
    }

    function isInternalLink(link) {
        if (!link || link.hasAttribute('download')) {
            return false;
        }
        if (link.target && link.target !== '_self') {
            return false;
        }
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) {
            return false;
        }
        const url = new URL(link.href);
        // location.origin is "null"-like for opaque file:// origins where
        // URL.origin differs, so compare protocol and host directly.
        return url.protocol === location.protocol && url.host === location.host;
    }

    function handleClick(event) {
        if (event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const link = event.target.closest('a');
        if (!isInternalLink(link)) {
            return;
        }
        const url = new URL(link.href);
        if (url.pathname === location.pathname && url.hash && url.hash !== '#') {
            return;
        }
        event.preventDefault();
        navigate(url.pathname + url.search);
    }

    function handlePopState() {
        render(location.pathname + location.search);
    }

    return {
        render,
        navigate,
        start() {
            if (started) {
                return;
            }
            document.addEventListener('click', handleClick);
            window.addEventListener('popstate', handlePopState);
            started = true;
            render(location.pathname + location.search);
        },
        stop() {
            if (!started) {
                return;
            }
            document.removeEventListener('click', handleClick);
            window.removeEventListener('popstate', handlePopState);
            started = false;
        },
        getPath: () => currentPath,
    };
}
