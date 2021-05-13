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

use Contao\CoreBundle\DependencyInjection\Compiler\TwigPathsPass;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader as BaseFilesystemLoader;

class TwigPathsPassTest extends TestCase
{
    public function testRewiresAndAddsMethodCalls(): void
    {
        $container = new ContainerBuilder();

        $baseLoader = (new Definition(BaseFilesystemLoader::class))
            ->addMethodCall('addPath', ['path1', 'namespace1'])
            ->addMethodCall('addPath', ['path2', 'namespace2'])
            ->addMethodCall('foo')
        ;

        $loader = new Definition(ContaoFilesystemLoader::class);

        $container->addDefinitions([
            'twig.loader.native_filesystem' => $baseLoader,
            ContaoFilesystemLoader::class => $loader,
        ]);

        (new TwigPathsPass())->process($container);

        $this->assertFalse($baseLoader->hasMethodCall('addPath'));
        $this->assertTrue($baseLoader->hasMethodCall('foo'));

        $expectedLoaderCalls = [
            ['addPath', ['path1', 'namespace1']],
            ['addPath', ['path2', 'namespace2']],
        ];

        $this->assertSame($expectedLoaderCalls, $loader->getMethodCalls());
    }
}
