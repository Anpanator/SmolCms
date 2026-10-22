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
    case TAG_QUEUE = '/tag-queue';
    case TAGME_QUEUE = '/tagme';
    case USER_MANAGEMENT = '/users';
    case USER_PROFILE = '/user';
    case AUDIT_LOG = '/audit-log';
    case API_SESSION = '/api/session';
    case API_SESSION_QUICK_TAGS = '/api/session/quick-tags';
    case API_IMAGES = '/api/images';
    case API_IMAGE_DETAIL = '/api/image/detail';
    case API_IMAGE_NEIGHBOR = '/api/image/neighbor';
    case API_TAGS = '/api/tags';
    case API_TAGS_QUICK = '/api/tags/quick';
    case API_TAG_SUGGEST = '/api/tags/suggest';
    case API_TAG_CATEGORIES = '/api/tag-categories';
    case API_TAG_DELETE = '/api/tags/delete';
    case API_IMAGE_SAMPLE = '/api/image/sample';
    case API_IMAGE_DOWNLOAD = '/api/image/download';
    case API_REVIEW_QUEUE = '/api/review/queue';
    case API_REVIEW_APPROVE = '/api/review/approve';
    case API_REVIEW_REJECT = '/api/review/reject';
    case API_IMAGE_DELETE = '/api/image/delete';
    case API_IMAGE_TAGS = '/api/image/tags';
    case API_IMAGE_TAGS_REMOVE = '/api/image/tags/remove';
    case API_TAG_SUGGESTIONS = '/api/tag-suggestions';
    case API_TAG_SUGGESTION_QUEUE = '/api/tag-suggestions/queue';
    case API_TAG_SUGGESTION_APPROVE = '/api/tag-suggestions/approve';
    case API_TAG_SUGGESTION_REJECT = '/api/tag-suggestions/reject';
    case API_AUDIT_LOG = '/api/audit-log';
    case API_USERS = '/api/users';
    case API_USERS_UPDATE = '/api/users/update';
    case API_PROFILE = '/api/profile';
    case API_PROFILE_API_KEY = '/api/profile/api-key';
    case API_USER_PROFILE = '/api/user/profile';
    case API_TAGME_RANDOM = '/api/tagme/random';
}
