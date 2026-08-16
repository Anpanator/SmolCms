<?php

declare(strict_types=1);

namespace SmolCms\Config;


use PDO;
use SmolCms\Controller\ImageUploadController;
use SmolCms\Data\Business\Service;
use SmolCms\Exception\ServiceConflictException;
use SmolCms\Service\Core\Action\AuthorizationPreControllerAction;
use SmolCms\Service\Core\Action\PreControllerActionFacade;
use SmolCms\Service\Core\Action\RequestMappingPreControllerAction;
use SmolCms\Service\Core\Action\ValidationPreControllerAction;
use SmolCms\Service\Core\ApplicationStartupHandler;
use SmolCms\Service\Core\CliCommand\GenerateMigrationExecutor;
use SmolCms\Service\Core\CliCommand\MigrationsExecutor;
use SmolCms\Service\Core\CliCommand\ResetDbExecutor;
use SmolCms\Service\Core\CliCommandHandler;
use SmolCms\Service\Core\ContextService;
use SmolCms\Service\Core\Startup\RegisterSessionHandlerStartupAction;
use SmolCms\Service\Core\Startup\RestrictDirectoryAccessStartupAction;
use SmolCms\Service\Core\Startup\ResumeSessionStartupAction;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\AvifImageProcessor;
use SmolCms\Service\Image\AvifImageTypeDetector;
use SmolCms\Service\Image\ImageStorageService;
use SmolCms\Service\Image\ImageTypeDetectionFacade;
use SmolCms\Service\Image\JpegImageProcessor;
use SmolCms\Service\Image\JpegImageTypeDetector;
use SmolCms\Service\Image\PngImageProcessor;
use SmolCms\Service\Image\PngImageTypeDetector;
use SmolCms\Service\Validation\ImageUploadValidator;

class CoreServiceConfiguration
{
    /** @var array<string, Service> */
    private array $services;

    public function __construct()
    {
        $services = [
            new Service(
                identifier: PDO::class,
                class: null,
                parameters: [
                    'sqlite:' . ROOT_DIR . '/private/db/smolcms.sq3',
                ]
            ),
            new Service(
                identifier: RestrictDirectoryAccessStartupAction::class,
                parameters: [
                    ...FS_DIRECTORY_WHITELIST
                ]
            ),
            new Service(
                identifier: FileSystemAccessService::class,
                parameters: [
                    ...FS_APP_LEVEL_WHITELIST
                ]
            ),
            new Service(
                identifier: ApplicationStartupHandler::class,
                parameters: [
                    RestrictDirectoryAccessStartupAction::class,
                    RegisterSessionHandlerStartupAction::class,
                    ResumeSessionStartupAction::class,
                ]
            ),
            new Service(
                identifier: PreControllerActionFacade::class,
                parameters: [
                    AuthorizationPreControllerAction::class,
                    RequestMappingPreControllerAction::class,
                    ValidationPreControllerAction::class,
                ]
            ),
            new Service(
                identifier: MigrationsExecutor::class,
                parameters: [
                    MIGRATION_DIR,
                    PDO::class,
                    FileSystemAccessService::class,
                ]
            ),
            new Service(
                identifier: CliCommandHandler::class,
                parameters: [
                    ContextService::class,
                    GenerateMigrationExecutor::class,
                    MigrationsExecutor::class,
                    ResetDbExecutor::class,
                ]
            ),
            new Service(
                identifier: ImageStorageService::class,
                parameters: [
                    IMAGE_STORAGE_DIR,
                    FileSystemAccessService::class,
                ]
            ),
            new Service(
                identifier: PngImageProcessor::class,
                parameters: [
                    ImageStorageService::class,
                ]
            ),
            new Service(
                identifier: JpegImageProcessor::class,
                parameters: [
                    ImageStorageService::class,
                ]
            ),
            new Service(
                identifier: AvifImageProcessor::class,
                parameters: [
                    ImageStorageService::class,
                ]
            ),
            new Service(
                identifier: ImageTypeDetectionFacade::class,
                parameters: [
                    PngImageTypeDetector::class,
                    JpegImageTypeDetector::class,
                    AvifImageTypeDetector::class,
                ]
            ),
            new Service(
                identifier: PngImageTypeDetector::class,
            ),
            new Service(
                identifier: JpegImageTypeDetector::class,
            ),
            new Service(
                identifier: AvifImageTypeDetector::class,
            ),
            new Service(
                identifier: ImageUploadValidator::class,
                parameters: [
                    ImageTypeDetectionFacade::class,
                ]
            ),
            new Service(
                identifier: ImageUploadController::class,
                parameters: [
                    ImageUploadValidator::class,
                    ImageTypeDetectionFacade::class,
                    JpegImageProcessor::class,
                ]
            ),
        ];

        foreach ($services as $service) {
            $this->services[$service->getIdentifier()] = $service;
        }
    }

    public function getServiceByIdentifier(string $identifier): ?Service
    {
        return $this->services[$identifier] ?? null;
    }

    /**
     * @param Service $service
     * @param bool $replace Whether or not to replace existing service.
     *                      If this is false (default) and the service id already exists, an exception will be thrown.
     * @throws ServiceConflictException
     */
    public function addService(Service $service, bool $replace = false): void
    {
        if (!$replace && isset($this->services[$service->getIdentifier()])) {
            throw new ServiceConflictException(
                "Service {$service->getIdentifier()} is already set and service replacement is disabled."
            );
        }
        $this->services[$service->getIdentifier()] = $service;
    }
}