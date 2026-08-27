<?php
declare(strict_types=1);

namespace SmolCms\Test\Service\Image;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use SmolCms\Service\Image\ImageProcessorFacade;
use SmolCms\Service\Image\ImageProcessorInterface;
use SmolCms\TestUtils\Attributes\Mock;
use SmolCms\TestUtils\SimpleTestCase;

class ImageProcessorFacadeTest extends SimpleTestCase
{
    #[Mock(ImageProcessorInterface::class)]
    private ImageProcessorInterface|MockObject $mockProcessor1;
    #[Mock(ImageProcessorInterface::class)]
    private ImageProcessorInterface|MockObject $mockProcessor2;
    #[Mock(ImageProcessorInterface::class)]
    private ImageProcessorInterface|MockObject $mockProcessor3;
    private ImageProcessorFacade $testee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testee = new ImageProcessorFacade(
            $this->mockProcessor1,
            $this->mockProcessor2,
            $this->mockProcessor3,
        );
    }

    public function testProcessImage_callsAllProcessors(): void
    {
        $imageData = 'test image data';
        $filename = 'test.jpg';

        $this->mockProcessor1->expects($this->once())
            ->method('process')
            ->with($imageData, $filename);
        $this->mockProcessor2->expects($this->once())
            ->method('process')
            ->with($imageData, $filename);
        $this->mockProcessor3->expects($this->once())
            ->method('process')
            ->with($imageData, $filename);

        $this->testee->processImage($imageData, $filename);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessImage_emptyArray_noErrors(): void
    {
        $testee = new ImageProcessorFacade();
        $testee->processImage('test data', 'test.jpg');

        $this->addToAssertionCount(1); // Verify no exception was thrown
    }
}
