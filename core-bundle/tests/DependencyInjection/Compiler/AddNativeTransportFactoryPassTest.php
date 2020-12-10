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

use Contao\CoreBundle\DependencyInjection\Compiler\AddNativeTransportFactoryPass;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mailer\Transport\NativeTransportFactory;

class AddNativeTransportFactoryPassTest extends TestCase
{
    public function testAddsTheNativeTransportFactoryIfSupported(): void
    {
        $container = new ContainerBuilder();

        $pass = new AddNativeTransportFactoryPass();
        $pass->process($container);

        if ($this->supportsNativeMailer()) {
            $this->assertTrue($container->hasDefinition('mailer.transport_factory.native'));

            /** @var ChildDefinition $definition */
            $definition = $container->getDefinition('mailer.transport_factory.native');

            $this->assertTrue($definition->hasTag('mailer.transport_factory'));
            $this->assertSame('mailer.transport_factory.abstract', $definition->getParent());
        } else {
            $this->assertFalse($container->hasDefinition('mailer.transport_factory.native'));
        }
    }

    private function supportsNativeMailer(): bool
    {
        return class_exists(NativeTransportFactory::class);
    }
}
