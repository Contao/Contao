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

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Content\ContentRoute;
use Contao\PageModel;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

@trigger_error('The Contao\CoreBundle\Routing\UrlGenerator is deprecated. Use the Symfony router instead.', E_USER_DEPRECATED);

/**
 * @deprecated The Contao\CoreBundle\Routing\UrlGenerator is deprecated. Use the Symfony router instead.
 */
class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var bool
     */
    private $legacyRouting;

    /**
     * @var bool
     */
    private $prependLocale;

    /**
     * @var string
     */
    private $urlSuffix;

    /**
     * @internal Do not inherit from this class; decorate the "contao.routing.url_generator" service instead
     */
    public function __construct(UrlGeneratorInterface $router, ContaoFramework $framework, bool $legacyRouting, bool $prependLocale, string $urlSuffix)
    {
        $this->router = $router;
        $this->framework = $framework;
        $this->legacyRouting = $legacyRouting;
        $this->prependLocale = $prependLocale;
        $this->urlSuffix = $urlSuffix;
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): ?string
    {
        $this->framework->initialize();

        if (!\is_array($parameters)) {
            $parameters = [];
        }

        $context = $this->getContext();

        // Store the original request context
        $host = $context->getHost();
        $scheme = $context->getScheme();
        $httpPort = $context->getHttpPort();
        $httpsPort = $context->getHttpsPort();

        $parameters[ContentRoute::ROUTE_OBJECT_PARAMETER] = $this->getRoute($name, $parameters);

        $this->prepareAlias($name, $parameters);
        $this->prepareDomain($context, $parameters, $referenceType);

        unset($parameters['auto_item']);

        $url = $this->router->generate(
            ContentRoute::ROUTE_NAME,
            $parameters,
            $referenceType
        );

        // Reset the request context
        $context->setHost($host);
        $context->setScheme($scheme);
        $context->setHttpPort($httpPort);
        $context->setHttpsPort($httpsPort);

        return $url;
    }

    /**
     * Adds the parameters to the alias.
     *
     * @throws MissingMandatoryParametersException
     */
    private function prepareAlias(string $alias, array &$parameters): void
    {
        if ('index' === $alias) {
            return;
        }

        $hasAutoItem = false;
        $autoItems = $this->getAutoItems($parameters);

        /** @var Config $config */
        $config = $this->framework->getAdapter(Config::class);

        $parameters['alias'] = preg_replace_callback(
            '/{([^}]+)}/',
            static function (array $matches) use ($alias, &$parameters, $autoItems, &$hasAutoItem, $config): string {
                $param = $matches[1];

                if (!isset($parameters[$param])) {
                    throw new MissingMandatoryParametersException(sprintf('Parameters "%s" is missing to generate a URL for "%s"', $param, $alias));
                }

                $value = $parameters[$param];
                unset($parameters[$param]);

                if ($hasAutoItem || !$config->get('useAutoItem') || !\in_array($param, $autoItems, true)) {
                    return $param.'/'.$value;
                }

                $hasAutoItem = true;

                return $value;
            },
            $alias
        );
    }

    /**
     * Forces the router to add the host if necessary.
     */
    private function prepareDomain(RequestContext $context, array &$parameters, int &$referenceType): void
    {
        if (isset($parameters['_ssl'])) {
            $context->setScheme(true === $parameters['_ssl'] ? 'https' : 'http');
        }

        if (isset($parameters['_domain']) && '' !== $parameters['_domain']) {
            $this->addHostToContext($context, $parameters, $referenceType);
        }

        unset($parameters['_domain'], $parameters['_ssl']);
    }

    /**
     * Sets the context from the domain.
     */
    private function addHostToContext(RequestContext $context, array $parameters, int &$referenceType): void
    {
        [$host, $port] = $this->getHostAndPort($parameters['_domain']);

        if ($context->getHost() === $host) {
            return;
        }

        $context->setHost($host);
        $referenceType = UrlGeneratorInterface::ABSOLUTE_URL;

        if (!$port) {
            return;
        }

        if (isset($parameters['_ssl']) && true === $parameters['_ssl']) {
            $context->setHttpsPort($port);
        } else {
            $context->setHttpPort($port);
        }
    }

    /**
     * Extracts host and port from the domain.
     *
     * @return array<(string|null)>
     */
    private function getHostAndPort(string $domain): array
    {
        if (false !== strpos($domain, ':')) {
            return explode(':', $domain, 2);
        }

        return [$domain, null];
    }

    /**
     * Returns the auto_item key from the parameters or the global array.
     *
     * @return array<string>
     */
    private function getAutoItems(array $parameters): array
    {
        if (isset($parameters['auto_item'])) {
            return [$parameters['auto_item']];
        }

        if (isset($GLOBALS['TL_AUTO_ITEM']) && \is_array($GLOBALS['TL_AUTO_ITEM'])) {
            return $GLOBALS['TL_AUTO_ITEM'];
        }

        return [];
    }

    private function getRoute(string $name, array &$parameters)
    {
        $urlPrefix = '';
        $requirements = [];

        if ($this->legacyRouting) {
            $urlSuffix = $this->urlSuffix;

            if ($this->prependLocale) {
                $urlPrefix = '/{_locale}';
                $requirements['_locale'] = '[a-z]{2}(\-[A-Z]{2})?';
            } else {
                unset($parameters['_locale']);
            }
        } else {
            $rootPage = $this->findRootPage($parameters);
            $urlSuffix = $rootPage->urlSuffix;

            if ($rootPage->urlPrefix) {
                $urlPrefix = '/'.$rootPage->urlPrefix;
            }

            unset($parameters['_locale']);
        }

        if ('index' === $name) {
            return new Route($urlPrefix.'/', [], $requirements);
        }

        $requirements['alias'] = '.+';

        return new Route($urlPrefix.'/{alias}'.$urlSuffix, [], $requirements);
    }

    private function findRootPage(array $parameters): PageModel
    {
        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $rootPage = $pageAdapter->findFirstPublishedRootByHostAndLanguage(
            $parameters['_domain'] ?? '',
            $parameters['_locale'] ?? ''
        );

        if (null === $rootPage) {
            throw new \RuntimeException(static::class.' requires a domain and locale or legacy routing. Configure "prepend_locale" or "url_suffix" in the Contao bundle.');
        }

        $rootPage->loadDetails();

        return $rootPage;
    }
}
