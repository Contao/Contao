<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_user'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'enableVersioning'            => true,
		'onload_callback' => array
		(
			array('tl_user', 'handleUserProfile'),
			array('tl_user', 'checkPermission'),
			array('tl_user', 'addTemplateWarning')
		),
		'onsubmit_callback' => array
		(
			array('tl_user', 'storeDateAdded'),
			array('tl_user', 'updateCurrentUser')
		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'username' => 'unique',
				'email' => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 2,
			'fields'                  => array('dateAdded'),
			'flag'                    => 1,
			'panelLayout'             => 'filter;sort,search,limit'
		),
		'label' => array
		(
			'fields'                  => array('icon', 'name', 'username', 'dateAdded'),
			'showColumns'             => true,
			'label_callback'          => array('tl_user', 'addIcon')
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg',
				'button_callback'     => array('tl_user', 'editUser')
			),
			'copy' => array
			(
				'href'                => 'act=copy',
				'icon'                => 'copy.svg',
				'button_callback'     => array('tl_user', 'copyUser')
			),
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				'button_callback'     => array('tl_user', 'deleteUser')
			),
			'toggle' => array
			(
				'icon'                => 'visible.svg',
				'attributes'          => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				'button_callback'     => array('tl_user', 'toggleIcon')
			),
			'show' => array
			(
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			),
			'su' => array
			(
				'href'                => 'key=su',
				'icon'                => 'su.svg',
				'button_callback'     => array('tl_user', 'switchUser')
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                => array('inherit', 'admin'),
		'login'                       => '{name_legend},name,email;{backend_legend},language,uploader,showHelp,thumbnails,useRTE,useCE;{session_legend},session;{password_legend},password;{theme_legend:hide},backendTheme,fullscreen',
		'admin'                       => '{name_legend},username,name,email;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{account_legend},disable,start,stop',
		'default'                     => '{name_legend},username,name,email;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{groups_legend},groups,inherit;{account_legend},disable,start,stop',
		'group'                       => '{name_legend},username,name,email;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{groups_legend},groups,inherit;{account_legend},disable,start,stop',
		'extend'                      => '{name_legend},username,name,email;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{groups_legend},groups,inherit;{modules_legend},modules,themes;{pagemounts_legend},pagemounts,alpty;{filemounts_legend},filemounts,fop;{imageSizes_legend},imageSizes;{forms_legend},forms,formp;{amg_legend},amg;{account_legend},disable,start,stop',
		'custom'                      => '{name_legend},username,name,email;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{groups_legend},groups,inherit;{modules_legend},modules,themes;{pagemounts_legend},pagemounts,alpty;{filemounts_legend},filemounts,fop;{imageSizes_legend},imageSizes;{forms_legend},forms,formp;{amg_legend},amg;{account_legend},disable,start,stop'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'username' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'extnd', 'nospace'=>true, 'unique'=>true, 'maxlength'=>64, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) BINARY NULL"
		),
		'name' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50 clr'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'email' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'email', 'maxlength'=>255, 'unique'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'language' => array
		(
			'default'                 => str_replace('-', '_', $GLOBALS['TL_LANGUAGE']),
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'select',
			'eval'                    => array('rgxp'=>'locale', 'tl_class'=>'w50'),
			'options_callback' => static function ()
			{
				return Contao\System::getLanguages(true);
			},
			'sql'                     => "varchar(5) NOT NULL default ''"
		),
		'backendTheme' => array
		(
			'exclude'                 => true,
			'inputType'               => 'select',
			'options_callback' => static function ()
			{
				return Contao\Backend::getThemes();
			},
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "varchar(32) NOT NULL default ''"
		),
		'fullscreen' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'uploader' => array
		(
			'exclude'                 => true,
			'inputType'               => 'select',
			'options'                 => array('DropZone', 'FileUpload'),
			'reference'               => &$GLOBALS['TL_LANG']['tl_user'],
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "varchar(32) NOT NULL default ''"
		),
		'showHelp' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default '1'"
		),
		'thumbnails' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default '1'"
		),
		'useRTE' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default '1'"
		),
		'useCE' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default '1'"
		),
		'password' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['password'],
			'exclude'                 => true,
			'inputType'               => 'password',
			'eval'                    => array('mandatory'=>true, 'preserveTags'=>true, 'minlength'=>Contao\Config::get('minPasswordLength')),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'pwChange' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'admin' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('submitOnChange'=>true),
			'save_callback' => array
			(
				array('tl_user', 'checkAdminStatus')
			),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'groups' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkboxWizard',
			'foreignKey'              => 'tl_user_group.name',
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL",
			'relation'                => array('type'=>'belongsToMany', 'load'=>'lazy')
		),
		'inherit' => array
		(
			'exclude'                 => true,
			'inputType'               => 'radio',
			'options'                 => array('group', 'extend', 'custom'),
			'reference'               => &$GLOBALS['TL_LANG']['tl_user'],
			'eval'                    => array('helpwizard'=>true, 'submitOnChange'=>true),
			'sql'                     => "varchar(12) NOT NULL default 'group'"
		),
		'modules' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'options_callback'        => array('tl_user', 'getModules'),
			'reference'               => &$GLOBALS['TL_LANG']['MOD'],
			'eval'                    => array('multiple'=>true, 'helpwizard'=>true),
			'sql'                     => "blob NULL"
		),
		'themes' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'options'                 => array('css', 'modules', 'layout', 'image_sizes', 'theme_import', 'theme_export'),
			'reference'               => &$GLOBALS['TL_LANG']['MOD'],
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL"
		),
		'pagemounts' => array
		(
			'exclude'                 => true,
			'inputType'               => 'pageTree',
			'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox'),
			'sql'                     => "blob NULL"
		),
		'alpty' => array
		(
			'default'                 => array('regular', 'redirect', 'forward'),
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'options'                 => array_keys($GLOBALS['TL_PTY']),
			'reference'               => &$GLOBALS['TL_LANG']['PTY'],
			'eval'                    => array('multiple'=>true, 'helpwizard'=>true),
			'sql'                     => "blob NULL"
		),
		'filemounts' => array
		(
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox'),
			'sql'                     => "blob NULL"
		),
		'fop' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['FOP']['fop'],
			'default'                 => array('f1', 'f2', 'f3'),
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'options'                 => array('f1', 'f2', 'f3', 'f4', 'f5', 'f6'),
			'reference'               => &$GLOBALS['TL_LANG']['FOP'],
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL"
		),
		'imageSizes' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'eval'                    => array('multiple'=>true),
			'options_callback' => static function ()
			{
				return Contao\System::getContainer()->get('Contao\CoreBundle\Image\ImageSizes')->getAllOptions();
			},
			'sql'                     => "blob NULL"
		),
		'forms' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'foreignKey'              => 'tl_form.title',
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL"
		),
		'formp' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'options'                 => array('create', 'delete'),
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL"
		),
		'amg' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'foreignKey'              => 'tl_member_group.name',
			'eval'                    => array('multiple'=>true),
			'sql'                     => "blob NULL"
		),
		'disable' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'flag'                    => 2,
			'inputType'               => 'checkbox',
			'save_callback' => array
			(
				array('tl_user', 'checkAdminDisable')
			),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'start' => array
		(
			'exclude'                 => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'datim', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
			'sql'                     => "varchar(10) NOT NULL default ''"
		),
		'stop' => array
		(
			'exclude'                 => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'datim', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
			'sql'                     => "varchar(10) NOT NULL default ''"
		),
		'session' => array
		(
			'exclude'                 => true,
			'input_field_callback'    => array('tl_user', 'sessionField'),
			'eval'                    => array('doNotShow'=>true, 'doNotCopy'=>true),
			'sql'                     => "blob NULL"
		),
		'dateAdded' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
			'default'                 => time(),
			'sorting'                 => true,
			'flag'                    => 6,
			'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'secret' => array
		(
			'eval'                    => array('doNotShow'=>true, 'doNotCopy'=>true),
			'sql'                     => "binary(128) NULL default NULL"
		),
		'useTwoFactor' => array
		(
			'eval'                    => array('isBoolean'=>true, 'doNotCopy'=>true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'lastLogin' => array
		(
			'eval'                    => array('rgxp'=>'datim', 'doNotShow'=>true, 'doNotCopy'=>true),
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'currentLogin' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['lastLogin'],
			'sorting'                 => true,
			'flag'                    => 6,
			'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'loginCount' => array
		(
			'eval'                    => array('doNotCopy'=>true),
			'sql'                     => "smallint(5) unsigned NOT NULL default 3"
		),
		'locked' => array
		(
			'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		)
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_user extends Contao\Backend
{

	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Contao\BackendUser', 'User');
	}

	/**
	 * Check permissions to edit table tl_user
	 *
	 * @throws Contao\CoreBundle\Exception\AccessDeniedException
	 */
	public function checkPermission()
	{
		if ($this->User->isAdmin)
		{
			return;
		}

		// Unset the "admin" checkbox for regular users
		unset($GLOBALS['TL_DCA']['tl_user']['fields']['admin']);

		// Check current action
		switch (Contao\Input::get('act'))
		{
			case 'create':
			case 'select':
			case 'show':
				// Allow
				break;

			case 'delete':
				if (Contao\Input::get('id') == $this->User->id)
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Attempt to delete own account ID ' . Contao\Input::get('id') . '.');
				}
				// no break

			case 'edit':
			case 'copy':
			case 'toggle':
			default:
				$objUser = $this->Database->prepare("SELECT `admin` FROM tl_user WHERE id=?")
										  ->limit(1)
										  ->execute(Contao\Input::get('id'));

				if ($objUser->admin && Contao\Input::get('act') != '')
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' administrator account ID ' . Contao\Input::get('id') . '.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
				/** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
				$objSession = Contao\System::getContainer()->get('session');

				$session = $objSession->all();
				$objUser = $this->Database->execute("SELECT id FROM tl_user WHERE `admin`=1");
				$session['CURRENT']['IDS'] = array_diff($session['CURRENT']['IDS'], $objUser->fetchEach('id'));
				$objSession->replace($session);
				break;
		}
	}

	/**
	 * Handle the profile page.
	 *
	 * @param Contao\DataContainer $dc
	 */
	public function handleUserProfile(Contao\DataContainer $dc)
	{
		if (Contao\Input::get('do') != 'login')
		{
			return;
		}

		// Should not happen because of the redirect but better safe than sorry
		if (Contao\BackendUser::getInstance()->id != Contao\Input::get('id') || Contao\Input::get('act') != 'edit')
		{
			throw new Contao\CoreBundle\Exception\AccessDeniedException('Not allowed to edit this page.');
		}

		$GLOBALS['TL_DCA'][$dc->table]['config']['closed'] = true;
		$GLOBALS['TL_DCA'][$dc->table]['config']['hideVersionMenu'] = true;

		$GLOBALS['TL_DCA'][$dc->table]['palettes'] = array
		(
			'__selector__' => $GLOBALS['TL_DCA'][$dc->table]['palettes']['__selector__'],
			'default' => $GLOBALS['TL_DCA'][$dc->table]['palettes']['login']
		);

		$arrFields = Contao\StringUtil::trimsplit('[,;]', $GLOBALS['TL_DCA'][$dc->table]['palettes']['default']);

		foreach ($arrFields as $strField)
		{
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]['exclude'] = false;
		}
	}

	/**
	 * Add a warning if there are users with access to the template editor.
	 */
	public function addTemplateWarning()
	{
		if (Contao\Input::get('act') && Contao\Input::get('act') != 'select')
		{
			return;
		}

		$objResult = $this->Database->query("SELECT COUNT(*) AS cnt FROM tl_user WHERE admin='' AND modules LIKE '%\"tpl_editor\"%'");

		if ($objResult->cnt > 0)
		{
			Contao\Message::addInfo($GLOBALS['TL_LANG']['MSC']['userTemplateEditor']);
		}
	}

	/**
	 * Add an image to each record
	 *
	 * @param array                $row
	 * @param string               $label
	 * @param Contao\DataContainer $dc
	 * @param array                $args
	 *
	 * @return array
	 */
	public function addIcon($row, $label, Contao\DataContainer $dc, $args)
	{
		$image = $row['admin'] ? 'admin' : 'user';
		$time = Contao\Date::floorToMinute();

		$disabled = ($row['start'] !== '' && $row['start'] > $time) || ($row['stop'] !== '' && $row['stop'] < $time);

		if ($row['useTwoFactor'])
		{
			$image .= '_two_factor';
		}

		if ($row['disable'] || $disabled)
		{
			$image .= '_';
		}

		$args[0] = sprintf('<div class="list_icon_new" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="%s.svg" data-icon-disabled="%s.svg">&nbsp;</div>', Contao\System::getContainer()->get('contao.assets.assets_context')->getStaticUrl(), Contao\Backend::getTheme(), $image, $disabled ? $image : rtrim($image, '_'), rtrim($image, '_') . '_');

		return $args;
	}

	/**
	 * Return the edit user button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function editUser($row, $href, $label, $title, $icon, $attributes)
	{
		return ($this->User->isAdmin || !$row['admin']) ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
	}

	/**
	 * Return the copy user button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 * @param string $table
	 *
	 * @return string
	 */
	public function copyUser($row, $href, $label, $title, $icon, $attributes, $table)
	{
		if ($GLOBALS['TL_DCA'][$table]['config']['closed'])
		{
			return '';
		}

		return ($this->User->isAdmin || !$row['admin']) ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
	}

	/**
	 * Return the delete user button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function deleteUser($row, $href, $label, $title, $icon, $attributes)
	{
		return ($this->User->isAdmin || !$row['admin']) ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
	}

	/**
	 * Generate a "switch account" button and return it as string
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public function switchUser($row, $href, $label, $title, $icon)
	{
		$authorizationChecker = Contao\System::getContainer()->get('security.authorization_checker');

		if (!$authorizationChecker->isGranted('ROLE_ALLOWED_TO_SWITCH') || $authorizationChecker->isGranted('ROLE_PREVIOUS_ADMIN'))
		{
			return '';
		}

		if ($this->User->id == $row['id'])
		{
			return Contao\Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
		}

		$router = Contao\System::getContainer()->get('router');
		$url = $router->generate('contao_backend', array('_switch_user'=>$row['username']));

		return '<a href="'.$url.'" title="'.Contao\StringUtil::specialchars($title).'">'.Contao\Image::getHtml($icon, $label).'</a> ';
	}

	/**
	 * Return a checkbox to delete session data
	 *
	 * @param Contao\DataContainer $dc
	 *
	 * @return string
	 */
	public function sessionField(Contao\DataContainer $dc)
	{
		if (Contao\Input::post('FORM_SUBMIT') == 'tl_user')
		{
			$arrPurge = Contao\Input::post('purge');

			if (\is_array($arrPurge))
			{
				$this->import('Contao\Automator', 'Automator');

				if (\in_array('purge_session', $arrPurge))
				{
					/** @var Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface $objSessionBag */
					$objSessionBag = Contao\System::getContainer()->get('session')->getBag('contao_backend');
					$objSessionBag->clear();
					Contao\Message::addConfirmation($GLOBALS['TL_LANG']['tl_user']['sessionPurged']);
				}

				if (\in_array('purge_images', $arrPurge))
				{
					$this->Automator->purgeImageCache();
					Contao\Message::addConfirmation($GLOBALS['TL_LANG']['tl_user']['htmlPurged']);
				}

				if (\in_array('purge_pages', $arrPurge))
				{
					$this->Automator->purgePageCache();
					Contao\Message::addConfirmation($GLOBALS['TL_LANG']['tl_user']['tempPurged']);
				}

				$this->reload();
			}
		}

		return '
<div class="widget">
  <fieldset class="tl_checkbox_container">
    <legend>'.$GLOBALS['TL_LANG']['tl_user']['session'][0].'</legend>
    <input type="checkbox" id="check_all_purge" class="tl_checkbox" onclick="Backend.toggleCheckboxGroup(this, \'ctrl_purge\')"> <label for="check_all_purge" style="color:#a6a6a6"><em>'.$GLOBALS['TL_LANG']['MSC']['selectAll'].'</em></label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_0" class="tl_checkbox" value="purge_session" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_0">'.$GLOBALS['TL_LANG']['tl_user']['sessionLabel'].'</label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_1" class="tl_checkbox" value="purge_images" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_1">'.$GLOBALS['TL_LANG']['tl_user']['htmlLabel'].'</label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_2" class="tl_checkbox" value="purge_pages" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_2">'.$GLOBALS['TL_LANG']['tl_user']['tempLabel'].'</label>
  </fieldset>'.$dc->help().'
</div>';
	}

	/**
	 * Return all modules except profile modules
	 *
	 * @return array
	 */
	public function getModules()
	{
		$arrModules = array();

		foreach ($GLOBALS['BE_MOD'] as $k=>$v)
		{
			if (empty($v))
			{
				continue;
			}

			foreach ($v as $kk=>$vv)
			{
				if (isset($vv['disablePermissionChecks']) && $vv['disablePermissionChecks'] === true)
				{
					unset($v[$kk]);
				}
			}

			$arrModules[$k] = array_keys($v);
		}

		return $arrModules;
	}

	/**
	 * Prevent administrators from downgrading their own account
	 *
	 * @param mixed                $varValue
	 * @param Contao\DataContainer $dc
	 *
	 * @return mixed
	 */
	public function checkAdminStatus($varValue, Contao\DataContainer $dc)
	{
		if ($varValue == '' && $this->User->id == $dc->id)
		{
			$varValue = 1;
		}

		return $varValue;
	}

	/**
	 * Prevent administrators from disabling their own account
	 *
	 * @param mixed                $varValue
	 * @param Contao\DataContainer $dc
	 *
	 * @return mixed
	 */
	public function checkAdminDisable($varValue, Contao\DataContainer $dc)
	{
		if ($varValue == 1 && $this->User->id == $dc->id)
		{
			$varValue = '';
		}

		return $varValue;
	}

	/**
	 * Store the date when the account has been added
	 *
	 * @param Contao\DataContainer $dc
	 */
	public function storeDateAdded(Contao\DataContainer $dc)
	{
		// Return if there is no active record (override all)
		if (!$dc->activeRecord || $dc->activeRecord->dateAdded > 0)
		{
			return;
		}

		// Fallback solution for existing accounts
		if ($dc->activeRecord->lastLogin > 0)
		{
			$time = $dc->activeRecord->lastLogin;
		}
		else
		{
			$time = time();
		}

		$this->Database->prepare("UPDATE tl_user SET dateAdded=? WHERE id=?")
					   ->execute($time, $dc->id);
	}

	/**
	 * Update the current user if something changes, otherwise they would be
	 * logged out automatically
	 *
	 * @param Contao\DataContainer $dc
	 */
	public function updateCurrentUser(Contao\DataContainer $dc)
	{
		if ($this->User->id == $dc->id)
		{
			$this->User->findBy('id', $this->User->id);
		}
	}

	/**
	 * Return the "toggle visibility" button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
	{
		if (Contao\Input::get('tid'))
		{
			$this->toggleVisibility(Contao\Input::get('tid'), (Contao\Input::get('state') == 1), (@func_get_arg(12) ?: null));
			$this->redirect($this->getReferer());
		}

		// Check permissions AFTER checking the tid, so hacking attempts are logged
		if (!$this->User->hasAccess('tl_user::disable', 'alexf'))
		{
			return '';
		}

		$href .= '&amp;tid='.$row['id'].'&amp;state='.$row['disable'];

		if ($row['disable'])
		{
			$icon = 'invisible.svg';
		}

		// Protect admin accounts
		if (!$this->User->isAdmin && $row['admin'])
		{
			return Contao\Image::getHtml($icon) . ' ';
		}

		return '<a href="'.$this->addToUrl($href).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"').'</a> ';
	}

	/**
	 * Disable/enable a user group
	 *
	 * @param integer              $intId
	 * @param boolean              $blnVisible
	 * @param Contao\DataContainer $dc
	 *
	 * @throws Contao\CoreBundle\Exception\AccessDeniedException
	 */
	public function toggleVisibility($intId, $blnVisible, Contao\DataContainer $dc=null)
	{
		// Set the ID and action
		Contao\Input::setGet('id', $intId);
		Contao\Input::setGet('act', 'toggle');

		if ($dc)
		{
			$dc->id = $intId; // see #8043
		}

		// Protect own account
		if ($this->User->id == $intId)
		{
			return;
		}

		// Trigger the onload_callback
		if (\is_array($GLOBALS['TL_DCA']['tl_user']['config']['onload_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_user']['config']['onload_callback'] as $callback)
			{
				if (\is_array($callback))
				{
					$this->import($callback[0]);
					$this->{$callback[0]}->{$callback[1]}($dc);
				}
				elseif (\is_callable($callback))
				{
					$callback($dc);
				}
			}
		}

		// Check the field access
		if (!$this->User->hasAccess('tl_user::disable', 'alexf'))
		{
			throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate user ID ' . $intId . '.');
		}

		// Get the current record
		if ($dc)
		{
			$objRow = $this->Database->prepare("SELECT * FROM tl_user WHERE id=?")
									 ->limit(1)
									 ->execute($intId);

			if ($objRow->numRows)
			{
				$dc->activeRecord = $objRow;
			}
		}

		$objVersions = new Contao\Versions('tl_user', $intId);
		$objVersions->initialize();

		// Reverse the logic (users have disable=1)
		$blnVisible = !$blnVisible;

		// Trigger the save_callback
		if (\is_array($GLOBALS['TL_DCA']['tl_user']['fields']['disable']['save_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_user']['fields']['disable']['save_callback'] as $callback)
			{
				if (\is_array($callback))
				{
					$this->import($callback[0]);
					$blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
				}
				elseif (\is_callable($callback))
				{
					$blnVisible = $callback($blnVisible, $dc);
				}
			}
		}

		$time = time();

		// Update the database
		$this->Database->prepare("UPDATE tl_user SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
					   ->execute($intId);

		if ($dc)
		{
			$dc->activeRecord->tstamp = $time;
			$dc->activeRecord->disable = ($blnVisible ? '1' : '');
		}

		// Trigger the onsubmit_callback
		if (\is_array($GLOBALS['TL_DCA']['tl_user']['config']['onsubmit_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_user']['config']['onsubmit_callback'] as $callback)
			{
				if (\is_array($callback))
				{
					$this->import($callback[0]);
					$this->{$callback[0]}->{$callback[1]}($dc);
				}
				elseif (\is_callable($callback))
				{
					$callback($dc);
				}
			}
		}

		$objVersions->create();
	}
}
