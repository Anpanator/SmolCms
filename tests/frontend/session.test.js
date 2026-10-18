import { assertEqual, assertTrue } from './assert.js';
import { mockFetch, sessionResponse, testUser, useFetch } from './mocks.js';
import { createSession } from '../../public/js/session.js';

export async function testInitialLoadFetchesAndCachesUser() {
    const fetchMock = mockFetch((url) => {
        assertEqual(url, '/api/session', 'session should load from /api/session');
        return sessionResponse(testUser({ displayName: 'Amy' }));
    });
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        assertTrue(!session.isLoaded(), 'session should not be loaded before load()');
        const user = await session.load();
        assertEqual(user.displayName, 'Amy');
        assertEqual(session.getUser()?.displayName, 'Amy', 'user should be cached');
        assertTrue(session.isLoaded());
        assertEqual(fetchMock.calls.length, 1);
    } finally {
        restore();
    }
}

export async function testLoadIsCachedWithoutForce() {
    const fetchMock = mockFetch(() => sessionResponse(testUser()));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        await session.load();
        await session.load();
        assertEqual(fetchMock.calls.length, 1, 'cached load must not refetch');
    } finally {
        restore();
    }
}

export async function testRefreshRefetches() {
    const fetchMock = mockFetch(() => sessionResponse(testUser()));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        await session.refresh();
        await session.refresh();
        assertEqual(fetchMock.calls.length, 3, 'refresh() must refetch every time');
    } finally {
        restore();
    }
}

export async function testSubscribersAreNotifiedOnLoadAndRefresh() {
    const seen = [];
    const users = [
        testUser({ id: 1, displayName: 'First' }),
        testUser({ id: 1, displayName: 'Second' }),
    ];
    let call = 0;
    const fetchMock = mockFetch(() => sessionResponse(users[call++]));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        const unsubscribe = session.subscribe((user) => seen.push(user?.displayName ?? null));
        await session.load();
        await session.refresh();
        assertEqual(JSON.stringify(seen), JSON.stringify(['First', 'Second']), 'subscribers should see each change');

        unsubscribe();
        await session.refresh();
        assertEqual(JSON.stringify(seen), JSON.stringify(['First', 'Second']), 'unsubscribed listeners must not be called');
    } finally {
        restore();
    }
}

export async function testAnonymousSessionLoadsUserNull() {
    const fetchMock = mockFetch(() => sessionResponse(null));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        const user = await session.load();
        assertEqual(user, null, '{"user":null} should yield a null user');
        assertEqual(session.getUser(), null);
        assertTrue(session.isLoaded());
    } finally {
        restore();
    }
}

export async function testSetUserUpdatesAndNotifies() {
    const fetchMock = mockFetch(() => sessionResponse(null));
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        const seen = [];
        const unsubscribe = session.subscribe((user) => seen.push(user));
        const updated = testUser({ displayName: 'Updated' });
        session.setUser(updated);
        assertEqual(session.getUser()?.displayName, 'Updated');
        assertEqual(seen.length, 1, 'setUser should notify subscribers');
        assertEqual(seen[0]?.displayName, 'Updated');
        unsubscribe();
    } finally {
        restore();
    }
}
