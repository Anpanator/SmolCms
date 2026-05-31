<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

/**
 * Enum ContextKey
 *
 * Used to store contextual data in the application's context. The backed value must match a gettype() return value:
 * "string", "integer", "double", "boolean", "array", "object".
 */
enum ContextKey: string
{
    case PAGE_TITLE = 'string';
}