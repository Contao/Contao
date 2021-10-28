<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Interop;

use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Interop\ContextFactory;
use Contao\Template;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

class ContextFactoryTest extends TestCase
{
    public function testCreateContextFromTemplate(): void
    {
        $object = new \stdClass();
        $object->x = 'y';

        $data = [
            'foo' => 'bar',
            'a' => [1, 2],
            'o' => $object,
            'lazy1' => static function (): string {
                return 'evaluated';
            },
            'lazy2' => static function (int $n = 0): string {
                return "evaluated: $n";
            },
            'lazy3' => static function (): array {
                return [1, 2];
            },
            'lazy4' => \Closure::fromCallable(
                static function (): string {
                    return 'evaluated Closure';
                }
            ),
            'value' => 'strtolower', // do not confuse with callable
        ];

        $template = $this->createMock(Template::class);
        $template
            ->method('getData')
            ->willReturn($data)
        ;

        $content =
            <<<'TEMPLATE'

                foo:   {{ foo }}
                a:     {{ a|join('|') }}
                o:     {{ o.x }}
                lazy1: {{ lazy1 }}
                lazy2: {{ lazy2 }}, {{ lazy2.invoke(5) }}
                lazy3: {{ lazy3.invoke()|join('|') }}
                lazy4: {{ lazy4 }}
                value: {{ value }}

                TEMPLATE;

        $expectedOutput =
            <<<'OUTPUT'

                foo:   bar
                a:     1|2
                o:     y
                lazy1: evaluated
                lazy2: evaluated: 0, evaluated: 5
                lazy3: 1|2
                lazy4: evaluated Closure
                value: strtolower

                OUTPUT;

        $output = (new Environment(new ArrayLoader(['test.html.twig' => $content])))->render(
            'test.html.twig',
            (new ContextFactory())->fromContaoTemplate($template)
        );

        $this->assertSame($expectedOutput, $output);
    }

    public function testCreateContextFromClass(): void
    {
        $object = new class() {
            private const PRIVATE_CONSTANT = 1;
            protected const PROTECTED_CONSTANT = 2;
            public const PUBLIC_CONSTANT = 3;

            private $privateField = 'a';
            protected $protectedField = 'b';
            public $publicField = 'c';

            private static $privateStaticField = 'A';
            protected static $protectedStaticField = 'B';
            public static $publicStaticField = 'C';

            private function privateDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            protected function protectedDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            public function publicDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            private static function privateStaticDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            protected static function protectedStaticDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            public static function publicStaticDo(string $x = ''): string
            {
                return __FUNCTION__.$x;
            }

            public function __construct()
            {
                /** @phpstan-ignore-next-line */
                $this->dynamic = 'd';
            }

            public function __foo(): void
            {
                // should be ignored
            }
        };

        $context = (new ContextFactory())->fromClass($object);

        $expectedFields = [
            'PRIVATE_CONSTANT' => 1,
            'PROTECTED_CONSTANT' => 2,
            'PUBLIC_CONSTANT' => 3,
            'privateField' => 'a',
            'protectedField' => 'b',
            'publicField' => 'c',
            'privateStaticField' => 'A',
            'protectedStaticField' => 'B',
            'publicStaticField' => 'C',
            'dynamic' => 'd',
        ];

        $expectedFunctions = [
            'privateDo',
            'protectedDo',
            'publicDo',
            'privateStaticDo',
            'protectedStaticDo',
            'publicStaticDo',
        ];

        $this->assertCount(\count($expectedFields) + \count($expectedFunctions) + 1, $context);

        $this->assertArrayHasKey('data', $context);
        $this->assertSame($object, $context['data']);

        foreach ($expectedFields as $field => $value) {
            $this->assertArrayHasKey($field, $context);
            $this->assertSame($context[$field], $value);
        }

        foreach ($expectedFunctions as $function) {
            $this->assertArrayHasKey($function, $context);
            $this->assertSame($function, $context[$function](), 'function call without parameters');
            $this->assertSame("{$function}foo", $context[$function]('foo'), 'function call with parameters');
        }
    }

    public function testEnhancesErrorMessageInCallableWrappersIfStringAccessFails(): void
    {
        if (\PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('This test requires at least PHP 7.4.');
        }

        $data = [
            'lazy' => static function (): object {
                return new \stdClass();
            },
        ];

        $template = $this->createMock(Template::class);
        $template
            ->method('getData')
            ->willReturn($data)
        ;

        $content = '{{ lazy }}';
        $environment = (new Environment(new ArrayLoader(['test.html.twig' => $content])));
        $context = (new ContextFactory())->fromContaoTemplate($template);

        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage(
            'An exception has been thrown during the rendering of a template ("'.
            'Error evaluating \'lazy\': Object of class stdClass could not be converted to string'.
            '") in "test.html.twig" at line 1.'
        );

        $environment->render('test.html.twig', $context);
    }
}
