<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Table(
 *     name="tl_remember_me",
 *     indexes={
 *         @ORM\Index(name="series", columns={"series"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="Contao\CoreBundle\Repository\RememberMeRepository")
 */
class RememberMe
{
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=64, nullable=false, options={"fixed"=true})
     */
    protected $series;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=88, nullable=false, options={"fixed"=true})
     * @ORM\Id
     */
    protected $value;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $lastUsed;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $expires;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    protected $class;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=200, nullable=false)
     */
    protected $username;

    public function __construct(UserInterface $user, string $encodedSeries)
    {
        $this->class = \get_class($user);
        $this->series = $encodedSeries;
        $this->value = base64_encode(random_bytes(64));
        $this->username = $user->getUsername();
        $this->lastUsed = new \DateTime();
        $this->expires = null;
    }

    public function __clone()
    {
        $this->value = '';
        $this->lastUsed = new \DateTime();
        $this->expires = null;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLastUsed(): \DateTime
    {
        return $this->lastUsed;
    }

    public function getExpires(): ?\DateTime
    {
        return $this->expires;
    }

    public function setExpiresInSeconds(int $seconds): self
    {
        if (null === $this->expires) {
            $this->expires = (new \DateTime())->add(new \DateInterval('PT'.$seconds.'S'));
        }

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function cloneWithNewValue()
    {
        $clone = clone $this;

        $clone->value = base64_encode(random_bytes(64));

        return $clone;
    }
}
