<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

/**
 * Content element "YouTube".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ContentYouTube extends ContentElement
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'ce_youtube';

	/**
	 * Show the YouTube link in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		if ($this->youtube == '')
		{
			return '';
		}

		if (TL_MODE == 'BE')
		{
			$return = '<p><a href="https://youtu.be/' . $this->youtube . '" target="_blank" rel="noreferrer noopener">youtu.be/' . $this->youtube . '</a></p>';

			if ($this->headline != '')
			{
				$return = '<'. $this->hl .'>'. $this->headline .'</'. $this->hl .'>'. $return;
			}

			return $return;
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$size = StringUtil::deserialize($this->playerSize);

		if (!\is_array($size) || empty($size[0]) || empty($size[1]))
		{
			$this->Template->size = ' width="640" height="360"';
			$this->Template->width = 640;
			$this->Template->height = 360;
		}
		else
		{
			$this->Template->size = ' width="' . $size[0] . '" height="' . $size[1] . '"';
			$this->Template->width = $size[0];
			$this->Template->height = $size[1];
		}

		$params = array();
		$options = StringUtil::deserialize($this->youtubeOptions);
		$domain = 'https://www.youtube.com';

		if (\is_array($options))
		{
			foreach ($options as $option)
			{
				switch ($option)
				{
					case 'youtube_fs':
					case 'youtube_rel':
					case 'youtube_showinfo':
					case 'youtube_controls':
						$params[] = substr($option, 8) . '=0';
						break;

					case 'youtube_hl':
						$params[] = substr($option, 8) . '=' . substr($GLOBALS['TL_LANGUAGE'], 0, 2);
						break;

					case 'youtube_iv_load_policy':
						$params[] = substr($option, 8) . '=3';
						break;

					case 'youtube_nocookie':
						$domain = 'https://www.youtube-nocookie.com';
						break;

					default:
						$params[] = substr($option, 8) . '=1';
				}
			}
		}

		if ($this->playerStart > 0)
		{
			$params[] = 'start=' . (int) $this->playerStart;
		}

		if ($this->playerStop > 0)
		{
			$params[] = 'end=' . (int) $this->playerStop;
		}

		$url = $domain . '/embed/' . $this->youtube;

		if (!empty($params))
		{
			$url .= '?' . implode('&amp;', $params);
		}

		if ($this->videoSplash)
		{
			$objFile = FilesModel::findByUuid($this->singleSRC);

			if ($objFile !== null && \is_file(TL_ROOT . '/' . $objFile->path))
			{
				$this->singleSRC = $objFile->path;
				$objSplash = new \stdClass();
				$this->addImageToTemplate($objSplash, $this->arrData, null, null, $objFile);
				$this->Template->splashImage = $objSplash;
			}
			else
			{
				$this->videoSplash = false;
			}
		}

		$this->Template->src = $url;
		$this->Template->aspect = str_replace(':', '', $this->playerAspect);
		$this->Template->caption = $this->playerCaption;
	}
}

class_alias(ContentYouTube::class, 'ContentYouTube');
