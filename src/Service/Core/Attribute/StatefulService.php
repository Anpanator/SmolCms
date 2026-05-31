<?php
declare(strict_types=1);

namespace SmolCms\Service\Core\Attribute;

use Attribute;

/**
 * Marker attribute for stateful services
 */
#[Attribute(Attribute::TARGET_CLASS)]
class StatefulService
{

}