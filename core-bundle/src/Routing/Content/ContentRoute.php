<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Content;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\PageModel;
use Symfony\Component\Routing\Route;

class ContentRoute extends Route
{
    public const ROUTE_NAME = 'contao_routing_object';
    public const ROUTE_NAME_PARAMETER = '_route';
    public const ROUTE_OBJECT_PARAMETER = '_route_object';
    public const CONTENT_PARAMETER = '_content';

    /**
     * @var PageModel
     */
    private $page;

    /**
     * @var string
     */
    private $urlPrefix;

    /**
     * @var string
     */
    private $urlSuffix;

    /**
     * The referenced content object.
     */
    private $content;

    public function __construct(PageModel $page, $content = null)
    {
        $page->loadDetails();

        $defaults = [
            '_token_check' => true,
            '_controller' => 'Contao\FrontendIndex::renderPage',
            '_scope' => ContaoCoreBundle::SCOPE_FRONTEND,
            '_locale' => $page->rootLanguage,
            '_format' => 'html',
            'pageModel' => $page,
        ];

        parent::__construct(
            '/'.$page->alias,
            $defaults,
            [],
            ['utf8' => true],
            $page->domain,
            $page->rootUseSSL ? 'https' : null
        );

        $this->page = $page;
        $this->urlPrefix = $page->urlPrefix;
        $this->urlSuffix = $page->urlSuffix;
        $this->content = $content;
    }

    public function getPage(): PageModel
    {
        return $this->page;
    }

    public function getPath(): string
    {
        $path = parent::getPath();

        if ('' !== $this->getUrlPrefix()) {
            $path = '/'.$this->getUrlPrefix().$path;
        }

        return $path.$this->getUrlSuffix();
    }

    public function getUrlPrefix(): string
    {
        return $this->urlPrefix;
    }

    public function setUrlPrefix(string $urlPrefix): self
    {
        $this->urlPrefix = $urlPrefix;

        return $this;
    }

    public function getUrlSuffix(): string
    {
        return $this->urlSuffix;
    }

    public function setUrlSuffix(string $urlSuffix): self
    {
        $this->urlSuffix = $urlSuffix;

        return $this;
    }

    /**
     * Set the object this url points to.
     *
     * @param mixed $object
     */
    public function setContent($object): self
    {
        $this->content = $object;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    public static function createWithParameters(PageModel $page, string $parameters = '', $content = null): ContentRoute
    {
        $route = new self($page, $content);

        $route->setPath(sprintf('/%s{parameters}', $page->alias));
        $route->setDefault('parameters', $parameters);
        $route->setRequirement('parameters', $page->requireItem ? '/.+' : '(/.+)?');

        return $route;
    }
}
