<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum CliCommandFlag: string
{
    case GENERATE_MIGRATION = 'generate-migration';
    case GENERATE_JS_INTEGRITY = 'generate-js-integrity';
    case MIGRATIONS = 'migrations';
    case NOCONFIRM = 'noconfirm';
    case INIT_DB = 'init-db';
    case RESET = 'reset';
    case PROCESS_IMAGES = 'process-images';
}
