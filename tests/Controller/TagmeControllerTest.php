<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\Core\Session\SessionService;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Image\ImageQueryService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class TagmeControllerTest extends FunctionalTestCase
{
    #[Autowire]
    private SessionService $sessionService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private ImageQueryService $imageQueryService;

    #[Autowire]
    private TagService $tagService;

    public function testRandomTagmeImageRequiresAuthenticationAndReturnsAnEligibleImageDetail(): void
    {
        $this->clearTagmeImages();
        self::assertSame(HttpStatus::UNAUTHORIZED, $this->simulateRandomRequest()->getStatus());

        $this->loginUser('tagme-user-' . bin2hex(random_bytes(5)), 'Tagme User');
        $userId = $this->sessionService->getUserData()->id;
        $acceptedTagged = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            $userId,
            status: ImageStatus::ACCEPTED,
        );
        $this->tagService->attachTagToImage($acceptedTagged->getId(), 'tagme');

        $acceptedUntagged = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            $userId,
            status: ImageStatus::ACCEPTED,
        );
        $this->tagService->attachTagToImage($acceptedUntagged->getId(), 'not-tagme-' . bin2hex(random_bytes(3)));

        $pendingTagged = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            $userId,
            status: ImageStatus::PENDING_REVIEW,
        );
        $this->tagService->attachTagToImage($pendingTagged->getId(), 'tagme');

        $response = $this->simulateRandomRequest();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $returnedImage = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR)['image'];
        self::assertContains($returnedImage['id'], [$acceptedTagged->getId(), $pendingTagged->getId()]);
        self::assertSame($this->imageQueryService->getImageDetail($returnedImage['id']), $returnedImage);
    }

    public function testRandomTagmeImageReturnsPendingTagmeImageForTheGame(): void
    {
        $this->clearTagmeImages();
        $this->loginUser('tagme-empty-user-' . bin2hex(random_bytes(5)), 'Tagme Empty User');
        $userId = $this->sessionService->getUserData()->id;
        $pendingTagged = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            $userId,
            status: ImageStatus::PENDING_REVIEW,
        );
        $this->tagService->attachTagToImage($pendingTagged->getId(), 'tagme');

        $response = $this->simulateRandomRequest();

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(
            ['image' => $this->imageQueryService->getImageDetail($pendingTagged->getId())],
            json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR),
        );
    }

    public function testRandomTagmeImageReturnsNotFoundWhenNoEligibleImageHasTagme(): void
    {
        $this->clearTagmeImages();
        $this->loginUser('tagme-no-image-user-' . bin2hex(random_bytes(5)), 'Tagme No Image User');
        ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            $this->sessionService->getUserData()->id,
            status: ImageStatus::PENDING_REVIEW,
        );

        self::assertSame(HttpStatus::NOT_FOUND, $this->simulateRandomRequest()->getStatus());
    }

    private function simulateRandomRequest(): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/tagme/random'),
            method: HttpMethod::GET,
        ));
    }

    private function clearTagmeImages(): void
    {
        foreach (ImageStatus::cases() as $status) {
            foreach ($this->imageEntityService->findByStatus($status) as $image) {
                if ($image->getId() !== null) {
                    $this->tagService->detachTagFromImage($image->getId(), 'tagme');
                }
            }
        }
    }
}
