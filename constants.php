<?php
declare(strict_types=1);

const ROOT_DIR = __DIR__;

// Runtime value (function call), therefore defined via define() instead of a const expression.
// Used by config.php so PHP's upload temp directory stays readable when upload_tmp_dir is not
// overridden (local development without docker).
define('PHP_TEMP_DIR', sys_get_temp_dir());
