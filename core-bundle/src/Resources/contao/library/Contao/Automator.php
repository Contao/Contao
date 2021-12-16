<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCacheBundle\CacheManager;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Provide methods to run automated jobs.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class Automator extends System
{
	/**
	 * Make the constructor public
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Purge the search tables
	 */
	public function purgeSearchTables()
	{
		// The search indexer is disabled
		if (!System::getContainer()->has('contao.search.indexer'))
		{
			return;
		}

		$searchIndexer = System::getContainer()->get('contao.search.indexer');

		// Clear the index
		$searchIndexer->clear();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the search tables');
	}

	/**
	 * Purge the undo table
	 */
	public function purgeUndoTable()
	{
		$objDatabase = Database::getInstance();

		// Truncate the table
		$objDatabase->execute("TRUNCATE TABLE tl_undo");

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the undo table');
	}

	/**
	 * Purge the version table
	 */
	public function purgeVersionTable()
	{
		$objDatabase = Database::getInstance();

		// Truncate the table
		$objDatabase->execute("TRUNCATE TABLE tl_version");

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the version table');
	}

	/**
	 * Purge the system log
	 */
	public function purgeSystemLog()
	{
		$objDatabase = Database::getInstance();

		// Truncate the table
		$objDatabase->execute("TRUNCATE TABLE tl_log");

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the system log');
	}

	/**
	 * Purge the crawl queue
	 */
	public function purgeCrawlQueue()
	{
		$objDatabase = Database::getInstance();

		// Truncate the table
		$objDatabase->execute("TRUNCATE TABLE tl_crawl_queue");

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the crawl queue');
	}

	/**
	 * Purge the image cache
	 */
	public function purgeImageCache()
	{
		$container = System::getContainer();
		$strTargetPath = StringUtil::stripRootDir($container->getParameter('contao.image.target_dir'));
		$strRootDir = $container->getParameter('kernel.project_dir');

		// Walk through the subfolders
		foreach (Folder::scan($strRootDir . '/' . $strTargetPath) as $dir)
		{
			if (strncmp($dir, '.', 1) !== 0)
			{
				$objFolder = new Folder($strTargetPath . '/' . $dir);
				$objFolder->purge();
			}
		}

		// Also empty the shared cache so there are no links to deleted images
		$this->purgePageCache();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the image cache');
	}

	/**
	 * Purge the script cache
	 */
	public function purgeScriptCache()
	{
		// assets/js and assets/css
		foreach (array('assets/js', 'assets/css') as $dir)
		{
			// Purge the folder
			$objFolder = new Folder($dir);
			$objFolder->purge();
		}

		// Recreate the internal style sheets
		$this->import(StyleSheets::class, 'StyleSheets');
		$this->StyleSheets->updateStyleSheets();

		// Also empty the shared cache so there are no links to deleted scripts
		$this->purgePageCache();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the script cache');
	}

	/**
	 * Purge the shared cache
	 */
	public function purgePageCache()
	{
		$container = System::getContainer();

		if (!$container->has('fos_http_cache.cache_manager'))
		{
			System::getContainer()->get('contao.monolog.logger')->asContaoError()->error('Cannot purge the shared cache; invalid reverse proxy configuration');

			return;
		}

		/** @var CacheManager $cacheManager */
		$cacheManager = $container->get('fos_http_cache.cache_manager');

		if (!$cacheManager->supports(CacheInvalidator::CLEAR))
		{
			System::getContainer()->get('contao.monolog.logger')->asContaoError()->error('Cannot purge the shared cache; invalid reverse proxy configuration');

			return;
		}

		$cacheManager->clearCache();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the shared cache');
	}

	/**
	 * Purge the search cache
	 *
	 * @deprecated Deprecated since Contao 4.12, to be removed in Contao 5.0.
	 */
	public function purgeSearchCache()
	{
		trigger_deprecation('contao/core-bundle', '4.12', 'Using "Contao\Automator::purgeSearchCache()" has been deprecated and will no longer work in Contao 5.0.');

		$strCacheDir = StringUtil::stripRootDir(System::getContainer()->getParameter('kernel.cache_dir'));

		$objFolder = new Folder($strCacheDir . '/contao/search');
		$objFolder->purge();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the search cache');
	}

	/**
	 * Purge the internal cache
	 */
	public function purgeInternalCache()
	{
		$container = System::getContainer();

		$clearer = $container->get('contao.cache.clearer');
		$clearer->clear($container->getParameter('kernel.cache_dir'));

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the internal cache');
	}

	/**
	 * Purge the temp folder
	 */
	public function purgeTempFolder()
	{
		// Purge the folder
		$objFolder = new Folder('system/tmp');
		$objFolder->purge();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the temp folder');
	}

	/**
	 * Purge registrations that have not been activated within 24 hours
	 */
	public function purgeRegistrations()
	{
		$objMember = MemberModel::findExpiredRegistrations();

		if ($objMember === null)
		{
			return;
		}

		while ($objMember->next())
		{
			$objMember->delete();
		}

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the unactivated member registrations');
	}

	/**
	 * Purge opt-in tokens
	 */
	public function purgeOptInTokens()
	{
		$optIn = System::getContainer()->get('contao.opt_in');
		$optIn->purgeTokens();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Purged the expired double opt-in tokens');
	}

	/**
	 * Remove old XML files from the share directory
	 *
	 * @param boolean $blnReturn If true, only return the finds and don't delete
	 *
	 * @return array An array of old XML files
	 */
	public function purgeXmlFiles($blnReturn=false)
	{
		$arrFeeds = array();

		// HOOK: preserve third party feeds
		if (isset($GLOBALS['TL_HOOKS']['removeOldFeeds']) && \is_array($GLOBALS['TL_HOOKS']['removeOldFeeds']))
		{
			foreach ($GLOBALS['TL_HOOKS']['removeOldFeeds'] as $callback)
			{
				$this->import($callback[0]);
				$arrFeeds = array_merge($arrFeeds, $this->{$callback[0]}->{$callback[1]}());
			}
		}

		// Delete the old files
		if (!$blnReturn)
		{
			$shareDir = System::getContainer()->getParameter('contao.web_dir') . '/share';

			foreach (Folder::scan($shareDir) as $file)
			{
				if (is_dir($shareDir . '/' . $file))
				{
					continue; // see #6652
				}

				$objFile = new File(StringUtil::stripRootDir($shareDir) . '/' . $file);

				if ($objFile->extension == 'xml' && !\in_array($objFile->filename, $arrFeeds))
				{
					$objFile->delete();
				}
			}
		}

		return $arrFeeds;
	}

	/**
	 * Invalidate the cached XML sitemaps
	 *
	 * @param integer $intId The root page ID
	 */
	public function generateSitemap($intId=0)
	{
		$container = System::getContainer();

		if (!$container->has('fos_http_cache.cache_manager'))
		{
			return;
		}

		/** @var CacheManager $cacheManager */
		$cacheManager = $container->get('fos_http_cache.cache_manager');
		$tag = 'contao.sitemap';

		if ($intId > 0)
		{
			$tag .= '.' . $intId;
		}

		$cacheManager->invalidateTags(array($tag));
	}

	/**
	 * Regenerate the XML files
	 */
	public function generateXmlFiles()
	{
		// Sitemaps
		$this->generateSitemap();

		// HOOK: add custom jobs
		if (isset($GLOBALS['TL_HOOKS']['generateXmlFiles']) && \is_array($GLOBALS['TL_HOOKS']['generateXmlFiles']))
		{
			foreach ($GLOBALS['TL_HOOKS']['generateXmlFiles'] as $callback)
			{
				$this->import($callback[0]);
				$this->{$callback[0]}->{$callback[1]}();
			}
		}

		// Also empty the shared cache so there are no links to deleted files
		$this->purgePageCache();

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Regenerated the XML files');
	}

	/**
	 * Generate the symlinks in the public folder
	 */
	public function generateSymlinks()
	{
		$container = System::getContainer();

		$command = $container->get('contao.command.symlinks');
		$status = $command->run(new ArgvInput(array()), new NullOutput());

		if ($status > 0)
		{
			System::getContainer()->get('contao.monolog.logger')->asContaoError()->error('The symlinks could not be regenerated');
		}
		else
		{
			System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Regenerated the symlinks');
		}
	}

	/**
	 * Generate the internal cache
	 */
	public function generateInternalCache()
	{
		$container = System::getContainer();

		$warmer = $container->get('contao.cache.warmer');
		$warmer->warmUp($container->getParameter('kernel.cache_dir'));

		System::getContainer()->get('contao.monolog.logger')->asContaoCron()->log('Generated the internal cache');
	}

	/**
	 * Rotate the log files
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use the logger service instead, which rotates its log files automatically.
	 */
	public function rotateLogs()
	{
		trigger_deprecation('contao/core-bundle', '4.0', 'Using "Contao\Automator::rotateLogs()" has been deprecated and will no longer work in Contao 5.0. Use the logger service instead, which rotates its log files automatically.');

		$projectDir = System::getContainer()->getParameter('kernel.project_dir');
		$arrFiles = preg_grep('/\.log$/', Folder::scan($projectDir . '/system/logs'));

		foreach ($arrFiles as $strFile)
		{
			// Ignore Monolog log files (see #2579)
			if (preg_match('/-\d{4}-\d{2}-\d{2}\.log$/', $strFile))
			{
				continue;
			}

			$objFile = new File('system/logs/' . $strFile . '.9');

			// Delete the oldest file
			if ($objFile->exists())
			{
				$objFile->delete();
			}

			// Rotate the files (e.g. error.log.4 becomes error.log.5)
			for ($i=8; $i>0; $i--)
			{
				$strGzName = 'system/logs/' . $strFile . '.' . $i;

				if (file_exists($projectDir . '/' . $strGzName))
				{
					$objFile = new File($strGzName);
					$objFile->renameTo('system/logs/' . $strFile . '.' . ($i+1));
				}
			}

			// Add .1 to the latest file
			$objFile = new File('system/logs/' . $strFile);
			$objFile->renameTo('system/logs/' . $strFile . '.1');
		}
	}
}

class_alias(Automator::class, 'Automator');
