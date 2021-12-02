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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes services public that we need to retrieve directly.
 *
 * @internal
 */
class MakeServicesPublicPass implements CompilerPassInterface
{
    private const IDS = [
        'assets.packages',
        'database_connection',
        'debug.stopwatch',
        'fragment.handler',
        'lexik_maintenance.driver.factory',
        'monolog.logger.contao',
        'security.authentication.trust_resolver',
        'security.encoder_factory',
        'security.firewall.map',
        'security.helper',
        'security.logout_url_generator',
        'swiftmailer.mailer',
        'uri_signer',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::IDS as $id) {
            if ($container->hasAlias($id)) {
                $alias = $container->getAlias($id);
                $alias->setPublic(true);
            }

            if ($container->hasDefinition($id)) {
                $definition = $container->getDefinition($id);
                $definition->setPublic(true);
            }
        }
    }
}
