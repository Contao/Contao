<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Security;

use Contao\CoreBundle\Entity\AccessToken;
use Contao\CoreBundle\Repository\AccessTokenRepository;
use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @param non-empty-string $secret
     */
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        string $secret,
        private Configuration|null $configuration = null,
    ) {
        $this->configuration = $configuration ?: Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($secret));
        $this->configuration->setValidationConstraints(new SignedWith($this->configuration->signer(), $this->configuration->signingKey()));
    }

    public function createTokenForUser(string $username): string
    {
        $this->accessTokenRepository->removeExpired();

        $plainToken = $this->issueToken(['username' => $username]);
        $claims = $plainToken->claims();

        $accessToken = new AccessToken(hash('sha256', $plainToken->toString()), $username, $claims->get(RegisteredClaims::EXPIRATION_TIME));
        $this->accessTokenRepository->persist($accessToken);

        return $plainToken->toString();
    }

    public function issueToken(array $payload = []): Plain
    {
        $builder = $this->configuration->builder();

        foreach ($payload as $k => $v) {
            $builder = $builder->withClaim($k, $v);
        }

        return $builder
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt(new \DateTimeImmutable('now +10 seconds'))
            ->getToken($this->configuration->signer(), $this->configuration->signingKey())
        ;
    }

    public function parseToken(string $data): UnencryptedToken|null
    {
        $parser = new Parser(new JoseEncoder());

        try {
            /** @var UnencryptedToken $token */
            $token = $parser->parse($data);
        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound) {
            return null;
        }

        return $token;
    }

    public function validateToken(UnencryptedToken $token, string $username): bool
    {
        $claims = $token->claims();
        $now = new \DateTimeImmutable();

        if (!$claims->has(RegisteredClaims::EXPIRATION_TIME)) {
            return false;
        }

        if ($now >= $claims->get(RegisteredClaims::EXPIRATION_TIME)) {
            return false;
        }

        if (!$claims->has('username')) {
            return false;
        }

        return $username === $claims->get('username');
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            $token = $this->accessTokenRepository->findByToken($accessToken);
        } catch (TokenNotFoundException $e) {
            throw new AuthenticationException('Token not found.');
        }

        $unencryptedToken = $this->parseToken($accessToken);

        if (!$unencryptedToken || !$this->validateToken($unencryptedToken, $token->getUsername())) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return new UserBadge($token->getUsername());
    }

    public function getAuthenticatedFrontendSessionCookie(array $uris, string $username): Cookie|null
    {
        $userExists = $this->connection
            ->prepare('SELECT COUNT(id) FROM tl_member WHERE username = :username')
            ->executeQuery(['username' => $username])
            ->fetchOne()
        ;

        if (!$userExists) {
            throw new UserNotFoundException('User not found.');
        }

        return $this->getAuthenticatedSessionCookie($uris, $username);
    }

    public function getAuthenticatedBackendSessionCookie(array $uris, string $username): Cookie|null
    {
        $userExists = $this->connection
            ->prepare('SELECT COUNT(id) FROM tl_user WHERE username = :username')
            ->executeQuery(['username' => $username])
            ->fetchOne()
        ;

        if (!$userExists) {
            throw new UserNotFoundException('User not found.');
        }

        return $this->getAuthenticatedSessionCookie($uris, $username);
    }

    private function getAuthenticatedSessionCookie(array $uris, string $username): Cookie|null
    {
        $cookieJar = new CookieJar();
        $accessToken = $this->createTokenForUser($username);

        foreach ($uris as $uri) {
            try {
                $response = $this->httpClient->request('GET', (string) $uri, ['auth_bearer' => $accessToken]);

                if (200 !== $response->getStatusCode()) {
                    continue;
                }

                $headers = $response->getHeaders();
            } catch (TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface) {
                continue;
            }

            if (\array_key_exists('set-cookie', $headers)) {
                $cookieJar->updateFromSetCookie($headers['set-cookie']);

                break;
            }
        }

        return $cookieJar->get(session_name());
    }
}
