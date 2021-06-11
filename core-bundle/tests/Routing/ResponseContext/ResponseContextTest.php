<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Routing\ResponseContext;

use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class ResponseContextTest extends TestCase
{
    public function testCanAddAndGetServices(): void
    {
        $context = new ResponseContext();

        $this->assertFalse($context->has(HtmlHeadBag::class));

        $context->add(new HtmlHeadBag());

        $this->assertTrue($context->has(HtmlHeadBag::class));
        $this->assertInstanceOf(HtmlHeadBag::class, $context->get(HtmlHeadBag::class));
    }

    public function testLazyServices(): void
    {
        $context = new ResponseContext();

        $this->assertFalse($context->has(HtmlHeadBag::class));

        $context->addLazy(HtmlHeadBag::class, static function () { return new HtmlHeadBag(); });

        $this->assertTrue($context->has(HtmlHeadBag::class));
        $this->assertInstanceOf(HtmlHeadBag::class, $context->get(HtmlHeadBag::class));
    }

    public function testGettingANonExistentServiceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $context = new ResponseContext();
        $context->get(HtmlHeadBag::class);
    }

    public function testInterfacesAndParentsAreAutomaticallyAddedAsAliases(): void
    {
        $context = new ResponseContext();

        // Using some anonymous classes here so we don't have to create nonsense classes implementing nonsense
        // interfaces here. We took the BundleInterface as that is very unlikely to change.
        $serviceA = new class() extends Bundle implements BundleInterface {
        };
        $serviceB = new class() extends Bundle implements BundleInterface {
        };

        $serviceAClassname = \get_class($serviceA);
        $serviceBClassname = \get_class($serviceB);

        $context->add($serviceA);

        $this->assertSame($serviceA, $context->get($serviceAClassname));
        $this->assertSame($serviceA, $context->get(BundleInterface::class));
        $this->assertSame($serviceA, $context->get(Bundle::class));

        $context->add($serviceB);

        $this->assertSame($serviceA, $context->get($serviceAClassname));
        $this->assertSame($serviceB, $context->get($serviceBClassname));
        $this->assertSame($serviceB, $context->get(BundleInterface::class)); // Service B was added later
        $this->assertSame($serviceB, $context->get(Bundle::class)); // Service B was added later
    }

    public function testHeaderBagIsInitializedCompletelyEmpty(): void
    {
        $context = new ResponseContext();

        $this->assertCount(0, $context->getHeaderBag()->all());
    }
}
