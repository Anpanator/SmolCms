<?php
declare(strict_types=1);

namespace SmolCms\Controller;

use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpHeader;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Attribute\Authenticated;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
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
    private const FILE_NAME_PATTERN = '/^[a-f0-9]{64}\.(avif|webp|jpeg|png)$/';

    private const CONTENT_TYPES = [
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(
        private ImageEntityService $imageEntityService,
        private SessionService     $sessionService,
    )
    {
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function sampleAction(Request $request): Response
    {
        return $this->serveFile(
            $request,
            findEntity: fn(string $name) => $this->imageEntityService->findOneBySampleFilename($name),
            baseDir: IMAGE_SAMPLE_DIR,
            internalLocation: '/internal/img/sample/',
            asAttachment: false,
        );
    }

    #[Authenticated(AccessLevel::NOVICE)]
    public function downloadAction(Request $request): Response
    {
        return $this->serveFile(
            $request,
            findEntity: fn(string $name) => $this->imageEntityService->findOneByDownloadFilename($name),
            baseDir: IMAGE_DOWNLOAD_DIR,
            internalLocation: '/internal/img/dl/',
            asAttachment: true,
        );
    }

    private function serveFile(
        Request  $request,
        callable $findEntity,
        string   $baseDir,
        string   $internalLocation,
        bool     $asAttachment,
    ): Response
    {
        $name = ($request->getParams ?? [])['name'] ?? '';
        if (!is_string($name) || preg_match(self::FILE_NAME_PATTERN, $name) !== 1) {
            return new Response(HttpStatus::BAD_REQUEST);
        }

        $entity = $findEntity($name);
        if ($entity === null) {
            // Unknown name: reveal nothing about stored image names.
            return new Response(HttpStatus::NOT_FOUND);
        }

        // Only ACCEPTED images are visible to regular users; MOD+ may preview
        // queued images. Hidden images answer 404 so their existence is not disclosed.
        $userData = $this->sessionService->getUserData();
        $isModerator = $userData !== null && $userData->accessLevel->value >= AccessLevel::MOD->value;
        if ($entity->getStatus() !== ImageStatus::ACCEPTED && !$isModerator) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $filePath = $baseDir . '/' . ImagePathLayout::relativePathForFileName($name);
        if (!is_file($filePath)) {
            return new Response(HttpStatus::NOT_FOUND);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $response = new Response(HttpStatus::OK);
        $response->addHeader(HttpHeader::X_ACCEL_REDIRECT->value, $internalLocation . ImagePathLayout::relativePathForFileName($name));
        $response->addHeader(HttpHeader::CONTENT_TYPE->value, self::CONTENT_TYPES[$extension]);
        if ($asAttachment) {
            $response->addHeader(HttpHeader::CONTENT_DISPOSITION->value, "attachment; filename=\"$name\"");
        }
        return $response;
    }
}
