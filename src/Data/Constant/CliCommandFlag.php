<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum CliCommandFlag: string
{
    case MIGRATIONS = 'migrations';
    case NOCONFIRM = 'noconfirm';
    case RESET_DB = 'reset-db';
}
