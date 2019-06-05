<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Image;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\CoreBundle\Image\LegacyResizer;
use Contao\CoreBundle\Tests\TestCase;
use Contao\File;
use Contao\FilesModel;
use Contao\Image as ContaoImage;
use Contao\Image\Image;
use Contao\Image\ImageDimensionsInterface;
use Contao\Image\ImageInterface;
use Contao\Image\ImportantPart;
use Contao\Image\ImportantPartInterface;
use Contao\Image\ResizeCalculator;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeConfigurationInterface;
use Contao\Image\ResizeOptions;
use Contao\Image\ResizerInterface;
use Contao\ImageSizeModel;
use Contao\System;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface as ImagineImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Filesystem\Filesystem;

class ImageFactoryTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->getFixturesDir().'/assets/images')) {
            (new Filesystem())->remove($this->getFixturesDir().'/assets/images');
        }
    }

    public function testCreatesAnImageObjectFromAnImagePath(): void
    {
        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $imageMock = $this->createMock(ImageInterface::class);

        $resizer = $this->createMock(ResizerInterface::class);
        $resizer
            ->expects($this->exactly(2))
            ->method('resize')
            ->with(
                $this->callback(
                    function (Image $image) use (&$path): bool {
                        $this->assertSame($path, $image->getPath());

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeConfiguration $config): bool {
                        $this->assertSame(100, $config->getWidth());
                        $this->assertSame(200, $config->getHeight());
                        $this->assertSame(ResizeConfiguration::MODE_BOX, $config->getMode());

                        return true;
                    }
                )
            )
            ->willReturn($imageMock)
        ;

        /** @var FilesModel&MockObject $filesModel */
        $filesModel = $this->mockClassWithProperties(FilesModel::class);

        $filesAdapter = $this->mockConfiguredAdapter(['findByPath' => $filesModel]);
        $framework = $this->mockContaoFramework([FilesModel::class => $filesAdapter]);
        $imageFactory = $this->getImageFactory($resizer, null, null, null, $framework);
        $image = $imageFactory->create($path, [100, 200, ResizeConfiguration::MODE_BOX]);

        $this->assertSameImage($imageMock, $image);

        $path = $this->getFixturesDir().'/assets/images/dummy.svg';

        if (!file_exists(\dirname($path))) {
            mkdir(\dirname($path), 0777, true);
        }

        file_put_contents($path, '');

        $image = $imageFactory->create($path, [100, 200, ResizeConfiguration::MODE_BOX]);

        $this->assertSameImage($imageMock, $image);
    }

    public function testFailsToCreateAnImageObjectIfTheFileExtensionIsInvalid(): void
    {
        $imageFactory = $this->getImageFactory();

        $this->expectException('InvalidArgumentException');

        $imageFactory->create($this->getFixturesDir().'/images/dummy.foo');
    }

    public function testCreatesAnImageObjectFromAnImagePathWithAnImageSize(): void
    {
        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $imageMock = $this->createMock(ImageInterface::class);

        $resizer = $this->createMock(ResizerInterface::class);
        $resizer
            ->expects($this->once())
            ->method('resize')
            ->with(
                $this->callback(
                    function (Image $image) use ($path): bool {
                        $this->assertSame($path, $image->getPath());

                        $this->assertSameImportantPart(
                            new ImportantPart(new Point(50, 50), new Box(25, 25)),
                            $image->getImportantPart()
                        );

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeConfiguration $config): bool {
                        $this->assertSame(100, $config->getWidth());
                        $this->assertSame(200, $config->getHeight());
                        $this->assertSame(ResizeConfiguration::MODE_BOX, $config->getMode());
                        $this->assertSame(50, $config->getZoomLevel());

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeOptions $options): bool {
                        $this->assertSame([
                            'jpeg_quality' => 80,
                            'interlace' => ImagineImageInterface::INTERLACE_PLANE,
                        ], $options->getImagineOptions());

                        $this->assertSame($this->getFixturesDir().'/target/path.jpg', $options->getTargetPath());

                        return true;
                    }
                )
            )
            ->willReturn($imageMock)
        ;

        /** @var ImageSizeModel&MockObject $imageSizeModel */
        $imageSizeModel = $this->mockClassWithProperties(ImageSizeModel::class);
        $imageSizeModel->width = 100;
        $imageSizeModel->height = 200;
        $imageSizeModel->resizeMode = ResizeConfiguration::MODE_BOX;
        $imageSizeModel->zoom = 50;

        $imageSizeAdapter = $this->mockConfiguredAdapter(['findByPk' => $imageSizeModel]);

        /** @var FilesModel&MockObject $filesModel */
        $filesModel = $this->mockClassWithProperties(FilesModel::class);
        $filesModel->importantPartX = 50;
        $filesModel->importantPartY = 50;
        $filesModel->importantPartWidth = 25;
        $filesModel->importantPartHeight = 25;

        $filesAdapter = $this->mockConfiguredAdapter(['findByPath' => $filesModel]);

        $adapters = [
            ImageSizeModel::class => $imageSizeAdapter,
            FilesModel::class => $filesAdapter,
        ];

        $framework = $this->mockContaoFramework($adapters);
        $imageFactory = $this->getImageFactory($resizer, null, null, null, $framework);
        $image = $imageFactory->create($path, 1, $this->getFixturesDir().'/target/path.jpg');

        $this->assertSame($imageMock, $image);
    }

    public function testCreatesAnImageObjectFromAnImagePathIfTheImageSizeIsMissing(): void
    {
        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $imageSizeAdapter = $this->mockConfiguredAdapter(['findByPk' => null]);
        $filesAdapter = $this->mockConfiguredAdapter(['findByPath' => null]);

        $adapters = [
            ImageSizeModel::class => $imageSizeAdapter,
            FilesModel::class => $filesAdapter,
        ];

        $framework = $this->mockContaoFramework($adapters);
        $imageFactory = $this->getImageFactory(null, null, null, null, $framework);
        $image = $imageFactory->create($path, 1);

        $this->assertSame($path, $image->getPath());
    }

    public function testCreatesAnImageObjectFromAnImageObjectWithAResizeConfiguration(): void
    {
        $resizeConfig = (new ResizeConfiguration())
            ->setWidth(100)
            ->setHeight(200)
            ->setMode(ResizeConfiguration::MODE_BOX)
            ->setZoomLevel(50)
        ;

        $imageMock = $this->createMock(ImageInterface::class);

        $resizer = $this->createMock(ResizerInterface::class);
        $resizer
            ->expects($this->once())
            ->method('resize')
            ->with(
                $this->callback(
                    function (ImageInterface $image) use ($imageMock): bool {
                        $this->assertSameImage($imageMock, $image);

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeConfigurationInterface $config) use ($resizeConfig): bool {
                        $this->assertSame($resizeConfig->isEmpty(), $config->isEmpty());
                        $this->assertSame($resizeConfig->getWidth(), $config->getWidth());
                        $this->assertSame($resizeConfig->getHeight(), $config->getHeight());
                        $this->assertSame($resizeConfig->getMode(), $config->getMode());
                        $this->assertSame($resizeConfig->getZoomLevel(), $config->getZoomLevel());

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeOptions $options): bool {
                        $this->assertSame([
                            'jpeg_quality' => 80,
                            'interlace' => ImagineImageInterface::INTERLACE_PLANE,
                        ], $options->getImagineOptions());

                        $this->assertSame($this->getFixturesDir().'/target/path.jpg', $options->getTargetPath());

                        return true;
                    }
                )
            )
            ->willReturn($imageMock)
        ;

        $imageFactory = $this->getImageFactory($resizer);
        $image = $imageFactory->create($imageMock, $resizeConfig, $this->getFixturesDir().'/target/path.jpg');

        $this->assertSameImage($imageMock, $image);
    }

    public function testCreatesAnImageObjectFromAnImageObjectWithAnEmptyResizeConfiguration(): void
    {
        $imageMock = $this->createMock(ImageInterface::class);
        $imageFactory = $this->getImageFactory();
        $image = $imageFactory->create($imageMock, new ResizeConfiguration());

        $this->assertSameImage($imageMock, $image);
    }

    /**
     * @dataProvider getCreateWithLegacyMode
     */
    public function testCreatesAnImageObjectFromAnImagePathInLegacyMode(string $mode, array $expected): void
    {
        $path = $this->getFixturesDir().'/images/none.jpg';
        $imageMock = $this->createMock(ImageInterface::class);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true)
        ;

        $resizer = $this->createMock(ResizerInterface::class);
        $resizer
            ->expects($this->once())
            ->method('resize')
            ->with(
                $this->callback(
                    function (Image $image) use ($path, $expected): bool {
                        $this->assertSame($path, $image->getPath());

                        $this->assertSameImportantPart(
                            new ImportantPart(
                                new Point($expected[0], $expected[1]),
                                new Box($expected[2], $expected[3])
                            ),
                            $image->getImportantPart()
                        );

                        return true;
                    }
                ),
                $this->callback(
                    function (ResizeConfiguration $config): bool {
                        $this->assertSame(50, $config->getWidth());
                        $this->assertSame(50, $config->getHeight());
                        $this->assertSame(ResizeConfiguration::MODE_CROP, $config->getMode());
                        $this->assertSame(0, $config->getZoomLevel());

                        return true;
                    }
                )
            )
            ->willReturn($imageMock)
        ;

        $imagineImageMock = $this->createMock(ImagineImageInterface::class);
        $imagineImageMock
            ->expects($this->once())
            ->method('getSize')
            ->willReturn(new Box(100, 100))
        ;

        $imagine = $this->createMock(ImagineInterface::class);
        $imagine
            ->expects($this->once())
            ->method('open')
            ->willReturn($imagineImageMock)
        ;

        /** @var FilesModel&MockObject $filesModel */
        $filesModel = $this->mockClassWithProperties(FilesModel::class);
        $filesModel->importantPartX = 50;
        $filesModel->importantPartY = 50;
        $filesModel->importantPartWidth = 25;
        $filesModel->importantPartHeight = 25;

        $filesAdapter = $this->mockConfiguredAdapter(['findByPath' => $filesModel]);
        $framework = $this->mockContaoFramework([FilesModel::class => $filesAdapter]);
        $imageFactory = $this->getImageFactory($resizer, $imagine, $imagine, $filesystem, $framework);
        $image = $imageFactory->create($path, [50, 50, $mode]);

        $this->assertSame($imageMock, $image);
    }

    /**
     * @dataProvider getCreateWithLegacyMode
     */
    public function testReturnsTheImportantPartFromALegacyMode(string $mode, array $expected): void
    {
        $dimensionsMock = $this->createMock(ImageDimensionsInterface::class);
        $dimensionsMock
            ->method('getSize')
            ->willReturn(new Box(100, 100))
        ;

        $imageMock = $this->createMock(ImageInterface::class);
        $imageMock
            ->method('getDimensions')
            ->willReturn($dimensionsMock)
        ;

        $imageFactory = $this->getImageFactory();

        $this->assertSameImportantPart(
            new ImportantPart(new Point($expected[0], $expected[1]), new Box($expected[2], $expected[3])),
            $imageFactory->getImportantPartFromLegacyMode($imageMock, $mode)
        );
    }

    public function getCreateWithLegacyMode(): \Generator
    {
        yield 'Left Top' => ['left_top', [0, 0, 1, 1]];
        yield 'Left Center' => ['left_center', [0, 0, 1, 100]];
        yield 'Left Bottom' => ['left_bottom', [0, 99, 1, 1]];
        yield 'Center Top' => ['center_top', [0, 0, 100, 1]];
        yield 'Center Center' => ['center_center', [0, 0, 100, 100]];
        yield 'Center Bottom' => ['center_bottom', [0, 99, 100, 1]];
        yield 'Right Top' => ['right_top', [99, 0, 1, 1]];
        yield 'Right Center' => ['right_center', [99, 0, 1, 100]];
        yield 'Right Bottom' => ['right_bottom', [99, 99, 1, 1]];
        yield 'Invalid' => ['top_left', [0, 0, 100, 100]];
    }

    public function testFailsToReturnTheImportantPartIfTheModeIsInvalid(): void
    {
        $imageMock = $this->createMock(ImageInterface::class);
        $imageFactory = $this->getImageFactory();

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('not a legacy resize mode');

        $imageFactory->getImportantPartFromLegacyMode($imageMock, 'invalid');
    }

    public function testCreatesAnImageObjectFromAnImagePathWithoutAResizer(): void
    {
        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $adapter = $this->mockConfiguredAdapter(['findByPath' => null]);
        $framework = $this->mockContaoFramework([FilesModel::class => $adapter]);

        $imageFactory = $this->getImageFactory(null, null, null, null, $framework);
        $image = $imageFactory->create($path);

        $this->assertSame($path, $image->getPath());
    }

    public function testIgnoresTheImportantPartIfItIsOutOfBounds(): void
    {
        $path = $this->getFixturesDir().'/images/dummy.jpg';

        $filesModel = $this->mockClassWithProperties(FilesModel::class);
        $filesModel->importantPartX = 50;
        $filesModel->importantPartY = 50;
        $filesModel->importantPartWidth = 175;
        $filesModel->importantPartHeight = 175;

        $filesAdapter = $this->mockConfiguredAdapter(['findByPath' => $filesModel]);
        $framework = $this->mockContaoFramework([FilesModel::class => $filesAdapter]);
        $imageFactory = $this->getImageFactory(null, null, null, null, $framework);
        $image = $imageFactory->create($path);

        $this->assertSameImportantPart(
            new ImportantPart(new Point(0, 0), new Box(200, 200)),
            $image->getImportantPart()
        );
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Using new Contao\Image() has been deprecated %s.
     */
    public function testExecutesTheExecuteResizeHook(): void
    {
        $GLOBALS['TL_CONFIG']['validImageTypes'] = 'jpg';

        System::setContainer($this->getContainerWithContaoConfiguration($this->getFixturesDir()));

        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $adapter = $this->mockConfiguredAdapter(['findByPath' => null]);
        $framework = $this->mockContaoFramework([FilesModel::class => $adapter]);
        $resizer = new LegacyResizer($this->getFixturesDir().'/assets/images', new ResizeCalculator());
        $imagine = new Imagine();
        $imageFactory = $this->getImageFactory($resizer, $imagine, $imagine, null, $framework);

        $GLOBALS['TL_HOOKS'] = [
            'executeResize' => [[\get_class($this), 'executeResizeHookCallback']],
        ];

        $image = $imageFactory->create($path, [100, 100, ResizeConfiguration::MODE_CROP]);

        $this->assertSame(
            $this->getFixturesDir().'/assets/images/dummy.jpg&executeResize_100_100_crop__Contao-Image.jpg',
            $image->getPath()
        );

        $image = $imageFactory->create($path, [200, 200, ResizeConfiguration::MODE_CROP]);

        $this->assertSame(
            $this->getFixturesDir().'/assets/images/dummy.jpg&executeResize_200_200_crop__Contao-Image.jpg',
            $image->getPath()
        );

        $image = $imageFactory->create(
            $path,
            [200, 200, ResizeConfiguration::MODE_CROP],
            $this->getFixturesDir().'/target.jpg'
        );

        $this->assertSame(
            $this->getFixturesDir().'/assets/images/dummy.jpg&executeResize_200_200_crop_target.jpg_Contao-Image.jpg',
            $image->getPath()
        );

        unset($GLOBALS['TL_HOOKS']);
    }

    public static function executeResizeHookCallback(ContaoImage $imageObj): string
    {
        // Do not include $cacheName as it is dynamic (mtime)
        $path = 'assets/'
            .$imageObj->getOriginalPath()
            .'&executeResize'
            .'_'.$imageObj->getTargetWidth()
            .'_'.$imageObj->getTargetHeight()
            .'_'.$imageObj->getResizeMode()
            .'_'.$imageObj->getTargetPath()
            .'_'.str_replace('\\', '-', \get_class($imageObj))
            .'.jpg'
        ;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists(\dirname($rootDir.'/'.$path))) {
            mkdir(\dirname($rootDir.'/'.$path), 0777, true);
        }

        file_put_contents($rootDir.'/'.$path, '');

        return $path;
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Using new Contao\Image() has been deprecated %s.
     */
    public function testExecutesTheGetImageHook(): void
    {
        $GLOBALS['TL_CONFIG']['validImageTypes'] = 'jpg';

        System::setContainer($this->getContainerWithContaoConfiguration($this->getFixturesDir()));

        $path = $this->getFixturesDir().'/images/dummy.jpg';
        $adapter = $this->mockConfiguredAdapter(['findByPath' => null]);
        $framework = $this->mockContaoFramework([FilesModel::class => $adapter]);
        $resizer = new LegacyResizer($this->getFixturesDir().'/assets/images', new ResizeCalculator());
        $imagine = new Imagine();
        $imageFactory = $this->getImageFactory($resizer, $imagine, $imagine, null, $framework);

        $GLOBALS['TL_HOOKS'] = [
            'executeResize' => [[\get_class($this), 'executeResizeHookCallback']],
        ];

        // Build cache before adding the hook
        $imageFactory->create($path, [50, 50, ResizeConfiguration::MODE_CROP]);

        $GLOBALS['TL_HOOKS'] = [
            'getImage' => [[\get_class($this), 'getImageHookCallback']],
        ];

        $image = $imageFactory->create($path, [100, 100, ResizeConfiguration::MODE_CROP]);

        $this->assertSame(
            $this->getFixturesDir().'/assets/images/dummy.jpg&getImage_100_100_crop_Contao-File__Contao-Image.jpg',
            $image->getPath()
        );

        $image = $imageFactory->create($path, [50, 50, ResizeConfiguration::MODE_CROP]);

        $this->assertRegExp(
            '(/images/.*dummy.*.jpg$)',
            $image->getPath(),
            'Hook should not get called for cached images'
        );

        $image = $imageFactory->create($path, [200, 200, ResizeConfiguration::MODE_CROP]);

        $this->assertSame(
            $this->getFixturesDir().'/images/dummy.jpg',
            $image->getPath(),
            'Hook should not get called if no resize is necessary'
        );

        unset($GLOBALS['TL_HOOKS']);
    }

    public static function getImageHookCallback(string $originalPath, int $targetWidth, int $targetHeight, string $resizeMode, string $cacheName, File $fileObj, string $targetPath, ContaoImage $imageObj): string
    {
        // Do not include $cacheName as it is dynamic (mtime)
        $path = 'assets/'
            .$originalPath
            .'&getImage'
            .'_'.$targetWidth
            .'_'.$targetHeight
            .'_'.$resizeMode
            .'_'.str_replace('\\', '-', \get_class($fileObj))
            .'_'.$targetPath
            .'_'.str_replace('\\', '-', \get_class($imageObj))
            .'.jpg'
        ;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists(\dirname($rootDir.'/'.$path))) {
            mkdir(\dirname($rootDir.'/'.$path), 0777, true);
        }

        file_put_contents($rootDir.'/'.$path, '');

        return $path;
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Using new Contao\Image() has been deprecated %s.
     */
    public function testIgnoresAnEmptyHookReturnValue(): void
    {
        $GLOBALS['TL_CONFIG']['validImageTypes'] = 'jpg';

        System::setContainer($this->getContainerWithContaoConfiguration($this->getFixturesDir()));

        $path = $this->getFixturesDir().'/images/dummy.jpg';

        $adapters = [
            FilesModel::class => $this->mockConfiguredAdapter(['findByPath' => null]),
            Config::class => $this->mockConfiguredAdapter(['get' => 3000]),
        ];

        $framework = $this->mockContaoFramework($adapters);

        $resizer = new LegacyResizer($this->getFixturesDir().'/assets/images', new ResizeCalculator());
        $resizer->setFramework($framework);

        $imagine = new Imagine();
        $imageFactory = $this->getImageFactory($resizer, $imagine, $imagine, null, $framework);

        $GLOBALS['TL_HOOKS'] = [
            'getImage' => [[\get_class($this), 'emptyHookCallback']],
        ];

        $image = $imageFactory->create($path, [100, 100, ResizeConfiguration::MODE_CROP]);

        $this->assertRegExp('(/images/.*dummy.*.jpg$)', $image->getPath(), 'Empty hook should be ignored');
        $this->assertSame(100, $image->getDimensions()->getSize()->getWidth());
        $this->assertSame(100, $image->getDimensions()->getSize()->getHeight());

        unset($GLOBALS['TL_HOOKS']);
    }

    public static function emptyHookCallback(): void
    {
    }

    /**
     * @param ResizerInterface&MockObject $resizer
     * @param ImagineInterface&MockObject $imagine
     * @param ImagineInterface&MockObject $imagineSvg
     * @param ContaoFramework&MockObject  $framework
     */
    private function getImageFactory(ResizerInterface $resizer = null, ImagineInterface $imagine = null, ImagineInterface $imagineSvg = null, Filesystem $filesystem = null, ContaoFramework $framework = null, bool $bypassCache = null, array $imagineOptions = null, array $validExtensions = null): ImageFactory
    {
        if (null === $resizer) {
            $resizer = $this->createMock(ResizerInterface::class);
        }

        if (null === $imagine) {
            $imagine = $this->createMock(ImagineInterface::class);
        }

        if (null === $imagineSvg) {
            $imagineSvg = $this->createMock(ImagineInterface::class);
        }

        if (null === $filesystem) {
            $filesystem = new Filesystem();
        }

        if (null === $framework) {
            $framework = $this->createMock(ContaoFramework::class);
        }

        if (null === $bypassCache) {
            $bypassCache = false;
        }

        if (null === $imagineOptions) {
            $imagineOptions = [
                'jpeg_quality' => 80,
                'interlace' => ImagineImageInterface::INTERLACE_PLANE,
            ];
        }

        if (null === $validExtensions) {
            $validExtensions = ['jpg', 'svg'];
        }

        return new ImageFactory(
            $resizer,
            $imagine,
            $imagineSvg,
            $filesystem,
            $framework,
            $bypassCache,
            $imagineOptions,
            $validExtensions
        );
    }

    private function assertSameImage(ImageInterface $imageA, ImageInterface $imageB): void
    {
        $this->assertSame($imageA->getDimensions(), $imageB->getDimensions());
        $this->assertSame($imageA->getImagine(), $imageB->getImagine());
        $this->assertSame($imageA->getImportantPart(), $imageB->getImportantPart());
        $this->assertSame($imageA->getPath(), $imageB->getPath());
        $this->assertSame($imageA->getUrl($this->getFixturesDir()), $imageB->getUrl($this->getFixturesDir()));
    }

    private function assertSameImportantPart(ImportantPartInterface $partA, ImportantPartInterface $partB): void
    {
        $this->assertSame($partA->getPosition()->getX(), $partB->getPosition()->getX());
        $this->assertSame($partA->getPosition()->getY(), $partB->getPosition()->getY());
        $this->assertSame($partA->getSize()->getHeight(), $partB->getSize()->getHeight());
        $this->assertSame($partA->getSize()->getWidth(), $partB->getSize()->getWidth());
    }
}
