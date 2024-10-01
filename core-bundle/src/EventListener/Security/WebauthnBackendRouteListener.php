<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Adds the "backend" scope to the back end controllers of the WebauthnBundle.
 */
#[AsEventListener(priority: 10)]
class WebauthnBackendRouteListener
{
    /**
     * @param list<string> $routes
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $controller = $request->attributes->get('_route');

        if (\in_array($controller, $this->routes, true)) {
            $request->attributes->set('_scope', 'backend');
        }
    }
}
