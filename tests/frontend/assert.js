/**
 * Tiny dependency-free assertion helpers. Every helper throws on failure.
 * Importing this module has no side effects.
 */

function fmt(value) {
    if (typeof value === 'string') {
        return JSON.stringify(value);
    }
    try {
        return JSON.stringify(value) ?? String(value);
    } catch {
        return String(value);
    }
}

export function fail(message = 'failed') {
    throw new Error(message);
}

export function assertTrue(value, message = 'expected value to be true') {
    if (value !== true) {
        throw new Error(`${message} (got ${fmt(value)})`);
    }
}

export function assertFalse(value, message = 'expected value to be false') {
    if (value !== false) {
        throw new Error(`${message} (got ${fmt(value)})`);
    }
}

export function assertEqual(actual, expected, message = 'expected values to be equal') {
    if (actual !== expected) {
        throw new Error(`${message}: expected ${fmt(expected)}, got ${fmt(actual)}`);
    }
}

export function assertNotEqual(actual, expected, message = 'expected values to differ') {
    if (actual === expected) {
        throw new Error(`${message}, but both were ${fmt(actual)}`);
    }
}

export function assertDeepEqual(actual, expected, message = 'expected deep equality') {
    const actualJson = JSON.stringify(actual);
    const expectedJson = JSON.stringify(expected);
    if (actualJson !== expectedJson) {
        throw new Error(`${message}: expected ${expectedJson}, got ${actualJson}`);
    }
}

export function assertMatches(value, pattern, message = 'expected value to match pattern') {
    if (!pattern.test(String(value))) {
        throw new Error(`${message}: ${fmt(value)} does not match ${pattern}`);
    }
}

export function assertThrows(callback, message = 'expected function to throw') {
    try {
        callback();
    } catch (error) {
        return error;
    }
    throw new Error(message);
}

export async function assertRejects(callbackOrPromise, predicate = null, message = 'expected promise to reject') {
    let promise;
    try {
        promise = typeof callbackOrPromise === 'function' ? callbackOrPromise() : callbackOrPromise;
        await promise;
    } catch (error) {
        if (predicate instanceof Function && !predicate(error)) {
            throw new Error(`${message}, but rejection did not satisfy the predicate: ${error}`);
        }
        if (predicate instanceof RegExp && !predicate.test(String(error?.message ?? error))) {
            throw new Error(`${message}, but message was: ${error?.message ?? error}`);
        }
        return error;
    }
    throw new Error(message);
}

/**
 * Waits until pending async work (including multi-step response body stream
 * reads, which span several macrotask turns) has settled.
 */
export function settle(turns = 12) {
    return new Promise((resolve) => {
        const step = (remaining) => (remaining > 0 ? setTimeout(() => step(remaining - 1), 0) : resolve());
        setTimeout(() => step(turns), 0);
    });
}
