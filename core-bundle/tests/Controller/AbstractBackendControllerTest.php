<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Controller;

use Contao\BackendUser;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Database;
use Contao\Environment as ContaoEnvironment;
use Contao\System;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class AbstractBackendControllerTest extends TestCase
{
    private array $globalsBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalsBackup['_SERVER'] = $_SERVER;
        $this->globalsBackup['_ENV'] = $_ENV;
    }

    protected function tearDown(): void
    {
        foreach ($this->globalsBackup as $key => $value) {
            if (null === $value) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }

        unset($GLOBALS['TL_LANG'], $GLOBALS['TL_LANGUAGE']);
        $_GET = [];
        $_POST = [];

        $this->resetStaticProperties([ContaoEnvironment::class, BackendUser::class, Database::class]);

        parent::tearDown();
    }

    public function testAddsAndMergesBackendContext(): void
    {
        $controller = new class() extends AbstractBackendController {
            public function fooAction(): Response
            {
                return $this->render('custom_be.html.twig', ['foo' => 'bar', 'version' => 'my version']);
            }
        };

        // Legacy setup
        ContaoEnvironment::reset();
        (new Filesystem())->mkdir($this->getTempDir().'/languages/en');

        $GLOBALS['TL_LANG']['MSC'] = [
            'version' => 'version',
            'dashboard' => 'dashboard',
            'home' => 'home',
            'learnMore' => 'learn more',
        ];

        $GLOBALS['TL_LANGUAGE'] = 'en';

        $_SERVER['HTTP_USER_AGENT'] = 'Contao/Foo';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = [];
        $_POST = [];

        $expectedContext = [
            'version' => 'my version',
            'headline' => 'dashboard',
            'title' => '',
            'theme' => 'flexible',
            'base' => 'http://localhost/',
            'language' => 'en',
            'host' => 'localhost',
            'charset' => 'UTF-8',
            'home' => 'home',
            'isPopup' => null,
            'learnMore' => 'learn more',
            'menu' => '<menu>',
            'headerMenu' => '<header_menu>',
            'foo' => 'bar',
        ];

        $container = $this->getContainerWithDefaultConfiguration($expectedContext);

        System::setContainer($container);
        $controller->setContainer($container);

        $this->assertSame('<custom_be_main>', $controller->fooAction()->getContent());
    }

    private function getContainerWithDefaultConfiguration(array $expectedContext): ContainerBuilder
    {
        $container = $this->getContainerWithContaoConfiguration($this->getTempDir());

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->method('isGranted')
            ->with('ROLE_USER')
            ->willReturn(true)
        ;

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->exactly(3))
            ->method('render')
            ->willReturnMap([
                ['@ContaoCore/Backend/be_menu.html.twig', [], '<menu>'],
                ['@ContaoCore/Backend/be_header_menu.html.twig', [], '<header_menu>'],
                ['custom_be.html.twig', $expectedContext, '<custom_be_main>'],
            ])
        ;

        $container->set('security.authorization_checker', $authorizationChecker);
        $container->set('security.token_storage', $this->createMock(TokenStorageInterface::class));
        $container->set('contao.security.token_checker', $this->createMock(TokenChecker::class));
        $container->set('database_connection', $this->createMock(Connection::class));
        $container->set('session', $this->createMock(Session::class));
        $container->set('twig', $twig);
        $container->set('router', $this->createMock(RouterInterface::class));

        $container->setParameter('contao.resources_paths', $this->getTempDir());

        return $container;
    }
}
