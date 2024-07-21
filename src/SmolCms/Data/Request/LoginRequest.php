<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Request\Request;

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