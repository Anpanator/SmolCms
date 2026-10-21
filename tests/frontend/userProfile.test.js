import {assertEqual, assertFalse, assertTrue, settle} from './assert.js';
import {mockFetch, testUser, useFetch} from './mocks.js';
import {createUserProfileView} from '../../public/js/views/userProfile.js';

const PROFILE = {
    user: {
        id: 2,
        displayName: 'Public User',
        accessLevel: 300,
        registerDate: '2026-10-12 09:30:00',
    },
    approvedTagSuggestions: 4,
    recentUploads: [
        {
            id: 18,
            thumbSrc: '/public/img/thumb/18.avif',
            status: 'accepted',
            uploadDate: '2026-10-20 12:00:00',
        },
        {
            id: 19,
            thumbSrc: '/public/img/thumb/19.avif',
            status: 'pending_review',
            uploadDate: '2026-10-20 13:00:00',
        },
    ],
    modActions: [],
};

function profileSession(user = null) {
    return {getUser: () => user};
}

function createView({sessionUser = null, query = 'id=2', response = {status: 200, body: PROFILE}} = {}) {
    const fetchMock = mockFetch((url) => {
        if (url === '/api/user/profile?id=2') {
            return response;
        }
        throw new Error(`unexpected fetch of ${url}`);
    });
    const restore = useFetch(fetchMock);
    const view = createUserProfileView({
        session: profileSession(sessionUser),
        query: query instanceof URLSearchParams ? query : new URLSearchParams(query),
    });
    return {view, fetchMock, restore};
}

export async function testAnonymousUsersCanViewPublicProfiles() {
    const {view, fetchMock, restore} = createView();
    try {
        await settle();

        assertEqual(fetchMock.calls.length, 1);
        assertEqual(fetchMock.calls[0].url, '/api/user/profile?id=2');
        assertEqual(view.querySelector('.user-profile-title')?.textContent, 'Public User');
        assertTrue(view.querySelector('.form-message')?.hidden, 'a public profile should load without a login');
        assertEqual(view.querySelector('.user-profile-edit'), null, 'anonymous visitors cannot edit the profile');
    } finally {
        restore();
    }
}

export async function testOwnProfileLinksToSettingsAndOtherProfilesAreViewOnly() {
    const own = createView({sessionUser: testUser({id: 2})});
    try {
        await settle();
        assertEqual(own.view.querySelector('.user-profile-edit')?.getAttribute('href'), '/settings');
        assertEqual(own.view.querySelector('.user-profile-edit')?.textContent, 'Edit profile');
    } finally {
        own.restore();
    }

    const other = createView({sessionUser: testUser({id: 1})});
    try {
        await settle();
        assertEqual(other.view.querySelector('.user-profile-edit'), null);
        assertEqual(other.view.querySelector('a[href="/settings"]'), null);
        assertEqual(other.view.querySelector('form, input, select, textarea, button'), null);
    } finally {
        other.restore();
    }
}

export async function testUserProfileRendersDetailsRecentUploadsAndModerationActions() {
    const body = {
        ...PROFILE,
        modActions: [
            {
                id: 5,
                actionType: 1,
                context: 'Added tag "landscape" to image #18',
                time: '2026-10-20 14:30:00',
            },
        ],
    };
    const {view, restore} = createView({response: {status: 200, body}});
    try {
        await settle();

        assertTrue(view.querySelector('.user-profile-details').textContent.includes('Master'));
        assertTrue(view.querySelector('.user-profile-details').textContent.includes('2026-10-12 09:30:00'));
        assertTrue(view.querySelector('.user-profile-details').textContent.includes('Approved tag suggestions4'));

        const grid = view.querySelector('.profile-recent-uploads-grid');
        assertEqual(grid?.querySelectorAll('a').length, 2);
        assertEqual(grid?.querySelector('a')?.getAttribute('href'), '/image?id=18');
        assertTrue(grid?.querySelector('a[data-image-id="19"]')?.classList.contains('tile-unapproved'));

        const actions = view.querySelector('.user-profile-mod-actions');
        assertEqual(actions?.querySelector('h2')?.textContent, 'Moderation actions');
        assertEqual(actions?.querySelectorAll('.user-profile-mod-action').length, 1);
        assertTrue(actions?.textContent.includes('Tag added'));
        assertTrue(actions?.textContent.includes('Added tag "landscape" to image #18'));
        assertEqual(actions?.querySelector('time')?.getAttribute('datetime'), '2026-10-20 14:30:00');
    } finally {
        restore();
    }
}

export async function testUserProfileOmitsEmptyModerationActions() {
    const {view, restore} = createView();
    try {
        await settle();
        assertEqual(view.querySelector('.user-profile-mod-actions'), null);
        const content = view.querySelector('.user-profile-content');
        assertEqual(content?.childNodes.length, 2, 'empty moderation actions should not add content');
        assertFalse(
            content?.textContent.includes('null'),
            'empty moderation actions should not render a "null" text node',
        );
    } finally {
        restore();
    }
}

export function testMalformedOrMissingProfileIdShowsNotFoundWithoutFetching() {
    for (const query of [
        new URLSearchParams(),
        new URLSearchParams('id=abc'),
        new URLSearchParams('id=0'),
        new URLSearchParams('id=-1'),
        new URLSearchParams('id=01'),
        new URLSearchParams('id=9007199254740992'),
    ]) {
        const fetchMock = mockFetch(() => {
            throw new Error('invalid IDs must not fetch a profile');
        });
        const restore = useFetch(fetchMock);
        try {
            const view = createUserProfileView({session: profileSession(), query});
            assertEqual(view.querySelector('.form-message')?.textContent, 'User not found.');
            assertEqual(fetchMock.calls.length, 0);
        } finally {
            restore();
        }
    }
}

export async function testUserProfileMapsNotFoundPermissionAndUnexpectedErrors() {
    for (const [status, expectedMessage] of [
        [404, 'User not found.'],
        [401, 'You do not have permission to view this profile.'],
        [403, 'You do not have permission to view this profile.'],
        [500, 'Something went wrong on the server. Please try again later.'],
    ]) {
        const {view, restore} = createView({response: {status, body: null}});
        try {
            await settle();
            assertEqual(view.querySelector('.form-message')?.textContent, expectedMessage);
            assertEqual(view.querySelector('.user-profile-details'), null);
        } finally {
            restore();
        }
    }
}

export async function testMissingUserInSuccessfulResponseShowsNotFound() {
    const {view, restore} = createView({response: {status: 200, body: {user: null}}});
    try {
        await settle();
        assertEqual(view.querySelector('.form-message')?.textContent, 'User not found.');
    } finally {
        restore();
    }
}
