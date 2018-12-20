<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\OptIn;

interface OptInInterface
{
    /**
     * Creates a new double opt-in token.
     */
    public function create(string $prefix, string $email, array $related): OptInTokenInterface;

    /**
     * Finds a double opt-in token by its identifier.
     */
    public function find(string $identifier): ?OptInTokenInterface;

    /**
     * Purges expired tokens.
     */
    public function purgeTokens(): void;
}
