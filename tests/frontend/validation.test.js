import {
    LIMITS,
    validateDisplayName,
    validateEmail,
    validateLoginName,
    validatePassword,
    validatePasswordMatch,
    normalizeTagName,
    validateTagCategoryName,
    validateTagName,
} from '../../public/js/validation.js';
import { assertEqual } from './assert.js';

export function testLoginNameValidation() {
    assertEqual(validateLoginName(''), 'Login name is required.');
    assertEqual(validateLoginName('   '), 'Login name is required.');
    assertEqual(validateLoginName('a'.repeat(LIMITS.loginNameMax + 1)), `Login name must be at most ${LIMITS.loginNameMax} characters.`);
    assertEqual(validateLoginName('amy'), null);
    assertEqual(validateLoginName('a'.repeat(LIMITS.loginNameMax)), null);
    assertEqual(validateLoginName('😀'.repeat(LIMITS.loginNameMax)), null, 'supplementary characters count once each');
    assertEqual(
        validateLoginName('😀'.repeat(LIMITS.loginNameMax + 1)),
        `Login name must be at most ${LIMITS.loginNameMax} characters.`,
    );
}

export function testPasswordValidation() {
    assertEqual(validatePassword('short'), `Password must be at least ${LIMITS.passwordMin} characters.`);
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMax + 1)), `Password must be at most ${LIMITS.passwordMax} characters.`);
    // 37 two-byte characters = 74 bytes, exceeding the byte limit.
    assertEqual(validatePassword('ä'.repeat(37)), `Password must be at most ${LIMITS.passwordMaxBytes} bytes.`);
    assertEqual(validatePassword('ä'.repeat(36)), null, '72 bytes must be accepted');
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMin)), null);
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMax)), null);
    assertEqual(validatePassword('😀'.repeat(LIMITS.passwordMin)), null, 'password length counts Unicode code points');
    assertEqual(
        validatePassword('😀'.repeat(LIMITS.passwordMax + 1)),
        `Password must be at most ${LIMITS.passwordMax} characters.`,
    );
}

export function testEmailValidation() {
    assertEqual(validateEmail(''), 'Email is required.');
    assertEqual(validateEmail('   '), 'Email is required.');
    assertEqual(validateEmail('not-an-email'), 'Please enter a valid email address.');
    assertEqual(validateEmail('foo@bar'), 'Please enter a valid email address.');
    assertEqual(validateEmail('foo bar@example.com'), 'Please enter a valid email address.');
    assertEqual(validateEmail('amy@example.com'), null);
    assertEqual(validateEmail('amy+tag@example.co.uk'), null);
    assertEqual(validateEmail('a..b@example.com'), 'Please enter a valid email address.');
    assertEqual(validateEmail('a.@example.com'), 'Please enter a valid email address.');
    assertEqual(validateEmail('a@example..com'), 'Please enter a valid email address.');
    assertEqual(validateEmail('a@[001.002.003.004]'), 'Please enter a valid email address.');
    assertEqual(validateEmail('a@[IPv6:2001:db8::1]'), null);
    assertEqual(validateEmail('a@[IPv6:fe80::1%25eth0]'), 'Please enter a valid email address.');
    const longLocal = 'a'.repeat(64);
    assertEqual(validateEmail(`${longLocal}@${'b'.repeat(63)}.${'c'.repeat(63)}.${'d'.repeat(61)}`), null);
    assertEqual(
        validateEmail(`${longLocal}@${'b'.repeat(63)}.${'c'.repeat(63)}.${'d'.repeat(62)}`),
        'Please enter a valid email address.',
    );
    assertEqual(validateEmail(' amy@example.com '), 'Please enter a valid email address.');
    assertEqual(
        validateEmail('😀'.repeat(LIMITS.emailMax + 1)),
        `Email must be at most ${LIMITS.emailMax} characters.`,
    );
}

export function testDisplayNameValidation() {
    assertEqual(validateDisplayName(''), 'Display name is required.');
    assertEqual(validateDisplayName('a'.repeat(LIMITS.displayNameMax + 1)), `Display name must be at most ${LIMITS.displayNameMax} characters.`);
    assertEqual(validateDisplayName('Amy'), null);
    assertEqual(validateDisplayName('a'.repeat(LIMITS.displayNameMax)), null);
    assertEqual(validateDisplayName('😀'.repeat(LIMITS.displayNameMax)), null);
    assertEqual(
        validateDisplayName('😀'.repeat(LIMITS.displayNameMax + 1)),
        `Display name must be at most ${LIMITS.displayNameMax} characters.`,
    );
}

export function testTagNameValidationMatchesBackendNormalization() {
    assertEqual(validateTagName(''), 'Tag name is required.');
    assertEqual(validateTagName(' \t\n '), 'Tag name is required.');
    assertEqual(validateTagName('a'.repeat(LIMITS.tagNameMax + 1)), `Tag name must be at most ${LIMITS.tagNameMax} characters.`);
    assertEqual(validateTagName('a'.repeat(LIMITS.tagNameMax)), null);
    assertEqual(validateTagName(' black   thighhighs '), null);
    assertEqual(normalizeTagName(' black\t\n thighhighs '), 'black thighhighs');
    assertEqual(validateTagName('black, thighhighs'), 'Tag name must not contain a comma.');
    assertEqual(validateTagName('😀'.repeat(LIMITS.tagNameMax)), null, 'Unicode code points should count as characters');
    assertEqual(validateTagName('😀'.repeat(LIMITS.tagNameMax + 1)), `Tag name must be at most ${LIMITS.tagNameMax} characters.`);
}

export function testTagCategoryNameUsesTheSameRules() {
    assertEqual(validateTagCategoryName(''), 'Category name is required.');
    assertEqual(validateTagCategoryName('Characters'), null);
    assertEqual(validateTagCategoryName('a,b'), 'Category name must not contain a comma.');
    assertEqual(validateTagCategoryName('x'.repeat(LIMITS.tagNameMax + 1)), `Category name must be at most ${LIMITS.tagNameMax} characters.`);
}

export function testPasswordMatchValidation() {
    assertEqual(validatePasswordMatch('long-enough-pass', 'different-pass'), 'Passwords do not match.');
    assertEqual(validatePasswordMatch('long-enough-pass', 'long-enough-pass'), null);
}
