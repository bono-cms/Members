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
 * Applies the language passed via ?lang= on any API request.
 *
 * Delegates to Cms::languageManager::changeSiteLanguage(), the same service
 * method the web controllers use. Runs before the controller initializes, so
 * the language is already in effect when the action executes and when
 * messages are translated.
 */
final class ApiLanguageMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServiceLocatorInterface $sl, RouteMatchInterface $route): bool
    {
        $moduleManager = $sl->get('moduleManager');
        $request = $sl->get('request');

        // Only act when a lang is actually supplied
        if (!$request->hasQuery('lang')) {
            return true;
        }

        // The Cms module must be loaded for changeSiteLanguage() to exist
        if (!$moduleManager->isLoaded('Cms')) {
            return true;
        }

        $lang = (string) $request->getQuery('lang');

        if ($lang === '') {
            return true;
        }

        $languageManager = $moduleManager->getModule('Cms')->getService('languageManager');
        $languageManager->changeSiteLanguage($lang);

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