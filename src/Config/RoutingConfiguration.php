<?php

declare(strict_types=1);

namespace SmolCms\Config;


use SmolCms\Controller\AuditLogController;
use SmolCms\Controller\AuthController;
use SmolCms\Controller\ImageApiController;
use SmolCms\Controller\ImageFileController;
use SmolCms\Controller\ImageReviewController;
use SmolCms\Controller\ImageTagController;
use SmolCms\Controller\ImageUploadController;
use SmolCms\Controller\IndexController;
use SmolCms\Controller\ProfileController;
use SmolCms\Controller\RegisterController;
use SmolCms\Controller\TagApiController;
use SmolCms\Controller\TagmeController;
use SmolCms\Controller\TagSuggestionController;
use SmolCms\Controller\UserManagementController;
use SmolCms\Controller\UserProfileController;
use SmolCms\Data\Business\Route;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\RouteEnum;

class RoutingConfiguration
{
    /** @var array<string, Route> */
    private array $routes;

    /**
     * RoutingConfiguration constructor.
     */
    public function __construct()
    {
        $this->routes = [
            RouteEnum::START_PAGE->name => new Route(
                path: RouteEnum::START_PAGE->value,
                method: HttpMethod::GET,
                controller: IndexController::class
            ),
            RouteEnum::LOGIN->name => new Route(
                path: RouteEnum::LOGIN->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::REGISTER->name => new Route(
                path: RouteEnum::REGISTER->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::SETTINGS->name => new Route(
                path: RouteEnum::SETTINGS->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::UPLOAD_IMAGE->name => new Route(
                path: RouteEnum::UPLOAD_IMAGE->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::API_SESSION->name => new Route(
                path: RouteEnum::API_SESSION->value,
                method: HttpMethod::GET,
                controller: AuthController::class,
                handler: 'getSessionAction'
            ),
            'LoginPostRoute' => new Route(
                path: RouteEnum::LOGIN->value,
                method: HttpMethod::POST,
                controller: AuthController::class
            ),
            'RegisterPostRoute' => new Route(
                path: RouteEnum::REGISTER->value,
                method: HttpMethod::POST,
                controller: RegisterController::class,
            ),
            'SettingsPostRoute' => new Route(
                path: RouteEnum::SETTINGS->value,
                method: HttpMethod::POST,
                controller: UserProfileController::class,
            ),
            RouteEnum::LOGOUT->name => new Route(
                path: RouteEnum::LOGOUT->value,
                method: HttpMethod::POST,
                controller: AuthController::class,
                handler: 'logoutAction'
            ),
            'ImageUploadPostRoute' => new Route(
                path: RouteEnum::UPLOAD_IMAGE->value,
                method: HttpMethod::POST,
                controller: ImageUploadController::class,
            ),
            RouteEnum::IMAGE_DISPLAY->name => new Route(
                path: RouteEnum::IMAGE_DISPLAY->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::IMAGE_REVIEW->name => new Route(
                path: RouteEnum::IMAGE_REVIEW->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::TAGS_PAGE->name => new Route(
                path: RouteEnum::TAGS_PAGE->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::TAG_QUEUE->name => new Route(
                path: RouteEnum::TAG_QUEUE->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::TAGME_QUEUE->name => new Route(
                path: RouteEnum::TAGME_QUEUE->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::USER_MANAGEMENT->name => new Route(
                path: RouteEnum::USER_MANAGEMENT->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::AUDIT_LOG->name => new Route(
                path: RouteEnum::AUDIT_LOG->value,
                method: HttpMethod::GET,
                controller: IndexController::class,
            ),
            RouteEnum::API_IMAGES->name => new Route(
                path: RouteEnum::API_IMAGES->value,
                method: HttpMethod::GET,
                controller: ImageApiController::class,
                handler: 'getImageListAction',
            ),
            RouteEnum::API_IMAGE_DETAIL->name => new Route(
                path: RouteEnum::API_IMAGE_DETAIL->value,
                method: HttpMethod::GET,
                controller: ImageApiController::class,
                handler: 'getImageDetailAction',
            ),
            RouteEnum::API_TAG_SUGGEST->name => new Route(
                path: RouteEnum::API_TAG_SUGGEST->value,
                method: HttpMethod::GET,
                controller: TagApiController::class,
                handler: 'getSuggestionsAction',
            ),
            RouteEnum::API_TAGS->name => new Route(
                path: RouteEnum::API_TAGS->value,
                method: HttpMethod::GET,
                controller: TagApiController::class,
                handler: 'getListAction',
            ),
            'ApiTagsPostRoute' => new Route(
                path: RouteEnum::API_TAGS->value,
                method: HttpMethod::POST,
                controller: TagApiController::class,
                handler: 'createTagAction',
            ),
            RouteEnum::API_TAG_CATEGORIES->name => new Route(
                path: RouteEnum::API_TAG_CATEGORIES->value,
                method: HttpMethod::GET,
                controller: TagApiController::class,
                handler: 'getCategoriesAction',
            ),
            'ApiTagCategoriesPostRoute' => new Route(
                path: RouteEnum::API_TAG_CATEGORIES->value,
                method: HttpMethod::POST,
                controller: TagApiController::class,
                handler: 'createCategoryAction',
            ),
            RouteEnum::API_TAG_DELETE->name => new Route(
                path: RouteEnum::API_TAG_DELETE->value,
                method: HttpMethod::POST,
                controller: TagApiController::class,
                handler: 'deleteTagAction',
            ),
            RouteEnum::API_IMAGE_SAMPLE->name => new Route(
                path: RouteEnum::API_IMAGE_SAMPLE->value,
                method: HttpMethod::GET,
                controller: ImageFileController::class,
                handler: 'sampleAction',
            ),
            RouteEnum::API_IMAGE_DOWNLOAD->name => new Route(
                path: RouteEnum::API_IMAGE_DOWNLOAD->value,
                method: HttpMethod::GET,
                controller: ImageFileController::class,
                handler: 'downloadAction',
            ),
            RouteEnum::API_REVIEW_QUEUE->name => new Route(
                path: RouteEnum::API_REVIEW_QUEUE->value,
                method: HttpMethod::GET,
                controller: ImageReviewController::class,
                handler: 'queueAction',
            ),
            RouteEnum::API_REVIEW_APPROVE->name => new Route(
                path: RouteEnum::API_REVIEW_APPROVE->value,
                method: HttpMethod::POST,
                controller: ImageReviewController::class,
                handler: 'approveAction',
            ),
            RouteEnum::API_REVIEW_REJECT->name => new Route(
                path: RouteEnum::API_REVIEW_REJECT->value,
                method: HttpMethod::POST,
                controller: ImageReviewController::class,
                handler: 'rejectAction',
            ),
            RouteEnum::API_IMAGE_TAGS->name => new Route(
                path: RouteEnum::API_IMAGE_TAGS->value,
                method: HttpMethod::POST,
                controller: ImageTagController::class,
                handler: 'addAction',
            ),
            RouteEnum::API_IMAGE_TAGS_REMOVE->name => new Route(
                path: RouteEnum::API_IMAGE_TAGS_REMOVE->value,
                method: HttpMethod::POST,
                controller: ImageTagController::class,
                handler: 'removeAction',
            ),
            RouteEnum::API_TAG_SUGGESTIONS->name => new Route(
                path: RouteEnum::API_TAG_SUGGESTIONS->value,
                method: HttpMethod::POST,
                controller: TagSuggestionController::class,
                handler: 'createAction',
            ),
            RouteEnum::API_TAG_SUGGESTION_QUEUE->name => new Route(
                path: RouteEnum::API_TAG_SUGGESTION_QUEUE->value,
                method: HttpMethod::GET,
                controller: TagSuggestionController::class,
                handler: 'queueAction',
            ),
            RouteEnum::API_TAG_SUGGESTION_APPROVE->name => new Route(
                path: RouteEnum::API_TAG_SUGGESTION_APPROVE->value,
                method: HttpMethod::POST,
                controller: TagSuggestionController::class,
                handler: 'approveAction',
            ),
            RouteEnum::API_TAG_SUGGESTION_REJECT->name => new Route(
                path: RouteEnum::API_TAG_SUGGESTION_REJECT->value,
                method: HttpMethod::POST,
                controller: TagSuggestionController::class,
                handler: 'rejectAction',
            ),
            RouteEnum::API_AUDIT_LOG->name => new Route(
                path: RouteEnum::API_AUDIT_LOG->value,
                method: HttpMethod::GET,
                controller: AuditLogController::class,
                handler: 'getAction',
            ),
            RouteEnum::API_USERS->name => new Route(
                path: RouteEnum::API_USERS->value,
                method: HttpMethod::GET,
                controller: UserManagementController::class,
                handler: 'getListAction',
            ),
            RouteEnum::API_USERS_UPDATE->name => new Route(
                path: RouteEnum::API_USERS_UPDATE->value,
                method: HttpMethod::POST,
                controller: UserManagementController::class,
                handler: 'updateAction',
            ),
            RouteEnum::API_PROFILE->name => new Route(
                path: RouteEnum::API_PROFILE->value,
                method: HttpMethod::GET,
                controller: ProfileController::class,
                handler: 'getAction',
            ),
            RouteEnum::API_TAGME_RANDOM->name => new Route(
                path: RouteEnum::API_TAGME_RANDOM->value,
                method: HttpMethod::GET,
                controller: TagmeController::class,
                handler: 'getRandomAction',
            ),
        ];
    }

    /**
     * @return array<string, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
