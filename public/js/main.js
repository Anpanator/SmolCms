/**
 * SPA entry point. This is the only module that performs work at import
 * time (it is loaded by the shell as <script type="module">).
 */
import {createHeaderNav} from './nav.js';
import {createRouter} from './router.js';
import {createSession} from './session.js';
import {el} from './dom.js';
import {RoutePath} from './routes.js';
import {createHomeView} from './views/home.js';
import {createImageView} from './views/image.js';
import {createLoginView} from './views/login.js';
import {createNotFoundView} from './views/notFound.js';
import {createRegisterView} from './views/register.js';
import {createProfileView} from './views/profile.js';
import {createUserProfileView} from './views/userProfile.js';
import {createReviewView} from './views/review.js';
import {createTagQueueView} from './views/tagQueue.js';
import {createUploadView} from './views/upload.js';
import {createTagsView} from './views/tags.js';
import {createTagmeView} from './views/tagme.js';
import {createUsersView} from './views/users.js';
import {createAuditLogView} from './views/auditLog.js';

export function createRouteMap({session, navigate}) {
    return {
        [RoutePath.START_PAGE]: (context) => createHomeView(context),
        [RoutePath.LOGIN]: (context) => createLoginView({session, ...context}),
        [RoutePath.REGISTER]: (context) => createRegisterView(context),
        [RoutePath.SETTINGS]: (context) => createProfileView({session, ...context}),
        [RoutePath.USER_PROFILE]: (context) => createUserProfileView({session, ...context}),
        [RoutePath.UPLOAD_IMAGE]: (context) => createUploadView(context),
        [RoutePath.IMAGE_DISPLAY]: (context) => createImageView({session, ...context}),
        [RoutePath.TAGME_QUEUE]: (context) => createTagmeView({session, ...context}),
        [RoutePath.IMAGE_REVIEW]: (context) => createReviewView({session, navigate, ...context}),
        [RoutePath.TAG_QUEUE]: (context) => createTagQueueView({session, navigate, ...context}),
        [RoutePath.TAGS_PAGE]: (context) => createTagsView({session, navigate, ...context}),
        [RoutePath.USER_MANAGEMENT]: (context) => createUsersView({session, navigate, ...context}),
        [RoutePath.AUDIT_LOG]: (context) => createAuditLogView({session, navigate, ...context}),
    };
}

export function isModerationRoute(path) {
    return path === RoutePath.IMAGE_REVIEW || path === RoutePath.TAG_QUEUE;
}

/**
 * Publishes the rendered header height so viewport-sized views can subtract it.
 * The header can wrap when the navigation changes or the window narrows, so a
 * ResizeObserver keeps the custom property current.
 *
 * @param {HTMLElement} header
 * @param {HTMLElement} root
 * @returns {{update: () => void, disconnect: () => void}}
 */
export function syncHeaderHeight(header, root = document.documentElement) {
    const update = () => {
        root.style.setProperty('--site-header-height', `${header.getBoundingClientRect().height}px`);
    };
    update();
    const observer = new ResizeObserver(update);
    observer.observe(header);
    return {
        update,
        disconnect() {
            observer.disconnect();
        },
    };
}

async function bootstrap() {
    const appRoot = document.getElementById('app');
    if (!appRoot) {
        return;
    }

    const session = createSession();
    await session.load();

    /** Hoisted so nav and route factories can reference it before router creation. */
    function navigate(path, options) {
        router.navigate(path, options);
    }

    const headerNav = createHeaderNav({ session, navigate });

    const viewContainer = el('main', { className: 'view-container' });
    const routes = createRouteMap({session, navigate});

    const router = createRouter({
        container: viewContainer,
        routes,
        notFound: () => createNotFoundView(),
        onNavigate: (path) => {
            const isModerationQueue = isModerationRoute(path);
            viewContainer.classList.toggle('view-container--review', isModerationQueue);
            if (isModerationQueue) {
                viewContainer.querySelector('.review-page')?.focus({preventScroll: true});
            }
            headerNav.setActive(path);
        },
    });

    const header = el('header', { className: 'site-header', children: headerNav.element });
    appRoot.append(header, viewContainer);
    syncHeaderHeight(header);
    router.start();
}

bootstrap();
