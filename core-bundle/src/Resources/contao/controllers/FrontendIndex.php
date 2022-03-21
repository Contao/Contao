<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\InsufficientAuthenticationException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Util\LocaleUtil;
use Symfony\Component\HttpFoundation\Response;

/**
 * Main front end controller.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class FrontendIndex extends Frontend
{
	/**
	 * Initialize the object
	 */
	public function __construct()
	{
		// Load the user object before calling the parent constructor
		$this->import(FrontendUser::class, 'User');
		parent::__construct();
	}

	/**
	 * Render a page
	 *
	 * @return Response
	 *
	 * @throws \LogicException
	 * @throws PageNotFoundException
	 * @throws AccessDeniedException
	 */
	public function renderPage(PageModel $pageModel)
	{
		/** @var PageModel $objPage */
		global $objPage;

		$objPage = $pageModel;

		// If the page has an alias, it can no longer be called via ID (see #7661)
		if ($objPage->alias)
		{
			$language = $objPage->urlPrefix ? preg_quote($objPage->urlPrefix . '/', '#') : '';
			$suffix = preg_quote($objPage->urlSuffix, '#');

			if (preg_match('#^' . $language . $objPage->id . '(' . $suffix . '($|\?)|/)#', Environment::get('relativeRequest')))
			{
				throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
			}
		}

		// Inherit the settings from the parent pages
		$objPage->loadDetails();
		$blnShowUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();

		// Trigger the 404 page if the page is not published and the front end preview is not active (see #374)
		if (!$blnShowUnpublished && !$objPage->isPublic)
		{
			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		// Load a website root page object (will redirect to the first active regular page)
		if ($objPage->type == 'root')
		{
			/** @var PageRoot $objHandler */
			$objHandler = new $GLOBALS['TL_PTY']['root']();
			$objHandler->generate($objPage->id);
		}

		// Set the admin e-mail address
		if ($objPage->adminEmail)
		{
			list($GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) = StringUtil::splitFriendlyEmail($objPage->adminEmail);
		}
		else
		{
			list($GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) = StringUtil::splitFriendlyEmail(Config::get('adminEmail'));
		}

		// Exit if the root page has not been published (see #2425)
		// Do not try to load the 404 page, it can cause an infinite loop!
		if (!$blnShowUnpublished && !$objPage->rootIsPublic)
		{
			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		// Check whether the language matches the root page language
		if (isset($_GET['language']) && $objPage->urlPrefix && Input::get('language') != LocaleUtil::formatAsLanguageTag($objPage->rootLanguage))
		{
			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		// Check whether there are domain name restrictions
		if ($objPage->domain && $objPage->domain != Environment::get('host'))
		{
			System::getContainer()->get('monolog.logger.contao.error')->error('Page ID "' . $objPage->id . '" was requested via "' . Environment::get('host') . '" but can only be accessed via "' . $objPage->domain . '" (' . Environment::get('base') . Environment::get('request') . ')');

			throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
		}

		// Authenticate the user if the page is protected
		if ($objPage->protected)
		{
			$security = System::getContainer()->get('security.helper');

			if (!$security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $objPage->groups))
			{
				if (($token = $security->getToken()) === null || System::getContainer()->get('security.authentication.trust_resolver')->isAnonymous($token))
				{
					throw new InsufficientAuthenticationException('Not authenticated: ' . Environment::get('uri'));
				}

				$user = $security->getUser();

				if ($user instanceof FrontendUser)
				{
					System::getContainer()->get('monolog.logger.contao.error')->error('Page ID "' . $objPage->id . '" can only be accessed by groups "' . implode(', ', $objPage->groups) . '" (current user groups: ' . implode(', ', StringUtil::deserialize($user->groups, true)) . ')');
				}

				throw new AccessDeniedException('Access denied: ' . Environment::get('uri'));
			}
		}

		// Backup some globals (see #7659)
		$arrHead = $GLOBALS['TL_HEAD'] ?? null;
		$arrBody = $GLOBALS['TL_BODY'] ?? null;
		$arrMootools = $GLOBALS['TL_MOOTOOLS'] ?? null;
		$arrJquery = $GLOBALS['TL_JQUERY'] ?? null;

		try
		{
			$pageType = $GLOBALS['TL_PTY'][$objPage->type] ?? PageRegular::class;
			$objHandler = new $pageType();

			// Backwards compatibility
			if (!method_exists($objHandler, 'getResponse'))
			{
				ob_start();

				try
				{
					$objHandler->generate($objPage, true);
					$objResponse = new Response(ob_get_contents(), http_response_code());
				}
				finally
				{
					ob_end_clean();
				}

				return $objResponse;
			}

			return $objHandler->getResponse($objPage, true);
		}

		// Render the error page (see #5570)
		catch (UnusedArgumentsException $e)
		{
			// Restore the globals (see #7659)
			$GLOBALS['TL_HEAD'] = $arrHead;
			$GLOBALS['TL_BODY'] = $arrBody;
			$GLOBALS['TL_MOOTOOLS'] = $arrMootools;
			$GLOBALS['TL_JQUERY'] = $arrJquery;

			throw $e;
		}
	}
}
