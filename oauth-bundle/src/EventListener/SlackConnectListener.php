<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\OAuthBundle\EventListener;

use AdamPaterson\OAuth2\Client\Provider\SlackResourceOwner;
use Contao\FrontendUser;
use Contao\OAuthBundle\Event\OAuthConnectEvent;

/**
 * Updates a new front end user's details with data from Slack.
 */
class SlackConnectListener
{
    public function __invoke(OAuthConnectEvent $event): void
    {
        $oauthUser = $event->getOauthUser();
        $user = $event->getUser();

        if (!$event->getIsNew() || !$oauthUser instanceof SlackResourceOwner || !$user instanceof FrontendUser) {
            return;
        }

        $user->email = $oauthUser->getEmail();
        $user->firstname = $oauthUser->getFirstName();
        $user->lastname = $oauthUser->getLastName();
        $user->phone = $oauthUser->getPhone();
        $user->save();
    }
}
