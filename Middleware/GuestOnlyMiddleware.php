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
 * Ensures that guest-only pages (login, register, recovery)
 * are not accessible to authenticated members.
 *
 * Authenticated users are redirected to their profile instead.
 */
final class GuestOnlyMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServiceLocatorInterface $sl, RouteMatchInterface $route): bool
    {
        $moduleManager = $sl->get('moduleManager');

        // Bail out if the Members module hasn't been loaded
        if (!$moduleManager->isLoaded('Members')) {
            return true;
        }

        $memberManager = $moduleManager->getModule('Members')->getService('memberManager');

        if ($memberManager->isLoggedIn()) {
            $urlBuilder = $sl->get('urlBuilder');
            $response = $sl->get('response');

            // Redirect to the member's profile page
            $url = $urlBuilder->build('Members:Profile@indexAction');

            $response->setStatusCode(302);
            $response->redirect($url);

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