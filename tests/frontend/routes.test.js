import {assertDeepEqual, assertEqual, assertTrue} from './assert.js';
import {RoutePath, userProfileHref} from '../../public/js/routes.js';

export function testRoutePathsMirrorTheBackendEnum() {
    assertDeepEqual(RoutePath, {
        START_PAGE: '/',
        LOGIN: '/login',
        LOGOUT: '/logout',
        REGISTER: '/register',
        SETTINGS: '/settings',
        USER_PROFILE: '/user',
        UPLOAD_IMAGE: '/upload-image',
        IMAGE_DISPLAY: '/image',
        IMAGE_REVIEW: '/review',
        TAGS_PAGE: '/tags',
        TAG_QUEUE: '/tag-queue',
        TAGME_QUEUE: '/tagme',
        USER_MANAGEMENT: '/users',
        AUDIT_LOG: '/audit-log',
        API_SESSION: '/api/session',
        API_IMAGES: '/api/images',
        API_IMAGE_DETAIL: '/api/image/detail',
        API_TAGS: '/api/tags',
        API_TAG_SUGGEST: '/api/tags/suggest',
        API_TAG_CATEGORIES: '/api/tag-categories',
        API_TAG_DELETE: '/api/tags/delete',
        API_IMAGE_SAMPLE: '/api/image/sample',
        API_IMAGE_DOWNLOAD: '/api/image/download',
        API_REVIEW_QUEUE: '/api/review/queue',
        API_REVIEW_APPROVE: '/api/review/approve',
        API_REVIEW_REJECT: '/api/review/reject',
        API_IMAGE_TAGS: '/api/image/tags',
        API_IMAGE_TAGS_REMOVE: '/api/image/tags/remove',
        API_TAG_SUGGESTIONS: '/api/tag-suggestions',
        API_TAG_SUGGESTION_QUEUE: '/api/tag-suggestions/queue',
        API_TAG_SUGGESTION_APPROVE: '/api/tag-suggestions/approve',
        API_TAG_SUGGESTION_REJECT: '/api/tag-suggestions/reject',
        API_AUDIT_LOG: '/api/audit-log',
        API_USERS: '/api/users',
        API_USERS_UPDATE: '/api/users/update',
        API_PROFILE: '/api/profile',
        API_USER_PROFILE: '/api/user/profile',
        API_TAGME_RANDOM: '/api/tagme/random',
    });
}

export function testRoutePathsAreImmutableAndUnique() {
    assertTrue(Object.isFrozen(RoutePath));
    const paths = Object.values(RoutePath);
    assertTrue(paths.every((path) => typeof path === 'string' && path.startsWith('/')));
    assertEqualUnique(paths);
}

export function testUserProfileHrefRequiresPositiveSafeInteger() {
    assertEqual(userProfileHref(1), '/user?id=1');
    assertEqual(userProfileHref(Number.MAX_SAFE_INTEGER), `/user?id=${Number.MAX_SAFE_INTEGER}`);

    for (const invalidId of [0, -1, 1.5, Number.NaN, Number.POSITIVE_INFINITY, Number.MAX_SAFE_INTEGER + 1, '1', null]) {
        assertEqual(userProfileHref(invalidId), null, `${String(invalidId)} should not form a profile URL`);
    }
}

function assertEqualUnique(values) {
    if (new Set(values).size !== values.length) {
        throw new Error('route paths should be unique');
    }
}
