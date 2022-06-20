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

use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\InsertTagSubscription;
use Contao\CoreBundle\InsertTag\ParsedSequence;
use Contao\CoreBundle\InsertTag\ProcessingMode;
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AddInsertTagsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('contao.insert_tag.parser')) {
            return;
        }

        $serviceIds = $container->findTaggedServiceIds('contao.insert_tag');
        $definition = $container->findDefinition('contao.insert_tag.parser');
        $subscriptions = [];
        $priorities = [];

        foreach ($serviceIds as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                if (!preg_match('/^[a-z\x80-\xFF][a-z0-9_\x80-\xFF]*$/i', (string) ($attributes['name'] ?? ''))) {
                    throw new InvalidDefinitionException(sprintf('Invalid insert tag name "%s"', $attributes['name'] ?? ''));
                }

                $attributes['mode'] = unserialize($attributes['mode'], ['allowed_classes' => false]);

                $method = $this->getMethod($attributes['method'], $attributes['mode'], $container->findDefinition($serviceId)->getClass(), $serviceId);

                $priorities[] = $attributes['priority'];
                $subscriptions[] = new Definition(
                    InsertTagSubscription::class,
                    [
                        new Reference($serviceId),
                        $method,
                        $attributes['name'],
                        $attributes['endTag'],
                        $attributes['mode'],
                    ],
                );
            }
        }

        // Order by priorities
        array_multisort($priorities, $subscriptions);

        foreach ($subscriptions as $subscription) {
            $definition->addMethodCall('addSubscription', [$subscription]);
        }
    }

    private function getMethod(string|null $method, ProcessingMode $mode, string $class, string $serviceId): string
    {
        $ref = new \ReflectionClass($class);
        $invalid = sprintf('The contao.insert_tag definition for service "%s" is invalid. ', $serviceId);

        $method = $method ?: '__invoke';

        if (!$ref->hasMethod($method)) {
            $invalid .= sprintf('The class "%s" does not have a method "%s".', $class, $method);

            throw new InvalidDefinitionException($invalid);
        }

        $ref = $ref->getMethod($method);

        if (!$ref->isPublic()) {
            $invalid .= sprintf('The "%s::%s" method exists but is not public.', $class, $method);

            throw new InvalidDefinitionException($invalid);
        }

        $expectedReturnType = InsertTagResult::class;

        if (ProcessingMode::wrappedParsed === $mode || ProcessingMode::wrappedResolved === $mode) {
            $expectedReturnType = ParsedSequence::class;
        }

        if ($expectedReturnType !== (string) $ref->getReturnType()) {
            $invalid .= sprintf('The "%s::%s" method exists but has an invalid return type. Expected "%s", got "%s".', $class, $method, $expectedReturnType, (string) $ref->getReturnType());

            throw new InvalidDefinitionException($invalid);
        }

        // TODO: validate method signature?

        return $method;
    }
}
