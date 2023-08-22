<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Doctrine\Backup;

use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Doctrine\Backup\Config\RestoreConfig;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class BackupManager
{
    final public const DUMP_HEADER = '-- Generated by the Contao Open Source CMS Backup Manager (version: v1).';

    public function __construct(
        private readonly Connection $connection,
        private readonly DumperInterface $dumper,
        private readonly VirtualFilesystemInterface $backupsStorage,
        private readonly array $tablesToIgnore,
        private readonly RetentionPolicyInterface $retentionPolicy,
    ) {
    }

    public function createNewBackup(\DateTime|null $dateTime = null): Backup
    {
        $now = $dateTime ?? new \DateTime('now');
        $now->setTimezone(new \DateTimeZone('UTC'));

        $filename = sprintf('backup__%s.sql.gz', $now->format(Backup::DATETIME_FORMAT));

        return new Backup($filename);
    }

    public function createCreateConfig(): CreateConfig
    {
        $config = new CreateConfig($this->createNewBackup());

        return $config->withTablesToIgnore($this->tablesToIgnore);
    }

    public function createRestoreConfig(): RestoreConfig
    {
        if (!$latestBackup = $this->getLatestBackup()) {
            throw new BackupManagerException('No backups found.');
        }

        $config = new RestoreConfig($latestBackup);

        return $config->withTablesToIgnore($this->tablesToIgnore);
    }

    public function getLatestBackup(): Backup|null
    {
        return $this->listBackups()[0] ?? null;
    }

    /**
     * Lists all backups (the latest one first).
     *
     * @return array<Backup>
     */
    public function listBackups(): array
    {
        $backups = [];

        foreach ($this->backupsStorage->listContents('', false, VirtualFilesystemInterface::BYPASS_DBAFS)->files() as $file) {
            try {
                $backup = new Backup($file->getPath());
            } catch (BackupManagerException) {
                continue;
            }

            $this->updateBackupWithSize($backup);
            $backups[] = $backup;
        }

        usort($backups, static fn (Backup $a, Backup $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $backups;
    }

    /**
     * @throws BackupManagerException
     */
    public function create(CreateConfig $config): void
    {
        try {
            $this->connection->transactional(fn () => $this->doCreate($config));
        } catch (BackupManagerException $e) {
            throw $e;
        } catch (\Throwable $t) {
            throw new BackupManagerException($t->getMessage(), 0, $t);
        }
    }

    /**
     * @return resource
     *
     * @throws BackupManagerException
     */
    public function readStream(Backup $backup)
    {
        if (!$this->getBackupByName($backup->getFilename())) {
            throw new BackupManagerException('Cannot read stream of a non-existent backup.');
        }

        return $this->backupsStorage->readStream($backup->getFilename());
    }

    public function getBackupByName(string $fileName): Backup|null
    {
        if (!$this->backupsStorage->fileExists($fileName)) {
            return null;
        }

        return new Backup($fileName);
    }

    /**
     * @throws BackupManagerException
     */
    public function restore(RestoreConfig $config): void
    {
        try {
            $this->doRestore($config);
        } catch (BackupManagerException $e) {
            throw $e;
        } catch (\Throwable $t) {
            throw new BackupManagerException($t->getMessage(), 0, $t);
        }
    }

    private function updateBackupWithSize(Backup $backup): void
    {
        try {
            $backup->setSize($this->backupsStorage->getFileSize($backup->getFilename()));
        } catch (\Exception) {
            $backup->setSize(0);
        }
    }

    private function doCreate(CreateConfig $config): void
    {
        $backup = $config->getBackup();

        // Ensure the target file exists and is empty
        $this->backupsStorage->write($backup->getFilename(), '');

        $tmpFile = (new Filesystem())->tempnam(sys_get_temp_dir(), 'ctobckupmgr');
        $fileHandle = fopen($tmpFile, 'r+w');
        $deflateContext = $config->isGzCompressionEnabled() ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 9]) : null;

        // Header lines
        $this->writeLine(self::DUMP_HEADER, $fileHandle, $deflateContext);
        $this->writeLine('-- Generated at '.$config->getBackup()->getCreatedAt()->format(\DateTimeInterface::ATOM), $fileHandle, $deflateContext);

        try {
            foreach ($this->dumper->dump($this->connection, $config) as $data) {
                $this->writeLine($data, $fileHandle, $deflateContext);
            }

            $this->finishWriting($backup, $fileHandle, $deflateContext);
            $this->tidyDirectory($config->getBackup());
            (new Filesystem())->remove($tmpFile);
        } catch (BackupManagerException $exception) {
            $this->backupsStorage->delete($backup->getFilename());
            (new Filesystem())->remove($tmpFile);

            throw $exception;
        }
    }

    /**
     * @param resource                 $fileHandle
     * @param \DeflateContext|resource $deflateContext
     */
    private function writeLine(string $data, $fileHandle, $deflateContext): void
    {
        $data .= PHP_EOL;

        if ($deflateContext) {
            $data = deflate_add($deflateContext, $data, ZLIB_NO_FLUSH);
        }

        @fwrite($fileHandle, $data);
        fflush($fileHandle);
    }

    /**
     * @param resource                 $fileHandle
     * @param \DeflateContext|resource $deflateContext
     */
    private function finishWriting(Backup $backup, $fileHandle, $deflateContext): void
    {
        if ($deflateContext) {
            fwrite($fileHandle, deflate_add($deflateContext, '', ZLIB_FINISH));
        }

        $this->backupsStorage->writeStream($backup->getFilename(), $fileHandle);
        fclose($fileHandle);

        $this->updateBackupWithSize($backup);
    }

    private function doRestore(RestoreConfig $config): void
    {
        $backup = $config->getBackup();

        if (!$this->backupsStorage->fileExists($backup->getFilename())) {
            throw new BackupManagerException(sprintf('Dump "%s" does not exist.', $backup->getFilename()));
        }

        $tmpFile = (new Filesystem())->tempnam(sys_get_temp_dir(), 'ctobckupmgr');
        $handle = fopen($tmpFile, 'w');
        stream_copy_to_stream($this->backupsStorage->readStream($backup->getFilename()), $handle);
        fclose($handle);
        $handle = gzopen($tmpFile, 'r');

        $currentQuery = '';
        $checkedForHeader = $config->ignoreOriginCheck();

        while (false !== ($line = gzgets($handle))) {
            $line = trim($line);

            if (!$checkedForHeader) {
                if (self::DUMP_HEADER !== $line) {
                    throw new BackupManagerException('The Contao database importer only supports dumps generated by Contao.');
                }

                $checkedForHeader = true;
                continue;
            }

            // Ignore comments
            if (str_starts_with($line, '--')) {
                continue;
            }

            $currentQuery .= $line;

            // Current query ends
            if (str_ends_with($currentQuery, ';')) {
                $this->executeWrappedQuery($currentQuery);
                $currentQuery = '';
            }
        }

        if ('' !== $currentQuery) {
            $this->executeWrappedQuery($currentQuery);
        }
    }

    private function tidyDirectory(Backup $currentBackup): void
    {
        $allBackups = $this->listBackups();
        $backupsToKeep = $this->retentionPolicy->apply($currentBackup, $allBackups);

        foreach (array_diff($allBackups, $backupsToKeep) as $backup) {
            $this->backupsStorage->delete($backup->getFilename());
        }
    }

    private function executeWrappedQuery(string $query): void
    {
        try {
            $this->connection->executeQuery($query);
        } catch (\Exception $e) {
            throw new BackupManagerException($e->getMessage(), 0, $e);
        }
    }
}
