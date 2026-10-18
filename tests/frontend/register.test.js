import { assertEqual, assertTrue } from './assert.js';
import { mockFetch, submitForm, useFetch } from './mocks.js';
import { createRegisterView } from '../../public/js/views/register.js';

function fillValidRegistration(view) {
    const values = {
        email: 'amy@example.com',
        loginName: 'amy',
        displayName: 'Amy Pond',
        password: 'long-enough-pass',
        passwordRepeat: 'long-enough-pass',
    };
    for (const [name, value] of Object.entries(values)) {
        view.querySelector(`input[name="${name}"]`).value = value;
    }
    return values;
}

export function testRegisterViewRendersAllFields() {
    const view = createRegisterView({ navigate: () => {} });
    for (const name of ['email', 'loginName', 'displayName', 'password', 'passwordRepeat']) {
        assertTrue(view.querySelector(`input[name="${name}"]`) !== null, `${name} input should exist`);
    }
    assertTrue(view.querySelector('button[type="submit"]') !== null, 'submit button should exist');
}

export async function testInvalidInputShowsErrorsWithoutFetching() {
    const fetchMock = mockFetch(() => {
        throw new Error('fetch must not be called for invalid input');
    });
    const restore = useFetch(fetchMock);
    try {
        const view = createRegisterView({ navigate: () => {} });
        const values = fillValidRegistration(view);
        view.querySelector('input[name="email"]').value = 'not-an-email';
        view.querySelector('input[name="password"]').value = 'short';
        view.querySelector('input[name="passwordRepeat"]').value = values.password;

        await submitForm(view.querySelector('form'));
        const errors = [...view.querySelectorAll('.field-error')].filter((node) => !node.hidden);
        assertTrue(errors.length >= 2, 'email and password errors should be visible');
        assertEqual(fetchMock.calls.length, 0, 'fetch must not be called');
    } finally {
        restore();
    }
}

export async function testMismatchedPasswordRepeatShowsError() {
    const fetchMock = mockFetch(() => {
        throw new Error('fetch must not be called for invalid input');
    });
    const restore = useFetch(fetchMock);
    try {
        const view = createRegisterView({ navigate: () => {} });
        fillValidRegistration(view);
        view.querySelector('input[name="passwordRepeat"]').value = 'different-long-pass';

        await submitForm(view.querySelector('form'));
        const errorText = view.querySelector('.field-error:not([hidden])')?.textContent;
        assertEqual(errorText, 'Passwords do not match.');
        assertEqual(fetchMock.calls.length, 0);
    } finally {
        restore();
    }
}

export async function testValidSubmitPostsRegisterAndNavigatesToLogin() {
    const fetchMock = mockFetch((url) => {
        assertEqual(url, '/register', 'should post to /register');
        return { status: 201, body: { created: true } };
    });
    const restore = useFetch(fetchMock);
    try {
        const navigateCalls = [];
        const view = createRegisterView({ navigate: (path) => navigateCalls.push(path) });
        const values = fillValidRegistration(view);
        await submitForm(view.querySelector('form'));

        assertEqual(fetchMock.calls.length, 1);
        const { options } = fetchMock.calls[0];
        assertEqual(options.method, 'POST');
        assertEqual(options.headers['Content-Type'], 'application/json');
        const body = JSON.parse(options.body);
        assertEqual(body.email, values.email);
        assertEqual(body.loginName, values.loginName);
        assertEqual(body.displayName, values.displayName);
        assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login?registered=1']));
    } finally {
        restore();
    }
}

export async function testConflictShowsLoginNameTakenMessage() {
    const fetchMock = mockFetch(() => ({ status: 409, body: null }));
    const restore = useFetch(fetchMock);
    try {
        const navigateCalls = [];
        const view = createRegisterView({ navigate: (path) => navigateCalls.push(path) });
        fillValidRegistration(view);
        await submitForm(view.querySelector('form'));

        const message = view.querySelector('.form-message');
        assertTrue(!message.hidden, 'conflict message should be visible');
        assertTrue(message.textContent.includes('already taken'), '409 should map to "already taken"');
        assertEqual(navigateCalls.length, 0, 'conflict must not navigate');
    } finally {
        restore();
    }
}
