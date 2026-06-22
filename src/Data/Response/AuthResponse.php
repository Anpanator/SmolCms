<?php
declare(strict_types=1);

namespace SmolCms\Data\Response;

use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\RouteEnum;

class AuthResponse extends Response
{

    public function __construct(?RouteEnum $targetRoute = null)
    {
        parent::__construct(
            status: HttpStatus::SEE_OTHER,
            headers: [
                HttpHeader::LOCATION->value =>
                    $targetRoute ? $targetRoute->value : RouteEnum::START_PAGE->value
            ]
        );
    }
}