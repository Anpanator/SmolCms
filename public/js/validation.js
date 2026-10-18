/**
 * Client-side validation rules mirroring the backend contract:
 * - loginName: 1..255 characters
 * - password: 10..72 characters and at most 72 UTF-8 bytes
 * - email: non-empty, valid format
 * - displayName: 1..69 characters
 */

export const LIMITS = {
    loginNameMax: 255,
    displayNameMax: 69,
    passwordMin: 10,
    passwordMax: 72,
    passwordMaxBytes: 72,
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** @returns {string|null} error message or null when valid */
export function validateLoginName(loginName) {
    const value = loginName.trim();
    if (value.length < 1) {
        return 'Login name is required.';
    }
    if (value.length > LIMITS.loginNameMax) {
        return `Login name must be at most ${LIMITS.loginNameMax} characters.`;
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validatePassword(password) {
    if (password.length < LIMITS.passwordMin) {
        return `Password must be at least ${LIMITS.passwordMin} characters.`;
    }
    if (password.length > LIMITS.passwordMax) {
        return `Password must be at most ${LIMITS.passwordMax} characters.`;
    }
    const bytes = new TextEncoder().encode(password).length;
    if (bytes > LIMITS.passwordMaxBytes) {
        return `Password must be at most ${LIMITS.passwordMaxBytes} bytes.`;
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validateEmail(email) {
    const value = email.trim();
    if (value.length < 1) {
        return 'Email is required.';
    }
    if (!EMAIL_PATTERN.test(value)) {
        return 'Please enter a valid email address.';
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validateDisplayName(displayName) {
    const value = displayName.trim();
    if (value.length < 1) {
        return 'Display name is required.';
    }
    if (value.length > LIMITS.displayNameMax) {
        return `Display name must be at most ${LIMITS.displayNameMax} characters.`;
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validatePasswordMatch(password, passwordRepeat) {
    if (password !== passwordRepeat) {
        return 'Passwords do not match.';
    }
    return null;
}
