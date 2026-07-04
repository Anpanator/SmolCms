<?php
declare(strict_types=1);
// Used in early application bootstrapping as input for open_basedir
// Note that some includes and other file access may already have happened at that point
const FS_DIRECTORY_WHITELIST = [
    ROOT_DIR . '/private'
];

// In case app-level access checks should be more restrictive, you can be more specific here.
// Note this only affects access made through internal services. PHP-native file operations can only be influenced via
// FS_DIRECTORY_WHITELIST above. Also note that this config cannot be more permissive than FS_DIRECTORY_WHITELIST.
// You may define individual files OR directories here.
const FS_APP_LEVEL_WHITELIST = [
    ROOT_DIR . '/private'
];