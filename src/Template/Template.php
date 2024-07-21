<?php
declare(strict_types=1);

namespace SmolCms\Template;


interface Template
{
    public function render(): string;
}