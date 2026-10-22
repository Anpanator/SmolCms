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
 *   getQuickTagsEnabled: () => boolean,
 *   setQuickTagsEnabled: (enabled: boolean) => void,
 *   isLoaded: () => boolean,
 *   subscribe: (listener: (user: *) => void) => () => void,
 * }}
 */
export function createSession() {
    let user = null;
    let quickTagsEnabled = false;
    let loaded = false;
    /** @type {Set<(user: *) => void>} */
    const subscribers = new Set();

    function notify() {
        for (const listener of subscribers) {
            listener(user, quickTagsEnabled);
        }
    }

    async function load({ force = false } = {}) {
        if (loaded && !force) {
            return user;
        }
        const data = await get(SESSION_URL);
        user = data?.user ?? null;
        quickTagsEnabled = user !== null && data?.quickTagsEnabled === true;
        loaded = true;
        notify();
        return user;
    }

    return {
        load,
        refresh: () => load({ force: true }),
        getUser: () => user,
        setUser(nextUser) {
            if (!nextUser || nextUser?.id !== user?.id) {
                quickTagsEnabled = false;
            }
            user = nextUser;
            loaded = true;
            notify();
        },
        getQuickTagsEnabled: () => quickTagsEnabled,
        setQuickTagsEnabled(enabled) {
            quickTagsEnabled = user !== null && enabled === true;
            notify();
        },
        isLoaded: () => loaded,
        subscribe(listener) {
            subscribers.add(listener);
            return () => subscribers.delete(listener);
        },
    };
}
