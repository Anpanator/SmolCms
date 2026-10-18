import {
    LIMITS,
    validateDisplayName,
    validateEmail,
    validateLoginName,
    validatePassword,
    validatePasswordMatch,
} from '../../public/js/validation.js';
import { assertEqual } from './assert.js';

export function testLoginNameValidation() {
    assertEqual(validateLoginName(''), 'Login name is required.');
    assertEqual(validateLoginName('   '), 'Login name is required.');
    assertEqual(validateLoginName('a'.repeat(LIMITS.loginNameMax + 1)), `Login name must be at most ${LIMITS.loginNameMax} characters.`);
    assertEqual(validateLoginName('amy'), null);
    assertEqual(validateLoginName('a'.repeat(LIMITS.loginNameMax)), null);
}

export function testPasswordValidation() {
    assertEqual(validatePassword('short'), `Password must be at least ${LIMITS.passwordMin} characters.`);
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMax + 1)), `Password must be at most ${LIMITS.passwordMax} characters.`);
    // 37 two-byte characters = 74 bytes, exceeding the byte limit.
    assertEqual(validatePassword('ä'.repeat(37)), `Password must be at most ${LIMITS.passwordMaxBytes} bytes.`);
    assertEqual(validatePassword('ä'.repeat(36)), null, '72 bytes must be accepted');
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMin)), null);
    assertEqual(validatePassword('a'.repeat(LIMITS.passwordMax)), null);
}

export function testEmailValidation() {
    assertEqual(validateEmail(''), 'Email is required.');
    assertEqual(validateEmail('   '), 'Email is required.');
    assertEqual(validateEmail('not-an-email'), 'Please enter a valid email address.');
    assertEqual(validateEmail('foo@bar'), 'Please enter a valid email address.');
    assertEqual(validateEmail('foo bar@example.com'), 'Please enter a valid email address.');
    assertEqual(validateEmail('amy@example.com'), null);
}

export function testDisplayNameValidation() {
    assertEqual(validateDisplayName(''), 'Display name is required.');
    assertEqual(validateDisplayName('a'.repeat(LIMITS.displayNameMax + 1)), `Display name must be at most ${LIMITS.displayNameMax} characters.`);
    assertEqual(validateDisplayName('Amy'), null);
    assertEqual(validateDisplayName('a'.repeat(LIMITS.displayNameMax)), null);
}

export function testPasswordMatchValidation() {
    assertEqual(validatePasswordMatch('long-enough-pass', 'different-pass'), 'Passwords do not match.');
    assertEqual(validatePasswordMatch('long-enough-pass', 'long-enough-pass'), null);
}
