<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

use Members\Middleware\GuestOnlyMiddleware;
use Members\Middleware\AuthRequiredMiddleware;
use Members\Middleware\JwtAuthMiddleware;

return [
    // ─── Admin area ────────────────────────────────────────────────
    '/%s/module/members' => [
        'controller' => 'Admin:Members@indexAction'
    ],
    '/%s/module/members/save' => [
        'controller' => 'Admin:Members@saveAction'
    ],
    '/%s/module/members/add' => [
        'controller' => 'Admin:Members@addAction'
    ],
    '/%s/module/members/edit/(:var)' => [
        'controller' => 'Admin:Members@editAction'
    ],
    '/%s/module/members/delete/(:var)' => [
        'controller' => 'Admin:Members@deleteAction'
    ],

    '/%s/module/members/changes' => [
        'controller' => 'Admin:Changes@indexAction'
    ],
    '/%s/module/members/changes/approve/(:var)' => [
        'controller' => 'Admin:Changes@approveAction'
    ],
    '/%s/module/members/changes/reject/(:var)' => [
        'controller' => 'Admin:Changes@rejectAction'
    ],
    '/%s/module/members/history/(:var)' => [
        'controller' => 'Admin:Members@historyAction'
    ],

    '/%s/module/members/tags' => [
        'controller' => 'Admin:TagGroup@indexAction'
    ],
    '/%s/module/members/tags/add' => [
        'controller' => 'Admin:TagGroup@addAction'
    ],
    '/%s/module/members/tags/edit/(:var)' => [
        'controller' => 'Admin:TagGroup@editAction'
    ],
    '/%s/module/members/tags/save' => [
        'controller' => 'Admin:TagGroup@saveAction'
    ],
    '/%s/module/members/tags/delete/(:var)' => [
        'controller' => 'Admin:TagGroup@deleteAction'
    ],
    '/%s/module/members/tags/group/(:var)/add' => [
        'controller' => 'Admin:Tag@addAction'
    ],
    '/%s/module/members/tags/group/(:var)' => [
        'controller' => 'Admin:Tag@indexAction'
    ],
    '/%s/module/members/tags/tag/edit/(:var)' => [
        'controller' => 'Admin:Tag@editAction'
    ],
    '/%s/module/members/tags/tag/save' => [
        'controller' => 'Admin:Tag@saveAction'
    ],
    '/%s/module/members/tags/tag/delete/(:var)' => [
        'controller' => 'Admin:Tag@deleteAction'
    ],

    // ─── Web: Chat ─────────────────────────────────────────────────
    '/member/messages' => [
        'controller' => 'Chat@inboxAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/messages/with/(:var)' => [
        'controller' => 'Chat@withAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/messages/converstation/(:var)' => [
        'controller' => 'Chat@conversationAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/messages/send' => [
        'controller' => 'Chat@sendAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/messages/poll' => [
        'controller' => 'Chat@pollAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],

    // ─── Web: Site area ────────────────────────────────────────────
    '/member/profile' => [
        'controller' => 'Profile@indexAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/profile/edit' => [
        'controller' => 'Profile@editAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],
    '/member/profile/update' => [
        'controller' => 'Profile@updateAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],

    '/members' => [
        'controller' => 'Directory@indexAction'
    ],
    '/member/profile/(:var)' => [
        'controller' => 'Directory@profileAction'
    ],

    '/member/recovery' => [
        'controller' => 'Recovery@indexAction'
    ],
    '/member/recovery/process' => [
        'controller' => 'Recovery@processAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/recovery/success' => [
        'controller' => 'Recovery@successAction'
    ],
    '/member/recovery/confirm/(:var)' => [
        'controller' => 'Recovery@resetAction'
    ],

    '/member/register' => [
        'controller' => 'Register@indexAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/register/submit' => [
        'controller' => 'Register@saveAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/register/confirm/(:var)' => [
        'controller' => 'Register@confirmAction'
    ],
    '/member/register/success' => [
        'controller' => 'Register@successAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/register/resend' => [
        'controller' => 'Register@resendAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],

    '/member/login' => [
        'controller' => 'Auth@indexAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/login/submit' => [
        'controller' => 'Auth@loginAction',
        'middleware' => [GuestOnlyMiddleware::class]
    ],
    '/member/logout' => [
        'controller' => 'Auth@logoutAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ],

    // ─── API v1: Auth ──────────────────────────────────────────────
    '/api/v1/members/auth/login' => [
        'controller' => 'Api:Auth@loginAction'
    ],
    '/api/v1/members/auth/recovery' => [
        'controller' => 'Api:Auth@recoveryAction'
    ],
    '/api/v1/members/auth/whoami' => [
        'controller' => 'Api:Auth@whoamiAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],

    // ─── API v1: Register ──────────────────────────────────────────
    '/api/v1/members/register' => [
        'controller' => 'Api:Register@saveAction'
    ],
    '/api/v1/members/register/resend' => [
        'controller' => 'Api:Register@resendAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],

    // ─── API v1: Own profile ───────────────────────────────────────
    '/api/v1/members/me' => [
        'controller' => 'Api:Profile@indexAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/me/update' => [
        'controller' => 'Api:Profile@updateAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/me/avatar' => [
        'controller' => 'Api:Profile@avatarAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/me/password' => [
        'controller' => 'Api:Profile@passwordAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],

    // ─── API v1: Directory ─────────────────────────────────────────
    '/api/v1/members/profile' => [
        'controller' => 'Api:Directory@profileAction'
    ],
    '/api/v1/members' => [
        'controller' => 'Api:Directory@indexAction'
    ],

    // ─── API v1: Chat ──────────────────────────────────────────────
    '/api/v1/members/chats' => [
        'controller' => 'Api:Chat@indexAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/chat/thread' => [
        'controller' => 'Api:Chat@threadAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/chat/send' => [
        'controller' => 'Api:Chat@sendAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/chat/start' => [
        'controller' => 'Api:Chat@startAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/chat/poll' => [
        'controller' => 'Api:Chat@pollAction',
        'middleware' => [JwtAuthMiddleware::class]
    ],
    '/api/v1/members/chat/unread' => [
        'controller' => 'Api:Chat@unreadAction',
        'middleware' => [JwtAuthMiddleware::class]
    ]
];