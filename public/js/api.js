/**
 * Minimal JSON/multipart fetch wrapper.
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
        throw new ApiError(0, null, 'Network error: could not reach the server.');
    }

    const text = await response.text();
    let decoded = null;
    if (text.length > 0) {
        try {
            decoded = JSON.parse(text);
        } catch {
            decoded = text;
        }
    }

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
