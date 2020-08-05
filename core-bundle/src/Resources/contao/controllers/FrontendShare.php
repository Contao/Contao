<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Share a page via a social network.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class FrontendShare extends Frontend
{
	/**
	 * Run the controller
	 *
	 * @return RedirectResponse
	 */
	public function run()
	{
		switch (Input::get('p'))
		{
			case 'facebook':
				return new RedirectResponse(
					'https://www.facebook.com/sharer/sharer.php'
						. '?u=' . rawurlencode(Input::get('u', true)),
					Response::HTTP_SEE_OTHER
				);

			case 'twitter':
				return new RedirectResponse(
					'https://twitter.com/intent/tweet'
						. '?url=' . rawurlencode(Input::get('u', true))
						. '&text=' . rawurlencode(Input::get('t', true)),
					Response::HTTP_SEE_OTHER
				);
		}

		return new RedirectResponse('../', Response::HTTP_SEE_OTHER);
	}
}

class_alias(FrontendShare::class, 'FrontendShare');
