<?php

declare(strict_types=1);

namespace SmolCms\Test\Service\Tag;

use DateTime;
use InvalidArgumentException;
use PDO;
use SmolCms\Data\Constant\ImageStatus;
use SmolCms\Exception\TagmeRemovalBlockedException;
use SmolCms\Service\DB\ImageEntityService;
use SmolCms\Service\Tag\TagService;
use SmolCms\TestUtils\Attributes\Autowire;
use SmolCms\TestUtils\FunctionalTestCase;
use SmolCms\TestUtils\ImageTestUtils;

class TagServiceTest extends FunctionalTestCase
{
    #[Autowire]
    private TagService $tagService;

    #[Autowire]
    private ImageEntityService $imageEntityService;

    #[Autowire]
    private PDO $pdo;

    public function testAddTagsToImageDoesNotCreateUnknownTagsAndReportsNewAssociations(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );
        $knownName = 'add-existing-' . bin2hex(random_bytes(4));
        $unknownName = 'add-unknown-' . bin2hex(random_bytes(4));
        $tooLongName = str_repeat('x', 101);
        $this->tagService->createTag($knownName);

        self::assertSame(
            [
                'applied' => [$knownName],
                'unknown' => [$unknownName, $tooLongName, ''],
                'newlyAttached' => [$knownName],
            ],
            $this->tagService->addTagsToImageWithChanges(
                $image->getId(),
                [mb_strtoupper($knownName, 'UTF-8'), $unknownName, $tooLongName, ''],
            ),
        );
        self::assertSame(
            ['applied' => [$knownName], 'unknown' => [$unknownName]],
            $this->tagService->addTagsToImage($image->getId(), [$knownName, $unknownName]),
        );
        self::assertSame(1, $this->tagService->getUsageCount($knownName));
        self::assertNull($this->tagService->getUsageCount($unknownName));
    }

    public function testRemoveTagFromImageRequiresTenOtherTagsBeforeRemovingTagme(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
        );
        $this->tagService->attachTagToImage($image->getId(), 'tagme');
        for ($index = 0; $index < 9; $index++) {
            $this->tagService->attachTagToImage($image->getId(), 'tagme-rule-' . $index . '-' . bin2hex(random_bytes(2)));
        }
        self::assertSame(10, $this->tagService->countTagsForImage($image->getId()));
        try {
            $this->tagService->removeTagFromImage($image->getId(), 'TAGME');
            self::fail('Removing tagme with fewer than ten other tags must be blocked.');
        } catch (TagmeRemovalBlockedException) {
            self::assertContains('tagme', $this->tagService->getTagsForImage($image->getId()));
        }

        $this->tagService->attachTagToImage($image->getId(), 'tagme-rule-extra-' . bin2hex(random_bytes(3)));
        self::assertSame(11, $this->tagService->countTagsForImage($image->getId()));
        self::assertTrue($this->tagService->removeTagFromImage($image->getId(), 'TAGME'));
        self::assertSame(10, $this->tagService->countTagsForImage($image->getId()));
        self::assertNotContains('tagme', $this->tagService->getTagsForImage($image->getId()));
        self::assertFalse($this->tagService->removeTagFromImage($image->getId(), 'tagme'));
    }

    public function testNormalizeName_collapsesWhitespaceAndRejectsInvalidNames(): void
    {
        self::assertSame('multi word tag', TagService::normalizeName("  multi\tword\n tag  "));
        self::assertSame('ätag', TagService::normalizeName(' ÄTAG '));

        foreach (['', '  ', 'not,a,tag', str_repeat('x', 101)] as $invalidName) {
            try {
                TagService::normalizeName($invalidName);
                self::fail('Expected an invalid tag name to be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testCreateTag_normalizesNamesUsesDefaultCategoryAndDetectsCaseInsensitiveDuplicates(): void
    {
        $name = 'service-' . bin2hex(random_bytes(5));

        $created = $this->tagService->createTag("  $name   with   spaces ");
        $duplicate = $this->tagService->createTag(mb_strtoupper($name . ' with spaces', 'UTF-8'));

        self::assertNotNull($created);
        self::assertSame($name . ' with spaces', $created['name']);
        self::assertSame('General', $created['category']);
        self::assertSame(0, $created['usageCount']);
        self::assertNull($duplicate);
    }

    public function testCreateTag_rejectsUnknownCategory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tagService->createTag('unknown-category-' . bin2hex(random_bytes(4)), 'Unknown Category');
    }

    public function testCreateCategory_normalizesAndDetectsCaseInsensitiveDuplicates(): void
    {
        $name = 'service-category-' . bin2hex(random_bytes(5));

        self::assertSame($name . ' name', $this->tagService->createCategory(" $name   name "));
        self::assertNull($this->tagService->createCategory(mb_strtoupper($name . ' name', 'UTF-8')));
    }

    public function testAttachAndDetachMaintainUsageCountIdempotentlyAndCaseInsensitively(): void
    {
        $firstImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 10:00:00'),
        );
        $secondImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 11:00:00'),
        );
        $tagName = 'usage-' . bin2hex(random_bytes(5));

        self::assertTrue($this->tagService->attachTagToImage($firstImage->getId(), $tagName));
        self::assertFalse($this->tagService->attachTagToImage($firstImage->getId(), mb_strtoupper($tagName, 'UTF-8')));
        self::assertTrue($this->tagService->attachTagToImage($secondImage->getId(), $tagName));
        self::assertSame(2, $this->tagService->getUsageCount($tagName));
        self::assertSame([$tagName], $this->tagService->getTagsForImage($firstImage->getId()));

        self::assertTrue($this->tagService->detachTagFromImage($firstImage->getId(), mb_strtoupper($tagName, 'UTF-8')));
        self::assertFalse($this->tagService->detachTagFromImage($firstImage->getId(), $tagName));
        self::assertSame(1, $this->tagService->getUsageCount($tagName));
    }

    public function testTagmeCannotBeDeletedByNameOrId(): void
    {
        $tagmeId = $this->tagService->findOrCreateTagId('TAGME');

        self::assertFalse($this->tagService->deleteTag('TaGmE'));
        self::assertFalse($this->tagService->deleteTagByName('tagme'));
        self::assertFalse($this->tagService->deleteTagById($tagmeId));
        self::assertSame(0, $this->tagService->getUsageCount('tagme'));
        self::assertContains('tagme', $this->tagService->suggestTags('tag'));
    }

    public function testDetachAllFromImageDecrementsEachAttachedTagOnce(): void
    {
        $firstImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 13:00:00'),
        );
        $secondImage = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 14:00:00'),
        );
        $sharedTag = 'detach-shared-' . bin2hex(random_bytes(4));
        $uniqueTag = 'detach-unique-' . bin2hex(random_bytes(4));
        $this->tagService->attachTagToImage($firstImage->getId(), $sharedTag);
        $this->tagService->attachTagToImage($secondImage->getId(), $sharedTag);
        $this->tagService->attachTagToImage($firstImage->getId(), $uniqueTag);

        self::assertSame(2, $this->tagService->detachAllFromImage($firstImage->getId()));
        self::assertSame(0, $this->tagService->detachAllFromImage($firstImage->getId()));
        self::assertSame(1, $this->tagService->getUsageCount($sharedTag));
        self::assertSame(0, $this->tagService->getUsageCount($uniqueTag));
        self::assertSame([$sharedTag], $this->tagService->getTagsForImage($secondImage->getId()));
        self::assertSame([], $this->tagService->getTagsForImage($firstImage->getId()));
    }

    public function testDeletingAnOrdinaryTagRemovesItsAssociations(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 15:00:00'),
        );
        $tagName = 'deletable-' . bin2hex(random_bytes(4));
        $this->tagService->attachTagToImage($image->getId(), $tagName);

        self::assertTrue($this->tagService->deleteTag($tagName));
        self::assertNull($this->tagService->getUsageCount($tagName));
        self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
    }

    public function testDeleteImageRemovesTagAssociationsAndDecrementsUsageCounts(): void
    {
        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-01 12:00:00'),
        );
        $firstTag = 'delete-first-' . bin2hex(random_bytes(4));
        $secondTag = 'delete-second-' . bin2hex(random_bytes(4));
        $this->tagService->attachTagToImage($image->getId(), $firstTag);
        $this->tagService->attachTagToImage($image->getId(), $secondTag);

        $this->imageEntityService->deleteById($image->getId());

        self::assertSame(0, $this->tagService->getUsageCount($firstTag));
        self::assertSame(0, $this->tagService->getUsageCount($secondTag));
        self::assertSame([], $this->tagService->getTagsForImage($image->getId()));
    }

    public function testSuggestionsAndBrowseOrderByUsageAndApplyCaseInsensitivePartialMatch(): void
    {
        $image1 = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-02 10:00:00'),
        );
        $image2 = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-02 11:00:00'),
        );
        $prefix = 'browse-' . bin2hex(random_bytes(4)) . '-';
        $mostUsed = $prefix . 'zeta';
        $lessUsed = $prefix . 'alpha';
        $unused = $prefix . 'beta';
        $this->tagService->createTag($mostUsed, 'Artist');
        $this->tagService->createTag($lessUsed, 'Copyright');
        $this->tagService->createTag($unused);
        $this->tagService->attachTagToImage($image1->getId(), $mostUsed);
        $this->tagService->attachTagToImage($image2->getId(), $mostUsed);
        $this->tagService->attachTagToImage($image1->getId(), $lessUsed);

        self::assertSame(
            [$mostUsed, $lessUsed, $unused],
            $this->tagService->suggestTags(mb_strtoupper($prefix, 'UTF-8'))
        );
        self::assertSame(
            [
                ['name' => $mostUsed, 'category' => 'Artist', 'usageCount' => 2],
                ['name' => $lessUsed, 'category' => 'Copyright', 'usageCount' => 1],
                ['name' => $unused, 'category' => 'General', 'usageCount' => 0],
            ],
            $this->tagService->browseTags(1, mb_strtoupper(substr($prefix, 1, -1), 'UTF-8'))['tags']
        );
        self::assertSame(3, $this->tagService->browseTags(1, substr($prefix, 1, -1))['total']);
        self::assertSame([], $this->tagService->suggestTags(''));
    }

    public function testUnicodeTagNamesAreNormalizedForWritesSuggestionsBrowseAndImageAttachments(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $tagName = 'Ätag-' . $suffix;
        $normalizedTagName = mb_strtolower($tagName, 'UTF-8');

        $created = $this->tagService->createTag($tagName);
        self::assertNotNull($created);
        self::assertSame($normalizedTagName, $created['name']);
        self::assertNull($this->tagService->createTag('ätag-' . $suffix));
        self::assertNull($this->tagService->createTag('ÄTAG-' . $suffix));

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM tag WHERE name = :name');
        $countStatement->execute(['name' => $normalizedTagName]);
        self::assertSame(1, (int)$countStatement->fetchColumn(), 'Unicode case variants should persist only one tag');

        self::assertSame(
            $this->tagService->suggestTags('Ä'),
            $this->tagService->suggestTags('ä'),
            'Unicode case variants of a suggestion prefix should return the same results',
        );
        self::assertContains($normalizedTagName, $this->tagService->suggestTags('Ä'));
        self::assertSame(
            [['name' => $normalizedTagName, 'category' => 'General', 'usageCount' => 0]],
            $this->tagService->browseTags(1, 'ÄTAG-' . $suffix)['tags'],
            'Unicode mixed-case browse terms should match lowercased stored names',
        );

        $image = ImageTestUtils::createImageEntity(
            $this->imageEntityService,
            userId: 1,
            status: ImageStatus::ACCEPTED,
            uploadDate: new DateTime('2026-10-02 12:00:00'),
        );
        $attachedTag = 'Äupload-' . $suffix;
        self::assertTrue($this->tagService->attachTagToImage($image->getId(), $attachedTag));
        self::assertFalse($this->tagService->attachTagToImage($image->getId(), 'ÄUPLOAD-' . $suffix));
        self::assertSame([mb_strtolower($attachedTag, 'UTF-8')], $this->tagService->getTagsForImage($image->getId()));
    }

    public function testBrowseTagsPaginatesAtTwentyFiveResults(): void
    {
        $prefix = 'browse-page-' . bin2hex(random_bytes(4)) . '-';
        for ($index = 0; $index < 26; $index++) {
            $this->tagService->createTag($prefix . sprintf('%03d', $index));
        }

        $firstPage = $this->tagService->browseTags(1, $prefix);
        $secondPage = $this->tagService->browseTags(2, $prefix);

        self::assertSame(26, $firstPage['total']);
        self::assertSame(25, $firstPage['perPage']);
        self::assertCount(25, $firstPage['tags']);
        self::assertSame(2, $secondPage['page']);
        self::assertCount(1, $secondPage['tags']);
    }
}
