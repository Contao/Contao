<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Search\Backend;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Reindex\ReindexConfig as SealReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use CmsIg\Seal\Schema\Field\IdentifierField;
use CmsIg\Seal\Schema\Field\TextField;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;
use CmsIg\Seal\Search\Condition\EqualCondition;
use CmsIg\Seal\Search\Condition\SearchCondition;
use CmsIg\Seal\Search\SearchBuilder;
use Contao\CoreBundle\Event\BackendSearch\EnhanceHitEvent;
use Contao\CoreBundle\Event\BackendSearch\IndexDocumentEvent;
use Contao\CoreBundle\Messenger\Message\BackendSearch\DeleteDocumentsMessage;
use Contao\CoreBundle\Messenger\Message\BackendSearch\ReindexMessage;
use Contao\CoreBundle\Messenger\WebWorker;
use Contao\CoreBundle\Search\Backend\Provider\ProviderInterface;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental
 */
class BackendSearch implements ReindexProviderInterface
{
    public const SEAL_TYPE_NAME = 'contao_backend_search';

    /**
     * @param iterable<ProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly Security $security,
        private readonly EngineInterface $engine,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly WebWorker $webWorker,
        private readonly string $indexName,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->webWorker->hasCliWorkersRunning();
    }

    public function deleteDocuments(GroupedDocumentIds $groupedDocumentIds, bool $async = true): self
    {
        if ($groupedDocumentIds->isEmpty()) {
            return $this;
        }

        if ($async) {
            $this->messageBus->dispatch(new DeleteDocumentsMessage($groupedDocumentIds));

            return $this;
        }

        $documentIds = [];

        foreach ($groupedDocumentIds->getTypes() as $type) {
            foreach ($groupedDocumentIds->getDocumentIdsForType($type) as $id) {
                $documentIds[] = $this->getGlobalIdForTypeAndDocumentId($type, $id);
            }
        }

        $this->engine->bulk($this->indexName, [], $documentIds);

        return $this;
    }

    public function reindex(ReindexConfig $config, bool $async = true): self
    {
        if ($async) {
            $this->messageBus->dispatch(new ReindexMessage($config));

            return $this;
        }

        $this->engine->reindex([$this], $this->internalReindexConfigToSealReindexConfig($config));

        return $this;
    }

    /**
     * TODO: This Query API object will change for sure because we might want to
     * introduce searching for multiple tags which is currently not supported by SEAL.
     * It's a matter of putting in some work there but it will affect the signature of
     * this object.
     */
    public function search(Query $query): Result
    {
        $sb = $this->createSearchBuilder($query);

        $hits = [];
        $hitCount = 0;
        $offset = 0;
        $limit = $query->getPerPage();

        // Stop after 10 iterations
        for ($i = 0; $i <= 10; ++$i) {
            /** @var array $document */
            foreach ($sb->offset($offset)->limit($limit)->getResult() as $document) {
                $hit = $this->convertSearchDocumentToProviderHit($document);

                if (!$hit) {
                    continue;
                }

                $hits[] = $hit;
                ++$hitCount;

                if ($hitCount >= $limit) {
                    break 2;
                }
            }

            $offset += $limit;
        }

        return new Result($hits);
    }

    public static function getSearchEngineSchema(string $indexName): Schema
    {
        return new Schema([
            self::SEAL_TYPE_NAME => new Index($indexName, [
                'id' => new IdentifierField('id'),
                'type' => new TextField('type', searchable: false, filterable: true),
                'searchableContent' => new TextField('searchableContent', searchable: true),
                'tags' => new TextField('tags', multiple: true, searchable: false, filterable: true),
                'document' => new TextField('document', searchable: false),
            ]),
        ]);
    }

    public function clear(): void
    {
        // TODO: We need an API for that in SEAL
        $this->engine->dropIndex($this->indexName);
        $this->engine->createIndex($this->indexName);
    }

    public function total(): int|null
    {
        return null;
    }

    public function provide(SealReindexConfig $reindexConfig): \Generator
    {
        // Not our index
        if (null !== $reindexConfig->getIndex() && self::SEAL_TYPE_NAME !== $reindexConfig->getIndex()) {
            return;
        }

        $internalConfig = $this->sealReindexConfigToInternalReindexConfig($reindexConfig);

        // In case the re-index was limited to a given set of document IDs, we check if all of
        // those still exist. If not, we need to delete the ones that have been removed.
        $trackDeletedDocumentIds = clone $internalConfig->getLimitedDocumentIds();

        /** @var ProviderInterface $provider */
        foreach ($this->providers as $provider) {
            /** @var Document $document */
            foreach ($provider->updateIndex($internalConfig) as $document) {
                $event = new IndexDocumentEvent($document);
                $this->eventDispatcher->dispatch($event);

                if (!$document = $event->getDocument()) {
                    continue;
                }


                // This document is still used, remove from tracking
                $trackDeletedDocumentIds->removeIdFromType($document->getType(), $document->getId());

                yield $document;
            }
        }

        $this->deleteDocuments($trackDeletedDocumentIds);
    }

