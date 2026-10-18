import {assertEqual} from './assert.js';
import {accessLevelName} from '../../public/js/accessLevel.js';

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
