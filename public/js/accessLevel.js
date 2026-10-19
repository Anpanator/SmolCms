/**
 * Named references for the numeric AccessLevel enum values returned by the API.
 * Keep these values aligned with src/Data/Constant/AccessLevel.php.
 */
export const AccessLevel = Object.freeze({
    NOVICE: 100,
    FELLOW: 200,
    MASTER: 300,
    WIZARD: 400,
    MOD: 500,
    ADMIN: 600,
});

const ACCESS_LEVEL_NAMES = new Map([
    [AccessLevel.NOVICE, 'NOVICE'],
    [AccessLevel.FELLOW, 'FELLOW'],
    [AccessLevel.MASTER, 'MASTER'],
    [AccessLevel.WIZARD, 'WIZARD'],
    [AccessLevel.MOD, 'MOD'],
    [AccessLevel.ADMIN, 'ADMIN'],
]);

export function accessLevelName(value) {
    const enumName = ACCESS_LEVEL_NAMES.get(value);
    if (enumName === undefined) {
        return 'Unknown';
    }
    return `${enumName[0]}${enumName.slice(1).toLowerCase()}`;
}
