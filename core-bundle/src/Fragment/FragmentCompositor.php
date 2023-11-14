<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Fragment;

use Contao\ContentModel;
use Contao\CoreBundle\Fragment\Reference\ContentElementReference;
use Contao\Model\Registry;

class FragmentCompositor
{
    private array $slotsByIdentifier = [];

    /**
     * @internal Do not inherit from this class; decorate the "contao.fragment.compositor" service instead
     */
    public function __construct()
    {
    }

    public function add(string $identifier, array $slots): void
    {
        $this->slotsByIdentifier[$identifier] = $slots;
    }

    public function isNested(string $identifier): bool
    {
        return isset($this->slotsByIdentifier[$identifier]);
    }

    public function getNestedContentElements(string $identifier, int $id): array
    {
        if (!isset($this->slotsByIdentifier[$identifier])) {
            return [];
        }

        $children = ContentModel::findPublishedByPidAndTable($id, ContentModel::getTable());

        $rendered = [];

        foreach (array_keys($this->slotsByIdentifier[$identifier]) as $slot) {
            if ('default' !== $slot) {
                throw new \InvalidArgumentException(sprintf('Only the slot "default" is supported, got "%s".', $slot));
            }

            $rendered[$slot] = [];
        }

        foreach ($children ?? [] as $child) {
            if (
                !empty($this->slotsByIdentifier[$identifier]['default']['allowedTypes'])
                && !\in_array($child->type, $this->slotsByIdentifier[$identifier]['default']['allowedTypes'], true)
            ) {
                continue;
            }

            $rendered['default'][] = new ContentElementReference(
                $child,
                'main',
                [],
                !Registry::getInstance()->isRegistered($child),
                $this->getNestedContentElements(ContentElementReference::TAG_NAME.'.'.$child->type, $child->id),
            );
        }

        return $rendered;
    }
}
