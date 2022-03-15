<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Fixtures\Image;

use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\Image\PictureInterface;

class PictureFactoryWithoutResizeOptionsStub implements PictureFactoryInterface
{
    public function setDefaultDensities($densities): never
    {
        throw new \RuntimeException('not implemented');
    }

    public function create($path, $size = null): never
    {
        throw new \RuntimeException('not implemented');
    }
}
