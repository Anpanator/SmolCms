<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum AccessLevel: int
{
    case NOVICE = 100;
    case FELLOW = 200;
    case MASTER = 300;
    case WIZARD = 400;
    case MOD = 500;
    case ADMIN = 600;
}
