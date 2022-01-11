<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\EventListener\DataContainer;

use Contao\CoreBundle\EventListener\DataContainer\RootPageDependentSelectListener;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RootPageDependentSelectListenerTest extends ContaoTestCase
{
    private ContainerBuilder $container;

    public function testReturnsIfTheRequestIsNotABackendRequest(): void
    {
        $request = new Request([], [], ['_scope' => 'frontend']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new RootPageDependentSelectListener(
            $this->createMock(Connection::class),
            $this->createMock(TranslatorInterface::class),
            $this->mockScopeMatcher(false, $request),
            $requestStack,
            $this->createMock(CsrfTokenManagerInterface::class),
            'contao_csrf_token'
        );

        $listener->onLoadDataContainer('tl_module');
    }

    public function testReturnsIfThereAreNoFields(): void
    {
        $request = new Request([], [], ['_scope' => 'backend']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new RootPageDependentSelectListener(
            $this->createMock(Connection::class),
            $this->createMock(TranslatorInterface::class),
            $this->mockScopeMatcher(true, $request),
            $requestStack,
            $this->createMock(CsrfTokenManagerInterface::class),
            'contao_csrf_token'
        );

        $this->populateGlobalsArray([]);

        $listener->onLoadDataContainer('tl_module');

        $this->unsetGlobalsArray();
    }

    public function testOnlyModifyFieldsWithProperInputType(): void
    {
        $request = new Request([], [], ['_scope' => 'backend']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new RootPageDependentSelectListener(
            $this->createMock(Connection::class),
            $this->createMock(TranslatorInterface::class),
            $this->mockScopeMatcher(true, $request),
            $requestStack,
            $this->createMock(CsrfTokenManagerInterface::class),
            'contao_csrf_token'
        );

        $this->populateGlobalsArray([
            'field1' => [
                'inputType' => 'rootPageDependentSelect',
            ],
            'field2' => [
                'eval' => [],
            ],
            'field3' => [
                'inputType' => 'text',
            ],
        ]);

        $listener->onLoadDataContainer('tl_module');

        $this->assertSame([
            'field1' => [
                'inputType' => 'rootPageDependentSelect',
                'eval' => [
                    'rootPages' => [],
                    'blankOptionLabel' => null,
                ],
            ],
            'field2' => [
                'eval' => [],
            ],
            'field3' => [
                'inputType' => 'text',
            ],
        ], $this->getGlobalsArray());

        $this->unsetGlobalsArray();
    }

    public function testAddRootPagesToFieldConfig(): void
    {
        $request = new Request([], [], ['_scope' => 'backend']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 1, 'title' => 'title-1', 'language' => 'language-1'],
                ['id' => 2, 'title' => 'title-2', 'language' => 'language-2'],
                ['id' => 3, 'title' => 'title-3', 'language' => 'language-3'],
            ])
        ;

        $statement = $this->createMock(Statement::class);
        $statement
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result)
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $listener = new RootPageDependentSelectListener(
            $connection,
            $this->createMock(TranslatorInterface::class),
            $this->mockScopeMatcher(true, $request),
            $requestStack,
            $this->createMock(CsrfTokenManagerInterface::class),
            'contao_csrf_token'
        );

        $this->populateGlobalsArray([
            'field1' => [
                'inputType' => 'rootPageDependentSelect',
            ],
        ]);

        $listener->onLoadDataContainer('tl_module');

        $this->assertSame([
            'field1' => [
                'inputType' => 'rootPageDependentSelect',
                'eval' => [
                    'rootPages' => [
                        1 => 'title-1 (language-1)',
                        2 => 'title-2 (language-2)',
                        3 => 'title-3 (language-3)',
                    ],
                    'blankOptionLabel' => null,
                ],
            ],
        ], $this->getGlobalsArray());

        $this->unsetGlobalsArray();
    }

    public function testDoesNotAddWizardWhenNoValuesSet(): void
    {
        $listener = new RootPageDependentSelectListener(
            $this->createMock(Connection::class),
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ScopeMatcher::class),
            $this->createMock(RequestStack::class),
            $this->createMock(CsrfTokenManagerInterface::class),
            'contao_csrf_token'
        );

        $dataContainer = $this->mockClassWithProperties(DataContainer::class);
        $dataContainer->value = serialize([]);

        $this->assertSame('', $listener->wizardCallback($dataContainer));
    }

    public function testAddWizardToSelectWhenModuleIsSet(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->exactly(3))
            ->method('trans')
        ;

        $token = $this->createMock(CsrfToken::class);
        $token
            ->expects($this->exactly(3))
            ->method('getValue')
        ;

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects($this->exactly(3))
            ->method('getToken')
            ->willReturn($token)
        ;

        $this->container = $this->getContainerWithContaoConfiguration('/directory/project');
        System::setContainer($this->container);

        $listener = new RootPageDependentSelectListener(
            $this->createMock(Connection::class),
            $translator,
            $this->createMock(ScopeMatcher::class),
            $this->createMock(RequestStack::class),
            $csrfTokenManager,
            'contao_csrf_token'
        );

        $dataContainer = $this->mockClassWithProperties(DataContainer::class);
        $dataContainer->value = serialize([
            '1' => '10',
            '2' => '20',
            '3' => '30',
            '4' => '',
        ]);

        $this->assertCount(3, unserialize($listener->wizardCallback($dataContainer)));
    }

    private function populateGlobalsArray(array $data): void
    {
        $GLOBALS['TL_DCA']['tl_module']['fields'] = $data;
    }

    private function getGlobalsArray(): array
    {
        return $GLOBALS['TL_DCA']['tl_module']['fields'];
    }

    private function unsetGlobalsArray(): void
    {
        unset($GLOBALS['TL_DCA']['tl_module']['fields']);
    }

    /**
     * @return ScopeMatcher&MockObject
     */
    private function mockScopeMatcher(bool $hasBackendUser, Request $request): ScopeMatcher
    {
        $scopeMatcher = $this->createMock(ScopeMatcher::class);
        $scopeMatcher
            ->expects($this->once())
            ->method('isBackendRequest')
            ->with($request)
            ->willReturn($hasBackendUser)
        ;

        return $scopeMatcher;
    }
}
