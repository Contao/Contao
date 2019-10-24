<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Picker;

use Knp\Menu\ItemInterface;

interface PickerMenuInterface
{
    /**
     * Add one or multiple menu items to the picker.
     */
    public function addMenuItems(ItemInterface $menu, PickerConfig $config): void;
}
