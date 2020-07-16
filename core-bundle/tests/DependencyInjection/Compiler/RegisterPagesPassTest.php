<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\DependencyInjection\Compiler;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Controller\FrontendModule\TwoFactorController;
use Contao\CoreBundle\Controller\Page\RootPageController;
use Contao\CoreBundle\DependencyInjection\Compiler\RegisterPagesPass;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FrontendIndex;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterPagesPassTest extends TestCase
{
    public function testDoesNothingIfContainerDoesNotHavePageRegistry(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container
            ->expects($this->once())
            ->method('has')
            ->with(PageRegistry::class)
            ->willReturn(false)
        ;

        $container
            ->expects($this->never())
            ->method('findDefinition')
        ;

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testGetsPageTypeFromAttributes(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    static function ($arguments) {
                        return 'my_type' === $arguments[0];
                    }
                )
            )
        ;

        $definition = new Definition(AbstractController::class);
        $definition->addTag('contao.page', ['type' => 'my_type']);

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testGetsPageTypeFromClass(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    static function ($arguments) {
                        return 'contao_core_bundle' === $arguments[0];
                    }
                )
            )
        ;

        $definition = new Definition(ContaoCoreBundle::class);
        $definition->addTag('contao.page');

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testStripsControllerSuffixOnGetPageTypeFromClass(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    static function ($arguments) {
                        return 'two_factor' === $arguments[0];
                    }
                )
            )
        ;

        $definition = new Definition(TwoFactorController::class);
        $definition->addTag('contao.page');

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testStripsPageControllerSuffixOnGetPageTypeFromClass(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    static function ($arguments) {
                        return 'root' === $arguments[0];
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page');

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testSetsControllerFromArguments(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Definition $definition */
                        $definition = $arguments[1];
                        $this->assertInstanceOf(Definition::class, $definition);

                        return 'MyController::action' === $definition->getArgument(3)['_controller'];
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page', ['defaults' => ['_controller' => 'MyController::action']]);

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testSetsControllerMethodAndMakesServicePublic(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Definition $definition */
                        $definition = $arguments[1];
                        $this->assertInstanceOf(Definition::class, $definition);

                        return 'test.controller:action' === $definition->getArgument(3)['_controller'];
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page', ['method' => 'action']);
        $definition->setPublic(false);

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);

        $this->assertTrue($definition->isPublic());
    }

    public function testSetsControllerInvokeMethodAndMakesServicePublic(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Definition $definition */
                        $definition = $arguments[1];
                        $this->assertInstanceOf(Definition::class, $definition);

                        return 'test.controller' === $definition->getArgument(3)['_controller'];
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page');
        $definition->setPublic(false);

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);

        $this->assertTrue($definition->isPublic());
    }

    public function testUsesFrontendIndexControllerIfClassIsNotCallable(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Definition $definition */
                        $definition = $arguments[1];
                        $this->assertInstanceOf(Definition::class, $definition);

                        return FrontendIndex::class.'::renderPage' === $definition->getArgument(3)['_controller'];
                    }
                )
            )
        ;

        $definition = new Definition(ContaoCoreBundle::class);
        $definition->addTag('contao.page');
        $definition->setPublic(false);

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);

        $this->assertFalse($definition->isPublic());
    }

    public function testRegistersPageRouteEnhancer(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Reference $reference */
                        $reference = $arguments[2];
                        $this->assertInstanceOf(Reference::class, $reference);

                        return 'test.controller' === (string) $reference;
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page');

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }

    public function testRegistersCompositionAware(): void
    {
        $registry = $this->createMock(Definition::class);
        $registry
            ->expects($this->once())
            ->method('addMethodCall')
            ->with(
                'add',
                $this->callback(
                    function ($arguments) {
                        /** @var Reference $reference */
                        $reference = $arguments[3];
                        $this->assertInstanceOf(Reference::class, $reference);

                        return 'test.controller' === (string) $reference;
                    }
                )
            )
        ;

        $definition = new Definition(RootPageController::class);
        $definition->addTag('contao.page');

        $container = new ContainerBuilder();
        $container->setDefinition(PageRegistry::class, $registry);
        $container->setDefinition('test.controller', $definition);

        $pass = new RegisterPagesPass();
        $pass->process($container);
    }
}
