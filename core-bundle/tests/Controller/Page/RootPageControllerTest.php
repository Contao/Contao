<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Controller\Page;

use Contao\CoreBundle\Controller\Page\RootPageController;
use Contao\CoreBundle\Exception\NoActivePageFoundException;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Tests\TestCase;
use Contao\PageModel;
use Contao\System;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RootPageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        System::setContainer($this->getContainerWithContaoConfiguration());
    }

    protected function tearDown(): void
    {
        $this->resetStaticProperties([System::class]);

        parent::tearDown();
    }

    public function testRedirectsToTheFirstChildPage(): void
    {
        $rootPage = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $childPage = $this->mockClassWithProperties(PageModel::class);

        $adapter = $this->mockAdapter(['findFirstPublishedByPid']);
        $adapter
            ->expects($this->once())
            ->method('findFirstPublishedByPid')
            ->with(42)
            ->willReturn($childPage)
        ;

        $urlGenerator = $this->createMock(ContentUrlGenerator::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($childPage, [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/foobar')
        ;

        $container = $this->getContainerWithContaoConfiguration();
        $container->set('contao.framework', $this->mockContaoFramework([PageModel::class => $adapter]));
        $container->set('contao.routing.content_url_generator', $urlGenerator);

        $controller = new RootPageController(new NullLogger());
        $controller->setContainer($container);

        $response = $controller($rootPage);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.com/foobar', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testThrowsExceptionIfNoRedirectPageIsFound(): void
    {
        $rootPage = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $adapter = $this->mockAdapter(['findFirstPublishedByPid']);
        $adapter
            ->expects($this->once())
            ->method('findFirstPublishedByPid')
            ->with(42)
            ->willReturn(null)
        ;

        $container = $this->getContainerWithContaoConfiguration();
        $container->set('contao.framework', $this->mockContaoFramework([PageModel::class => $adapter]));

        $controller = new RootPageController(new NullLogger());
        $controller->setContainer($container);

        $this->expectException(NoActivePageFoundException::class);
        $this->expectExceptionMessage('No active page found under root page.');

        $controller($rootPage);
    }
}
