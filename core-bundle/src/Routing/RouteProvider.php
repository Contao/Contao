<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing;

use Contao\CoreBundle\Exception\NoRootPageFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\CoreBundle\Routing\Page\PageRouteFactory;
use Contao\Model;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Cmf\Component\Routing\Candidates\CandidatesInterface;
use Symfony\Cmf\Component\Routing\RouteProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteProvider implements RouteProviderInterface
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $database;

    /**
     * @var CandidatesInterface
     */
    private $candidates;

    /**
     * @var PageRouteFactory
     */
    private $routeFactory;

    /**
     * @var bool
     */
    private $legacyRouting;

    /**
     * @var bool
     */
    private $prependLocale;

    /**
     * @internal Do not inherit from this class; decorate the "contao.routing.route_provider" service instead
     */
    public function __construct(ContaoFramework $framework, Connection $database, CandidatesInterface $candidates, PageRouteFactory $routeFactory, bool $legacyRouting, bool $prependLocale)
    {
        $this->framework = $framework;
        $this->database = $database;
        $this->candidates = $candidates;
        $this->routeFactory = $routeFactory;
        $this->legacyRouting = $legacyRouting;
        $this->prependLocale = $prependLocale;
    }

    public function getRouteCollectionForRequest(Request $request): RouteCollection
    {
        $this->framework->initialize(true);

        $pathInfo = rawurldecode($request->getPathInfo());

        // The request string must not contain "auto_item" (see #4012)
        if (false !== strpos($pathInfo, '/auto_item/')) {
            return new RouteCollection();
        }

        $routes = [];

        if ('/' === $pathInfo || ($this->legacyRouting && $this->prependLocale && preg_match('@^/([a-z]{2}(-[A-Z]{2})?)/$@', $pathInfo))) {
            $this->addRoutesForRootPages($this->findRootPages($request->getHttpHost()), $routes);

            return $this->createCollectionForRoutes($routes, $request->getLanguages());
        }

        $candidates = $this->candidates->getCandidates($request);

        if (0 === \count($candidates)) {
            return new RouteCollection();
        }

        $pages = $this->findPages($candidates);

        $this->addRoutesForPages($pages, $routes, $request);

        return $this->createCollectionForRoutes($routes, $request->getLanguages());
    }

    public function getRouteByName($name): Route
    {
        $this->framework->initialize(true);

        $ids = $this->getPageIdsFromNames([$name]);

        if (empty($ids)) {
            throw new RouteNotFoundException('Route name does not match a page ID');
        }

        /** @var PageModel $pageModel */
        $pageModel = $this->framework->getAdapter(PageModel::class);
        $page = $pageModel->findByPk($ids[0]);

        if (null === $page) {
            throw new RouteNotFoundException(sprintf('Page ID "%s" not found', $ids[0]));
        }

        $routes = [];

        $this->addRoutesForPage($page, $routes);

        return $routes[$name];
    }

    public function getRoutesByNames($names): array
    {
        $this->framework->initialize(true);

        /** @var PageModel $pageModel */
        $pageModel = $this->framework->getAdapter(PageModel::class);

        if (null === $names) {
            $pages = $pageModel->findAll();
        } else {
            $ids = $this->getPageIdsFromNames($names);

            if (empty($ids)) {
                return [];
            }

            $pages = $pageModel->findBy('tl_page.id IN ('.implode(',', $ids).')', []);
        }

        if (!$pages instanceof Collection) {
            return [];
        }

        $routes = [];

        $this->addRoutesForPages($pages, $routes);
        $this->sortRoutes($routes);

        return $routes;
    }

    /**
     * @param iterable<PageModel> $pages
     */
    private function addRoutesForPages(iterable $pages, array &$routes, Request $request = null): void
    {
        foreach ($pages as $page) {
            $this->addRoutesForPage($page, $routes, $request);
        }
    }

    /**
     * @param array<PageModel> $pages
     */
    private function addRoutesForRootPages(array $pages, array &$routes): void
    {
        foreach ($pages as $page) {
            $this->addRoutesForRootPage($page, $routes);
        }
    }

    private function createCollectionForRoutes(array $routes, array $languages): RouteCollection
    {
        $this->sortRoutes($routes, $languages);

        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return $collection;
    }

    private function addRoutesForPage(PageModel $page, array &$routes, Request $request = null): void
    {
        try {
            $page->loadDetails();

            if (!$page->rootId) {
                return;
            }
        } catch (NoRootPageFoundException $e) {
            return;
        }

        $route = $this->routeFactory->createRoute($page);
        $routes['tl_page.'.$page->id] = $route;

        if ($route instanceof PageRoute) {
            $this->addLocaleRedirect($route, $request, $routes);
        }

        $this->addRoutesForRootPage($page, $routes);
    }

    private function addLocaleRedirect(PageRoute $route, ?Request $request, array &$routes): void
    {
        $length = \strlen($route->getUrlPrefix());

        if (0 === $length) {
            return;
        }

        $redirect = new Route(
            substr($route->getPath(), $length + 1),
            $route->getDefaults(),
            $route->getRequirements(),
            $route->getOptions(),
            $route->getHost(),
            $route->getSchemes(),
            $route->getMethods()
        );

        $path = $route->getPath();

        if (null !== $request) {
            $path = '/'.$route->getUrlPrefix().$request->getPathInfo();
        }

        $redirect->addDefaults([
            '_controller' => RedirectController::class.'::urlRedirectAction',
            'path' => $path,
            'permanent' => true,
        ]);

        $routes['tl_page.'.$route->getPageModel()->id.'.locale'] = $redirect;
    }

    private function addRoutesForRootPage(PageModel $page, array &$routes): void
    {
        if ('root' !== $page->type && 'index' !== $page->alias && '/' !== $page->alias) {
            return;
        }

        $page->loadDetails();
        $route = $this->routeFactory->createRoute($page);
        $urlPrefix = '';

        if ($route instanceof PageRoute) {
            $urlPrefix = $route->getUrlPrefix();
        }

        $routes['tl_page.'.$page->id.'.root'] = new Route(
            $urlPrefix ? '/'.$urlPrefix.'/' : '/',
            $route->getDefaults(),
            [],
            $route->getOptions(),
            $route->getHost(),
            $route->getSchemes(),
            $route->getMethods()
        );

        if (!$urlPrefix) {
            return;
        }

        $routes['tl_page.'.$page->id.'.fallback'] = new Route(
            '/',
            array_merge(
                $route->getDefaults(),
                [
                    '_controller' => 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction',
                    'path' => '/'.$urlPrefix.'/',
                    'permanent' => true,
                ]
            ),
            [],
            $route->getOptions(),
            $route->getHost(),
            $route->getSchemes(),
            $route->getMethods()
        );
    }

    /**
     * @return array<int>
     */
    private function getPageIdsFromNames(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            if (0 !== strncmp($name, 'tl_page.', 8)) {
                continue;
            }

            [, $id] = explode('.', $name);

            if (!is_numeric($id)) {
                continue;
            }

            $ids[] = (int) $id;
        }

        return array_unique($ids);
    }

    /**
     * Sorts routes so that the FinalMatcher will correctly resolve them.
     *
     * 1. The ones with hostname should come first, so the ones with empty host are only taken if no hostname matches
     * 2. Root pages come last, so non-root pages with index alias (= identical path) match first
     * 3. Root/Index pages must be sorted by accept language and fallback, so the best language matches first
     * 4. Pages with longer alias (folder page) must come first to match if applicable
     */
    private function sortRoutes(array &$routes, array $languages = null): void
    {
        // Convert languages array so key is language and value is priority
        if (null !== $languages) {
            foreach ($languages as &$language) {
                $language = str_replace('_', '-', $language);

                if (5 === \strlen($language)) {
                    $lng = substr($language, 0, 2);

                    // Append the language if only language plus dialect is given (see #430)
                    if (!\in_array($lng, $languages, true)) {
                        $languages[] = $lng;
                    }
                }
            }

            unset($language);

            $languages = array_flip(array_values($languages));
        }

        uasort(
            $routes,
            static function (Route $a, Route $b) use ($languages, $routes) {
                $nameA = array_search($a, $routes, true);
                $nameB = array_search($b, $routes, true);

                $fallbackA = 0 === substr_compare($nameA, '.fallback', -9);
                $fallbackB = 0 === substr_compare($nameB, '.fallback', -9);
                $localeA = 0 === substr_compare($nameA, '.locale', -7);
                $localeB = 0 === substr_compare($nameB, '.locale', -7);

                if ($fallbackA && !$fallbackB) {
                    return 1;
                }

                if ($fallbackB && !$fallbackA) {
                    return -1;
                }

                if ($localeA && !$localeB) {
                    return 1;
                }

                if ($localeB && !$localeA) {
                    return -1;
                }

                if ('' !== $a->getHost() && '' === $b->getHost()) {
                    return -1;
                }

                if ('' === $a->getHost() && '' !== $b->getHost()) {
                    return 1;
                }

                /** @var PageModel|null $pageA */
                $pageA = $a->getDefault('pageModel');

                /** @var PageModel|null $pageB */
                $pageB = $b->getDefault('pageModel');

                // Check if the page models are valid (should always be the case, as routes are generated from pages)
                if (!$pageA instanceof PageModel || !$pageB instanceof PageModel) {
                    return 0;
                }

                if (null !== $languages && $pageA->rootLanguage !== $pageB->rootLanguage) {
                    $langA = $languages[$pageA->rootLanguage] ?? null;
                    $langB = $languages[$pageB->rootLanguage] ?? null;

                    if (null === $langA && null === $langB) {
                        if ($pageA->rootIsFallback && !$pageB->rootIsFallback) {
                            return -1;
                        }

                        if ($pageB->rootIsFallback && !$pageA->rootIsFallback) {
                            return 1;
                        }

                        return $pageA->rootSorting <=> $pageB->rootSorting;
                    }

                    if (null === $langA && null !== $langB) {
                        return 1;
                    }

                    if (null !== $langA && null === $langB) {
                        return -1;
                    }

                    if ($langA < $langB) {
                        return -1;
                    }

                    if ($langA > $langB) {
                        return 1;
                    }
                }

                if ('root' !== $pageA->type && 'root' === $pageB->type) {
                    return -1;
                }

                if ('root' === $pageA->type && 'root' !== $pageB->type) {
                    return 1;
                }

                return strnatcasecmp((string) $pageB->alias, (string) $pageA->alias);
            }
        );
    }

    /**
     * @return array<Model>
     */
    private function findPages(array $candidates): array
    {
        $ids = [];
        $aliases = [];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $ids[] = (int) $candidate;
            } else {
                $aliases[] = $this->database->quote($candidate);
            }
        }

        $conditions = [];

        if (!empty($ids)) {
            $conditions[] = 'tl_page.id IN ('.implode(',', $ids).')';
        }

        if (!empty($aliases)) {
            $conditions[] = 'tl_page.alias IN ('.implode(',', $aliases).')';
        }

        /** @var PageModel $pageModel */
        $pageModel = $this->framework->getAdapter(PageModel::class);
        $pages = $pageModel->findBy([implode(' OR ', $conditions)], []);

        if (!$pages instanceof Collection) {
            return [];
        }

        return $pages->getModels();
    }

    /**
     * @return array<Model>
     */
    private function findRootPages(string $httpHost): array
    {
        if (
            !empty($GLOBALS['TL_HOOKS']['getRootPageFromUrl'])
            && \is_array($GLOBALS['TL_HOOKS']['getRootPageFromUrl'])
        ) {
            /** @var System $system */
            $system = $this->framework->getAdapter(System::class);

            foreach ($GLOBALS['TL_HOOKS']['getRootPageFromUrl'] as $callback) {
                $page = $system->importStatic($callback[0])->{$callback[1]}();

                if ($page instanceof PageModel) {
                    return [$page];
                }
            }
        }

        $rootPages = [];
        $indexPages = [];

        /** @var PageModel $pageModel */
        $pageModel = $this->framework->getAdapter(PageModel::class);
        $pages = $pageModel->findBy(["(tl_page.type='root' AND (tl_page.dns=? OR tl_page.dns=''))"], $httpHost);

        if ($pages instanceof Collection) {
            $rootPages = $pages->getModels();
        }

        $pages = $pageModel->findBy(["tl_page.alias='index' OR tl_page.alias='/'"], null);

        if ($pages instanceof Collection) {
            $indexPages = $pages->getModels();
        }

        return array_merge($rootPages, $indexPages);
    }
}
