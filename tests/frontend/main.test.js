import {assertEqual, assertTrue} from './assert.js';
import {createRouteMap, isModerationRoute} from '../../public/js/main.js';
import {RoutePath} from '../../public/js/routes.js';

export function testMainRouteRegistryCoversEveryClientPage() {
    const routes = createRouteMap({session: {}, navigate: () => {}});
    const expected = [
        RoutePath.START_PAGE,
        RoutePath.LOGIN,
        RoutePath.REGISTER,
        RoutePath.SETTINGS,
        RoutePath.USER_PROFILE,
        RoutePath.UPLOAD_IMAGE,
        RoutePath.IMAGE_DISPLAY,
        RoutePath.TAGME_QUEUE,
        RoutePath.IMAGE_REVIEW,
        RoutePath.TAG_QUEUE,
        RoutePath.TAGS_PAGE,
        RoutePath.USER_MANAGEMENT,
        RoutePath.AUDIT_LOG,
    ];

    assertEqual(Object.keys(routes).join('|'), expected.join('|'));
    assertTrue(Object.values(routes).every((factory) => typeof factory === 'function'));
}

export function testMainIdentifiesOnlyModerationRoutes() {
    assertTrue(isModerationRoute(RoutePath.IMAGE_REVIEW));
    assertTrue(isModerationRoute(RoutePath.TAG_QUEUE));
    assertTrue(!isModerationRoute(RoutePath.START_PAGE));
    assertTrue(!isModerationRoute(RoutePath.TAGS_PAGE));
}
