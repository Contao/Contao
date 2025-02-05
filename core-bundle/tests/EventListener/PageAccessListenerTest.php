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

use Contao\CoreBundle\EventListener\PageAccessListener;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\InsufficientAuthenticationException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Tests\TestCase;
use Contao\PageModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class PageAccessListenerTest extends TestCase
{
    public function testDoesNothingWithoutPageModelInRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->never())
            ->method('isGranted')
        ;

        $request = new Request();

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertFalse($request->attributes->has('pageModel'));
    }

    public function testSetsPageModelFromGlobalWithIdInRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->never())
            ->method('isGranted')
        ;

        $request = new Request();
        $request->attributes->set('pageModel', 42);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $GLOBALS['objPage'] = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($GLOBALS['objPage'], $request->attributes->get('pageModel'));

        unset($GLOBALS['objPage']);
    }

    public function testSetsPageModelFromModelWithIdInRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->never())
            ->method('isGranted')
        ;

        $request = new Request();
        $request->attributes->set('pageModel', 42);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $listener = new PageAccessListener($this->mockFramework(42, $pageModel), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testSetsPageModelFromGlobalWithModelInRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->never())
            ->method('isGranted')
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $GLOBALS['objPage'] = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertNotSame($pageModel, $request->attributes->get('pageModel'));
        $this->assertSame($GLOBALS['objPage'], $request->attributes->get('pageModel'));

        unset($GLOBALS['objPage']);
    }

    public function testSetsPageModelFromModelInRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->never())
            ->method('isGranted')
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, ['id' => 42]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testDeniesAccessToProtectedPageIfMemberIsNotLoggedIn(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_MEMBER')
            ->willReturn(false)
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'id' => 42,
            'protected' => true,
            'groups' => [1, 2, 3],
        ]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $this->expectException(InsufficientAuthenticationException::class);
        $this->expectExceptionMessage('Not authenticated');

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testDeniesAccessToProtectedPageIfMemberIsNotInGroup(): void
    {
        $security = $this->createMock(Security::class);
        $matcher = $this->exactly(2);
        $security
            ->expects($matcher)
            ->method('isGranted')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if (1 === $matcher->numberOfInvocations()) {
                        $this->assertSame('ROLE_MEMBER', $parameters[0]);
                    }
                    if (2 === $matcher->numberOfInvocations()) {
                        $this->assertSame(ContaoCorePermissions::MEMBER_IN_GROUPS, $parameters[0]);
                        $this->assertSame([1, 2, 3], $parameters[1]);
                    }

                    return true;
                },
            )
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'id' => 42,
            'protected' => true,
            'groups' => [1, 2, 3],
        ]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Member does not have access to page ID 42');

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testDeniesAccessToProtectedPageIfMemberIsNotGuest(): void
    {
        $security = $this->createMock(Security::class);
        $matcher = $this->exactly(2);
        $security
            ->expects($matcher)
            ->method('isGranted')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if (1 === $matcher->numberOfInvocations()) {
                        $this->assertSame('ROLE_MEMBER', $parameters[0]);
                    }
                    if (2 === $matcher->numberOfInvocations()) {
                        $this->assertSame(ContaoCorePermissions::MEMBER_IN_GROUPS, $parameters[0]);
                        $this->assertSame([-1, 1], $parameters[1]);
                    }

                    return false;
                },
            )
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'id' => 42,
            'protected' => true,
            'groups' => [-1, 1],
        ]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Member does not have access to page ID 42');

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testGrantsAccessToProtectedPageIfMemberIsInGroup(): void
    {
        $security = $this->createMock(Security::class);
        $matcher = $this->exactly(2);
        $security
            ->expects($matcher)
            ->method('isGranted')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if (1 === $matcher->numberOfInvocations()) {
                        $this->assertSame('ROLE_MEMBER', $parameters[0]);
                    }
                    if (2 === $matcher->numberOfInvocations()) {
                        $this->assertSame(ContaoCorePermissions::MEMBER_IN_GROUPS, $parameters[0]);
                        $this->assertSame([1, 2, 3], $parameters[1]);
                    }

                    return true;
                },
            )
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'id' => 42,
            'protected' => true,
            'groups' => [1, 2, 3],
        ]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    public function testGrantsAccessToProtectedPageIfIsGuest(): void
    {
        $security = $this->createMock(Security::class);
        $matcher = $this->exactly(2);
        $security
            ->expects($matcher)
            ->method('isGranted')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if (1 === $matcher->numberOfInvocations()) {
                        $this->assertSame('ROLE_MEMBER', $parameters[0]);
                    }
                    if (2 === $matcher->numberOfInvocations()) {
                        $this->assertSame(ContaoCorePermissions::MEMBER_IN_GROUPS, $parameters[0]);
                        $this->assertSame([-1, 1], $parameters[1]);
                    }

                    return false;
                },
            )
        ;

        $pageModel = $this->mockClassWithProperties(PageModel::class, [
            'id' => 42,
            'protected' => true,
            'groups' => [-1, 1],
        ]);

        $request = new Request();
        $request->attributes->set('pageModel', $pageModel);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request)
        ;

        $listener = new PageAccessListener($this->mockFramework(), $security);
        $listener($event);

        $this->assertSame($pageModel, $request->attributes->get('pageModel'));
    }

    private function mockFramework(int|null $id = null, PageModel|null $pageModel = null): ContaoFramework&MockObject
    {
        $framework = $this->createMock(ContaoFramework::class);

        if (null === $id) {
            $framework
                ->expects($this->never())
                ->method('initialize')
            ;

            $framework
                ->expects($this->never())
                ->method('getAdapter')
            ;

            return $framework;
        }

        $pageAdapter = $this->mockAdapter(['findById']);
        $pageAdapter
            ->expects($this->once())
            ->method('findById')
            ->willReturn($pageModel)
        ;

        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        $framework
            ->expects($this->once())
            ->method('getAdapter')
            ->willReturn($pageAdapter)
        ;

        return $framework;
    }
}
