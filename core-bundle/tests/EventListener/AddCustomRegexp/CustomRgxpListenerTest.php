<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\EventListener\AddCustomRegexp;

use Contao\CoreBundle\EventListener\AddCustomRegexp\CustomRgxpListener;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Widget;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomRgxpListenerTest extends TestCase
{
    public function testReturnsFalseIfNotCustomRgxpType(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans')
            ->willReturnArgument(0)
        ;

        $listener = new CustomRgxpListener($translator);

        $this->assertFalse($listener('foobar', 'input', $this->createMock(Widget::class)));
    }

    public function testReturnsTrueIfNoCustomRgxpSet(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans')
            ->willReturnArgument(0)
        ;

        $listener = new CustomRgxpListener($translator);

        $this->assertTrue($listener(CustomRgxpListener::RGXP_NAME, 'input', $this->createMock(Widget::class)));
    }

    public function testAddsErrorIfInputDoesNotMatchCustomRgxp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnArgument(0)
        ;

        /** @var Widget&MockObject $widget */
        $widget = $this->mockClassWithProperties(Widget::class, ['custom_rgxp' => '/^foo/i']);
        $widget
            ->expects($this->once())
            ->method('addError')
            ->with('ERR.customRgxp')
        ;

        $listener = new CustomRgxpListener($translator);

        $this->assertTrue($listener(CustomRgxpListener::RGXP_NAME, 'notfoo', $widget));
    }

    public function testDoesNotAddErrorIfInputMatchesCustomRgxp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans')
            ->willReturnArgument(0)
        ;

        /** @var Widget&MockObject $widget */
        $widget = $this->mockClassWithProperties(Widget::class, ['custom_rgxp' => '/^foo/i']);
        $widget
            ->expects($this->never())
            ->method('addError')
            ->with('ERR.customRgxp')
        ;

        $listener = new CustomRgxpListener($translator);

        $this->assertTrue($listener(CustomRgxpListener::RGXP_NAME, 'foobar', $widget));
    }
}
