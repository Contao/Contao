<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Mailer;

use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

final class ContaoMailer implements MailerInterface
{
    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var AvailableTransports
     */
    private $transports;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(MailerInterface $mailer, AvailableTransports $transports, RequestStack $requestStack)
    {
        $this->mailer = $mailer;
        $this->transports = $transports;
        $this->requestStack = $requestStack;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($message instanceof Message) {
            $this->setTransport($message);
        }

        if ($message instanceof Email) {
            $this->setFrom($message);
        }

        $this->mailer->send($message, $envelope);
    }

    /**
     * Sets the transport defined in the website root.
     */
    private function setTransport(Message $message): void
    {
        if ($message->getHeaders()->has('X-Transport')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $attributes = $this->requestStack->getCurrentRequest()->attributes;

        if ($attributes->has('pageModel') && ($page = $attributes->get('pageModel')) instanceof PageModel) {
            /** @var PageModel $page */
            $page->loadDetails();

            if (!empty($page->mailer_transport) && null !== $this->transports->getTransport($page->mailer_transport)) {
                $message->getHeaders()->addTextHeader('X-Transport', $page->mailer_transport);
            }
        }
    }

    /**
     * Overrides the from address according to the transport.
     */
    private function setFrom(Email $message): void
    {
        if (!$message->getHeaders()->has('X-Transport')) {
            return;
        }

        $transportName = $message->getHeaders()->get('X-Transport')->getBodyAsString();
        $transport = $this->transports->getTransport($transportName);

        if (null !== $transport && null !== ($from = $transport->getFrom())) {
            $message->from($from);
        }
    }
}
