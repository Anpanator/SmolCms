/**
 * Session state store.
 *
 * Loads GET /api/session once, caches the user and notifies subscribers on
 * every change. Factories only: importing this module has no side effects.
 */
import { get } from './api.js';

const SESSION_URL = '/api/session';

/**
 * @returns {{
 *   load: (options?: { force?: boolean }) => Promise<*>,
 *   refresh: () => Promise<*>,
 *   getUser: () => *,
 *   setUser: (user: *) => void,
 *   isLoaded: () => boolean,
 *   subscribe: (listener: (user: *) => void) => () => void,
 * }}
 */
export function createSession() {
    let user = null;
    let loaded = false;
    /** @type {Set<(user: *) => void>} */
    const subscribers = new Set();

    function notify() {
        for (const listener of subscribers) {
            listener(user);
        }
    }

    async function load({ force = false } = {}) {
        if (loaded && !force) {
            return user;
        }
        const data = await get(SESSION_URL);
        user = data?.user ?? null;
        loaded = true;
        notify();
        return user;
    }

    return {
        load,
        refresh: () => load({ force: true }),
        getUser: () => user,
        setUser(nextUser) {
            user = nextUser;
            loaded = true;
            notify();
        },
        isLoaded: () => loaded,
        subscribe(listener) {
            subscribers.add(listener);
            return () => subscribers.delete(listener);
        },
    };
}
