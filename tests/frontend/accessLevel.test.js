import {assertEqual, assertTrue} from './assert.js';
import {AccessLevel, accessLevelName} from '../../public/js/accessLevel.js';

export function testAccessLevelNames() {
    const levels = [
        [100, 'Novice'],
        [200, 'Fellow'],
        [300, 'Master'],
        [400, 'Wizard'],
        [500, 'Mod'],
        [600, 'Admin'],
    ];
    for (const [value, name] of levels) {
        assertEqual(accessLevelName(value), name);
    }
}

export function testUnknownAccessLevelHasFallbackLabel() {
    assertEqual(accessLevelName(999), 'Unknown');
}

export function testAccessLevelConstantsMirrorPhpEnum() {
    const levels = [
        ['NOVICE', 100],
        ['FELLOW', 200],
        ['MASTER', 300],
        ['WIZARD', 400],
        ['MOD', 500],
        ['ADMIN', 600],
    ];
    for (const [name, value] of levels) {
        assertTrue(Object.hasOwn(AccessLevel, name), `AccessLevel.${name} exists`);
        assertEqual(AccessLevel[name], value, `AccessLevel.${name} === ${value}`);
    }
}

export function testAccessLevelConstantsAreFrozen() {
    assertTrue(Object.isFrozen(AccessLevel));
}
