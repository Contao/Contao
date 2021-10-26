<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Twig\Runtime;

use Contao\CoreBundle\InsertTag\ChunkedText;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\InsertTags;
use Twig\Extension\RuntimeExtensionInterface;

final class InsertTagRuntime implements RuntimeExtensionInterface
{
    private InsertTagParser $insertTagParser;

    /**
     * @internal
     */
    public function __construct(InsertTagParser $insertTagParser)
    {
        $this->insertTagParser = $insertTagParser;
    }

    public function renderInsertTag(string $insertTag): string
    {
        return $this->insertTagParser->render($insertTag);
    }

    public function replaceInsertTags(string $text): string
    {
        return $this->insertTagParser->replaceInline($text);
    }

    public function replaceInsertTagsChunkedRaw(string $text): ChunkedText
    {
        return $this->insertTagParser->replaceChunked($text);
    }
}
