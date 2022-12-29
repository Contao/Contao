<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\OAuthBundle\Security;

use Contao\CoreBundle\Filesystem\Dbafs\UnableToResolveUuidException;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Slug\Slug;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\OAuthBundle\ClientGenerator;
use Contao\OAuthBundle\Event\OAuthLoginEvent;
use Contao\OAuthBundle\Model\OAuthClientModel;
use Doctrine\DBAL\Connection;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Uid\Uuid;

class OAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientGenerator $clientGenerator,
        private readonly Connection $db,
        private readonly ContaoFramework $framework,
        private readonly VirtualFilesystem $filesStorage,
        private readonly Slug $slug,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Security $security
    )
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'contao_oauth_check';
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        $session = $request->getSession();
        $clientModel = $this->framework->getAdapter(OAuthClientModel::class)->findById((int) $session->get('_oauth_client_id'));

        if (null === $clientModel) {
            throw new \RuntimeException('OAuth client model not found.');
        }

        $oauthClient = $this->clientGenerator->getClientById($session->get('_oauth_client_id'));
        $accessToken = $this->fetchAccessToken($oauthClient);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $clientModel, $oauthClient, $session): UserInterface {
                $oauthUser = $oauthClient->fetchUserFromToken($accessToken);

                // Search for existing member
                $user = $this->getExistingMember($oauthUser, $oauthClient, $clientModel);

                if (null !== $user) {
                    $this->updateOAuthData($user, $clientModel, $oauthUser);

                    $this->eventDispatcher->dispatch(new OAuthLoginEvent($accessToken, $oauthClient, $oauthUser, $user, false));

                    return $user;
                }

                $module = ModuleModel::findByPk($session->get('_oauth_module_id'));

                if (null === $module) {
                    throw new \RuntimeException('Invalid module ID in session.');
                }

                // Some OAuth resource owners provide the email address
                $username = $oauthUser->toArray()['email'] ?? $oauthUser->getId();

                $this->db->insert('tl_member', [
                    'tstamp' => time(),
                    'login' => 1,
                    'groups' => $module->reg_groups,
                    'username' => $username,
                    'dateAdded' => time(),
                    'email' => $oauthUser->toArray()['email'] ?? '',
                ]);

                $user = $this->framework->getAdapter(FrontendUser::class)->loadUserByIdentifier($username);

                if ($module->reg_assignDir) {
                    $this->assignHomeDir($user, $module);
                }

                $this->updateOAuthData($user, $clientModel, $oauthUser);

                $this->eventDispatcher->dispatch(new OAuthLoginEvent($accessToken, $oauthClient, $oauthUser, $user, true));

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();

        $url = $session->get('_oauth_redirect', $request->getSchemeAndHttpHost());

        $session->remove('_oauth_redirect');
        $session->remove('_oauth_client_id');
        $session->remove('_oauth_module_id');

        return new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();

        $session->remove('_oauth_redirect');
        $session->remove('_oauth_client_id');
        $session->remove('_oauth_module_id');

        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    private function assignHomeDir(FrontendUser $user, ModuleModel $model): void
    {
        try {
            $homeDir = $this->filesStorage->get(Uuid::fromBinary($model->reg_homeDir));
            $userDir = $this->slug->generate($user->username);

            while ($this->filesStorage->directoryExists(Path::join($homeDir->getPath(), $userDir))) {
                $userDir .= '_'.$user->id;
            }

            $finalDir = Path::join($homeDir->getPath(), $userDir);

            $this->filesStorage->createDirectory($finalDir);

            $userDirModel = FilesModel::findByPath('files/'.Path::normalize($finalDir));

            $user->assignDir = 1;
            $user->homeDir = $userDirModel->uuid;
            $user->save();
        } catch (UnableToResolveUuidException) {
            // Do nothing
        }
    }

    private function getExistingMember(ResourceOwnerInterface $oauthUser, OAuth2Client $oauthClient, OAuthClientModel $clientModel): ?FrontendUser
    {
        // Check if we already have a logged in user
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            return $user;
        }

        // Some OAuth resource owners provide the email address
        $email = $oauthUser->toArray()['email'] ?? '';

        // Check for existing users using the OAuth ID, email address or email address as a username
        $existingUsername = $this->db->fetchOne("
            SELECT m.username
              FROM tl_member AS m, tl_member_oauth AS o
             WHERE (o.pid = m.id AND o.oauthClient = ? AND o.oauthId = ?) OR (m.email = ? AND m.email != '') OR (m.username = ? AND m.username != '')
        ", [(int) $clientModel->id, $oauthUser->getId(), $email, $email]);

        if (false === $existingUsername) {
            return null;
        }

        return $this->framework->getAdapter(FrontendUser::class)->loadUserByIdentifier($existingUsername);
    }

    private function updateOAuthData(FrontendUser $user, OAuthClientModel $clientModel, ResourceOwnerInterface $oauthUser): void
    {
        $set = [
            'tstamp' => time(),
            'pid' => (int) $user->id,
            'oauthClient' => (int) $clientModel->id,
            'oauthId' => $oauthUser->getId(),
            'oauthUserData' => json_encode($oauthUser->toArray()),
        ];

        $existingRecord = $this->db->fetchOne("SELECT id FROM tl_member_oauth WHERE pid = ? AND oauthClient = ? AND oauthId = ?", [$user->id, $clientModel->id, $oauthUser->getId()]);

        if (false !== $existingRecord) {
            $this->db->update('tl_member_oauth', $set, ['id' => (int) $existingRecord]);
        } else {
            $this->db->insert('tl_member_oauth', $set);
        }
    }
}
