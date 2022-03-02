<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Image\Preview;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\CoreBundle\Image\PictureFactory;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\FigureBuilder;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\Image\ImageInterface;
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureInterface;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeOptions;
use Contao\ImageSizeItemModel;
use Contao\ImageSizeModel;
use Contao\StringUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class PreviewFactory
{
    /**
     * @var iterable<int,PreviewProviderInterface>
     */
    private iterable $previewProviders;

    private ImageFactoryInterface $imageFactory;
    private PictureFactoryInterface $pictureFactory;
    private Studio $imageStudio;
    private ContaoFramework $framework;
    private string $secret;
    private string $cacheDir;
    private array $validImageExtensions;
    private string $defaultDensities = '';
    private array $predefinedSizes = [];
    private int $defaultSize;
    private int $maxSize;

    /**
     * @param iterable<int,PreviewProviderInterface> $previewProviders
     */
    public function __construct(iterable $previewProviders, ImageFactoryInterface $imageFactory, PictureFactoryInterface $pictureFactory, Studio $imageStudio, ContaoFramework $framework, string $secret, string $cacheDir, array $validImageExtensions, int $defaultSize, int $maxSize)
    {
        $this->previewProviders = $previewProviders;
        $this->imageFactory = $imageFactory;
        $this->pictureFactory = $pictureFactory;
        $this->imageStudio = $imageStudio;
        $this->framework = $framework;
        $this->secret = $secret;
        $this->cacheDir = $cacheDir;
        $this->validImageExtensions = $validImageExtensions;
        $this->defaultSize = $defaultSize;
        $this->maxSize = $maxSize;
    }

    public function setDefaultDensities(string $densities): self
    {
        $this->defaultDensities = $densities;

        return $this;
    }

    public function setPredefinedSizes(array $predefinedSizes): void
    {
        $this->predefinedSizes = $predefinedSizes;
    }

    /**
     * @throws UnableToGeneratePreviewException|MissingPreviewProviderException
     */
    public function createPreview(string $path, int $size = 0, int $page = 1, array $previewOptions = []): ImageInterface
    {
        foreach ($this->createPreviews($path, $size, $page, $page, $previewOptions) as $preview) {
            return $preview;
        }

        throw new UnableToGeneratePreviewException();
    }

    /**
     * @throws UnableToGeneratePreviewException|MissingPreviewProviderException
     *
     * @return iterable<ImageInterface>
     */
    public function createPreviews(string $path, int $size = 0, int $lastPage = PHP_INT_MAX, int $firstPage = 1, array $previewOptions = []): iterable
    {
        if ($firstPage < 1 || $lastPage < 1 || $firstPage > $lastPage) {
            throw new \InvalidArgumentException();
        }

        // Supported image formats do not need an extra preview image
        if (\in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $this->validImageExtensions, true)) {
            return [$this->imageFactory->create($path)];
        }

        $size = $this->normalizeSize($size);

        $targetPath = Path::join(
            $this->cacheDir,
            $this->createCachePath($path, $size, $previewOptions)
        );

        if (null !== ($cachedPreviews = $this->getCachedPreviews($targetPath, $firstPage, $lastPage))) {
            return array_map(fn ($path) => $this->imageFactory->create($path), $cachedPreviews);
        }

        if (!is_dir(\dirname($targetPath))) {
            (new Filesystem())->mkdir(\dirname($targetPath));
        }

        $header = $this->getHeader($path);
        $lastProviderException = null;
        $targetPathCallback = fn (int $page) => $targetPath.$this->getPageSuffix($page);

        foreach ($this->previewProviders as $provider) {
            if ($provider->supports($path, $header, $previewOptions)) {
                try {
                    $previews = $provider->generatePreviews(
                        $path,
                        $size,
                        $targetPathCallback,
                        $lastPage,
                        $firstPage,
                        $previewOptions
                    );

                    if (!\is_array($previews)) {
                        $previews = iterator_to_array($previews, false);
                    }

                    // We reached the last page if the number of returned
                    // previews was less than the number of pages requested
                    if (\count($previews) > 0 && \count($previews) <= $lastPage - $firstPage) {
                        $lastPreview = $previews[array_key_last($previews)];
                        $fileExtension = pathinfo($lastPreview, PATHINFO_EXTENSION);
                        $this->symlink($lastPreview, "$targetPath-last.$fileExtension");
                    }

                    if (\count($previews) > 1 + $lastPage - $firstPage) {
                        throw new \LogicException(sprintf('Preview provider "%s" returned %s pages instead of the requested %s.', \get_class($provider), \count($previews), 1 + $lastPage - $firstPage));
                    }

                    return array_map(fn ($path) => $this->imageFactory->create($path), $previews);
                } catch (UnableToGeneratePreviewException $exception) {
                    $lastProviderException = $exception;
                }
            }
        }

        throw $lastProviderException ?? new MissingPreviewProviderException();
    }

    /**
     * @param int|string|array|ResizeConfiguration|null $size
     */
    public function createPreviewImage(string $path, $size = null, ResizeOptions $resizeOptions = null, int $page = 1, array $previewOptions = []): ImageInterface
    {
        return $this->imageFactory->create(
            $this->createPreview($path, $this->getPreviewSizeFromImageSize($size), $page, $previewOptions),
            $size,
            $resizeOptions,
        );
    }

    /**
     * @param int|string|array|ResizeConfiguration|null $size
     *
     * @return iterable<ImageInterface>
     */
    public function createPreviewImages(string $path, $size = null, ResizeOptions $resizeOptions = null, int $lastPage = PHP_INT_MAX, int $firstPage = 1, array $previewOptions = []): iterable
    {
        $previews = $this->createPreviews(
            $path,
            $this->getPreviewSizeFromImageSize($size),
            $lastPage,
            $firstPage,
            $previewOptions,
        );

        return array_map(
            fn ($preview) => $this->imageFactory->create($preview, $size, $resizeOptions),
            \is_array($previews) ? $previews : iterator_to_array($previews, false),
        );
    }

    /**
     * @param int|string|array|PictureConfiguration|null $size
     */
    public function createPreviewPicture(string $path, $size = null, ResizeOptions $resizeOptions = null, int $page = 1, array $previewOptions = []): PictureInterface
    {
        $previewPath = $this->createPreview($path, $this->getPreviewSizeFromImageSize($size), $page, $previewOptions);

        return $this->convertPreviewsToPictures([$previewPath], $size, $resizeOptions)[0];
    }

    /**
     * @param int|string|array|PictureConfiguration|null $size
     *
     * @return iterable<PictureInterface>
     */
    public function createPreviewPictures(string $path, $size = null, ResizeOptions $resizeOptions = null, int $lastPage = PHP_INT_MAX, int $firstPage = 1, array $previewOptions = []): iterable
    {
        return $this->convertPreviewsToPictures(
            $this->createPreviews(
                $path,
                $this->getPreviewSizeFromImageSize($size),
                $lastPage,
                $firstPage,
                $previewOptions,
            ),
            $size,
            $resizeOptions,
        );
    }

    /**
     * @param int|string|array|PictureConfiguration|null $size
     */
    public function createPreviewFigure(string $path, $size = null, ResizeOptions $resizeOptions = null, int $page = 1, array $previewOptions = []): Figure
    {
        return $this->createPreviewFigureBuilder($path, $size, $resizeOptions, $page, $previewOptions)->build();
    }

    /**
     * @param int|string|array|PictureConfiguration|null $size
     *
     * @return iterable<Figure>
     */
    public function createPreviewFigures(string $path, $size = null, ResizeOptions $resizeOptions = null, int $lastPage = PHP_INT_MAX, int $firstPage = 1, array $previewOptions = []): iterable
    {
        $previews = $this->createPreviews(
            $path,
            $this->getPreviewSizeFromImageSize($size),
            $lastPage,
            $firstPage,
            $previewOptions,
        );

        $builder = $this->imageStudio->createFigureBuilder()->setSize($size)->setResizeOptions($resizeOptions);
        $figures = [];

        foreach ($previews as $preview) {
            $figures[] = $builder->fromImage($preview)->build();
        }

        return $figures;
    }

    /**
     * @param int|string|array|PictureConfiguration|null $size
     */
    public function createPreviewFigureBuilder(string $path, $size = null, ResizeOptions $resizeOptions = null, int $page = 1, array $previewOptions = []): FigureBuilder
    {
        return $this->imageStudio
            ->createFigureBuilder()
            ->fromImage($this->createPreview($path, $this->getPreviewSizeFromImageSize($size), $page, $previewOptions))
            ->setSize($size)
            ->setResizeOptions($resizeOptions)
        ;
    }

    /**
     * @param int|string|array|ResizeConfiguration|PictureConfiguration|null $size
     */
    public function getPreviewSizeFromImageSize($size): int
    {
        if ($size instanceof ResizeConfiguration) {
            return max($size->getWidth(), $size->getHeight());
        }

        if ($size instanceof PictureConfiguration) {
            $previewSize = $this->getPreviewSizeFromWidthHeightDensities(
                $size->getSize()->getResizeConfig()->getWidth(),
                $size->getSize()->getResizeConfig()->getHeight(),
                $size->getSize()->getDensities(),
            );

            foreach ($size->getSizeItems() as $sizeItem) {
                $previewSize = max(
                    $previewSize,
                    $this->getPreviewSizeFromWidthHeightDensities(
                        $sizeItem->getResizeConfig()->getWidth(),
                        $sizeItem->getResizeConfig()->getHeight(),
                        $sizeItem->getDensities(),
                    ),
                );
            }

            return $previewSize;
        }

        // Support arrays in a serialized form
        $size = StringUtil::deserialize($size);

        if (!\is_array($size)) {
            $size = [0, 0, $size];
        }

        $size += [0, 0, 'crop'];

        if ($predefinedSize = $this->predefinedSizes[$size[2] ?? null] ?? null) {
            $previewSize = $this->getPreviewSizeFromWidthHeightDensities(
                $predefinedSize['width'] ?? 0,
                $predefinedSize['height'] ?? 0,
                $predefinedSize['densities'],
            );

            foreach ($predefinedSize['items'] ?? [] as $sizeItem) {
                $previewSize = max(
                    $previewSize,
                    $this->getPreviewSizeFromWidthHeightDensities(
                        $sizeItem['width'] ?? 0,
                        $sizeItem['height'] ?? 0,
                        $sizeItem['densities'] ?? '',
                    ),
                );
            }

            return $previewSize;
        }

        if (is_numeric($size[2])) {
            $imageSize = $this->framework->getAdapter(ImageSizeModel::class)->findByPk($size[2]);

            if (null === $imageSize) {
                return 0;
            }

            $previewSize = $this->getPreviewSizeFromWidthHeightDensities(
                (int) $imageSize->width,
                (int) $imageSize->height,
                $imageSize->densities,
            );

            $imageSizeItems = $this->framework->getAdapter(ImageSizeItemModel::class)->findVisibleByPid($size[2], ['order' => 'sorting ASC']);

            foreach ($imageSizeItems ?? [] as $sizeItem) {
                $previewSize = max(
                    $previewSize,
                    $this->getPreviewSizeFromWidthHeightDensities(
                        (int) $sizeItem->width,
                        (int) $sizeItem->height,
                        $sizeItem->densities,
                    ),
                );
            }

            return $previewSize;
        }

        return $this->getPreviewSizeFromWidthHeightDensities(
            (int) ($size[0] ?? 0),
            (int) ($size[1] ?? 0),
            $this->defaultDensities,
        );
    }

    /**
     * @param iterable<ImageInterface>                   $previews
     * @param int|string|array|PictureConfiguration|null $size
     *
     * @return iterable<PictureInterface>
     */
    private function convertPreviewsToPictures(iterable $previews, $size, ResizeOptions $resizeOptions = null): iterable
    {
        // Unlike the Contao\Image\PictureFactory, the PictureFactoryInterface
        // does not know about ResizeOptions. We therefore check if the third
        // argument of the 'create' method allows setting them.
        // TODO: Adjust this in Contao 5 after the interface has been adjusted.
        $canHandleResizeOptions = static function (PictureFactoryInterface $factory): bool {
            if ($factory instanceof PictureFactory) {
                return true;
            }

            $createParameters = (new \ReflectionClass($factory))
                ->getMethod('create')
                ->getParameters()
            ;

            if (!isset($createParameters[2])) {
                return false;
            }

            $type = $createParameters[2]->getType();

            return $type instanceof \ReflectionNamedType && ResizeOptions::class === $type->getName();
        };

        $commonArguments = [$size];

        if (null !== $resizeOptions && $canHandleResizeOptions($this->pictureFactory)) {
            $commonArguments[] = $resizeOptions;
        }

        return array_map(
            fn ($path) => $this->pictureFactory->create($path, ...$commonArguments),
            \is_array($previews) ? $previews : iterator_to_array($previews, false),
        );
    }

    private function normalizeSize(int $size): int
    {
        if ($size < 0) {
            throw new \InvalidArgumentException('Preview size must not be negative');
        }

        $newSize = $this->defaultSize;

        while ($newSize < $size) {
            $newSize *= 2;
        }

        return min($newSize, $this->maxSize);
    }

    private function getCachedPreviews(string $targetPath, int $firstPage, int $lastPage): ?array
    {
        $globPattern = preg_replace('/[*?[{\\\\]/', '\\\\$0', $targetPath).'*.*';
        $filesFound = [];

        foreach (glob($globPattern) as $cacheFile) {
            if (\in_array(pathinfo($cacheFile, PATHINFO_EXTENSION), $this->validImageExtensions, true)) {
                $filesFound[pathinfo($cacheFile, PATHINFO_FILENAME)] = $cacheFile;
            }
        }

        $previews = [];
        $fileName = pathinfo($targetPath, PATHINFO_BASENAME);

        for ($page = $firstPage; $page <= $lastPage; ++$page) {
            $cacheFileName = $fileName.$this->getPageSuffix($page);

            if (isset($filesFound[$cacheFileName])) {
                $previews[] = $filesFound[$cacheFileName];
                continue;
            }

            if (!isset($filesFound["$fileName-last"])) {
                return null;
            }

            $lastName = pathinfo((new Filesystem())->readlink($filesFound["$fileName-last"]), PATHINFO_FILENAME);
            $lastFound = $fileName.$this->getPageSuffix($page - 1);

            if ($lastName !== $lastFound) {
                return null;
            }

            break;
        }

        return $previews;
    }

    private function getHeader(string $path): string
    {
        $size = 0;

        foreach ($this->previewProviders as $provider) {
            $size = max($size, $provider->getFileHeaderSize());
        }

        return $size > 0 ? file_get_contents($path, false, null, 0, $size) : '';
    }

    private function getPreviewSizeFromWidthHeightDensities(int $width, int $height, string $densities): int
    {
        $widthDescriptor = 0;
        $scaleFactor = 1;

        foreach (explode(',', $densities) as $density) {
            if ('w' === substr(trim($density), -1)) {
                $widthDescriptor = max($widthDescriptor, (int) $density);
            } else {
                $scaleFactor = max($scaleFactor, (float) $density);
            }
        }

        return (int) round(
            max(
                max($width, $height) * $scaleFactor,
                $widthDescriptor,
            )
        );
    }

    private function createCachePath(string $path, int $size, array $previewOptions): string
    {
        ksort($previewOptions);

        $hashData = [
            Path::makeRelative($path, $this->cacheDir),
            (string) $size,
            (string) filemtime($path),
            ...array_keys($previewOptions),
            ...array_values($previewOptions),
        ];

        $hash = hash_hmac('sha256', implode('|', $hashData), $this->secret, true);
        $hash = strtolower(substr(StringUtil::encodeBase32($hash), 0, 16));
        $name = pathinfo($path, PATHINFO_FILENAME);

        return $hash[0]."/$name-".substr($hash, 1);
    }

    private function getPageSuffix(int $page): string
    {
        return $page < 2 ? '' : '-'.$page;
    }

    private function symlink(string $linkToPath, string $linkPath): void
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $linkToPath = Path::makeRelative($linkToPath, \dirname($linkPath));
        }

        (new Filesystem())->symlink($linkToPath, $linkPath);
    }
}
