/**
 * SPA entry point. This is the only module that performs work at import
 * time (it is loaded by the shell as <script type="module">).
 */
import {createFooterNav, createHeaderNav} from './nav.js';
import {createRouter} from './router.js';
import {createSession} from './session.js';
import {el} from './dom.js';
import {createHomeView} from './views/home.js';
import {createImageView} from './views/image.js';
import {createLoginView} from './views/login.js';
import {createNotFoundView} from './views/notFound.js';
import {createRegisterView} from './views/register.js';
import {createProfileView} from './views/profile.js';
import {createReviewView} from './views/review.js';
import {createUploadView} from './views/upload.js';
import {createTagsView} from './views/tags.js';

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
    const footerNav = createFooterNav({ session });

    const viewContainer = el('main', { className: 'view-container' });
    const routes = {
        '/': (context) => createHomeView(context),
        '/login': (context) => createLoginView({ session, ...context }),
        '/register': (context) => createRegisterView(context),
        '/settings': (context) => createProfileView({session, ...context}),
        '/upload-image': (context) => createUploadView(context),
        '/image': (context) => createImageView(context),
        '/review': (context) => createReviewView({session, ...context}),
        '/tags': (context) => createTagsView({session, ...context}),
    };

    const router = createRouter({
        container: viewContainer,
        routes,
        notFound: () => createNotFoundView(),
        onNavigate: (path) => {
            viewContainer.classList.toggle('view-container--review', path === '/review');
            if (path === '/review') {
                viewContainer.querySelector('.review-page')?.focus({preventScroll: true});
            }
            headerNav.setActive(path);
            footerNav.setActive(path);
        },
    });

    appRoot.append(
        el('header', { className: 'site-header', children: headerNav.element }),
        viewContainer,
        el('footer', { className: 'site-footer', children: footerNav.element }),
    );
    router.start();
}

bootstrap();
