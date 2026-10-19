import {ApiError, get, postForm, postFormWithProgress, postJson, request} from '../../public/js/api.js';
import {HttpContentType, HttpHeader} from '../../public/js/httpConstants.js';
import {assertDeepEqual, assertEqual, assertRejects, assertTrue} from './assert.js';
import {mockFetch, mockXhr, useFetch, useXhr} from './mocks.js';

export async function testSuccessJsonRoundTrip() {
    const fetchMock = mockFetch((url) => {
        assertEqual(url, '/api/session', 'should request the session url');
        return { status: 200, body: { user: { id: 1 } } };
    });
    const restore = useFetch(fetchMock);
    try {
        const data = await get('/api/session');
        assertDeepEqual(data, { user: { id: 1 } });
        assertEqual(fetchMock.calls.length, 1, 'fetch should be called once');
        assertEqual(fetchMock.calls[0].options.method, 'GET');
    } finally {
        restore();
    }
}

export async function testJsonBodyGetsJsonContentType() {
    const fetchMock = mockFetch(() => ({ status: 200, body: { ok: true } }));
    const restore = useFetch(fetchMock);
    try {
        await postJson('/login', { loginName: 'amy', password: 'long-enough-pass' });
        const { options } = fetchMock.calls[0];
        assertEqual(options.method, 'POST');
        assertEqual(options.headers[HttpHeader.CONTENT_TYPE], HttpContentType.JSON);
        assertDeepEqual(JSON.parse(options.body), { loginName: 'amy', password: 'long-enough-pass' });
    } finally {
        restore();
    }
}

export async function testNon2xxThrowsApiErrorWithStatus() {
    const fetchMock = mockFetch(() => ({ status: 409, body: null }));
    const restore = useFetch(fetchMock);
    try {
        const error = await assertRejects(() => postJson('/register', {}));
        assertTrue(error instanceof ApiError, 'should throw an ApiError');
        assertEqual(error.status, 409);
        assertEqual(error.body, null);
    } finally {
        restore();
    }
}

export async function testNetworkFailureThrowsApiError() {
    const restore = useFetch(() => Promise.reject(new TypeError('Failed to fetch')));
    try {
        const error = await assertRejects(() => get('/api/session'));
        assertTrue(error instanceof ApiError, 'network failure should throw an ApiError');
        assertEqual(error.status, 0);
        assertEqual(error.body, null);
    } finally {
        restore();
    }
}

export async function testEmptyBody204ResolvesToNull() {
    const fetchMock = mockFetch(() => ({ status: 204, body: null }));
    const restore = useFetch(fetchMock);
    try {
        const data = await postJson('/logout');
        assertEqual(data, null, '204 response should decode to null');
        assertEqual(fetchMock.calls[0].options.method, 'POST');
    } finally {
        restore();
    }
}

export async function testFormDataPassesThroughUntouched() {
    const fetchMock = mockFetch(() => ({ status: 201, body: { filename: 'a.png' } }));
    const restore = useFetch(fetchMock);
    try {
        const formData = new FormData();
        formData.append('image', new File(['x'], 'a.png', { type: 'image/png' }));
        await postForm('/upload-image', formData);

        const { options } = fetchMock.calls[0];
        assertEqual(options.method, 'POST');
        assertEqual(options.body, formData, 'FormData instance should be passed through untouched');
        assertTrue(!(HttpHeader.CONTENT_TYPE in options.headers), 'multipart must not get a JSON content-type header');
    } finally {
        restore();
    }
}

export async function testMultipartProgressUsesXhrAndDoesNotSetContentType() {
    const xhrMock = mockXhr(() => ({
        status: 201,
        body: {filename: 'a.png'},
        progressEvents: [{loaded: 3, total: 10, lengthComputable: true}],
    }));
    const restore = useXhr(xhrMock);
    try {
        const formData = new FormData();
        formData.append('image[]', new File(['x'], 'a.png', {type: 'image/png'}));
        const progressEvents = [];
        const data = await postFormWithProgress('/upload-image', formData, (event) => {
            progressEvents.push({loaded: event.loaded, total: event.total, lengthComputable: event.lengthComputable});
        });

        assertDeepEqual(data, {filename: 'a.png'});
        assertEqual(xhrMock.calls.length, 1);
        assertEqual(xhrMock.calls[0].method, 'POST');
        assertEqual(xhrMock.calls[0].url, '/upload-image');
        assertEqual(xhrMock.calls[0].body, formData, 'FormData should be passed through untouched');
        assertEqual(xhrMock.calls[0].headers.length, 0, 'multipart Content-Type should be set by the browser');
        assertDeepEqual(progressEvents, [{loaded: 3, total: 10, lengthComputable: true}]);
    } finally {
        restore();
    }
}

export async function testMultipartApiErrorPreservesDecodedBody() {
    const xhrMock = mockXhr(() => ({status: 422, body: {error: 'bad image'}}));
    const restore = useXhr(xhrMock);
    try {
        const error = await assertRejects(() => postFormWithProgress('/upload-image', new FormData()));
        assertTrue(error instanceof ApiError, 'multipart errors should be ApiError instances');
        assertEqual(error.status, 422);
        assertDeepEqual(error.body, {error: 'bad image'});
    } finally {
        restore();
    }
}

export async function testMultipartNetworkFailureUsesApiError() {
    const xhrMock = mockXhr(() => Promise.reject(new TypeError('Network error')));
    const restore = useXhr(xhrMock);
    try {
        const error = await assertRejects(() => postFormWithProgress('/upload-image', new FormData()));
        assertTrue(error instanceof ApiError, 'multipart network failures should be ApiError instances');
        assertEqual(error.status, 0);
        assertEqual(error.body, null);
    } finally {
        restore();
    }
}

export async function testNonJsonBodyResolvesAsText() {
    const fetchMock = mockFetch(() => ({ status: 200, body: 'plain-text-ok' }));
    const restore = useFetch(fetchMock);
    try {
        assertEqual(await get('/anything'), 'plain-text-ok');
    } finally {
        restore();
    }
}

export async function testErrorBodyIsPreservedOnApiError() {
    const fetchMock = mockFetch(() => ({ status: 400, body: { error: 'bad' } }));
    const restore = useFetch(fetchMock);
    try {
        const error = await assertRejects(() => request('/anything'));
        assertDeepEqual(error.body, { error: 'bad' });
    } finally {
        restore();
    }
}
