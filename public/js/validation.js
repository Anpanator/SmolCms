/**
 * Client-side validation rules mirroring the backend contract:
 * - loginName: 1..255 characters
 * - password: 10..72 characters and at most 72 UTF-8 bytes
 * - email: non-empty, valid format
 * - displayName: 1..69 characters
 * - tag/category names: 1..100 characters after whitespace normalization, no commas
 */

export const LIMITS = {
    loginNameMax: 255,
    displayNameMax: 69,
    emailMax: 255,
    tagNameMax: 100,
    passwordMin: 10,
    passwordMax: 72,
    passwordMaxBytes: 72,
};

const EMAIL_LOCAL_PART_PATTERN = /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*$/;
const EMAIL_DOMAIN_LABEL_PATTERN = /^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/;
const EMAIL_DOMAIN_TLD_PATTERN = /^[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/;

/** @returns {string|null} error message or null when valid */
export function validateLoginName(loginName) {
    const value = loginName.trim();
    if (codePointLength(value) < 1) {
        return 'Login name is required.';
    }
    if (codePointLength(value) > LIMITS.loginNameMax) {
        return `Login name must be at most ${LIMITS.loginNameMax} characters.`;
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validatePassword(password) {
    if (codePointLength(password) < LIMITS.passwordMin) {
        return `Password must be at least ${LIMITS.passwordMin} characters.`;
    }
    if (codePointLength(password) > LIMITS.passwordMax) {
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
    if (codePointLength(email.trim()) < 1) {
        return 'Email is required.';
    }
    if (codePointLength(email) > LIMITS.emailMax) {
        return `Email must be at most ${LIMITS.emailMax} characters.`;
    }
    if (!isValidEmail(email)) {
        return 'Please enter a valid email address.';
    }
    return null;
}

/** @returns {string|null} error message or null when valid */
export function validateDisplayName(displayName) {
    const value = displayName.trim();
    if (codePointLength(value) < 1) {
        return 'Display name is required.';
    }
    if (codePointLength(value) > LIMITS.displayNameMax) {
        return `Display name must be at most ${LIMITS.displayNameMax} characters.`;
    }
    return null;
}

/** Trims a tag/category name and collapses whitespace runs like the backend. */
export function normalizeTagName(name) {
    return String(name ?? '').replace(/[\s\p{Z}]+/gu, ' ').trim();
}

/** @returns {string|null} error message or null when valid */
export function validateTagName(name) {
    return validateNormalizedName(name, 'Tag name');
}

/** @returns {string|null} error message or null when valid */
export function validateTagCategoryName(name) {
    return validateNormalizedName(name, 'Category name');
}

function validateNormalizedName(name, label) {
    const value = normalizeTagName(name);
    if (codePointLength(value) === 0) {
        return `${label} is required.`;
    }
    if (codePointLength(value) > LIMITS.tagNameMax) {
        return `${label} must be at most ${LIMITS.tagNameMax} characters.`;
    }
    if (value.includes(',')) {
        return `${label} must not contain a comma.`;
    }
    return null;
}

function codePointLength(value) {
    return Array.from(value).length;
}

function isValidEmail(email) {
    if (email.length > 254) {
        return false;
    }
    const atIndex = email.indexOf('@');
    if (atIndex <= 0 || atIndex !== email.lastIndexOf('@')) {
        return false;
    }

    const localPart = email.slice(0, atIndex);
    const domain = email.slice(atIndex + 1);
    if (localPart.length > 64 || !EMAIL_LOCAL_PART_PATTERN.test(localPart)) {
        return false;
    }
    if (domain.startsWith('[') && domain.endsWith(']')) {
        return isValidEmailAddressLiteral(domain);
    }

    if (domain.length > 253) {
        return false;
    }
    const labels = domain.split('.');
    if (labels.length < 2) {
        return false;
    }
    const topLevelDomain = labels.at(-1);
    return labels.every((label) => label.length <= 63 && EMAIL_DOMAIN_LABEL_PATTERN.test(label))
        && EMAIL_DOMAIN_TLD_PATTERN.test(topLevelDomain);
}

function isValidEmailAddressLiteral(domain) {
    const ipv4 = domain.slice(1, -1);
    if (/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(ipv4)) {
        return ipv4.split('.').every((part) => /^(?:0|[1-9][0-9]{0,2})$/.test(part) && Number(part) <= 255);
    }

    if (!/^IPv6:[0-9A-Fa-f:.]+$/i.test(ipv4)) {
        return false;
    }
    try {
        new URL(`http://[${ipv4.slice(5)}]/`);
        return true;
    } catch {
        return false;
    }
}

/** @returns {string|null} error message or null when valid */
export function validatePasswordMatch(password, passwordRepeat) {
    if (password !== passwordRepeat) {
        return 'Passwords do not match.';
    }
    return null;
}
