<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Middleware;

use Krystal\Application\Middleware\MiddlewareInterface;
use Krystal\Application\Route\RouteMatchInterface;
use Krystal\InstanceManager\ServiceLocatorInterface;

/**
 * Blocks unauthenticated visitors from protected member pages.
 *
 * Unauthenticated users are redirected to the login page with the
 * originally-requested URI stashed in the session, so that after a
 * successful login the Auth controller can bounce them back.
 */
final class AuthRequiredMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServiceLocatorInterface $sl, RouteMatchInterface $route): bool
    {
        $moduleManager = $sl->get('moduleManager');

        if (!$moduleManager->isLoaded('Members')) {
            return true;
        }

        $memberManager = $moduleManager->getModule('Members')->getService('memberManager');

        // Not logged in? Stash intended URI and send to login
        if (!$memberManager->isLoggedIn()) {
            $sessionBag = $sl->get('sessionBag');
            $request = $sl->get('request');
            $urlBuilder = $sl->get('urlBuilder');
            $response = $sl->get('response');

            $sessionBag->set('login_redirect', $request->getUri());

            $response->redirect($urlBuilder->build('Members:Auth@indexAction'));
            return false;
        }

        // Session exists, but the member row may have been deleted
        // since login. Force a logout and bounce to login.
        $memberId = $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId);

        if ($member === false) {
            $memberManager->logout();

            $urlBuilder = $sl->get('urlBuilder');
            $response = $sl->get('response');

            $response->redirect($urlBuilder->build('Members:Auth@indexAction'));
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(ServiceLocatorInterface $sl, RouteMatchInterface $route)
    {
        // No cleanup required
    }
}