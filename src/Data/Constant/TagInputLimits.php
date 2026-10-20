<?php

declare(strict_types=1);

namespace SmolCms\Data\Constant;

final class TagInputLimits
{
    public const int MAX_TAG_LENGTH = 100;
    public const int MAX_TAG_LIST_LENGTH = 2048;
    public const int MAX_TAG_COUNT = 20;

    private function __construct()
    {
    }
}
