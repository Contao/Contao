<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Menu;

use Contao\CoreBundle\Event\MenuEvent;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BackendMenuBuilder
{
    private FactoryInterface $factory;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal Do not inherit from this class; decorate the "contao.menu.backend_menu_builder" service instead
     */
    public function __construct(FactoryInterface $factory, EventDispatcherInterface $eventDispatcher)
    {
        $this->factory = $factory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function buildMainMenu(): ItemInterface
    {
        $tree = $this->factory
            ->createItem('mainMenu')
            ->setChildrenAttribute('class', 'menu_level_0')
        ;

        $this->eventDispatcher->dispatch(new MenuEvent($this->factory, $tree));

        return $tree;
    }

    public function buildHeaderMenu(): ItemInterface
    {
        $tree = $this->factory
            ->createItem('headerMenu')
            ->setChildrenAttribute('id', 'tmenu')
        ;

        $this->eventDispatcher->dispatch(new MenuEvent($this->factory, $tree));

        return $tree;
    }
}
