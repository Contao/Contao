<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Mailer;

use Contao\CoreBundle\Mailer\AvailableTransports;
use Contao\CoreBundle\Mailer\TransportConfig;
use Contao\CoreBundle\Tests\TestCase;

class AvailableTransportsTest extends TestCase
{
    public function testAddsTransports(): void
    {
        $availableTransports = new AvailableTransports();

        $availableTransports->addTransport(new TransportConfig('foobar'));
        $availableTransports->addTransport(new TransportConfig('lorem', 'Lorem Ipsum <lorem.ipsum@example.org'));

        $this->assertSame([
            'foobar' => 'foobar',
            'lorem' => 'lorem',
        ], $availableTransports->getTransportOptions());

        $this->assertCount(2, $availableTransports->getTransports());
        $this->assertNotNull($availableTransports->getTransport('foobar'));
        $this->assertNotNull($availableTransports->getTransport('lorem'));
        $this->assertSame('foobar', $availableTransports->getTransport('foobar')->getName());
        $this->assertNull($availableTransports->getTransport('foobar')->getFrom());
        $this->assertSame('lorem', $availableTransports->getTransport('lorem')->getName());
        $this->assertSame('Lorem Ipsum <lorem.ipsum@example.org', $availableTransports->getTransport('lorem')->getFrom());
    }
}
