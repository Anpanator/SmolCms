import { assertEqual, assertTrue, settle } from './assert.js';
import { muteNavigation, withHistoryStub } from './mocks.js';
import { createRouter } from '../../public/js/router.js';

function textView(tag, label) {
    return ({ path }) => {
        const node = document.createElement(tag);
        node.dataset.view = label;
        node.dataset.path = path;
        node.textContent = label;
        return node;
    };
}

function assertDeepEqualLocal(actual, expected) {
    assertEqual(JSON.stringify(actual), JSON.stringify(expected), 'expected equal sequences');
}

export async function testKnownRouteRendersIntoContainer() {
    const container = document.createElement('div');
    const visited = [];
    const router = createRouter({
        container,
        routes: { '/': textView('section', 'home') },
        notFound: textView('section', 'not-found'),
        onNavigate: (path) => visited.push(path),
    });
    try {
        router.render('/');
        assertEqual(container.querySelector('[data-view]')?.textContent, 'home');
        assertEqual(router.getPath(), '/');
        assertDeepEqualLocal(visited, ['/']);
    } finally {
        router.stop();
    }
}

export async function testUnknownPathRenders404() {
    const container = document.createElement('div');
    const router = createRouter({
        container,
        routes: { '/': textView('section', 'home') },
        notFound: textView('section', 'not-found'),
    });
    try {
        router.render('/definitely/not/here');
        assertEqual(container.querySelector('[data-view]')?.textContent, 'not-found');
        assertEqual(router.getPath(), '/definitely/not/here');
    } finally {
        router.stop();
    }
}

export async function testNavigateUpdatesHistoryAndRenders() {
    await withHistoryStub(async (history) => {
        const container = document.createElement('div');
        const router = createRouter({
            container,
            routes: {
                '/': textView('section', 'home'),
                '/login': textView('section', 'login'),
            },
            notFound: textView('section', 'not-found'),
        });
        try {
            router.navigate('/login');
            assertEqual(container.querySelector('[data-view]')?.textContent, 'login');
            assertEqual(history.push.length, 1, 'pushState should be called once');
            assertEqual(history.push[0].url, '/login');
            assertEqual(router.getPath(), '/login');
        } finally {
            router.stop();
        }
    });
}

export async function testRouteFactoryReceivesNavigateAndPath() {
    const container = document.createElement('div');
    let factoryContext = null;
    const router = createRouter({
        container,
        routes: {
            '/settings': (context) => {
                factoryContext = context;
                const node = document.createElement('div');
                node.dataset.view = 'settings';
                return node;
            },
        },
        notFound: textView('section', 'not-found'),
    });
    try {
        router.render('/settings');
        assertTrue(typeof factoryContext.navigate === 'function', 'factory should receive navigate');
        assertEqual(factoryContext.path, '/settings');
    } finally {
        router.stop();
    }
}

export async function testInternalLinkClickIsIntercepted() {
    await withHistoryStub(async () => {
        const container = document.createElement('div');
        const router = createRouter({
            container,
            routes: {
                '/': textView('section', 'home'),
                '/login': textView('section', 'login'),
            },
            notFound: textView('section', 'not-found'),
        });
        const fixture = document.getElementById('test-fixture');
        const link = document.createElement('a');
        link.href = '/login';
        link.textContent = 'Login';
        fixture.append(link);
        try {
            router.start();
            // start() renders the runner page's own path, which is not a client route.
            assertEqual(container.querySelector('[data-view]')?.dataset.view, 'not-found');

            const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
            link.dispatchEvent(event);
            await settle();

            assertTrue(event.defaultPrevented, 'internal navigation should preventDefault');
            assertEqual(container.querySelector('[data-view]')?.dataset.view, 'login');
            assertEqual(router.getPath(), '/login');
        } finally {
            router.stop();
            link.remove();
        }
    });
}

export async function testExternalLinkClickIsNotIntercepted() {
    const container = document.createElement('div');
    const router = createRouter({
        container,
        routes: { '/': textView('section', 'home') },
        notFound: textView('section', 'not-found'),
    });
    const fixture = document.getElementById('test-fixture');
    const link = document.createElement('a');
    link.href = 'https://example.com/external';
    link.textContent = 'External';
    fixture.append(link);
    try {
        router.start();
        const before = container.querySelector('[data-view]')?.dataset.view;

        // The runner's navigation guard prevents the real cross-document
        // navigation (untrusted clicks still trigger it in Chromium); mute it
        // because this default action is intentional here.
        muteNavigation(true);
        const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        link.dispatchEvent(event);
        muteNavigation(false);

        assertTrue(!event.defaultPrevented, 'external links should keep default behaviour');
        assertEqual(container.querySelector('[data-view]')?.dataset.view, before, 'external click must not re-render');
    } finally {
        muteNavigation(false);
        router.stop();
        link.remove();
    }
}

export async function testPopstateRendersCurrentLocation() {
    const container = document.createElement('div');
    let renders = 0;
    const selfView = () => {
        renders++;
        const node = document.createElement('div');
        node.dataset.view = 'self';
        return node;
    };
    const router = createRouter({
        container,
        routes: {
            '/': textView('section', 'home'),
            // The runner page's own path so the popstate handler's
            // render(location.pathname) resolves deterministically on file://.
            [location.pathname]: selfView,
        },
        notFound: textView('section', 'not-found'),
    });
    try {
        router.start();
        assertEqual(container.querySelector('[data-view]')?.dataset.view, 'self');
        const rendersBefore = renders;

        window.dispatchEvent(new PopStateEvent('popstate'));
        assertEqual(renders, rendersBefore + 1, 'popstate should re-render the current location');
        assertEqual(container.querySelector('[data-view]')?.dataset.view, 'self');
    } finally {
        router.stop();
    }
}

export async function testStopRemovesListeners() {
    const container = document.createElement('div');
    const router = createRouter({
        container,
        routes: { '/': textView('section', 'home') },
        notFound: textView('section', 'not-found'),
    });
    try {
        router.start();
        router.stop();

        // A click after stop() must neither render nor intercept the event.
        // (A plain div avoids triggering a real link navigation.)
        const fixture = document.getElementById('test-fixture');
        const target = document.createElement('div');
        fixture.append(target);
        container.replaceChildren();
        const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        target.dispatchEvent(event);
        assertEqual(container.childElementCount, 0, 'stopped router should not render');
        assertTrue(!event.defaultPrevented, 'stopped router should not intercept clicks');
        target.remove();
    } finally {
        router.stop();
    }
}
