<?php
declare(strict_types=1);

namespace SmolCms\Data\Request;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;

class LoginRequest extends Request
{

    public function __construct(Url        $url,
                                HttpMethod $method,
                                array      $headers = [],
                                ?string    $rawBody = null,
                                ?array     $postParams = null,
                                ?array     $getParams = null
    )
    {
        parent::__construct($url, $method, $headers, $rawBody, $postParams, $getParams);
    }

}