    public static function getIndex(): string
    {
        return self::SEAL_TYPE_NAME;
    }

    private function createSearchBuilder(Query $query): SearchBuilder
    {
        $sb = $this->engine->createSearchBuilder($this->indexName);

        if ($query->getKeywords()) {
            $sb->addFilter(new SearchCondition($query->getKeywords()));
        }

        if ($query->getType()) {
            $sb->addFilter(new EqualCondition('type', $query->getType()));
        }

        if ($query->getTag()) {
            $sb->addFilter(new EqualCondition('tags', $query->getTag()));
        }

        return $sb;
    }

    private function convertSearchDocumentToProviderHit(array $document): Hit|null
    {
        $fileProvider = $this->getProviderForType($document['type'] ?? '');

        if (!$fileProvider) {
            return null;
        }

        $document = Document::fromArray(json_decode($document['document'], true, 512, JSON_THROW_ON_ERROR));
        $hit = $fileProvider->convertDocumentToHit($document);

        // The provider did not find any hit for it anymore so it must have been removed
        // or expired. Remove from the index.
        if (!$hit) {
            $this->deleteDocuments(new GroupedDocumentIds([$document->getType() => [$document->getId()]]));

            return null;
        }

        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_BACKEND_SEARCH_HIT, $hit)) {
            return null;
        }

        $event = new EnhanceHitEvent($hit);
        $this->eventDispatcher->dispatch($event);

        return $event->getHit();
    }

    private function getProviderForType(string $type): ProviderInterface|null
    {
        /** @var ProviderInterface $provider */
        foreach ($this->providers as $provider) {
            if ($provider->supportsType($type)) {
                return $provider;
            }
        }

        return null;
    }

    private function convertProviderDocumentForSearchIndex(Document $document): array
    {
        return [
            'id' => $this->getGlobalIdForTypeAndDocumentId($document->getType(), $document->getId()),
            'type' => $document->getType(),
            'searchableContent' => $document->getSearchableContent(),
            'tags' => $document->getTags(),
            'document' => json_encode($document->toArray(), JSON_THROW_ON_ERROR),
        ];
    }

    private function getGlobalIdForTypeAndDocumentId(string $type, string $id): string
    {
        // Ensure the ID is global across the search index by prefixing the id
        return $type.'_'.$id;
    }

    private function internalReindexConfigToSealReindexConfig(ReindexConfig $reindexConfig): SealReindexConfig
    {
        $sealConfig = new SealReindexConfig();

        if ($reindexConfig->getUpdateSince()) {
            $sealConfig = $sealConfig->withDateTimeBoundary($reindexConfig->getUpdateSince());
        }

        if (!$reindexConfig->getLimitedDocumentIds()->isEmpty()) {
            $globalIdentifiers = [];

            foreach ($reindexConfig->getLimitedDocumentIds()->getTypes() as $type) {
                foreach ($reindexConfig->getLimitedDocumentIds()->getDocumentIdsForType($type) as $documentId) {
                    $globalIdentifiers[] = $this->getGlobalIdForTypeAndDocumentId($type, $documentId);
                }
            }

            $sealConfig = $sealConfig->withIdentifiers($globalIdentifiers);
        }

        return $sealConfig;
    }

    private function sealReindexConfigToInternalReindexConfig(SealReindexConfig $sealReindexConfig): ReindexConfig
    {
        $internalConfig = new ReindexConfig();

        if (null !== $sealReindexConfig->getDateTimeBoundary()) {
            $internalConfig = $internalConfig->limitToDocumentsNewerThan($sealReindexConfig->getDateTimeBoundary());
        }

        if ([] !== $sealReindexConfig->getIdentifiers()) {
            $groupedDocumentIds = new GroupedDocumentIds();

            foreach ($sealReindexConfig->getIdentifiers() as $globalIdentifier) {
                [$type, $documentId] = explode('__', $globalIdentifier, 2);

                if (!\is_string($type) || !\is_string($documentId) || '' === $type || '' === $documentId) {
                    continue;
                }

                $groupedDocumentIds->addIdToType($type, $documentId);
            }

            $internalConfig = $internalConfig->limitToDocumentIds($groupedDocumentIds);
        }

        return $internalConfig;
    }
}
