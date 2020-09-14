<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Orm\Collector;

class ExtensionCollector
{
    private $paths;
    private $cached;

    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function collect(): array
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        $extensions = [];
        $includedFiles = [];

        foreach ($this->paths as $path) {
            $searchPath = sprintf('%s/Extension', $path);

            if (!is_dir($searchPath)) {
                continue;
            }

            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($searchPath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+'.preg_quote('.php').'$/i',
                \RecursiveRegexIterator::GET_MATCH
            );

            foreach ($iterator as $file) {
                $sourceFile = $file[0];

                require_once $sourceFile;

                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_traits();

        foreach ($declared as $traitName) {
            $rc = new \ReflectionClass($traitName);
            $sourceFile = $rc->getFileName();

            if (!\in_array($sourceFile, $includedFiles, true)) {
                continue;
            }

            $extensions[] = $traitName;
        }

        $this->cached = $extensions;

        return $extensions;
    }
}
