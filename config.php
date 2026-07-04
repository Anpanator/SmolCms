<?php
declare(strict_types=1);

// Used in early application bootstrapping as input for open_basedir
// Note that some includes and other file access may already have happened at that point
const FS_DIRECTORY_WHITELIST = [
    ROOT_DIR . '/private'
];