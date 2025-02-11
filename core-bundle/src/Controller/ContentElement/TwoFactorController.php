<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\TwoFactor\Authenticator;
use Contao\CoreBundle\Security\TwoFactor\BackupCodeManager;
use Contao\CoreBundle\Security\TwoFactor\TrustedDeviceManager;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\PageModel;
use ParagonIE\ConstantTime\Base32;
use Scheb\TwoFactorBundle\Security\Authentication\Exception\InvalidTwoFactorCodeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsContentElement(category: 'miscellaneous')]
class TwoFactorController extends AbstractContentElementController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly BackupCodeManager $backupCodeManager,
        private readonly TrustedDeviceManager $trustedDeviceManager,
        private readonly Authenticator $authenticator,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $template->getResponse();
        }

        $user = $this->getUser();
        $pageModel = $this->getPageModel();

        if (!$user instanceof FrontendUser || !$pageModel instanceof PageModel) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY', null, 'Full authentication is required to configure the two-factor authentication.');

        $adapter = $this->framework->getAdapter(PageModel::class);
        $redirectPage = $model->jumpTo > 0 ? $adapter->findById($model->jumpTo) : null;
        $return = $this->generateContentUrl($redirectPage instanceof PageModel ? $redirectPage : $pageModel, [], UrlGeneratorInterface::ABSOLUTE_URL);

        $template->set('enforce_two_factor', $pageModel->enforceTwoFactor);
        $template->set('target_path', $return);

        // Inform the user if 2FA is enforced
        if ($pageModel->enforceTwoFactor) {
            $template->set('message', $this->translator->trans('MSC.twoFactorEnforced', [], 'contao_default'));
        }

        $enable = 'enable' === $request->get('2fa');

        if (!$user->useTwoFactor && $pageModel->enforceTwoFactor) {
            $enable = true;
        }

        if($enable) {
            $exception = $this->authenticationUtils->getLastAuthenticationError();

            if ($exception instanceof InvalidTwoFactorCodeException) {
                $template->set('message', $this->translator->trans('ERR.invalidTwoFactor', [], 'contao_default'));
            }

            // Validate the verification code
            if ('tl_two_factor' === $request->request->get('FORM_SUBMIT')) {
                if ($this->authenticator->validateCode($user, $request->request->get('verify'))) {
                    // Enable 2FA
                    $user->useTwoFactor = true;
                    $user->save();

                    return new RedirectResponse($return);
                }

                $template->set('message', $this->translator->trans('ERR.invalidTwoFactor', [], 'contao_default'));
            }

            // Generate the secret
            if (!$user->secret) {
                $user->secret = random_bytes(128);
                $user->save();
            }

            $template->set('enable', true);
            $template->set('secret', Base32::encodeUpperUnpadded($user->secret));
            $template->set('qr_code', base64_encode($this->authenticator->getQrCode($user, $request)));
        }

        $formId = $request->request->get('FORM_SUBMIT');

        if ('tl_two_factor_disable' === $formId && ($response = $this->disableTwoFactor($user, $pageModel))) {
            return $response;
        }

        if('tl_two_factor_disable' === $formId) {
            // Don't apply if 2FA is disabled already
            if ($user->useTwoFactor) {
                $user->secret = null;
                $user->useTwoFactor = false;
                $user->backupCodes = null;
                $user->save();

                // Clear all trusted devices
                $this->trustedDeviceManager->clearTrustedDevices($user);

                return new RedirectResponse($this->generateContentUrl($pageModel, [], UrlGeneratorInterface::ABSOLUTE_URL));
            }
        }

        try {
            $template->set('backupCodes', json_decode((string) $user->backupCodes, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            $template->set('backup_codes', []);
        }

        if ('tl_two_factor_generate_backup_codes' === $formId) {
            $template->set('show_backup_codes', true);
            $template->set('backup_codes', $this->backupCodeManager->generateBackupCodes($user));
        }

        if ('tl_two_factor_clear_trusted_devices' === $formId) {
            $this->trustedDeviceManager->clearTrustedDevices($user);
        }

        $template->set('is_enabled', (bool) $user->useTwoFactor);
        $template->set('href', $this->generateContentUrl($pageModel, [], UrlGeneratorInterface::ABSOLUTE_URL).'?2fa=enable');
        $template->set('trusted_devices', $this->trustedDeviceManager->getTrustedDevices($user));

        return $template->getResponse();
    }
}
