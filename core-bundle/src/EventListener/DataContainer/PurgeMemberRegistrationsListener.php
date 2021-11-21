<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\Automator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Purges the member registrations in the front and back end, whenever tl_member is loaded.
 *
 * @internal
 *
 * @Callback(table="tl_member", target="config.onload")
 */
class PurgeMemberRegistrationsListener
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Do not purge during any back end edit actions
        if (null !== $request && $this->scopeMatcher->isBackendRequest($request) && null !== $request->query->get('act')) {
            return;
        }

        (new Automator())->purgeRegistrations();
    }
}
