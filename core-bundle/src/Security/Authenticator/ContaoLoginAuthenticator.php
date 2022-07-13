<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Security\Authenticator;

use Contao\CoreBundle\Exception\InsufficientAuthenticationException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageError401;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\Passport\Credentials\TwoFactorCodeCredentials;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\ParameterBagUtils;

class ContaoLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    private array $options;

    public function __construct(private UserProviderInterface $userProvider, private AuthenticationSuccessHandlerInterface $successHandler, private AuthenticationFailureHandlerInterface $failureHandler, private ScopeMatcher $scopeMatcher, private RouterInterface $router, private UriSigner $uriSigner, private ContaoFramework $framework, private TokenStorageInterface $tokenStorage, private TwoFactorAuthenticator $twoFactorAuthenticator, array $options)
    {
        $this->options = array_merge([
            'username_parameter' => 'username',
            'password_parameter' => 'password',
            'check_path' => '/login_check',
            'post_only' => true,
            'enable_csrf' => false,
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
        ], $options);
    }

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse|Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $this->redirectToBackend($request);
        }

        $this->framework->initialize();

        if (!isset($GLOBALS['TL_PTY']['error_401']) || !class_exists($GLOBALS['TL_PTY']['error_401'])) {
            throw new UnauthorizedHttpException('', 'Not authorized');
        }

        /** @var PageError401 $pageHandler */
        $pageHandler = new $GLOBALS['TL_PTY']['error_401']();

        try {
            return $pageHandler->getResponse();
        } catch (InsufficientAuthenticationException $e) {
            throw new UnauthorizedHttpException('', $e->getMessage(), $e);
        }
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->request->has('FORM_SUBMIT')
            && str_starts_with($request->request->get('FORM_SUBMIT'), 'tl_login');
    }

    public function authenticate(Request $request): Passport
    {
        // When the firewall is lazy, the token is not initialized in the "supports" stage, so this check does only work
        // within the "authenticate" stage.
        $currentToken = $this->tokenStorage->getToken();

        if ($currentToken instanceof TwoFactorTokenInterface) {
            return $this->twoFactorAuthenticator->authenticate($request);
        }

        $credentials = $this->getCredentials($request);

        $passport = new Passport(
            new UserBadge($credentials['username'], [$this->userProvider, 'loadUserByIdentifier']),
            new PasswordCredentials($credentials['password']),
            [new RememberMeBadge()]
        );

        if ($this->options['enable_csrf']) {
            $passport->addBadge(new CsrfTokenBadge($this->options['csrf_token_id'], $credentials['csrf_token']));
        }

        if ($this->userProvider instanceof PasswordUpgraderInterface) {
            $passport->addBadge(new PasswordUpgradeBadge($credentials['password'], $this->userProvider));
        }

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $credentialsBadge = $passport->getBadge(TwoFactorCodeCredentials::class);

        if ($credentialsBadge instanceof TwoFactorCodeCredentials) {
            $twoFactorToken = $credentialsBadge->getTwoFactorToken();

            if ($this->isTwoFactorAuthenticationComplete($twoFactorToken)) {
                $authenticatedToken = $twoFactorToken->getAuthenticatedToken(); // Authentication complete, unwrap the token
                $authenticatedToken->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);

                return $authenticatedToken;
            }

            return $twoFactorToken;
        }

        return parent::createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return true;
    }

    private function isTwoFactorAuthenticationComplete(TwoFactorTokenInterface $token): bool
    {
        return $token->allTwoFactorProvidersAuthenticated();
    }

    private function getCredentials(Request $request): array
    {
        $credentials = [];
        $credentials['csrf_token'] = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);

        $credentials['username'] = ParameterBagUtils::getParameterBagValue($request->request, $this->options['username_parameter']);
        $credentials['password'] = ParameterBagUtils::getParameterBagValue($request->request, $this->options['password_parameter']) ?? '';

        if (!\is_string($credentials['username']) && (!\is_object($credentials['username']) || !method_exists($credentials['username'], '__toString'))) {
            throw new BadRequestHttpException(sprintf('The key "%s" must be a string, "%s" given.', $this->options['username_parameter'], \gettype($credentials['username'])));
        }

        $credentials['username'] = trim($credentials['username']);

        if (\strlen($credentials['username']) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['username']);

        return $credentials;
    }

    private function redirectToBackend(Request $request): RedirectResponse
    {
        $url = $this->router->generate(
            'contao_backend_login',
            ['redirect' => $request->getUri()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new RedirectResponse($this->uriSigner->sign($url));
    }
}
