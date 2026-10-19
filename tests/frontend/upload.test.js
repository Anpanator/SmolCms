import {assertEqual, assertTrue} from './assert.js';
import {HttpHeader} from '../../public/js/httpConstants.js';
import {mockFetch, sessionResponse, submitForm, testUser, useFetch} from './mocks.js';
import {createSession} from '../../public/js/session.js';
import {createUploadView} from '../../public/js/views/upload.js';

async function createLoggedInSession() {
    const fetchMock = mockFetch((url) => {
        if (url === '/api/session') {
            return sessionResponse(testUser());
        }
        throw new Error(`unexpected fetch of ${url}`);
    });
    const restore = useFetch(fetchMock);
    const session = createSession();
    await session.load();
    return { session, restore };
}

function chooseFile(view, name = 'cat.png') {
    const input = view.querySelector('input[name="image"]');
    const transfer = new DataTransfer();
    transfer.items.add(new File(['image-bytes'], name, { type: 'image/png' }));
    input.files = transfer.files;
    return input;
}

export function testUploadViewRendersFileInput() {
    const view = createUploadView({ navigate: () => {} });
    const input = view.querySelector('input[name="image"]');
    assertTrue(input !== null, 'image file input should exist');
    assertEqual(input.type, 'file');
    assertTrue(view.querySelector('button[type="submit"]') !== null, 'submit button should exist');
}

export async function testMissingFileShowsErrorWithoutFetching() {
    const fetchMock = mockFetch(() => {
        throw new Error('fetch must not be called without a file');
    });
    const restore = useFetch(fetchMock);
    try {
        const view = createUploadView({ navigate: () => {} });
        await submitForm(view.querySelector('form'));
        const error = view.querySelector('.field-error');
        assertTrue(!error.hidden, 'missing file should show an error');
        assertTrue(error.textContent.includes('choose an image'), 'error should ask for a file');
        assertEqual(fetchMock.calls.length, 0);
    } finally {
        restore();
    }
}

export async function testValidSubmitPostsFormData() {
    const { session, restore } = await createLoggedInSession();
    try {
        const fetchMock = mockFetch((url) => {
            assertEqual(url, '/upload-image', 'should post to /upload-image');
            return {status: 201, body: {message: 'Image uploaded successfully.'}};
        });
        const restoreUpload = useFetch(fetchMock);
        try {
            const view = createUploadView({ session, navigate: () => {} });
            const input = chooseFile(view);
            await submitForm(view.querySelector('form'));

            assertEqual(fetchMock.calls.length, 1);
            const { options } = fetchMock.calls[0];
            assertEqual(options.method, 'POST');
            assertTrue(options.body instanceof FormData, 'upload must use multipart FormData');
            assertTrue(options.body.get('image') instanceof File, 'file must be sent in field "image"');
            assertEqual(options.body.get('image').name, 'cat.png');
            assertTrue(!(HttpHeader.CONTENT_TYPE in options.headers), 'no manual content-type for multipart');

            const message = view.querySelector('.form-message');
            assertTrue(!message.hidden, 'success message should be visible');
            assertEqual(message.textContent, 'Image uploaded successfully.');
            assertTrue(!message.textContent.includes('cat.png'), 'success message must not contain the uploaded filename');
            assertEqual(input.files.length, 0, 'form should be reset after upload');
        } finally {
            restoreUpload();
        }
    } finally {
        restore();
    }
}

export async function testUnauthorizedNavigatesToLogin() {
    const { session, restore } = await createLoggedInSession();
    try {
        const fetchMock = mockFetch(() => ({ status: 401, body: null }));
        const restoreUpload = useFetch(fetchMock);
        try {
            const navigateCalls = [];
            const view = createUploadView({ session, navigate: (path) => navigateCalls.push(path) });
            chooseFile(view);
            await submitForm(view.querySelector('form'));
            assertEqual(JSON.stringify(navigateCalls), JSON.stringify(['/login']), '401 should navigate to the login view');
        } finally {
            restoreUpload();
        }
    } finally {
        restore();
    }
}

export async function testForbiddenShowsPermissionMessage() {
    const { session, restore } = await createLoggedInSession();
    try {
        const fetchMock = mockFetch(() => ({ status: 403, body: null }));
        const restoreUpload = useFetch(fetchMock);
        try {
            const navigateCalls = [];
            const view = createUploadView({ session, navigate: (path) => navigateCalls.push(path) });
            chooseFile(view);
            await submitForm(view.querySelector('form'));

            const message = view.querySelector('.form-message');
            assertTrue(!message.hidden, '403 should show a message');
            assertTrue(message.textContent.includes('permission'), '403 should map to a permission message');
            assertEqual(navigateCalls.length, 0, '403 should not navigate');
        } finally {
            restoreUpload();
        }
    } finally {
        restore();
    }
}

export async function testServerErrorShowsGenericMessage() {
    const { session, restore } = await createLoggedInSession();
    try {
        const fetchMock = mockFetch(() => ({ status: 500, body: null }));
        const restoreUpload = useFetch(fetchMock);
        try {
            const view = createUploadView({ session, navigate: () => {} });
            chooseFile(view);
            await submitForm(view.querySelector('form'));

            const message = view.querySelector('.form-message');
            assertTrue(!message.hidden, '500 should show a message');
            assertTrue(message.textContent.includes('went wrong'), '500 should map to a generic error');
        } finally {
            restoreUpload();
        }
    } finally {
        restore();
    }
}
