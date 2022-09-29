<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\Pagination;
use Contao\StringUtil;
use FeedIo\Feed;
use FeedIo\Feed\Item;
use FeedIo\FeedInterface;
use FeedIo\FeedIo;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @FrontendModule(category="miscellaneous")
 */
class FeedReaderController extends AbstractFrontendModuleController
{
    public function __construct(private readonly FeedIo $feedIo, private readonly LoggerInterface $logger, private readonly CacheInterface $cache)
    {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $this->initializeContaoFramework();

        $feeds = [];

        foreach (StringUtil::trimsplit('[\n\t ]', trim($model->feed_urls)) as $url) {
            try {
                $feed = $this->cache->get('feed_reader_'.$model->id.'_'.md5($url), function (ItemInterface $item) use ($url, $model) {
                    $readerResult = $this->feedIo->read($url, new Feed());

                    if ($model->feed_cache > 0) {
                        $item->expiresAfter($model->feed_cache);
                    }

                    return $readerResult->getFeed();
                });
            } catch (\Exception $exception) {
                $feed = null;
                $this->logger->error(sprintf('Could not read feed %s: %s', $url, $exception->getMessage()));
                continue;
            }

            if ($feed instanceof FeedInterface) {
                $feeds[] = $feed;
            }
        }

        $allItems = [];

        foreach ($feeds as $feed) {
            $feedItems = \array_slice([...$feed], $model->skipFirst, $model->numberOfItems ?: null);
            $allItems = [...$feedItems, ...$allItems];
        }

        uasort($allItems, [$this, 'sortItems']);

        $offset = 0;
        $limit = \count($allItems);

        if ($model->perPage > 0) {
            $param = 'page_r'.$model->id;
            $page = (int) $request->query->get($param, 1);
            $config = $this->container->get('contao.framework')->getAdapter(Config::class);

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil(\count($allItems) / $model->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
            }

            // Set limit and offset
            $offset = ($page - 1) * $model->perPage;
            $limit = $model->perPage + $offset;

            $pagination = new Pagination(\count($allItems), $model->perPage, $config->get('maxPaginationLinks'), $param);
            $template->set('pagination', $pagination->getContextForTwigComponent($request));
        }

        $items = [];

        for ($i = $offset, $c = \count($allItems); $i < $limit && $i < $c; ++$i) {
            $items[] = $allItems[$i];
        }

        $template->set('feeds', $feeds);
        $template->set('items', $items);

        return $template->getResponse();
    }

    private function sortItems(Item $a, Item $b): int
    {
        $aDate = $a->getLastModified();
        $bDate = $b->getLastModified();

        if ($aDate && $bDate) {
            return $aDate > $bDate ? -1 : 1;
        }

        // Sort items without dates to the top.
        if ($aDate) {
            return 1;
        }

        if ($bDate) {
            return -1;
        }

        return 0;
    }
}
