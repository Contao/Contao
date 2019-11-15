<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Search\Escargot\Subscriber;

class SubscriberResult
{
    /**
     * @var bool
     */
    private $wasSuccessful;

    /**
     * @var string|null
     */
    private $warning;

    /**
     * @var string
     */
    private $summary;

    /**
     * Mixed custom info. Must be serializable
     * so it can be transported between
     * requests.
     *
     * @var array
     */
    private $info;

    public function __construct(bool $wasSuccessful, string $summary)
    {
        $this->wasSuccessful = $wasSuccessful;
        $this->summary = $summary;
    }

    public function wasSuccessful(): bool
    {
        return $this->wasSuccessful;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getWarning(): ?string
    {
        return $this->warning;
    }

    public function setWarning(?string $warning): self
    {
        $this->warning = $warning;

        return $this;
    }

    public function setInfo(array $info): void
    {
        $this->info = $info;
    }

    public function addInfo(string $key, $value): self
    {
        $this->info[$key] = $value;

        return $this;
    }

    public function getInfo(string $key)
    {
        return $this->info[$key];
    }

    public function getAllInfo(): array
    {
        return $this->info;
    }
}
