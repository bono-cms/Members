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
use Krystal\Authentication\JWT;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * Decodes the bearer JWT, verifies the subject still exists, and passes
     * or rejects the request accordingly.
     *
     * {@inheritDoc}
     */
    public function process(ServiceLocatorInterface $sl, RouteMatchInterface $route): bool
    {
        $moduleManager = $sl->get('moduleManager');

        if (!$moduleManager->isLoaded('Members')) {
            return $this->reject($sl);
        }

        $members = $moduleManager->getModule('Members');
        $config = $members->getConfig('api');

        if (empty($config['jwt_secret'])) {
            return $this->reject($sl);
        }

        $payload = (new JWT($config['jwt_secret']))->decodeFromBearer();

        if ($payload === false || !isset($payload['sub'])) {
            return $this->reject($sl);
        }

        $memberManager = $members->getService('memberManager');

        if ($memberManager->fetchById((int) $payload['sub']) === false) {
            return $this->reject($sl);
        }

        return true;
    }

    /**
     * Sends a 401 JSON response and halts the pipeline.
     *
     * @param \Krystal\InstanceManager\ServiceLocatorInterface $sl
     * @return bool Always false
     */
    private function reject(ServiceLocatorInterface $sl): bool
    {
        $response = $sl->get('response');
        $response->setStatusCode(401);
        $response->respondAsJson();
        $response->send(json_encode(['error' => 'Unauthorized']));

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(ServiceLocatorInterface $sl, RouteMatchInterface $route)
    {
        // No cleanup required
    }
}