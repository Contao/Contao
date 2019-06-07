<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\EventListener\HttpCache;

use Contao\CoreBundle\EventListener\HttpCache\StripCookiesSubscriber;
use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\CacheInvalidation;
use FOS\HttpCache\SymfonyCache\Events;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StripCookiesSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $subscriber = new StripCookiesSubscriber();

        $this->assertSame([Events::PRE_HANDLE => 'preHandle'], $subscriber::getSubscribedEvents());
    }

    public function testCookiesAreIgnoredIfMethodNotCacheable(): void
    {
        $cookies = ['csrf_cookie' => 'super-secret-token'];
        $request = Request::create('/', 'POST', [], $cookies);
        $event = new CacheEvent($this->createMock(CacheInvalidation::class), $request);

        // Defined with a whitelist, meaning that it would get removed if it was a cacheable request
        $subscriber = new StripCookiesSubscriber(['PHPSESSID']);
        $subscriber->preHandle($event);

        $this->assertSame($cookies, $request->cookies->all());
    }

    /**
     * @dataProvider cookiesProvider
     */
    public function testCookiesAreStrippedCorrectly(array $cookies, array $expectedCookies, array $whitelist = []): void
    {
        $request = Request::create('/', 'GET', [], $cookies);
        $event = new CacheEvent($this->createMock(CacheInvalidation::class), $request);

        $subscriber = new StripCookiesSubscriber($whitelist);
        $subscriber->preHandle($event);

        $this->assertSame($expectedCookies, $request->cookies->all());
    }

    public function cookiesProvider(): \Generator
    {
        yield [
            ['PHPSESSID' => 'foobar', 'my_cookie' => 'value'],
            ['PHPSESSID' => 'foobar', 'my_cookie' => 'value'],
        ];

        yield [
            ['PHPSESSID' => 'foobar', '_ga' => 'value', '_pk_ref' => 'value', '_pk_hsr' => 'value'],
            ['PHPSESSID' => 'foobar'],
        ];

        yield [
            ['PHPSESSID' => 'foobar', '_gac_58168352' => 'value', 'myModal1' => 'closed'],
            ['PHPSESSID' => 'foobar'],
        ];

        yield [
            ['PHPSESSID' => 'foobar', '_gac_58168352' => 'value'],
            ['PHPSESSID' => 'foobar'],
            ['PHPSESSID'],
        ];
    }
}
