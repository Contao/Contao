<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\EventListener;

use Contao\ContentProxy;
use Contao\CoreBundle\EventListener\GlobalsMapListener;
use Contao\CoreBundle\Tests\TestCase;

class GlobalsMapListenerTest extends TestCase
{
    /**
     * @dataProvider getValuesData
     */
    public function testMergesTheValuesIntoTheGlobalsArray(array $existing, array $new, array $expected, array $forced = []): void
    {
        $GLOBALS['TL_CTE'] = $existing;

        $listener = new GlobalsMapListener(['TL_CTE' => $new], ['TL_CTE' => $forced]);
        $listener->onInitializeSystem();

        $this->assertSame($expected, $GLOBALS['TL_CTE']);

        unset($GLOBALS['TL_CTE']);
    }

    public static function getValuesData(): iterable
    {
        yield 'add single' => [
            [],
            ['text' => 'HeadlineFragment'],
            ['text' => 'HeadlineFragment'],
        ];

        yield 'add single forced' => [
            [],
            [],
            ['text' => 'HeadlineFragment'],
            ['text' => 'HeadlineFragment'],
        ];

        yield 'add group' => [
            [],
            ['texts' => ['headline' => 'HeadlineFragment']],
            ['texts' => ['headline' => 'HeadlineFragment']],
        ];

        yield 'add group forced' => [
            [],
            [],
            ['texts' => ['headline' => 'HeadlineFragment']],
            ['texts' => ['headline' => 'HeadlineFragment']],
        ];

        yield 'add to existing group' => [
            ['texts' => ['text' => 'LegacyText']],
            ['texts' => ['headline' => 'HeadlineFragment']],
            ['texts' => ['text' => 'LegacyText', 'headline' => 'HeadlineFragment']],
        ];

        yield 'prefer existing entries' => [
            ['texts' => ['headline' => 'LegacyHeadline']],
            ['texts' => ['headline' => 'HeadlineFragment']],
            ['texts' => ['headline' => 'LegacyHeadline']],
        ];

        yield 'prefer forced entries over existing entries' => [
            ['texts' => ['headline' => 'LegacyHeadline']],
            [],
            ['texts' => ['headline' => ContentProxy::class]],
            ['texts' => ['headline' => ContentProxy::class]],
        ];
    }
}
