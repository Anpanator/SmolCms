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
    case IMAGE_DISPLAY = '/image';
    case IMAGE_REVIEW = '/review';
    case TAGS_PAGE = '/tags';
    case API_SESSION = '/api/session';
    case API_IMAGES = '/api/images';
    case API_IMAGE_DETAIL = '/api/image/detail';
    case API_TAGS = '/api/tags';
    case API_TAG_SUGGEST = '/api/tags/suggest';
    case API_TAG_CATEGORIES = '/api/tag-categories';
    case API_IMAGE_SAMPLE = '/api/image/sample';
    case API_IMAGE_DOWNLOAD = '/api/image/download';
    case API_REVIEW_QUEUE = '/api/review/queue';
    case API_REVIEW_APPROVE = '/api/review/approve';
    case API_REVIEW_REJECT = '/api/review/reject';
}
