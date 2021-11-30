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

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\EventListener\DataContainer\UndoListener;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Tests\TestCase;
use Contao\DataContainer;
use Contao\Image;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

class UndoListenerTest extends TestCase
{
    /**
     * @var Image&MockObject
     */
    private $imageAdapter;

    /**
     * @var Backend&MockObject
     */
    private $backendAdapter;

    /**
     * @var Controller&MockObject
     */
    private $controllerAdapter;

    /**
     * @var ContaoFramework&MockObject
     */
    private $framework;

    /**
     * @var Connection&MockObject
     */
    private $connection;

    protected function setUp(): void
    {
        /** @var Backend&MockObject $backendAdapter */
        $backendAdapter = $this->mockAdapter(['addToUrl']);
        $this->backendAdapter = $backendAdapter;

        /** @var Controller&MockObject $controllerAdapter */
        $controllerAdapter = $this->mockAdapter(['loadLanguageFile', 'loadDataContainer']);
        $this->controllerAdapter = $controllerAdapter;

        /** @var Image&MockObject $imageAdapter */
        $imageAdapter = $this->mockAdapter(['getHtml']);
        $this->imageAdapter = $imageAdapter;

        $this->framework = $this->mockContaoFramework([
            Backend::class => $this->backendAdapter,
            Controller::class => $this->controllerAdapter,
            Image::class => $this->imageAdapter,
        ]);

        $this->connection = $this->createMock(Connection::class);
    }

    public function testRenderJumpToParentButton(): void
    {
        $row = $this->setupForDataSetWithParent();

        $this->imageAdapter
            ->expects($this->once())
            ->method('getHtml')
            ->with('parent.svg')
            ->willReturn('<img src="parent.svg">')
        ;

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM tl_news WHERE id = :id', ['id' => 24])
            ->willReturn('1')
        ;

        $this->connection
            ->method('quoteIdentifier')
            ->with('tl_news')
            ->willReturn('tl_news')
        ;

        $listener = new UndoListener($this->framework, $this->connection);

        $buttonHtml = $listener->renderJumpToParentButton($row, '', '', '', 'parent.svg');
        $this->assertSame("<a href=\"\" title=\"Show parent of Inhaltselement ID 42\" onclick=\"Backend.openModalIframe({'title':'Show parent of Inhaltselement ID 42','url': this.href });return false\"><img src=\"parent.svg\"></a> ", $buttonHtml);
    }

    public function testRendersDisabledJumpToParentButtonWhenParentHasBeenDeleted(): void
    {
        $row = $this->setupForDataSetWithParent();

        $GLOBALS['TL_LANG']['tl_content']['_type'] = ['Inhaltselement', 'Inhaltselemente'];
        $GLOBALS['TL_LANG']['tl_undo']['parent_modal'] = 'Show parent of %s ID %s';

        $GLOBALS['TL_DCA']['tl_content']['config']['dynamicPtable'] = true;

        $this->imageAdapter
            ->expects($this->once())
            ->method('getHtml')
            ->with('parent_.svg')
            ->willReturn('<img src="parent_.svg">')
        ;

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM tl_news WHERE id = :id', ['id' => 24])
            ->willReturn('0')
        ;

        $this->connection
            ->method('quoteIdentifier')
            ->with('tl_news')
            ->willReturn('tl_news')
        ;

        $listener = new UndoListener($this->framework, $this->connection);
        $buttonHtml = $listener->renderJumpToParentButton($row, '', '', '', 'parent.svg');
        $this->assertSame('<img src="parent_.svg"> ', $buttonHtml);
    }

    public function testRendersDisabledJumpToParentButton(): void
    {
        $row = $this->setupForDataSetWithoutParent();

        $this->imageAdapter
            ->expects($this->once())
            ->method('getHtml')
            ->with('parent_.svg')
            ->willReturn('<img src="parent_.svg">')
        ;

        $listener = new UndoListener($this->framework, $this->connection);
        $buttonHtml = $listener->renderJumpToParentButton($row);
        $this->assertSame('<img src="parent_.svg"> ', $buttonHtml);
    }

    private function setupForDataSetWithParent(): array
    {
        $GLOBALS['BE_MOD']['content']['news'] = [
            'tables' => ['tl_news_archive', 'tl_news'],
        ];

        $GLOBALS['TL_LANG']['tl_content']['_type'] = ['Inhaltselement', 'Inhaltselemente'];
        $GLOBALS['TL_LANG']['tl_undo']['parent_modal'] = 'Show parent of %s ID %s';

        $GLOBALS['TL_DCA']['tl_content']['config']['dynamicPtable'] = true;
        $GLOBALS['TL_DCA']['tl_news']['list']['sorting']['mode'] = DataContainer::MODE_PARENT;

        return [
            'id' => 1,
            'fromTable' => 'tl_content',
            'data' => serialize([
                'tl_content' => [
                    [
                        'id' => 42,
                        'pid' => 24,
                        'ptable' => 'tl_news',
                    ],
                ],
                'tl_news' => [
                    [
                        'id' => 24,
                    ],
                ],
            ]),
        ];
    }

    private function setupForDataSetWithoutParent(): array
    {
        $GLOBALS['TL_LANG']['tl_form']['_type'] = ['Formular', 'Formulare'];
        $GLOBALS['TL_LANG']['tl_undo']['parent_modal'] = 'Show parent of %s ID %s';

        $GLOBALS['TL_DCA']['tl_form']['config'] = [];

        return [
            'id' => 1,
            'fromTable' => 'tl_form',
            'data' => serialize([
                'tl_form' => [
                    [
                        'id' => 42,
                    ],
                ],
            ]),
        ];
    }
}
