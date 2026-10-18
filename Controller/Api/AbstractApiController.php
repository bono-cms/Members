<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

use Krystal\Authentication\JWT;
use Members\Controller\AbstractController;
use Members\Middleware\ApiLanguageMiddleware;

abstract class AbstractApiController extends AbstractController
{
    /**
     * Applied to every action in every API controller, before any
     * route-declared middleware.
     *
     * @var array<int, string>
     */
    protected $middlewares = [
        ApiLanguageMiddleware::class,
    ];

    /**
     * Returns the id of the language requested via ?lang=, falling back
     * to the site's active language (or the default one) when no
     * ?lang= is present.
     *
     * Resolves the id from the language code directly, so this does not
     * depend on changeSiteLanguage() having run first.
     *
     * @return int
     */
    protected function getRequestLanguageId(): int
    {
        $languageManager = $this->getService('Cms', 'languageManager');

        // Client explicitly requested a language
        $code = $this->request->getQuery('lang', null);

        if ($code !== null && $code !== '') {
            $id = $languageManager->fetchIdByCode((string) $code);

            if ($id) {
                return (int) $id;
            }
        }

        // Fall back to the active content language (or default)
        return (int) $languageManager->getCurrentId();
    }

    /**
     * Returns the authenticated member id by decoding the JWT.
     *
     * JwtAuthMiddleware has already validated the token before this method
     * can run, so a non-zero return here is guaranteed on any route that
     * declared the middleware.
     *
     * @return int Zero when no valid token is present
     */
    protected function getAuthUserId(): int
    {
        $config = $this->getApiConfig();
        $payload = (new JWT($config['jwt_secret']))->decodeFromBearer();

        return $payload && isset($payload['sub']) ? (int) $payload['sub'] : 0;
    }

    /**
     * Returns the current module config's "api" section.
     *
     * @return array
     */
    protected function getApiConfig(): array
    {
        return (array) $this->moduleManager->getModule($this->moduleName)->getConfig('api');
    }

    /**
     * Skips the site-facing bootstrap for API requests.
     *
     * Theme, layout, plugin bag, and view variables are not needed for JSON
     * responses. Translations still load from AbstractController::initialize().
     *
     * @param string $action
     * @return void
     */
    protected function bootstrap($action)
    {
        // No-op by design
    }
}