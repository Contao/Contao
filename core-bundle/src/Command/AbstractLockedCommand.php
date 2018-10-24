<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

abstract class AbstractLockedCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $store = new FlockStore($this->getTempDir());
        $factory = new Factory($store);
        $lock = $factory->createLock($this->getName());

        if (!$lock->acquire()) {
            $output->writeln('The command is already running in another process.');

            return 1;
        }

        if (($errorCode = $this->executeLocked($input, $output)) > 0) {
            $lock->release();

            return $errorCode;
        }

        $lock->release();

        return 0;
    }

    /**
     * @return int
     */
    abstract protected function executeLocked(InputInterface $input, OutputInterface $output);

    /**
     * Creates an installation specific folder in the temporary directory and returns its path.
     */
    private function getTempDir(): string
    {
        $container = $this->getContainer();
        $tmpDir = $container->getParameter('contao.tmp_dir').'/'.md5($container->getParameter('kernel.project_dir'));

        if (!is_dir($tmpDir)) {
            $container->get('filesystem')->mkdir($tmpDir);
        }

        return $tmpDir;
    }
}
