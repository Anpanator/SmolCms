/**
 * Shared test helpers: fetch mocking, history stubbing and navigation guard.
 * Importing this module has no side effects.
 */
import { settle } from './assert.js';

const NULL_BODY_STATUSES = new Set([204, 205, 304]);

/**
 * Creates a recordable fetch mock.
 *
 * @param {(url: string, options: object) => object|Response|Promise<*>|null} handler
 *   receives each request and returns either a Response, a descriptor
 *   ({ status, body, headers }) or a promise thereof. Returning null/undefined
 *   yields an empty 200 response.
 * @returns {Function & { calls: Array<{url: string, options: object}> }}
 */
export function mockFetch(handler) {
    const calls = [];
    const impl = (url, options = {}) => {
        calls.push({ url, options });
        return Promise.resolve(handler(url, options))
            .then((response) => {
                if (response instanceof Response) {
                    return response;
                }
                const { status = 200, body = null, headers = {} } = response ?? {};
                const hasNullBody = NULL_BODY_STATUSES.has(status);
                const payload = hasNullBody || body === null
                    ? null
                    : typeof body === 'string' ? body : JSON.stringify(body);
                return new Response(payload, { status, headers });
            });
    };
    impl.calls = calls;
    return impl;
}

/**
 * Temporarily replaces globalThis.fetch with the given implementation.
 *
 * @returns {Function} restore function
 */
export function useFetch(implementation) {
    const original = globalThis.fetch;
    globalThis.fetch = implementation;
    return () => {
        globalThis.fetch = original;
    };
}

/**
 * Submits a form programmatically and waits for the async handler.
 *
 * @param {HTMLFormElement} form
 */
export async function submitForm(form) {
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await settle();
}

/**
 * Mutes/unmutes the runner's navigation guard for tests that intentionally
 * let the default action of a click proceed (e.g. external links).
 *
 * @param {boolean} muted
 */
export function muteNavigation(muted) {
    window.__navigationMuted = muted;
}

/**
 * Creates a session user descriptor for the mocked /api/session endpoint.
 */
export function sessionResponse(user) {
    return { status: 200, body: { user } };
}

export function testUser(overrides = {}) {
    return { id: 1, displayName: 'Test User', accessLevel: 100, ...overrides };
}

/**
 * Stubs History.prototype.pushState/replaceState (the real ones throw
 * SecurityError on file:// origins) and records the calls.
 *
 * @param {(history: {push: Array, replace: Array}) => (void|Promise<void>) } callback
 */
export async function withHistoryStub(callback) {
    const originalPush = History.prototype.pushState;
    const originalReplace = History.prototype.replaceState;
    const calls = { push: [], replace: [] };
    History.prototype.pushState = function (data, title, url) {
        calls.push.push({ data, title, url });
    };
    History.prototype.replaceState = function (data, title, url) {
        calls.replace.push({ data, title, url });
    };
    try {
        await callback(calls);
    } finally {
        History.prototype.pushState = originalPush;
        History.prototype.replaceState = originalReplace;
    }
}
