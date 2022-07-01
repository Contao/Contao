<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command;

use Contao\CoreBundle\Command\MigrateCommand;
use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\BackupManagerException;
use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Doctrine\Schema\MysqlInnodbRowSizeCalculator;
use Contao\CoreBundle\Doctrine\Schema\SchemaProvider;
use Contao\CoreBundle\Migration\CommandCompiler;
use Contao\CoreBundle\Migration\MigrationCollection;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\Tests\TestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PdoDriver;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateCommandTest extends TestCase
{
    use ExpectDeprecationTrait;

    protected function tearDown(): void
    {
        $this->resetStaticProperties([Terminal::class]);

        parent::tearDown();
    }

    public function testAbortsEarlyIfTheBackupFails(): void
    {
        $backupManager = $this->createBackupManager(true);
        $backupManager
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new BackupManagerException('Something went terribly wrong.'))
        ;

        $command = $this->getCommand([], [], null, $backupManager);
        $tester = new CommandTester($command);
        $code = $tester->execute(['--no-check' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $code);
        $this->assertDoesNotMatchRegularExpression('/All migrations completed/', $display);
    }

    /**
     * @dataProvider getOutputFormats
     */
    public function testExecutesWithoutPendingMigrations(string $format): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);
        $code = $tester->execute(['--format' => $format, '--no-backup' => true, '--no-check' => true], ['interactive' => 'ndjson' !== $format]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);

        if ('ndjson' === $format) {
            $this->assertSame(
                [
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'schema-pending', 'commands' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                ],
                $this->jsonArrayFromNdjson($display)
            );
        } else {
            $this->assertMatchesRegularExpression('/All migrations completed/', $display);
        }
    }

    /**
     * @dataProvider getOutputFormatsAndBackup
     */
    public function testExecutesPendingMigrations(string $format, bool $backupsEnabled): void
    {
        $command = $this->getCommand(
            [['Migration 1', 'Migration 2']],
            [[new MigrationResult(true, 'Result 1'), new MigrationResult(true, 'Result 2')]],
            null,
            $this->createBackupManager($backupsEnabled)
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['y']);

        $code = $tester->execute(['--format' => $format, '--no-backup' => !$backupsEnabled, '--no-check' => true], ['interactive' => 'ndjson' !== $format]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);

        if ('ndjson' === $format) {
            $expected = [];

            if ($backupsEnabled) {
                $expected[] = ['type' => 'backup-result', 'createdAt' => '2021-11-01T14:12:54+00:00', 'size' => 0, 'name' => 'valid_backup_filename__20211101141254.sql'];
            }

            $expected = array_merge(
                $expected,
                [
                    ['type' => 'migration-pending', 'names' => ['Migration 1', 'Migration 2'], 'hash' => 'ba37bf15c565f47d20df024e3f18bd32e88985525920011c4669c574d71b69fd'],
                    ['type' => 'migration-result', 'message' => 'Result 1', 'isSuccessful' => true],
                    ['type' => 'migration-result', 'message' => 'Result 2', 'isSuccessful' => true],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'schema-pending', 'commands' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                ],
            );

            $this->assertSame($expected, $this->jsonArrayFromNdjson($display));
        } else {
            if ($backupsEnabled) {
                $this->assertStringContainsString('Creating a database dump', $display);
            }

            $this->assertStringContainsString('Migration 1', $display);
            $this->assertStringContainsString('Migration 2', $display);
            $this->assertStringContainsString('Result 1', $display);
            $this->assertStringContainsString('Result 2', $display);
            $this->assertStringContainsString('Executed 2 migrations', $display);
            $this->assertStringContainsString('All migrations completed', $display);
        }
    }

    /**
     * @dataProvider getOutputFormats
     */
    public function testExecutesSchemaDiff(string $format): void
    {
        $returnedCommands = [
            [
                'First call QUERY 1',
                'First call QUERY 2',
            ],
            [
                'Second call QUERY 1',
                'Second call QUERY 2',
                'DROP QUERY',
            ],
            [],
        ];

        $returnedCommandsWithoutDrops = [
            [
                'First call QUERY 1',
                'First call QUERY 2',
            ],
            [
                'Second call QUERY 1',
                'Second call QUERY 2',
            ],
            [],
        ];

        $commandCompiler = $this->createMock(CommandCompiler::class);
        $commandCompiler
            ->expects($this->atLeastOnce())
            ->method('compileCommands')
            ->willReturnCallback(
                static function (bool $doNotDropColumns = false) use (&$returnedCommandsWithoutDrops, &$returnedCommands): array {
                    return $doNotDropColumns ?
                        array_shift($returnedCommandsWithoutDrops) :
                        array_shift($returnedCommands);
                }
            )
        ;

        $command = $this->getCommand([], [], $commandCompiler);

        $tester = new CommandTester($command);
        $tester->setInputs(['yes', 'yes']);

        $code = $tester->execute(['--format' => $format, '--no-backup' => true, '--no-check' => true], ['interactive' => 'ndjson' !== $format]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);

        if ('ndjson' === $format) {
            $this->assertSame(
                [
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'schema-pending', 'commands' => ['First call QUERY 1', 'First call QUERY 2'], 'hash' => '06b103d878d056ea88d30fba6a88782227a7c34160bca50a6e63320ee104af5f'],
                    ['type' => 'schema-execute', 'command' => 'First call QUERY 1'],
                    ['type' => 'schema-result', 'command' => 'First call QUERY 1', 'isSuccessful' => true],
                    ['type' => 'schema-execute', 'command' => 'First call QUERY 2'],
                    ['type' => 'schema-result', 'command' => 'First call QUERY 2', 'isSuccessful' => true],
                    ['type' => 'schema-pending', 'commands' => ['Second call QUERY 1', 'Second call QUERY 2', 'DROP QUERY'], 'hash' => '929210d967bc630ef187795ca91759f9e27906fc16316b205600ff7b40cbfd1b'],
                    ['type' => 'schema-execute', 'command' => 'Second call QUERY 1'],
                    ['type' => 'schema-result', 'command' => 'Second call QUERY 1', 'isSuccessful' => true],
                    ['type' => 'schema-execute', 'command' => 'Second call QUERY 2'],
                    ['type' => 'schema-result', 'command' => 'Second call QUERY 2', 'isSuccessful' => true],
                    ['type' => 'schema-pending', 'commands' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                ],
                $this->jsonArrayFromNdjson($display)
            );
        } else {
            $this->assertMatchesRegularExpression('/First call QUERY 1/', $display);
            $this->assertMatchesRegularExpression('/First call QUERY 2/', $display);
            $this->assertMatchesRegularExpression('/Second call QUERY 1/', $display);
            $this->assertMatchesRegularExpression('/Second call QUERY 2/', $display);
            $this->assertMatchesRegularExpression('/Executed 2 SQL queries/', $display);
            $this->assertDoesNotMatchRegularExpression('/Executed 3 SQL queries/', $display);
            $this->assertMatchesRegularExpression('/All migrations completed/', $display);
        }
    }

    /**
     * @dataProvider getOutputFormats
     */
    public function testDoesNotExecuteWithDryRun(string $format): void
    {
        $commandCompiler = $this->createMock(CommandCompiler::class);
        $commandCompiler
            ->expects($this->once())
            ->method('compileCommands')
            ->willReturn(
                [
                    'First call QUERY 1',
                    'First call QUERY 2',
                ]
            )
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeQuery')
        ;

        $connection
            ->method('fetchOne')
            ->with('SELECT @@sql_mode')
            ->willReturn('TRADITIONAL')
        ;

        $command = $this->getCommand(
            [['Migration 1', 'Migration 2']],
            [[new MigrationResult(true, 'Result 1'), new MigrationResult(true, 'Result 2')]],
            $commandCompiler,
            null,
            $connection
        );

        $tester = new CommandTester($command);

        // No --no-backup here because --dry-run should automatically disable backups
        $code = $tester->execute(['--dry-run' => true, '--format' => $format, '--no-check' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);

        if ('ndjson' === $format) {
            $this->assertSame(
                [
                    [
                        'type' => 'migration-pending',
                        'names' => ['Migration 1', 'Migration 2'],
                        'hash' => 'ba37bf15c565f47d20df024e3f18bd32e88985525920011c4669c574d71b69fd',
                    ],
                    [
                        'type' => 'schema-pending',
                        'commands' => ['First call QUERY 1', 'First call QUERY 2'],
                        'hash' => '06b103d878d056ea88d30fba6a88782227a7c34160bca50a6e63320ee104af5f',
                    ],
                ],
                $this->jsonArrayFromNdjson($display)
            );
        } else {
            $this->assertMatchesRegularExpression('/Migration 1/', $display);
            $this->assertMatchesRegularExpression('/Migration 2/', $display);
            $this->assertDoesNotMatchRegularExpression('/Result 1/', $display);
            $this->assertDoesNotMatchRegularExpression('/Result 2/', $display);

            $this->assertMatchesRegularExpression('/First call QUERY 1/', $display);
            $this->assertMatchesRegularExpression('/First call QUERY 2/', $display);
            $this->assertDoesNotMatchRegularExpression('/Executed 2 SQL queries/', $display);

            $this->assertMatchesRegularExpression('/All migrations completed/', $display);
        }
    }

    public function testAbortsIfAnswerIsNo(): void
    {
        $command = $this->getCommand(
            [['Migration 1', 'Migration 2']],
            [[new MigrationResult(true, 'Result 1'), new MigrationResult(true, 'Result 2')]]
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['n']);

        $code = $tester->execute(['--no-backup' => true, '--no-check' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/Migration 1/', $display);
        $this->assertMatchesRegularExpression('/Migration 2/', $display);
        $this->assertDoesNotMatchRegularExpression('/Result 1/', $display);
        $this->assertDoesNotMatchRegularExpression('/Result 2/', $display);
        $this->assertDoesNotMatchRegularExpression('/All migrations completed/', $display);
    }

    /**
     * @dataProvider getOutputFormats
     */
    public function testDoesNotAbortIfMigrationFails(string $format): void
    {
        $command = $this->getCommand(
            [['Migration 1', 'Migration 2']],
            [[new MigrationResult(false, 'Result 1'), new MigrationResult(true, 'Result 2')]]
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['y']);

        $code = $tester->execute(['--format' => $format, '--no-backup' => true, '--no-check' => true], ['interactive' => 'ndjson' !== $format]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);

        if ('ndjson' === $format) {
            $this->assertSame(
                [
                    ['type' => 'migration-pending', 'names' => ['Migration 1', 'Migration 2'], 'hash' => 'ba37bf15c565f47d20df024e3f18bd32e88985525920011c4669c574d71b69fd'],
                    ['type' => 'migration-result', 'message' => 'Result 1', 'isSuccessful' => false],
                    ['type' => 'migration-result', 'message' => 'Result 2', 'isSuccessful' => true],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'schema-pending', 'commands' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                    ['type' => 'migration-pending', 'names' => [], 'hash' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945'],
                ],
                $this->jsonArrayFromNdjson($display)
            );
        } else {
            $this->assertMatchesRegularExpression('/Migration 1/', $display);
            $this->assertMatchesRegularExpression('/Migration 2/', $display);
            $this->assertMatchesRegularExpression('/Result 1/', $display);
            $this->assertMatchesRegularExpression('/Migration failed/', $display);
            $this->assertMatchesRegularExpression('/Result 2/', $display);
            $this->assertMatchesRegularExpression('/All migrations completed/', $display);
        }
    }

    /**
     * @dataProvider getOutputFormats
     */
    public function testDoesAbortOnFatalError(string $format): void
    {
        $commandCompiler = $this->createMock(CommandCompiler::class);
        $commandCompiler
            ->expects($this->atLeastOnce())
            ->method('compileCommands')
            ->willThrowException(new \Exception('Fatal'))
        ;

        $command = $this->getCommand([], [], $commandCompiler);
        $tester = new CommandTester($command);

        if ('ndjson' !== $format) {
            $this->expectException(\Exception::class);
        }

        $code = $tester->execute(['--format' => $format, '--no-backup' => true, '--no-check' => true], ['interactive' => 'ndjson' !== $format]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $code);

        $json = $this->jsonArrayFromNdjson($display)[1];

        $this->assertSame('error', $json['type']);
        $this->assertSame('Fatal', $json['message']);
    }

    /**
     * @dataProvider provideInvalidSqlModes
     */
    public function testOutputsWarningIfNotRunningInStrictMode(string $sqlMode, AbstractMySQLDriver $driver, int $expectedOptionKey): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->with('SELECT @@sql_mode')
            ->willReturn($sqlMode)
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $command = $this->getCommand(connection: $connection);

        $tester = new CommandTester($command);
        $tester->execute(['--no-backup' => true, '--no-check' => true]);

        $display = $tester->getDisplay();

        $this->assertStringContainsString(
            'Running MySQL in non-strict mode can cause corrupt or truncated data.',
            $display
        );

        $this->assertStringContainsString(
            sprintf('%s: "SET SESSION sql_mode=', $expectedOptionKey),
            $display
        );
    }

    /**
     * @dataProvider provideInvalidSqlModes
     */
    public function testEmitsWarningMessageIfNotRunningInStrictMode(string $sqlMode, AbstractMySQLDriver $driver, int $expectedOptionKey): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->with('SELECT @@sql_mode')
            ->willReturn($sqlMode)
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $command = $this->getCommand(connection: $connection);

        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'ndjson', '--no-backup' => true, '--no-check' => true], ['interactive' => false]);

        $display = $tester->getDisplay();

        $json = $this->jsonArrayFromNdjson($display)[1];

        $this->assertSame('warning', $json['type']);

        $this->assertStringContainsString(
            'Running MySQL in non-strict mode can cause corrupt or truncated data.',
            $json['message']
        );

        $this->assertStringContainsString(
            sprintf('%s: "SET SESSION sql_mode=', $expectedOptionKey),
            $json['message']
        );
    }

    public function getOutputFormats(): \Generator
    {
        yield ['txt'];
        yield ['ndjson'];
    }

    public function getOutputFormatsAndBackup(): \Generator
    {
        yield 'txt and backups enabled' => ['txt', true];
        yield 'txt and backups disabled' => ['txt', false];
        yield 'ndjson and backups enabled' => ['ndjson', true];
        yield 'ndjson and backups disabled' => ['ndjson', false];
    }

    public function provideInvalidSqlModes(): \Generator
    {
        $pdoDriver = new PdoDriver();
        $mysqliDriver = new MysqliDriver();

        yield 'empty sql_mode, pdo driver' => [
            '', $pdoDriver, 1002,
        ];

        yield 'empty sql_mode, mysqli driver' => [
            '', $mysqliDriver, 3,
        ];

        yield 'unrelated values, pdo driver' => [
            'IGNORE_SPACE,ONLY_FULL_GROUP_BY', $pdoDriver, 1002,
        ];

        yield 'unrelated values, mysqli driver' => [
            'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION', $mysqliDriver, 3,
        ];
    }

    /**
     * @param array<array<string>>          $pendingMigrations
     * @param array<array<MigrationResult>> $migrationResults
     */
    private function getCommand(array $pendingMigrations = [], array $migrationResults = [], CommandCompiler $commandCompiler = null, BackupManager $backupManager = null, Connection $connection = null): MigrateCommand
    {
        $migrations = $this->createMock(MigrationCollection::class);

        $pendingMigrations[] = [];
        $pendingMigrations[] = [];
        $pendingMigrations[] = [];

        $migrations
            ->method('getPendingNames')
            ->willReturn(...$pendingMigrations)
        ;

        $migrationResults[] = [];

        $migrations
            ->method('run')
            ->willReturn(...$migrationResults)
        ;

        $schemaProvider = $this->createMock(SchemaProvider::class);
        $schemaProvider
            ->method('createSchema')
            ->willReturn(new Schema())
        ;

        if (null === $connection) {
            $connection = $this->createMock(Connection::class);
            $connection
                ->method('fetchOne')
                ->with('SELECT @@sql_mode')
                ->willReturn('TRADITIONAL')
            ;
        }

        return new MigrateCommand(
            $commandCompiler ?? $this->createMock(CommandCompiler::class),
            $connection,
            $migrations,
            $backupManager ?? $this->createBackupManager(false),
            $schemaProvider,
            $this->createMock(MysqlInnodbRowSizeCalculator::class)
        );
    }

    /**
     * @return BackupManager&MockObject
     */
    private function createBackupManager(bool $backupsEnabled): BackupManager
    {
        $backupManager = $this->createMock(BackupManager::class);
        $backupManager
            ->expects($backupsEnabled ? $this->once() : $this->never())
            ->method('createCreateConfig')
            ->willReturn(new CreateConfig(new Backup('valid_backup_filename__20211101141254.sql')))
        ;

        $backupManager
            ->expects($backupsEnabled ? $this->once() : $this->never())
            ->method('create')
        ;

        return $backupManager;
    }

    private function jsonArrayFromNdjson(string $ndjson): array
    {
        return array_map(static fn (string $line) => json_decode($line, true), explode("\n", trim($ndjson)));
    }
}
