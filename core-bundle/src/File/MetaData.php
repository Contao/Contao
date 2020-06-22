<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\File;

/**
 * @psalm-immutable
 *
 * This class is as a container for file meta data as typically defined in
 * tl_files / tl_content. Its underlying data structure is a key-value store
 * with added getters/setters for convenience.
 *
 * The data must be stored in a normalized form. It's your responsibility to
 * ensure this is the case when creating an instance of this class. You can
 * use the public class constants as keys for a better DX.
 */
class MetaData
{
    public const VALUE_ALT = 'alt';
    public const VALUE_CAPTION = 'caption';
    public const VALUE_TITLE = 'title';
    public const VALUE_URL = 'link';

    /**
     * Key-value pairs of meta data.
     *
     * @var array<string, mixed>
     */
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * Get a new meta data container that is the result of merging this
     * container's data with the data of the specified one.
     */
    public function withOther(self $metaData): self
    {
        return new self(array_merge($this->values, $metaData->values));
    }

    /**
     * Get a value. Returns null if the value was not found.
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }

    public function getAlt(): string
    {
        return $this->values[self::VALUE_ALT] ?? '';
    }

    public function getCaption(): string
    {
        return $this->values[self::VALUE_CAPTION] ?? '';
    }

    public function getTitle(): string
    {
        return $this->values[self::VALUE_TITLE] ?? '';
    }

    public function getUrl(): string
    {
        return $this->values[self::VALUE_URL] ?? '';
    }

    /**
     * Return the whole data set as an associative array.
     */
    public function all(): array
    {
        return $this->values;
    }
}
