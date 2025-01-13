<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Filesystem;

use Contao\CoreBundle\File\MetadataBag;
use Contao\CoreBundle\File\TextTrack;
use Contao\Image\ImportantPart;

/**
 * @experimental
 *
 *  This class acts as a collection for arbitrary Virtual Filesystem (VFS)
 *  metadata, that you get when calling getExtraMetadata() on a
 *  \Contao\CoreBundle\Filesystem\VirtualFilesystemInterface.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class ExtraMetadata implements \ArrayAccess
{
    /**
     * @param array<string, mixed> $extraMetadata
     */
    public function __construct(private array $extraMetadata = [])
    {
        $localizedMetadata = $extraMetadata['metadata'] ?? null;

        if ($localizedMetadata instanceof MetadataBag) {
            trigger_deprecation('contao/core-bundle', '5.5', 'Using the key "metadata" to set localized metadata has been deprecated and will no longer work in Contao 6. Use the key "localized" instead.');

            $this->extraMetadata['localized'] = $localizedMetadata;
            unset($this->extraMetadata['metadata']);
        }
    }

    public function get(string $key): mixed
    {
        $this->handleDeprecatedMetadataKey($key);

        return $this->extraMetadata[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->handleDeprecatedMetadataKey($key);

        $this->extraMetadata[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->extraMetadata;
    }

    /**
     * Returns a MetadataBag of localized metadata.
     *
     * This might only be available if the metadata was generated by the core's
     * default DBAFS implementation and the respective data is available.
     */
    public function getLocalized(): MetadataBag|null
    {
        return $this->get('localized');
    }

    /**
     * Sets a MetadataBag of localized metadata using the key "localized".
     */
    public function setLocalized(MetadataBag $localizedMetadata): void
    {
        $this->set('localized', $localizedMetadata);
    }

    /**
     * Returns the track of a video text track file.
     *
     * This might only be available if the metadata was generated by the core's
     * default DBAFS implementation and the respective data is available.
     */
    public function getTextTrack(): TextTrack|null
    {
        return $this->get('textTrack');
    }

    /**
     * Returns an ImportantPart of an image.
     *
     * This might only be available if the metadata was generated by the core's
     * default DBAFS implementation and the respective data is available.
     */
    public function getImportantPart(): ImportantPart|null
    {
        return $this->get('importantPart');
    }

    public function offsetExists(mixed $offset): bool
    {
        return \is_string($offset) && null !== $this->get($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return (\is_string($offset) ? $this->get($offset) : null) ??
            throw new \OutOfBoundsException(\sprintf('The key "%s" does not exist in this extra metadata bag.', $offset));
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Setting metadata is not supported in this extra metadata bag.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Unsetting metadata is not supported in this extra metadata bag.');
    }

    private function handleDeprecatedMetadataKey(string &$key): void
    {
        if ('metadata' === $key) {
            trigger_deprecation('contao/core-bundle', '5.5', 'Using the key "metadata" to get localized metadata has been deprecated and will no longer work in Contao 6. Use the key "localized" instead.');

            $key = 'localized';
        }
    }
}
