<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class LocaleListener
{
    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var array
     */
    private $availableLocales;

    public function __construct(ScopeMatcher $scopeMatcher, array $availableLocales)
    {
        $this->scopeMatcher = $scopeMatcher;
        $this->availableLocales = $availableLocales;
    }

    /**
     * Sets the default locale based on the request or session.
     */
    public function onKernelRequest(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->scopeMatcher->isContaoRequest($request) || $request->attributes->has('_locale')) {
            return;
        }

        $request->attributes->set('_locale', $this->getLocale($request));
    }

    /**
     * Returns the locale from the request, the session or the HTTP header.
     */
    private function getLocale(Request $request): string
    {
        if (null !== $request->attributes->get('_locale')) {
            return $this->formatLocaleId($request->attributes->get('_locale'));
        }

        return $request->getPreferredLanguage($this->availableLocales);
    }

    /**
     * @throw \InvalidArgumentException
     */
    private function formatLocaleId(string $locale): string
    {
        if (!preg_match('/^[a-z]{2}([_-][a-z]{2})?$/i', $locale)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a supported locale.', $locale));
        }

        $values = preg_split('/-|_/', $locale);
        $locale = strtolower($values[0]);

        if (isset($values[1])) {
            $locale .= '_'.strtoupper($values[1]);
        }

        return $locale;
    }
}
