import {assertEqual, assertTrue} from './assert.js';
import {createRouteMap, isModerationRoute, syncHeaderHeight} from '../../public/js/main.js';
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

export function testMainTracksWrappedHeaderHeight(fixture) {
    const header = document.createElement('header');
    header.className = 'site-header';
    header.style.height = '57px';
    fixture.append(header);

    const root = document.documentElement;
    const previousHeight = root.style.getPropertyValue('--site-header-height');
    const tracking = syncHeaderHeight(header, root);
    try {
        assertEqual(root.style.getPropertyValue('--site-header-height'), '57px',
            'the header height should be published immediately for viewport-sized views');

        header.style.height = '105px';
        tracking.update();
        assertEqual(root.style.getPropertyValue('--site-header-height'), '105px',
            'updates should reflect a wrapped header');
    } finally {
        tracking.disconnect();
        if (previousHeight === '') {
            root.style.removeProperty('--site-header-height');
        } else {
            root.style.setProperty('--site-header-height', previousHeight);
        }
        header.remove();
    }
}
