<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

// Suppress error messages (see #1422)
@ini_set('display_errors', '0');

// Disable the phar stream wrapper for security reasons (see #105)
if (\in_array('phar', stream_get_wrappers(), true)) {
    stream_wrapper_unregister('phar');
}

// System maintenance mode comes first as it has to work even if the vendor directory does not exist
if (file_exists(__DIR__ . '/maintenance.html')) {
    $contents = file_get_contents(__DIR__ . '/maintenance.html');

    header('HTTP/1.1 503 Service Unavailable', true, 503);
    header('Content-Type: text/html; charset=UTF-8', true, 503);
    header('Content-Length: ' . strlen($contents), true, 503);
    header('Cache-Control: no-store', true, 503);
    echo $contents;
    exit;
}

/** @var Composer\Autoload\ClassLoader */
$loader = require __DIR__.'/../vendor/autoload.php';

$request = Request::createFromGlobals();
$kernel = ContaoKernel::fromRequest(\dirname(__DIR__), $request);

$response = $kernel->handle($request);
$response->send();

if ($kernel instanceof TerminableInterface) {
    $kernel->terminate($request, $response);
}
