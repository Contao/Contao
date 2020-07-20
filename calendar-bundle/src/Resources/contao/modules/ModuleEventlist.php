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
use Patchwork\Utf8;

/**
 * Front end module "event list".
 *
 * @property bool   $cal_noSpan
 * @property string $cal_template
 * @property int    $cal_limit
 * @property string $cal_order
 * @property array  $cal_calendar
 * @property string $cal_format
 * @property bool   $cal_ignoreDynamic
 * @property int    $cal_readerModule
 * @property bool   $cal_hideRunning
 * @property string $cal_featured
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleEventlist extends Events
{
	/**
	 * Current date object
	 * @var Date
	 */
	protected $Date;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_eventlist';

	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventlist'][0]) . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		$this->cal_calendar = $this->sortOutProtected(StringUtil::deserialize($this->cal_calendar, true));

		// Return if there are no calendars
		if (empty($this->cal_calendar) || !\is_array($this->cal_calendar))
		{
			return '';
		}

		// Show the event reader if an item has been selected
		if ($this->cal_readerModule > 0  && (isset($_GET['events']) || (Config::get('useAutoItem') && isset($_GET['auto_item']))))
		{
			return $this->getFrontendModule($this->cal_readerModule, $this->strColumn);
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		global $objPage;

		$blnClearInput = false;

		$intYear = Input::get('year');
		$intMonth = Input::get('month');
		$intDay = Input::get('day');

		// Handle featured events
		$blnFeatured = null;

		if ($this->cal_featured == 'featured')
		{
			$blnFeatured = true;
		}
		elseif ($this->cal_featured == 'unfeatured')
		{
			$blnFeatured = false;
		}

		// Jump to the current period
		if (!isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']))
		{
			switch ($this->cal_format)
			{
				case 'cal_year':
					$intYear = date('Y');
					break;

				case 'cal_month':
					$intMonth = date('Ym');
					break;

				case 'cal_day':
					$intDay = date('Ymd');
					break;
			}

			$blnClearInput = true;
		}

		$blnDynamicFormat = (!$this->cal_ignoreDynamic && \in_array($this->cal_format, array('cal_day', 'cal_month', 'cal_year')));

		// Create the date object
		try
		{
			if ($blnDynamicFormat && $intYear)
			{
				$this->Date = new Date($intYear, 'Y');
				$this->cal_format = 'cal_year';
				$this->headline .= ' ' . date('Y', $this->Date->tstamp);
			}
			elseif ($blnDynamicFormat && $intMonth)
			{
				$this->Date = new Date($intMonth, 'Ym');
				$this->cal_format = 'cal_month';
				$this->headline .= ' ' . Date::parse('F Y', $this->Date->tstamp);
			}
			elseif ($blnDynamicFormat && $intDay)
			{
				$this->Date = new Date($intDay, 'Ymd');
				$this->cal_format = 'cal_day';
				$this->headline .= ' ' . Date::parse($objPage->dateFormat, $this->Date->tstamp);
			}
			else
			{
				$this->Date = new Date();
			}
		}
		catch (\OutOfBoundsException $e)
		{
			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		list($intStart, $intEnd, $strEmpty) = $this->getDatesFromFormat($this->Date, $this->cal_format);

		// Get all events
		$arrAllEvents = $this->getAllEvents($this->cal_calendar, $intStart, $intEnd, $blnFeatured);

		$sort = ($this->cal_order == 'descending') ? 'krsort' : 'ksort';

		// Sort the days
		$sort($arrAllEvents);

		// Sort the events
		foreach (array_keys($arrAllEvents) as $key)
		{
			$sort($arrAllEvents[$key]);
		}

		$arrEvents = array();

		// Remove events outside the scope
		foreach ($arrAllEvents as $key=>$days)
		{
			foreach ($days as $day=>$events)
			{
				// Skip events before the start day if the "shortened view" option is not set.
				// Events after the end day are filtered in the Events::addEvent() method (see #8782).
				if (!$this->cal_noSpan && $day < $intStart)
				{
					continue;
				}

				foreach ($events as $event)
				{
					// Use repeatEnd if > 0 (see #8447)
					if ($event['startTime'] > $intEnd || ($event['repeatEnd'] ?: $event['endTime']) < $intStart)
					{
						continue;
					}

					// Hide running events
					if ($this->cal_hideRunning && $event['begin'] < $intStart)
					{
						continue;
					}

					// Skip occurrences in the past
					if ($event['repeatEnd'] && $event['end'] < $intStart)
					{
						continue;
					}

					// Hide running non-recurring events (see #30)
					if ($this->cal_hideRunning && !$event['recurring'] && $event['startTime'] < time())
					{
						continue;
					}

					$event['firstDay'] = $GLOBALS['TL_LANG']['DAYS'][date('w', $day)];
					$event['firstDate'] = Date::parse($objPage->dateFormat, $day);

					$arrEvents[] = $event;
				}
			}
		}

		unset($arrAllEvents);
		$total = \count($arrEvents);
		$limit = $total;
		$offset = 0;

		// Overall limit
		if ($this->cal_limit > 0)
		{
			$total = min($this->cal_limit, $total);
			$limit = $total;
		}

		// Pagination
		if ($this->perPage > 0)
		{
			$id = 'page_e' . $this->id;
			$page = Input::get($id) ?? 1;

			// Do not index or cache the page if the page number is outside the range
			if ($page < 1 || $page > max(ceil($total/$this->perPage), 1))
			{
				throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
			}

			$offset = ($page - 1) * $this->perPage;
			$limit = min($this->perPage + $offset, $total);

			$objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
			$this->Template->pagination = $objPagination->generate("\n  ");
		}

		$strMonth = '';
		$strDate = '';
		$strEvents = '';
		$dayCount = 0;
		$eventCount = 0;
		$headerCount = 0;
		$imgSize = false;

		// Override the default image size
		if ($this->imgSize != '')
		{
			$size = StringUtil::deserialize($this->imgSize);

			if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2]) || ($size[2][0] ?? null) === '_')
			{
				$imgSize = $this->imgSize;
			}
		}

		$projectDir = System::getContainer()->getParameter('kernel.project_dir');
		$uuids = array();

		for ($i=$offset; $i<$limit; $i++)
		{
			if ($arrEvents[$i]['addImage'] && $arrEvents[$i]['singleSRC'] != '')
			{
				$uuids[] = $arrEvents[$i]['singleSRC'];
			}
		}

		// Preload all images in one query so they are loaded into the model registry
		FilesModel::findMultipleByUuids($uuids);

		// Parse events
		for ($i=$offset; $i<$limit; $i++)
		{
			$event = $arrEvents[$i];
			$blnIsLastEvent = false;

			// Last event on the current day
			if (($i+1) == $limit || !isset($arrEvents[($i+1)]['firstDate']) || $event['firstDate'] != $arrEvents[($i+1)]['firstDate'])
			{
				$blnIsLastEvent = true;
			}

			$objTemplate = new FrontendTemplate($this->cal_template ?: 'event_list');
			$objTemplate->setData($event);

			// Month header
			if ($strMonth != $event['month'])
			{
				$objTemplate->newMonth = true;
				$strMonth = $event['month'];
			}

			// Day header
			if ($strDate != $event['firstDate'])
			{
				$headerCount = 0;
				$objTemplate->header = true;
				$objTemplate->classHeader = ((($dayCount % 2) == 0) ? ' even' : ' odd') . (($dayCount == 0) ? ' first' : '') . (($event['firstDate'] == $arrEvents[($limit-1)]['firstDate']) ? ' last' : '');
				$strDate = $event['firstDate'];

				++$dayCount;
			}

			// Show the teaser text of redirect events (see #6315)
			if (\is_bool($event['details']) && $event['source'] == 'default')
			{
				$objTemplate->hasDetails = false;
			}

			// Add the template variables
			$objTemplate->classList = $event['class'] . ((($headerCount % 2) == 0) ? ' even' : ' odd') . (($headerCount == 0) ? ' first' : '') . ($blnIsLastEvent ? ' last' : '') . ' cal_' . $event['parent'];
			$objTemplate->classUpcoming = $event['class'] . ((($eventCount % 2) == 0) ? ' even' : ' odd') . (($eventCount == 0) ? ' first' : '') . ((($offset + $eventCount + 1) >= $limit) ? ' last' : '') . ' cal_' . $event['parent'];
			$objTemplate->readMore = StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $event['title']));
			$objTemplate->more = $GLOBALS['TL_LANG']['MSC']['more'];
			$objTemplate->locationLabel = $GLOBALS['TL_LANG']['MSC']['location'];

			// Short view
			if ($this->cal_noSpan)
			{
				$objTemplate->day = $event['day'];
				$objTemplate->date = $event['date'];
			}
			else
			{
				$objTemplate->day = $event['firstDay'];
				$objTemplate->date = $event['firstDate'];
			}

			$objTemplate->addImage = false;

			// Add an image
			if ($event['addImage'] && $event['singleSRC'] != '')
			{
				$objModel = FilesModel::findByUuid($event['singleSRC']);

				if ($objModel !== null && is_file($projectDir . '/' . $objModel->path))
				{
					if ($imgSize)
					{
						$event['size'] = $imgSize;
					}

					$event['singleSRC'] = $objModel->path;
					$this->addImageToTemplate($objTemplate, $event, null, null, $objModel);

					// Link to the event if no image link has been defined
					if (!$objTemplate->fullsize && !$objTemplate->imageUrl)
					{
						// Unset the image title attribute
						$picture = $objTemplate->picture;
						unset($picture['title']);
						$objTemplate->picture = $picture;

						// Link to the event
						$objTemplate->linkTitle = $objTemplate->readMore;
					}
				}
			}

			$objTemplate->enclosure = array();

			// Add enclosure
			if ($event['addEnclosure'])
			{
				$this->addEnclosuresToTemplate($objTemplate, $event);
			}

			$strEvents .= $objTemplate->parse();

			++$eventCount;
			++$headerCount;
		}

		// No events found
		if ($strEvents == '')
		{
			$strEvents = "\n" . '<div class="empty">' . $strEmpty . '</div>' . "\n";
		}

		// See #3672
		$this->Template->headline = $this->headline;
		$this->Template->events = $strEvents;
		$this->Template->eventCount = $eventCount;

		// Clear the $_GET array (see #2445)
		if ($blnClearInput)
		{
			Input::setGet('year', null);
			Input::setGet('month', null);
			Input::setGet('day', null);
		}
	}
}

class_alias(ModuleEventlist::class, 'ModuleEventlist');
