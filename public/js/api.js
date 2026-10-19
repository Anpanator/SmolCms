/**
 * Minimal JSON/fetch and multipart/XHR wrappers.
 *
 * All functions resolve with the decoded response body (or null for empty
 * bodies such as 204) and reject with an ApiError for non-2xx responses and
 * network failures. No work happens at import time.
 */
import {HttpContentType, HttpHeader} from './httpConstants.js';

export class ApiError extends Error {
    /**
     * @param {number} status HTTP status code, 0 for network failures
     * @param {*} body decoded response body, null when empty
     * @param {string} [message]
     */
    constructor(status, body, message = `API error (status ${status})`) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }
}

function decodeResponseBody(text) {
    if (!text) {
        return null;
    }
    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

function networkError() {
    return new ApiError(0, null, 'Network error: could not reach the server.');
}

/**
 * Perform a request against the backend.
 *
 * @param {string} url
 * @param {{ method?: string, body?: *|FormData, headers?: Record<string, string> }} [options]
 * - JSON objects are serialized and sent with the standard JSON content type.
 * - FormData instances are passed through untouched (multipart handling,
 *   including the multipart boundary, is left to the browser).
 * @returns {Promise<*>} decoded JSON body, raw text for non-JSON bodies, null for empty bodies
 * @throws {ApiError} on non-2xx status and network failure
 */
export async function request(url, { method = 'GET', body = null, headers = {} } = {}) {
    let payload = null;
    const requestHeaders = { ...headers };

    if (body instanceof FormData) {
        payload = body;
    } else if (body !== null && body !== undefined) {
        payload = JSON.stringify(body);
        requestHeaders[HttpHeader.CONTENT_TYPE] = HttpContentType.JSON;
    }

    let response;
    try {
        response = await fetch(url, { method, headers: requestHeaders, body: payload });
    } catch {
        throw networkError();
    }

    const text = await response.text();
    const decoded = decodeResponseBody(text);

    if (!response.ok) {
        throw new ApiError(response.status, decoded);
    }
    return decoded;
}

/** GET a JSON resource. */
export const get = (url) => request(url);

/** POST a JSON body. */
export const postJson = (url, body) => request(url, { method: 'POST', body });

/** POST a multipart FormData body. */
export const postForm = (url, body) => request(url, { method: 'POST', body });

/**
 * POST a multipart body through XMLHttpRequest so callers can observe upload
 * progress. The browser supplies the multipart Content-Type and boundary.
 *
 * @param {string} url
 * @param {FormData} body
 * @param {(event: ProgressEvent) => void} [onUploadProgress]
 * @returns {Promise<*>} decoded response body, raw text, or null for an empty body
 * @throws {ApiError} on non-2xx status and network failure
 */
export function postFormWithProgress(url, body, onUploadProgress = () => {
}) {
    return new Promise((resolve, reject) => {
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.upload.addEventListener('progress', onUploadProgress);
            xhr.addEventListener('load', () => {
                if (xhr.status === 0) {
                    reject(networkError());
                    return;
                }

                const decoded = decodeResponseBody(xhr.responseText);
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new ApiError(xhr.status, decoded));
                    return;
                }
                resolve(decoded);
            });
            xhr.addEventListener('error', () => reject(networkError()));
            xhr.addEventListener('abort', () => reject(networkError()));
            xhr.send(body);
        } catch {
            reject(networkError());
        }
    });
}
