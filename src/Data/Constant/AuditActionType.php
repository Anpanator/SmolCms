<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum AuditActionType: int
{
    case TAG_ADDED = 1;
    case TAG_REMOVED = 2;
    case TAG_DELETED = 3;
}
