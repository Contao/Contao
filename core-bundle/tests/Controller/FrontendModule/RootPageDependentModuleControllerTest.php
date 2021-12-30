<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\CoreBundle\Controller\FrontendModule\RootPageDependentModuleController;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class RootPageDependentModuleControllerTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->getContainerWithContaoConfiguration();
        $this->container->set('contao.cache.entity_tags', $this->createMock(EntityCacheTags::class));

        System::setContainer($this->container);
    }

    public function testCreatesTheTemplateFromTheClassName(): void
    {
        $controller = new RootPageDependentModuleController();
        $controller->setContainer($this->mockContainerWithFrameworkTemplate());

        $controller(new Request([], [], ['_scope' => 'frontend']), $this->getModuleModel(), 'main');
    }

    public function testPopulatesTheTemplateWithTheModule(): void
    {
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->rootId = '1';

        $module = $this->mockClassWithProperties(ModuleModel::class);
        $module->rootPageDependentModules = serialize([1 => '10']);

        $request = new Request([], [], ['_scope' => 'frontend', 'pageModel' => $page]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controller = new RootPageDependentModuleController();
        $controller->setContainer($this->mockContainerWithFrameworkTemplate($requestStack));

        $controller($request, $module, 'main');
    }

    private function mockContainerWithFrameworkTemplate(RequestStack $requestStack = null): ContainerBuilder
    {
        $template = $this->createMock(FrontendTemplate::class);
        $template
            ->expects($this->atLeast(1))
            ->method('getResponse')
            ->willReturn(new Response())
        ;

        $controllerAdapter = $this->mockAdapter(['getFrontendModule']);
        $controllerAdapter
            ->method('getFrontendModule')
            ->willReturn('')
        ;

        $framework = $this->mockContaoFramework([Controller::class => $controllerAdapter]);
        $framework
            ->expects($this->once())
            ->method('createInstance')
            ->with(FrontendTemplate::class, ['mod_root_page_dependent_module'])
            ->willReturn($template)
        ;

        $this->container->set('contao.framework', $framework);
        $this->container->set('contao.routing.scope_matcher', $this->mockScopeMatcher());

        if ($requestStack instanceof RequestStack) {
            $this->container->set('request_stack', $requestStack);
        }

        return $this->container;
    }

    /**
     * @return ModuleModel&MockObject
     */
    private function getModuleModel(): ModuleModel
    {
        return $this->mockClassWithProperties(ModuleModel::class);
    }
}
