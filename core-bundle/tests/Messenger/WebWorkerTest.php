<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Messenger;

use Contao\CoreBundle\Messenger\Message\BackendSearch\ReindexMessage;
use Contao\CoreBundle\Messenger\Message\ScopeAwareMessageInterface;
use Contao\CoreBundle\Messenger\WebWorker;
use Contao\CoreBundle\Search\Backend\ReindexConfig;
use Contao\CoreBundle\Tests\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class WebWorkerTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private ConsumeMessagesCommand $command;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = new class() extends AbstractLogger {
            private array $logs = [];

            /**
             * @param string $message
             */
            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $message;
            }

            public function getLogs(): array
            {
                return $this->logs;
            }
        };

        $this->eventDispatcher = new EventDispatcher();
        $this->createConsumeCommand();
    }

    public function testPingOnlyAboutConfiguredTransports(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->atLeastOnce()) // Tests that transport-2 is not called
            ->method('getItem')
            ->with('contao-web-worker-transport-1')
        ;

        $webWorker = new WebWorker(
            $cache,
            $this->command,
            ['transport-1'],
        );

        $this->addEventsToEventDispatcher($webWorker);
        $this->triggerRealWorkers();
    }

    public function testWorkerIsStoppedIfIdle(): void
    {
        $cache = new ArrayAdapter(); // No real workers running

        $webWorker = new WebWorker(
            $cache,
            $this->command,
            ['transport-1'],
        );

        $this->addEventsToEventDispatcher($webWorker);
        $this->triggerWebWorker();

        // This test would run for 30 seconds if it failed. If the worker is correctly
        // stopped, it will return immediately and log "Stopping worker.".
        // @phpstan-ignore method.notFound
        $this->assertContains('Stopping worker.', $this->logger->getLogs());
    }

    public function testHasCliWorkersRunning(): void
    {
        $cache = new ArrayAdapter(); // No real workers running yet

        $webWorker = new WebWorker(
            $cache,
            $this->command,
            ['transport-1'],
        );

        $this->addEventsToEventDispatcher($webWorker);
        $this->assertFalse($webWorker->hasCliWorkersRunning());
        $this->triggerRealWorkers();
        $this->assertTrue($webWorker->hasCliWorkersRunning());
    }

    public function testSkipsMessagesThatRequireRealWorkersInWebWorker(): void
    {
        $webWorker = new WebWorker(
            new ArrayAdapter(),
            $this->command,
            [],
        );

        // Message that implements the WebworkerAwareInterface
        $message = new ReindexMessage(new ReindexConfig());
        $envelope = new Envelope($message);

        $event = new WorkerMessageReceivedEvent($envelope, 'receiver');
        $webWorker->onWorkerMessageReceived($event);

        $this->assertSame(ScopeAwareMessageInterface::SCOPE_CLI, $message->getScope());

        // Use reflection to simulate the web worker running. Testing it without
        // reflection would require setting up the entire message handler with all the
        // middleware which is not something we need to test
        $reflection = new \ReflectionObject($webWorker);
        $property = $reflection->getProperty('webWorkerRunning');
        $property->setValue($webWorker, true);

        $webWorker->onWorkerMessageReceived($event);

        $this->assertSame(ScopeAwareMessageInterface::SCOPE_WEB, $message->getScope());
    }

    private function triggerWebWorker(): void
    {
        $this->eventDispatcher->dispatch(new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            new Response(),
        ));
    }

    private function triggerRealWorkers(): void
    {
        $listener = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };

        $this->eventDispatcher->addListener(WorkerRunningEvent::class, $listener);

        $input = new ArrayInput([
            'receivers' => ['transport-1', 'transport-2'],
        ]);

        $this->command->run($input, new NullOutput());
        $this->eventDispatcher->removeListener(WorkerRunningEvent::class, $listener);
    }

    private function createConsumeCommand(): void
    {
        $receiverLocator = new Container();
        $receiverLocator->set('transport-1', $this->createMock(ReceiverInterface::class));
        $receiverLocator->set('transport-2', $this->createMock(ReceiverInterface::class));
        $receiverLocator->set('transport-3', $this->createMock(ReceiverInterface::class));

        $this->command = new ConsumeMessagesCommand(
            $this->createMock(RoutableMessageBus::class),
            $receiverLocator,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    private function addEventsToEventDispatcher(WebWorker $webWorker): void
    {
        $this->eventDispatcher->addListener(
            WorkerStartedEvent::class,
            static function (WorkerStartedEvent $event) use ($webWorker): void {
                $webWorker->onWorkerStarted($event);
            },
        );

        $this->eventDispatcher->addListener(
            WorkerRunningEvent::class,
            static function (WorkerRunningEvent $event) use ($webWorker): void {
                $webWorker->onWorkerRunning($event);
            },
        );

        $this->eventDispatcher->addListener(
            TerminateEvent::class,
            static function (TerminateEvent $event) use ($webWorker): void {
                $webWorker->onKernelTerminate($event);
            },
        );
    }
}
