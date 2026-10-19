<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

/**
 * Lifecycle status of an uploaded image.
 *
 * Matches the CHECK constraint on the `image` table in private/init/init_sqlite.sql.
 */
enum ImageStatus: string
{
    case PENDING = 'pending';
    case PENDING_REVIEW = 'pending_review';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
