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

use Contao\CoreBundle\Cache\ApplicationCacheState;
use Contao\CoreBundle\EventListener\BackendRebuildCacheMessageListener;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BackendRebuildCacheMessageListenerTest extends TestCase
{
    /**
     * @testWith [false, true]
     *           [true, false]
     *           [false, false]
     */
    public function testDoesNotAddMessageIfNotBackendRequestOrAppCacheIsNotDirty(bool $backendRequest, bool $dirty): void
    {
        $scopeMatcher = $this->createMock(ScopeMatcher::class);
        $scopeMatcher
            ->method('isBackendRequest')
            ->willReturn($backendRequest)
        ;

        $cacheState = $this->createMock(ApplicationCacheState::class);
        $cacheState
            ->method('isDirty')
            ->willReturn($dirty)
        ;

        $translator = $this->createMock(TranslatorInterface::class);

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->never())
            ->method('getSession')
        ;

        $listener = new BackendRebuildCacheMessageListener(
            $scopeMatcher,
            $cacheState,
            $translator
        );

        $event = new RequestEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $listener($event);
    }

    public function testAddsMessage(): void
    {
        $scopeMatcher = $this->createMock(ScopeMatcher::class);
        $scopeMatcher
            ->method('isBackendRequest')
            ->willReturn(true)
        ;

        $cacheState = $this->createMock(ApplicationCacheState::class);
        $cacheState
            ->method('isDirty')
            ->willReturn(true)
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->with('ERR.application_cache', [], 'contao_default')
            ->willReturn('message')
        ;

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag
            ->expects($this->once())
            ->method('add')
            ->with('contao.BE.info', 'message')
        ;

        $session = $this->createMock(Session::class);
        $session
            ->method('getFlashBag')
            ->willReturn($flashBag)
        ;

        $request = $this->createMock(Request::class);
        $request
            ->method('getSession')
            ->willReturn($session)
        ;

        $listener = new BackendRebuildCacheMessageListener(
            $scopeMatcher,
            $cacheState,
            $translator
        );

        $event = new RequestEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $listener($event);
    }
}
