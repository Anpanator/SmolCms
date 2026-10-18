/**
 * Display labels for the numeric AccessLevel enum values returned by the API.
 * Keep these values aligned with src/Data/Constant/AccessLevel.php.
 */
const ACCESS_LEVEL_NAMES = new Map([
    [100, 'NOVICE'],
    [200, 'FELLOW'],
    [300, 'MASTER'],
    [400, 'WIZARD'],
    [500, 'MOD'],
    [600, 'ADMIN'],
]);

export function accessLevelName(value) {
    const enumName = ACCESS_LEVEL_NAMES.get(value);
    if (enumName === undefined) {
        return 'Unknown';
    }
    return `${enumName[0]}${enumName.slice(1).toLowerCase()}`;
}
