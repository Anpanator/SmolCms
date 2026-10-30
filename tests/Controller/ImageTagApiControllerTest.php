<?php

declare(strict_types=1);

namespace SmolCms\Test\Controller;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use SmolCms\Data\Business\Url;
use SmolCms\Data\Constant\AccessLevel;
use SmolCms\Data\Constant\HttpMethod;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Data\Request\Request;
use SmolCms\Data\Response\Response;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;
use SmolCms\TestUtils\LoginWithAccessLevel;

class ImageTagApiControllerTest extends FunctionalTestCase
{
    use LoginWithAccessLevel;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private TagService $tagService;

    public static function invalidPageProvider(): array
    {
        return [
            'empty' => [''],
            'non-digit' => ['abc'],
            'zero' => ['0'],
            'negative' => ['-1'],
            'fraction' => ['1.5'],
        ];
    }

    public function testInclusiveMultiTagSearchIsCaseInsensitiveAndDedupeQueryTags(): void
    {
        $alpha = 'search-alpha-' . bin2hex(random_bytes(4));
        $beta = 'search-beta-' . bin2hex(random_bytes(4));
        $gamma = 'search-gamma-' . bin2hex(random_bytes(4));
        $both = $this->createAcceptedImage('2026-10-04 10:00:00');
        $onlyAlpha = $this->createAcceptedImage('2026-10-04 11:00:00');
        $alphaAndGamma = $this->createAcceptedImage('2026-10-04 12:00:00');
        $this->tagService->attachTagToImage($both->getId(), $alpha);
        $this->tagService->attachTagToImage($both->getId(), $beta);
        $this->tagService->attachTagToImage($onlyAlpha->getId(), $alpha);
        $this->tagService->attachTagToImage($alphaAndGamma->getId(), $alpha);
        $this->tagService->attachTagToImage($alphaAndGamma->getId(), $gamma);

        $bothResponse = $this->simulateImageList([
            'tags' => ' ' . mb_strtoupper($alpha, 'UTF-8') . ' , ' . $beta . ',,' . $alpha . ' ',
        ]);
        $bothBody = json_decode((string)$bothResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(HttpStatus::OK, $bothResponse->getStatus());
        self::assertSame(1, $bothBody['total']);
        self::assertSame([$both->getId()], array_column($bothBody['images'], 'id'));
        self::assertSame(
            [
                ['name' => $alpha, 'imageCount' => 1],
                ['name' => $beta, 'imageCount' => 1],
            ],
            $bothBody['tags']
        );

        $alphaResponse = $this->simulateImageList(['tags' => mb_strtoupper($alpha, 'UTF-8')]);
        $alphaBody = json_decode((string)$alphaResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(3, $alphaBody['total']);
        self::assertContains($both->getId(), array_column($alphaBody['images'], 'id'));

        $alphaAndGammaResponse = $this->simulateImageList(['tags' => $alpha . ',' . $gamma]);
        $alphaAndGammaBody = json_decode((string)$alphaAndGammaResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(1, $alphaAndGammaBody['total']);
        self::assertSame([$alphaAndGamma->getId()], array_column($alphaAndGammaBody['images'], 'id'));
    }

    public function testInclusiveImageSearchCaseFoldsUnicodeTagNames(): void
    {
        $tagName = 'Äimage-search-' . bin2hex(random_bytes(4));
        $normalizedName = mb_strtolower($tagName, 'UTF-8');
        $image = $this->createAcceptedImage('2026-10-04 13:00:00');
        $this->tagService->attachTagToImage($image->getId(), $tagName);

        $response = $this->simulateImageList(['tags' => mb_strtoupper($tagName, 'UTF-8')]);
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);

        self::assertSame(HttpStatus::OK, $response->getStatus());
        self::assertSame(1, $body['total']);
        self::assertSame([$image->getId()], array_column($body['images'], 'id'));
        self::assertSame([['name' => $normalizedName, 'imageCount' => 1]], $body['tags']);
    }

    public function testImageListPaginatesAndCalculatesTagCloudFromOnlyTheReturnedPage(): void
    {
        $marker = 'page-marker-' . bin2hex(random_bytes(4));
        $alpha = 'page-cloud-alpha-' . bin2hex(random_bytes(4));
        $beta = 'page-cloud-beta-' . bin2hex(random_bytes(4));
        $zeta = 'page-cloud-zeta-' . bin2hex(random_bytes(4));
        $oldest = null;
        $baseDate = new DateTime('2026-10-05 00:00:00');

        for ($index = 0; $index < 21; $index++) {
            $date = (clone $baseDate)->modify('+' . $index . ' minutes');
            $image = $this->createAcceptedImage($date->format('Y-m-d H:i:s'));
            if ($index === 0) {
                $oldest = $image;
            }
            $this->tagService->attachTagToImage($image->getId(), $marker);
            $this->tagService->attachTagToImage($image->getId(), $alpha);
            if ($index > 0) {
                $this->tagService->attachTagToImage($image->getId(), $beta);
            } else {
                $this->tagService->attachTagToImage($image->getId(), $zeta);
            }
        }

        $firstPageResponse = $this->simulateImageList(['tags' => $marker]);
        $firstPage = json_decode((string)$firstPageResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(HttpStatus::OK, $firstPageResponse->getStatus());
        self::assertSame(1, $firstPage['page']);
        self::assertSame(20, $firstPage['perPage']);
        self::assertSame(21, $firstPage['total']);
        self::assertCount(20, $firstPage['images']);
        self::assertNotNull($oldest);
        self::assertNotContains($oldest->getId(), array_column($firstPage['images'], 'id'));
        self::assertSame(
            [
                ['name' => $alpha, 'imageCount' => 20],
                ['name' => $beta, 'imageCount' => 20],
                ['name' => $marker, 'imageCount' => 20],
            ],
            $firstPage['tags']
        );

        $secondPageResponse = $this->simulateImageList(['page' => '2', 'tags' => $marker]);
        $secondPage = json_decode((string)$secondPageResponse->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame(2, $secondPage['page']);
        self::assertSame(21, $secondPage['total']);
        self::assertSame([$oldest->getId()], array_column($secondPage['images'], 'id'));
        self::assertSame(
            [
                ['name' => $alpha, 'imageCount' => 1],
                ['name' => $zeta, 'imageCount' => 1],
                ['name' => $marker, 'imageCount' => 1],
            ],
            $secondPage['tags']
        );
    }

    #[DataProvider('invalidPageProvider')]
    public function testImageListInvalidPageReturnsBadRequest(string $page): void
    {
        $response = $this->simulateImageList(['page' => $page]);

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
        self::assertNull($response->getContent());
    }

    public function testImageListRejectsArrayValuedTagsFilter(): void
    {
        $response = $this->simulateImageList(['tags' => ['not', 'a-string']]);

        self::assertSame(HttpStatus::BAD_REQUEST, $response->getStatus());
    }

    public function testImageDetailIncludesAlphabeticalTagNames(): void
    {
        $this->loginUserWithAccessLevel('image-tags-viewer', 'Image Tags Viewer', AccessLevel::NOVICE);
        $image = $this->createAcceptedImage('2026-10-06 10:00:00');
        $zeta = 'detail-zeta-' . bin2hex(random_bytes(4));
        $alpha = 'detail-alpha-' . bin2hex(random_bytes(4));
        $this->tagService->attachTagToImage($image->getId(), $zeta);
        $this->tagService->attachTagToImage($image->getId(), $alpha);

        $response = $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/image/detail'),
            method: HttpMethod::GET,
            getParams: ['id' => (string)$image->getId()],
        ));

        self::assertSame(HttpStatus::OK, $response->getStatus());
        $body = json_decode((string)$response->getContent(), true, JSON_THROW_ON_ERROR);
        self::assertSame([$alpha, $zeta], $body['image']['tags']);
    }

    private function createAcceptedImage(string $uploadDate): \SmolCms\Data\Persistence\ImageEntity
    {
        return ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime($uploadDate),
        );
    }

    private function simulateImageList(?array $params = null): Response
    {
        return $this->simulateRequest(new Request(
            url: new Url(protocol: 'https', host: 'localhost', path: '/api/images'),
            method: HttpMethod::GET,
            getParams: $params,
        ));
    }
}
