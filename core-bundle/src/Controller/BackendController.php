<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\BackendAlerts;
use Contao\BackendConfirm;
use Contao\BackendFile;
use Contao\BackendHelp;
use Contao\BackendIndex;
use Contao\BackendMain;
use Contao\BackendPage;
use Contao\BackendPassword;
use Contao\BackendPopup;
use Contao\CoreBundle\Picker\PickerBuilderInterface;
use Contao\CoreBundle\Picker\PickerConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="%contao.backend.route_prefix%", defaults={"_scope" = "backend", "_token_check" = true})
 *
 * @internal
 */
class BackendController extends AbstractController
{
    /**
     * @Route("", name="contao_backend")
     */
    public function mainAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendMain();

        return $controller->run();
    }

    /**
     * @Route("/login", name="contao_backend_login")
     */
    public function loginAction(Request $request): Response
    {
        $this->initializeContaoFramework();

        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            if ($request->query->has('redirect')) {
                $uriSigner = $this->container->get('uri_signer');

                // We cannot use $request->getUri() here as we want to work with the original URI (no query string reordering)
                if ($uriSigner->check($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().(null !== ($qs = $request->server->get('QUERY_STRING')) ? '?'.$qs : ''))) {
                    return new RedirectResponse($request->query->get('redirect'));
                }
            }

            return new RedirectResponse($this->generateUrl('contao_backend'));
        }

        $controller = new BackendIndex();

        return $controller->run();
    }

    /**
     * Symfony will un-authenticate the user automatically by calling this route.
     *
     * @Route("/logout", name="contao_backend_logout")
     */
    public function logoutAction(): RedirectResponse
    {
        return $this->redirectToRoute('contao_backend_login');
    }

    /**
     * @Route("/password", name="contao_backend_password")
     */
    public function passwordAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendPassword();

        return $controller->run();
    }

    /**
     * @Route("/confirm", name="contao_backend_confirm")
     */
    public function confirmAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendConfirm();

        return $controller->run();
    }

    /**
     * @Route("/file", name="contao_backend_file")
     *
     * @deprecated Deprecated since Contao 4.13, to be removed in Contao 5.0.
     *             Use the picker instead.
     */
    public function fileAction(): Response
    {
        trigger_deprecation('contao/core-bundle', '4.13', 'Calling "%s()" has been deprecated and will no longer work in Contao 5.0. Use the picker instead.', __METHOD__);

        $this->initializeContaoFramework();

        $controller = new BackendFile();

        return $controller->run();
    }

    /**
     * @Route("/help", name="contao_backend_help")
     */
    public function helpAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendHelp();

        return $controller->run();
    }

    /**
     * @Route("/page", name="contao_backend_page")
     *
     * @deprecated Deprecated since Contao 4.13, to be removed in Contao 5.0.
     *             Use the picker instead.
     */
    public function pageAction(): Response
    {
        trigger_deprecation('contao/core-bundle', '4.13', 'Calling "%s::%s()" has been deprecated and will no longer work in Contao 5.0. Use the picker instead.', __CLASS__, __METHOD__);

        $this->initializeContaoFramework();

        $controller = new BackendPage();

        return $controller->run();
    }

    /**
     * @Route("/popup", name="contao_backend_popup")
     */
    public function popupAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendPopup();

        return $controller->run();
    }

    /**
     * @Route("/alerts", name="contao_backend_alerts")
     */
    public function alertsAction(): Response
    {
        $this->initializeContaoFramework();

        $controller = new BackendAlerts();

        return $controller->run();
    }

    /**
     * Redirects the user to the Contao back end and adds the picker query parameter.
     * It will determine the current provider URL based on the value, which is usually
     * read dynamically via JavaScript.
     *
     * @Route("/picker", name="contao_backend_picker")
     */
    public function pickerAction(Request $request): RedirectResponse
    {
        $extras = [];

        if ($request->query->has('extras')) {
            $extras = $request->query->all('extras');

            if (empty($extras)) {
                throw new BadRequestHttpException('Invalid picker extras');
            }
        }

        $config = new PickerConfig($request->query->get('context'), $extras, $request->query->get('value'));
        $picker = $this->container->get('contao.picker.builder')->create($config);

        if (null === $picker) {
            throw new BadRequestHttpException('Unsupported picker context');
        }

        return new RedirectResponse($picker->getCurrentUrl());
    }

    /**
     * @Route("/{parameters}", name="contao_backend_fallback", requirements={"parameters" = ".*"}, priority=-1000)
     */
    public function backendFallback(): Response
    {
        return $this->render(
            '@ContaoCore/Error/backend.html.twig',
            [
                'language' => 'en',
                'statusName' => 'Page Not Found',
                'exception' => 'The requested page does not exist.',
                'template' => '@ContaoCore/Error/backend.html.twig',
            ],
            new Response('', 404),
        );
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.picker.builder'] = PickerBuilderInterface::class;
        $services['uri_signer'] = UriSigner::class;

        return $services;
    }
}
