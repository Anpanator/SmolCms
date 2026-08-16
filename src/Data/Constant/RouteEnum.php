<?php
declare(strict_types=1);

namespace SmolCms\Data\Constant;

enum RouteEnum: string
{
    case START_PAGE = '/';
    case LOGIN = '/login';
    case LOGOUT = '/logout';
    case REGISTER = '/register';
    case SETTINGS = '/settings';
    case UPLOAD_IMAGE = '/upload-image';
}
