<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Image\Studio;

use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\CoreBundle\Image\Studio\ImageResult;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Image\DeferredImage;
use Contao\Image\DeferredResizerInterface;
use Contao\Image\Image;
use Contao\Image\ImageDimensions;
use Contao\Image\ImageInterface;
use Contao\Image\PictureInterface;
use Contao\Image\Resizer;
use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageResultTest extends TestCase
{
    public function testGetPicture(): void
    {
        $filePathOrImage = 'foo/bar/foobar.png';
        $sizeConfiguration = [100, 200, 'crop'];

        /** @var PictureInterface&MockObject $picture */
        $picture = $this->createMock(PictureInterface::class);
        $pictureFactory = $this->getPictureFactoryMock($filePathOrImage, $sizeConfiguration, $picture);
        $locator = $this->getLocatorMock($pictureFactory);
        $imageResult = new ImageResult($locator, 'any/project/dir', $filePathOrImage, $sizeConfiguration);

        $this->assertSame($picture, $imageResult->getPicture());
    }

    public function testGetSourcesAndImg(): void
    {
        $filePathOrImage = 'foo/bar/foobar.png';
        $sizeConfiguration = [100, 200, 'crop'];

        $projectDir = 'project/dir';
        $staticUrl = 'static/url';

        $sources = ['sources result'];
        $img = ['img result'];

        /** @var PictureInterface&MockObject $picture */
        $picture = $this->createMock(PictureInterface::class);
        $picture
            ->expects($this->once())
            ->method('getSources')
            ->with($projectDir, $staticUrl)
            ->willReturn($sources)
        ;

        $picture
            ->expects($this->once())
            ->method('getImg')
            ->with($projectDir, $staticUrl)
            ->willReturn($img)
        ;

        $pictureFactory = $this->getPictureFactoryMock($filePathOrImage, $sizeConfiguration, $picture);
        $locator = $this->getLocatorMock($pictureFactory, $staticUrl);
        $imageResult = new ImageResult($locator, $projectDir, $filePathOrImage, $sizeConfiguration);

        $this->assertSame($sources, $imageResult->getSources());
        $this->assertSame($img, $imageResult->getImg());
    }

    public function testGetImageSrc(): void
    {
        $filePath = 'foo/bar/foobar.png';
        $sizeConfiguration = [100, 200, 'crop'];

        $projectDir = 'project/dir';
        $staticUrl = 'static/url';

        $img = ['src' => 'foo', 'other' => 'bar'];

        /** @var PictureInterface&MockObject $picture */
        $picture = $this->createMock(PictureInterface::class);
        $picture
            ->expects($this->once())
            ->method('getImg')
            ->with($projectDir, $staticUrl)
            ->willReturn($img)
        ;

        $pictureFactory = $this->getPictureFactoryMock($filePath, $sizeConfiguration, $picture);
        $locator = $this->getLocatorMock($pictureFactory, $staticUrl);
        $imageResult = new ImageResult($locator, $projectDir, $filePath, $sizeConfiguration);

        $this->assertSame('foo', $imageResult->getImageSrc());
    }

    public function testGetOriginalDimensionsFromPathResource(): void
    {
        $filePath = 'foo/bar/foobar.png';
        $dimensions = $this->createMock(ImageDimensions::class);

        /** @var ImageInterface&MockObject $image */
        $image = $this->createMock(ImageInterface::class);
        $image
            ->expects($this->once())
            ->method('getDimensions')
            ->willReturn($dimensions)
        ;

        /** @var ImageFactoryInterface&MockObject $imageFactory */
        $imageFactory = $this->createMock(ImageFactoryInterface::class);
        $imageFactory
            ->expects($this->once())
            ->method('create')
            ->with($filePath)
            ->willReturn($image)
        ;

        /** @var ContainerInterface&MockObject $locator */
        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->expects($this->once())
            ->method('get')
            ->with('contao.image.image_factory')
            ->willReturn($imageFactory)
        ;

        $imageResult = new ImageResult($locator, 'any/project/dir', $filePath);

        $this->assertSame($dimensions, $imageResult->getOriginalDimensions());

        // Expect result to be cached on second call
        $imageResult->getOriginalDimensions();
    }

    public function testGetOriginalDimensionsFromImageResource(): void
    {
        $dimensions = $this->createMock(ImageDimensions::class);

        /** @var ImageInterface&MockObject $image */
        $image = $this->createMock(ImageInterface::class);
        $image
            ->expects($this->once())
            ->method('getDimensions')
            ->willReturn($dimensions)
        ;

        $locator = $this->getLocatorMock();
        $imageResult = new ImageResult($locator, 'any/project/dir', $image);

        $this->assertSame($dimensions, $imageResult->getOriginalDimensions());
    }

    public function testGetFilePathFromPathResource(): void
    {
        $projectDir = 'project/dir';
        $filePath = 'project/dir/file/path';

        $locator = $this->getLocatorMock(null);
        $imageResult = new ImageResult($locator, $projectDir, $filePath);

        $this->assertSame('file/path', $imageResult->getFilePath());
        $this->assertSame('project/dir/file/path', $imageResult->getFilePath(true));
    }

    public function testGetFilePathFromImageResource(): void
    {
        $projectDir = 'project/dir';
        $filePath = 'project/dir/file/path';

        /** @var ImageInterface&MockObject $image */
        $image = $this->createMock(ImageInterface::class);
        $image
            ->expects($this->exactly(2))
            ->method('getPath')
            ->willReturn($filePath)
        ;

        $locator = $this->getLocatorMock();
        $imageResult = new ImageResult($locator, $projectDir, $image);

        $this->assertSame('file/path', $imageResult->getFilePath());
        $this->assertSame('project/dir/file/path', $imageResult->getFilePath(true));
    }

    /**
     * @dataProvider provideDeferredImages
     */
    public function testCreateIfDeferred(array $img, array $sources, array $expectedDeferredImages): void
    {
        /** @var PictureInterface&MockObject $picture */
        $picture = $this->createMock(PictureInterface::class);
        $picture
            ->expects($this->once())
            ->method('getSources')
            ->with()
            ->willReturn($sources)
        ;

        $picture
            ->expects($this->once())
            ->method('getImg')
            ->with()
            ->willReturn($img)
        ;

        $pictureFactory = $this->createMock(PictureFactoryInterface::class);
        $pictureFactory
            ->method('create')
            ->willReturn($picture)
        ;

        $deferredResizer = $this->createMock(DeferredResizerInterface::class);
        $deferredResizer
            ->expects($this->exactly(\count($expectedDeferredImages)))
            ->method('resizeDeferredImage')
            ->with($this->callback(
                static function ($deferredImage) use (&$expectedDeferredImages) {
                    unset($expectedDeferredImages[array_search($deferredImage, $expectedDeferredImages, true)]);

                    return true;
                }
            ))
        ;

        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->method('get')
            ->willReturnMap([
                ['contao.image.picture_factory', $pictureFactory],
                ['contao.image.resizer', $deferredResizer],
            ])
        ;

        $imageResult = new ImageResult($locator, '/project/dir', '/project/dir/image.jpg');
        $imageResult->createIfDeferred();

        $this->assertEmpty($expectedDeferredImages, 'test all images were processed');
    }

    public function provideDeferredImages(): \Generator
    {
        $imagine = $this->createMock(ImagineInterface::class);
        $dimensions = $this->createMock(ImageDimensions::class);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(true)
        ;

        $image = new Image('/project/dir/assets/image0.jpg', $imagine, $filesystem);
        $deferredImage1 = new DeferredImage('/project/dir/assets/image1.jpg', $imagine, $dimensions);
        $deferredImage2 = new DeferredImage('/project/dir/assets/image2.jpg', $imagine, $dimensions);
        $deferredImage3 = new DeferredImage('/project/dir/assets/image3.jpg', $imagine, $dimensions);
        $deferredImage4 = new DeferredImage('/project/dir/assets/image4.jpg', $imagine, $dimensions);

        yield 'no deferred images' => [
            ['src' => $image],
            [],
            [],
        ];

        yield 'img and sources with deferred images (without duplicates)' => [
            [
                'src' => $deferredImage1,
                'srcset' => [[$deferredImage2, 'foo']],
            ],
            [
                [
                    'src' => $deferredImage3,
                    'srcset' => [[$deferredImage4]],
                ],
            ],
            [$deferredImage1, $deferredImage2, $deferredImage3, $deferredImage4],
        ];

        yield 'img and sources with deferred images (with duplicates)' => [
            [
                'src' => $deferredImage1,
                'srcset' => [[$deferredImage2, 'foo'], [$deferredImage3]],
            ],
            [
                [
                    'src' => $deferredImage3,
                    'srcset' => [[$deferredImage2], [$deferredImage4]],
                ],
                [
                    'src' => $deferredImage2,
                    'srcset' => [[$deferredImage4]],
                ],
            ],
            [$deferredImage1, $deferredImage2, $deferredImage3, $deferredImage4],
        ];

        yield 'img and sources with both deferred and non-deferred images' => [
            [
                'src' => $deferredImage1,
            ],
            [
                [
                    'src' => $image,
                ],
                [
                    'src' => $deferredImage2,
                    'srcset' => [[$deferredImage3]],
                ],
            ],
            [$deferredImage1, $deferredImage2, $deferredImage3],
        ];

        yield 'elements without src or srcset key' => [
            [
                'foo' => 'bar',
            ],
            [
                [
                    'bar' => 'foo',
                ],
                [
                    'srcset' => [['foo'], [$deferredImage2]],
                ],
                [
                    'src' => $deferredImage1,
                ],
            ],
            [$deferredImage1, $deferredImage2],
        ];
    }

    public function testCreateIfDeferredFailsWithoutDeferredResizer(): void
    {
        $pictureFactory = $this->createMock(PictureFactoryInterface::class);

        $nonDeferredResizer = $this->createMock(Resizer::class);

        $locator = $this->createMock(ContainerInterface::class);
        $locator
            ->method('get')
            ->willReturnMap([
                ['contao.image.picture_factory', $pictureFactory],
                ['contao.image.resizer', $nonDeferredResizer],
            ])
        ;

        $imageResult = new ImageResult($locator, '/project/dir', '/project/dir/image.jpg');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "contao.image.resizer" service does not support deferred resizing.');

        $imageResult->createIfDeferred();
    }

    /**
     * @return PictureFactoryInterface&MockObject
     */
    private function getPictureFactoryMock($filePathOrImage, $sizeConfiguration, PictureInterface $picture)
    {
        $pictureFactory = $this->createMock(PictureFactoryInterface::class);
        $pictureFactory
            ->expects($this->once())
            ->method('create')
            ->with($filePathOrImage, $sizeConfiguration)
            ->willReturn($picture)
        ;

        return $pictureFactory;
    }

    /**
     * @return ContainerInterface&MockObject
     */
    private function getLocatorMock(?PictureFactoryInterface $pictureFactory = null, string $staticUrl = null)
    {
        $locator = $this->createMock(ContainerInterface::class);

        $context = null;

        if (null !== $staticUrl) {
            $context = $this->createMock(ContaoContext::class);
            $context
                ->expects($this->atLeastOnce())
                ->method('getStaticUrl')
                ->willReturn($staticUrl)
            ;
        }

        $locator
            ->method('get')
            ->willReturnMap([
                ['contao.image.picture_factory', $pictureFactory],
                ['contao.assets.files_context', $context],
            ])
        ;

        return $locator;
    }
}
