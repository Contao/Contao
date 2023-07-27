<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Model\Collection;

/**
 * Front end module "news archive".
 *
 * @property array  $news_archives
 * @property string $news_jumpToCurrent
 * @property string $news_format
 * @property string $news_order
 * @property int    $news_readerModule
 */
class ModuleNewsArchive extends ModuleNews
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_newsarchive';

	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['newsarchive'][0] . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

			return $objTemplate->parse();
		}

		$this->news_archives = $this->sortOutProtected(StringUtil::deserialize($this->news_archives));

		// No news archives available
		if (empty($this->news_archives) || !\is_array($this->news_archives))
		{
			return '';
		}

		// Show the news reader if an item has been selected
		if ($this->news_readerModule > 0 && (isset($_GET['items']) || (Config::get('useAutoItem') && isset($_GET['auto_item']))))
		{
			return $this->getFrontendModule($this->news_readerModule, $this->strColumn);
		}

		// Hide the module if no period has been selected
		if ($this->news_jumpToCurrent == 'hide_module' && !isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']))
		{
			return '';
		}

		// Tag the news archives (see #2137)
		if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger'))
		{
			$responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
			$responseTagger->addTags(array_map(static function ($id) { return 'contao.db.tl_news_archive.' . $id; }, $this->news_archives));
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		/** @var PageModel $objPage */
		global $objPage;

		$limit = null;
		$offset = 0;
		$intBegin = 0;
		$intEnd = 0;

		$intYear = (int) Input::get('year');
		$intMonth = (int) Input::get('month');
		$intDay = (int) Input::get('day');

		// Jump to the current period
		if (!isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']) && $this->news_jumpToCurrent != 'all_items')
		{
			switch ($this->news_format)
			{
				case 'news_year':
					$intYear = date('Y');
					break;

				default:
				case 'news_month':
					$intMonth = date('Ym');
					break;

				case 'news_day':
					$intDay = date('Ymd');
					break;
			}
		}

		// Create the date object
		try
		{
			if ($intYear)
			{
				$strDate = $intYear;
				$objDate = new Date($strDate, 'Y');
				$intBegin = $objDate->yearBegin;
				$intEnd = $objDate->yearEnd;
				$this->headline .= ' ' . date('Y', $objDate->tstamp);
			}
			elseif ($intMonth)
			{
				$strDate = $intMonth;
				$objDate = new Date($strDate, 'Ym');
				$intBegin = $objDate->monthBegin;
				$intEnd = $objDate->monthEnd;
				$this->headline .= ' ' . Date::parse('F Y', $objDate->tstamp);
			}
			elseif ($intDay)
			{
				$strDate = $intDay;
				$objDate = new Date($strDate, 'Ymd');
				$intBegin = $objDate->dayBegin;
				$intEnd = $objDate->dayEnd;
				$this->headline .= ' ' . Date::parse($objPage->dateFormat, $objDate->tstamp);
			}
			elseif ($this->news_jumpToCurrent == 'all_items')
			{
				$intBegin = 0; // 1970-01-01 00:00:00
				$intEnd = min(4294967295, PHP_INT_MAX); // 2106-02-07 07:28:15
			}
		}
		catch (\OutOfBoundsException $e)
		{
			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		$this->Template->articles = array();

		$limit = 0;
		$offset = 0;
		// Split the result
		if ($this->perPage > 0)
		{
			// Get the total number of items
			$intTotal = $this->countItems($this->news_archives, $intBegin, $intEnd);

			if ($intTotal > 0)
			{
				$total = $intTotal;

				// Get the current page
				$id = 'page_a' . $this->id;
				$page = (int) (Input::get($id) ?? 1);

				// Do not index or cache the page if the page number is outside the range
				if ($page < 1 || $page > max(ceil($total/$this->perPage), 1))
				{
					throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
				}

				// Set limit and offset
				$limit = $this->perPage;
				$offset = (max($page, 1) - 1) * $this->perPage;

				// Add the pagination menu
				$objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
				$this->Template->pagination = $objPagination->generate("\n  ");
			}
		}

		// Get the news items
		$objArticles = $this->fetchItems($this->news_archives, $intBegin, $intEnd, $limit, $offset);

		// Add the articles
		if ($objArticles !== null)
		{
			$this->Template->articles = $this->parseArticles($objArticles);
		}

		$this->Template->headline = trim($this->headline);
		$this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
		$this->Template->empty = $GLOBALS['TL_LANG']['MSC']['empty'];
	}

	/**
	 *  Count the total matching items
	 *
	 * @param array $newsArchives
	 * @param integer$intBegin
	 * @param integer$intEnd
	 *
	 * @return int|null
	 */
	protected function countItems($newsArchives, $intBegin, $intEnd)
	{
		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['newsArchiveCountItems']) && \is_array($GLOBALS['TL_HOOKS']['newsArchiveCountItems']))
		{
			foreach ($GLOBALS['TL_HOOKS']['newsArchiveCountItems'] as $callback)
			{
				if (($intResult = System::importStatic($callback[0])->{$callback[1]}($newsArchives, $intBegin, $intEnd, $this)) === false)
				{
					continue;
				}

				if (\is_int($intResult))
				{
					return $intResult;
				}
			}
		}

		return NewsModel::countPublishedFromToByPids($intBegin, $intEnd, $newsArchives);
	}

	/**
	 * Fetch the matching items
	 *
	 * @param array   $newsArchives
	 * @param integer $intBegin
	 * @param integer $intEnd
	 * @param integer $limit
	 * @param integer $offset
	 *
	 * @return Collection|NewsModel|NewsModel[]|null
	 */
	protected function fetchItems($newsArchives, $intBegin, $intEnd, $limit, $offset)
	{
		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['newsListFetchItems']) && \is_array($GLOBALS['TL_HOOKS']['newsListFetchItems']))
		{
			foreach ($GLOBALS['TL_HOOKS']['newsListFetchItems'] as $callback)
			{
				if (($objCollection = System::importStatic($callback[0])->{$callback[1]}($newsArchives, $intBegin, $intEnd, $limit, $offset, $this)) === false)
				{
					continue;
				}

				if ($objCollection === null || $objCollection instanceof Collection)
				{
					return $objCollection;
				}
			}
		}

		// Determine sorting
		$t = NewsModel::getTable();

		switch ($this->news_order)
		{
			case 'order_headline_asc':
				$order = "$t.headline";
				break;

			case 'order_headline_desc':
				$order = "$t.headline DESC";
				break;

			case 'order_random':
				$order = "RAND()";
				break;

			case 'order_date_asc':
				$order = "$t.date";
				break;

			default:
				$order = "$t.date DESC";
		}

		return NewsModel::findPublishedFromToByPids($intBegin, $intEnd, $newsArchives, $limit, $offset, array('order' => $order));
	}
}

class_alias(ModuleNewsArchive::class, 'ModuleNewsArchive');
