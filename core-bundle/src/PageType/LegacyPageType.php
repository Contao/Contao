<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\PageType;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class LegacyPageType is responsible to wrap an existing page type configured in $GLOBALS['TL_PTY']
 */
class LegacyPageType extends AbstractSinglePageType
{
    /** @var string */
    protected $name;

    public function __construct(string $name, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($eventDispatcher);

        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
