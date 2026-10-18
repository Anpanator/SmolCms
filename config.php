<?php
declare(strict_types=1);
// Used in early application bootstrapping as input for open_basedir
// Note that some includes and other file access may already have happened at that point
const FS_DIRECTORY_WHITELIST = [
    ROOT_DIR . '/private',
    ROOT_DIR . '/src',
    ROOT_DIR . '/public/js',
];

// In case app-level access checks should be more restrictive, you can be more specific here.
// Note this only affects access made through internal services. PHP-native file operations can only be influenced via
// FS_DIRECTORY_WHITELIST above. Also note that this config cannot be more permissive than FS_DIRECTORY_WHITELIST.
// You may define individual files OR directories here.
const FS_APP_LEVEL_WHITELIST = [
    ROOT_DIR . '/private/init',
    ROOT_DIR . '/private/sql',
    ROOT_DIR . '/private/upload',
    // AssetService reads the shell and --generate-js-integrity updates its script integrity attributes.
    ROOT_DIR . '/private/assets',
    ROOT_DIR . '/public/js',
];

const MIGRATION_DIR = ROOT_DIR . '/private/sql';

const IMAGE_STORAGE_DIR = ROOT_DIR . '/private/upload/img';

const INFO_LOG_FILE_PATH = ROOT_DIR . '/private/logs/info.log';
const ERROR_LOG_FILE_PATH = ROOT_DIR . '/private/logs/error.log';
