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
use Twig\Extension\RuntimeExtensionInterface;

final class InsertTagRuntime implements RuntimeExtensionInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly InsertTagParser $insertTagParser)
    {
    }

    public function renderInsertTag(string $insertTag): string
    {
        return $this->insertTagParser->renderTag($insertTag)->getValue();
    }

    public function replaceInsertTags(array $context, string $text, bool|null $asEditorView = null): string
    {
        $asEditorView ??= $context['as_editor_view'] ?? false;

        if ($asEditorView) {
            return $text;
        }

        return $this->insertTagParser->replaceInline($text);
    }

    public function replaceInsertTagsChunkedRaw(array $context, string $text, bool|null $asEditorView = null): ChunkedText
    {
        $asEditorView ??= $context['as_editor_view'] ?? false;

        if ($asEditorView) {
            return new ChunkedText([$text, '']);
        }

        return $this->insertTagParser->replaceChunked($text);
    }
}
