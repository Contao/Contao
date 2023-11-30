<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Dca\Definition\Builder;

class CallbackDefinition extends DcaArrayNodeDefinition implements PreconfiguredDefinitionInterface
{
    public function preconfigure(): void
    {
        $this
            ->beforeNormalization()
            ->always(
                static function ($value) {
                    if ($value instanceof \Closure) {
                        return [$value];
                    }

                    return $value;
                },
            )
        ;

        $this
            ->getNodeBuilder()
                ->variableNode('0')->end()
                ->scalarNode('1')->end()
        ;
    }
}
