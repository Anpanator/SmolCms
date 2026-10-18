import { assertEqual, assertTrue } from './assert.js';
import { mockFetch, sessionResponse, submitForm, testUser, useFetch } from './mocks.js';
import { createSession } from '../../public/js/session.js';
import { createLoginView } from '../../public/js/views/login.js';

function fieldValues(view) {
    return {
        loginName: view.querySelector('input[name="loginName"]'),
        password: view.querySelector('input[name="password"]'),
    };
}

export function testLoginViewRendersFields() {
    const session = createSession();
    const view = createLoginView({ session, navigate: () => {} });
    assertTrue(view.querySelector('input[name="loginName"]') !== null, 'loginName input should exist');
    assertTrue(view.querySelector('input[name="password"]') !== null, 'password input should exist');
    assertTrue(view.querySelector('input[type="password"]') !== null, 'password should be masked');
    assertTrue(view.querySelector('button[type="submit"]') !== null, 'submit button should exist');
}

export async function testInvalidInputShowsErrorsWithoutFetching() {
    const fetchMock = mockFetch(() => {
        throw new Error('fetch must not be called for invalid input');
    });
    const restore = useFetch(fetchMock);
    try {
        const view = createLoginView({ session: createSession(), navigate: () => {} });
        const inputs = fieldValues(view);
        inputs.loginName.value = '';
        inputs.password.value = 'short';
        await submitForm(view.querySelector('form'));

        const errors = [...view.querySelectorAll('.field-error')].filter((node) => !node.hidden);
        assertEqual(errors.length, 2, 'both fields should show validation errors');
        assertEqual(fetchMock.calls.length, 0, 'fetch must not be called');
    } finally {
        restore();
    }
}

export async function testValidSubmitPostsLoginAndNavigatesHome() {
    let loginCalls = 0;
    let sessionCalls = 0;
    const fetchMock = mockFetch((url, options) => {
        if (url === '/login') {
            loginCalls++;
            return { status: 200, body: { user: testUser() } };
        }
        if (url === '/api/session') {
            sessionCalls++;
            return sessionResponse(sessionCalls === 1 ? null : testUser());
        }
        throw new Error(`unexpected url ${url}`);
    });
    const restore = useFetch(fetchMock);
    try {
        const session = createSession();
        await session.load();
        const navigateCalls = [];
        const view = createLoginView({ session, navigate: (path) => navigateCalls.push(path) });
        const inputs = fieldValues(view);
        inputs.loginName.value = '  amy  ';
        inputs.password.value = 'long-enough-pass';
        await submitForm(view.querySelector('form'));

        assertEqual(loginCalls, 1, 'POST /login should be called once');
        const { url, options } = fetchMock.calls.find((call) => call.url === '/login');
        assertEqual(url, '/login');
        assertEqual(options.method, 'POST');
        assertEqual(JSON.parse(options.body).loginName, 'amy', 'login name should be trimmed');
        assertTrue(session.getUser()?.displayName === testUser().displayName, 'session should be refreshed after login');
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/']), 'should navigate home after login');
    } finally {
        restore();
    }
}

export async function testFailedLoginShowsErrorMessage() {
    const fetchMock = mockFetch((url) => {
        if (url === '/login') {
            return { status: 401, body: null };
        }
        return sessionResponse(null);
    });
    const restore = useFetch(fetchMock);
    try {
        const navigateCalls = [];
        const view = createLoginView({ session: createSession(), navigate: (path) => navigateCalls.push(path) });
        const inputs = fieldValues(view);
        inputs.loginName.value = 'amy';
        inputs.password.value = 'long-enough-pass';
        await submitForm(view.querySelector('form'));

        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, 'an error message should be visible');
        assertTrue(message.textContent.length > 0, 'error message should have text');
        assertEqual(navigateCalls.length, 0, 'failed login must not navigate');
    } finally {
        restore();
    }
}

export async function testRegisteredNoticeIsShownAfterRedirect() {
    const restore = useFetch(() => sessionResponse(null));
    try {
        const view = createLoginView({
            session: createSession(),
            navigate: () => {},
            query: new URLSearchParams('registered=1'),
        });
        const notice = view.querySelector('.notice--success');
        assertTrue(notice !== null, 'success notice should render');
        assertTrue(notice.textContent.includes('Registration successful'), 'notice should mention registration');
    } finally {
        restore();
    }
}
