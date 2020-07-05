<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Util;

use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class SymlinkUtil
{
    /**
     * Generates a symlink.
     *
     * The method will try to generate relative symlinks and fall back to generating
     * absolute symlinks if relative symlinks are not supported (see #208).
     */
    public static function symlink(string $target, string $link, string $rootDir): void
    {
        static::validateSymlink($target, $link, $rootDir);

        $target = Path::makeAbsolute($target, $rootDir);
        $link = Path::makeAbsolute($link, $rootDir);

        $fs = new Filesystem();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $fs->symlink($target, $link);
        } else {
            $fs->symlink(Path::makeRelative($target, Path::getDirectory($link)), $link);
        }
    }

    /**
     * Validates a symlink.
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public static function validateSymlink(string $target, string $link, string $rootDir): void
    {
        $target = Path::normalize($target);
        $link = Path::normalize($link);

        if ('' === $target) {
            throw new \InvalidArgumentException('The symlink target must not be empty.');
        }

        if ('' === $link) {
            throw new \InvalidArgumentException('The symlink path must not be empty.');
        }

        if (false !== strpos($link, '../')) {
            throw new \InvalidArgumentException('The symlink path must not be relative.');
        }

        $linkPath = Path::join($rootDir, $link);

        if ((new Filesystem())->exists($linkPath) && !is_link($linkPath)) {
            throw new \LogicException(sprintf('The path "%s" exists and is not a symlink.', $link));
        }
    }
}
