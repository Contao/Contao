<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\EventListener\MakeResponsePrivateListener;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractController extends SymfonyAbstractController
{
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['event_dispatcher'] = EventDispatcherInterface::class;
        $services['logger'] = '?'.LoggerInterface::class;
        $services['fos_http_cache.http.symfony_response_tagger'] = '?'.SymfonyResponseTagger::class;
        $services['contao.csrf.token_manager'] = ContaoCsrfTokenManager::class;
        $services['contao.cache.entity_tags'] = EntityCacheTags::class;

        return $services;
    }

    protected function initializeContaoFramework(): void
    {
        $this->container->get('contao.framework')->initialize();
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @phpstan-return Adapter<T>
     */
    protected function getContaoAdapter(string $class): Adapter
    {
        return $this->container->get('contao.framework')->getAdapter($class);
    }

    protected function tagResponse(array|object|string|null $tags): void
    {
        $this->container->get('contao.cache.entity_tags')->tagWith($tags);
    }

    /**
     * @return array{csrf_field_name: string, csrf_token_manager: ContaoCsrfTokenManager, csrf_token_id: string}
     */
    protected function getCsrfFormOptions(): array
    {
        return [
            'csrf_field_name' => 'REQUEST_TOKEN',
            'csrf_token_manager' => $this->container->get('contao.csrf.token_manager'),
            'csrf_token_id' => $this->getParameter('contao.csrf_token_name'),
        ];
    }

    /**
     * Set the cache headers according to the page settings.
     */
    protected function setCacheHeaders(Response $response, PageModel $pageModel): Response
    {
        // Do not cache the response if caching was not configured at all or disabled explicitly
        if ($pageModel->cache < 1 && $pageModel->clientCache < 1) {
            $response->headers->set('Cache-Control', 'no-cache, no-store');

            return $response->setPrivate(); // Make sure the response is private
        }

        // Private cache
        if ($pageModel->clientCache > 0) {
            $response->setMaxAge($pageModel->clientCache);
            $response->setPrivate(); // Make sure the response is private
        }

        // Shared cache
        if ($pageModel->cache > 0) {
            $response->setSharedMaxAge($pageModel->cache); // Automatically sets the response to public

            // Tag the page (see #2137)
            $this->container->get('contao.cache.entity_tags')->tagWithModelInstance($pageModel);
        }

        return $response;
    }
}
