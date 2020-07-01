<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\DependencyInjection\Compiler;

use Contao\CoreBundle\Mailer\AvailableTransports;
use Contao\CoreBundle\Mailer\TransportConfig;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class AddAvailableTransportsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(AvailableTransports::class)) {
            return;
        }

        $contaoConfig = array_merge(...$container->getExtensionConfig('contao'));
        $contaoMailerConfig = [];

        if (isset($contaoConfig['mailer'], $contaoConfig['mailer']['transports'])) {
            $contaoMailerConfig = $contaoConfig['mailer']['transports'];
        }

        if (empty($contaoMailerConfig)) {
            return;
        }

        $frameworkConfig = $container->getExtensionConfig('framework');
        $definition = $container->findDefinition(AvailableTransports::class);

        foreach ($frameworkConfig as $v) {
            if (!isset($v['mailer']) || !isset($v['mailer']['transports'])) {
                continue;
            }

            foreach (array_keys($v['mailer']['transports']) as $transportName) {
                if (!\array_key_exists($transportName, $contaoMailerConfig)) {
                    continue;
                }

                $from = $contaoMailerConfig[$transportName]['from'] ?? null;

                $definition->addMethodCall(
                    'addTransport',
                    [
                        new Definition(TransportConfig::class, [$transportName, $from]),
                    ]
                );
            }
        }
    }
}
