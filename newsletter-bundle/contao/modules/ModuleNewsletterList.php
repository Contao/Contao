<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Symfony\Component\Routing\Exception\ExceptionInterface;

/**
 * Front end module "newsletter list".
 *
 * @property array $nl_channels
 */
class ModuleNewsletterList extends Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_newsletterlist';

	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		$container = System::getContainer();
		$request = $container->get('request_stack')->getCurrentRequest();

		if ($request && $container->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['newsletterlist'][0] . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = StringUtil::specialcharsUrl($container->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

			return $objTemplate->parse();
		}

		$this->nl_channels = StringUtil::deserialize($this->nl_channels);

		// Return if there are no channels
		if (empty($this->nl_channels) || !\is_array($this->nl_channels))
		{
			return '';
		}

		// Tag the channels (see #2137)
		if ($container->has('fos_http_cache.http.symfony_response_tagger'))
		{
			$responseTagger = $container->get('fos_http_cache.http.symfony_response_tagger');
			$responseTagger->addTags(array_map(static function ($id) { return 'contao.db.tl_newsletter_channel.' . $id; }, $this->nl_channels));
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$arrNewsletter = array();

		$strRequest = StringUtil::ampersand(Environment::get('requestUri'));
		$objNewsletter = NewsletterModel::findSentByPids($this->nl_channels);
		$container = System::getContainer();
		$objPage = $container->get('contao.routing.page_finder')->getCurrentPage();

		if ($objNewsletter !== null)
		{
			$tags = array();

			while ($objNewsletter->next())
			{
				try
				{
					$strUrl = System::getContainer()->get('contao.routing.content_url_generator')->generate($objNewsletter->current());
				}
				catch (ExceptionInterface)
				{
					$strUrl = $strRequest;
				}

				$arrNewsletter[] = array
				(
					'subject' => $objNewsletter->subject,
					'title' => StringUtil::stripInsertTags($objNewsletter->subject),
					'href' => $strUrl,
					'date' => Date::parse($objPage->dateFormat, $objNewsletter->date),
					'datim' => Date::parse($objPage->datimFormat, $objNewsletter->date),
					'time' => Date::parse($objPage->timeFormat, $objNewsletter->date),
					'channel' => $objNewsletter->pid
				);

				$tags[] = 'contao.db.tl_newsletter.' . $objNewsletter->id;
			}

			// Tag the newsletters (see #2137)
			if ($container->has('fos_http_cache.http.symfony_response_tagger'))
			{
				$responseTagger = $container->get('fos_http_cache.http.symfony_response_tagger');
				$responseTagger->addTags($tags);
			}
		}

		$this->Template->newsletters = $arrNewsletter;
	}
}
