<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\ImageFileRequest;
use SmolCms\Data\Response\Response;
use SmolCms\Exception\FileAccessException;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\File\FileSystemAccessService;
use SmolCms\Service\Image\ImageAccessPolicy;
use SmolCms\Service\Image\ImagePathLayout;

/**
 * Serves the protected image variants (viewing image and download image) that live
 * below /private/ and are therefore not web-accessible: on success the endpoints
 * respond with an X-Accel-Redirect header so nginx delivers the file from an
 * internal-only location (see docker/nginx.conf). The response body stays empty.
 */
readonly class ImageFileController
{
    /**
     * Stored image file names are `<sha-256 hex checksum>.<extension>`.
     * The extension list covers every image variant that can be served.
     */
    private const CONTENT_TYPES = [
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(
        private ImageEntityService      $imageEntityService,
        private ImageAccessPolicy       $imageAccessPolicy,
        private SessionService          $sessionService,
        private FileSystemAccessService $fileSystemAccessService,
    )
    {
    }

    public function sampleAction(ImageFileRequest $request): Response
    {
        return $this->serveFile(
            $request->name,
            findEntity: fn(string $name) => $this->imageEntityService->findOneBySampleFilename($name),
            baseDir: IMAGE_SAMPLE_DIR,
            internalLocation: '/internal/img/sample/',
            asAttachment: false,
        );
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function downloadAction(ImageFileRequest $request): Response
    {
        return $this->serveFile(
            $request->name,
            findEntity: fn(string $name) => $this->imageEntityService->findOneByDownloadFilename($name),
            baseDir: IMAGE_DOWNLOAD_DIR,
            internalLocation: '/internal/img/dl/',
            asAttachment: true,
        );
    }

    private function serveFile(
        string   $name,
        callable $findEntity,
        string   $baseDir,
        string   $internalLocation,
        bool     $asAttachment,
    ): Response
    {
        $entity = $findEntity($name);
        if ($entity === null) {
            // Unknown name: reveal nothing about stored image names.
            return new Response(HttpStatus::NOT_FOUND);
        }

        $userData = $this->sessionService->getUserData();
        if ($userData === null) {
            if ($entity->getStatus() !== ImageStatus::ACCEPTED || $entity->isDeleted()) {
                return new Response(HttpStatus::FORBIDDEN);
            }
        } elseif (!$this->imageAccessPolicy->canAccess($entity, $userData->accessLevel, $userData->id)) {
            return new Response(HttpStatus::FORBIDDEN);
        }

        $relativePath = ImagePathLayout::relativePathForFileName($name);
        $filePath = $baseDir . '/' . $relativePath;
        try {
            if (!$this->fileSystemAccessService->fileExists($filePath)) {
                return new Response(HttpStatus::NOT_FOUND);
            }
        } catch (FileAccessException) {
            // Missing subdirectories and paths outside the whitelist are both unavailable.
            return new Response(HttpStatus::NOT_FOUND);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $response = new Response(HttpStatus::OK);
        $response->addHeader(HttpHeader::X_ACCEL_REDIRECT->value, $internalLocation . $relativePath);
        $response->addHeader(HttpHeader::CONTENT_TYPE->value, self::CONTENT_TYPES[$extension]);
        if ($asAttachment) {
            $response->addHeader(HttpHeader::CONTENT_DISPOSITION->value, "attachment; filename=\"$name\"");
        }
        return $response;
    }
}
