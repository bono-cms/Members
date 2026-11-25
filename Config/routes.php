<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
use Members\Middleware\AuthRequiredMiddleware;
use Members\Middleware\GuestOnlyMiddleware;
use Members\Middleware\JwtAuthMiddleware;

return [
    // ─── Admin area ────────────────────────────────────────────────
    '/%s/module/members' => [
        'controller' => 'Admin:Members@indexAction',
    ],
    '/%s/module/members/save' => [
        'controller' => 'Admin:Members@saveAction',
    ],
    '/%s/module/members/add' => [
        'controller' => 'Admin:Members@addAction',
    ],
    '/%s/module/members/edit/(:var)' => [
        'controller' => 'Admin:Members@editAction',
    ],
    '/%s/module/members/delete/(:var)' => [
        'controller' => 'Admin:Members@deleteAction',
    ],
    '/%s/module/members/changes' => [
        'controller' => 'Admin:Changes@indexAction',
    ],
    '/%s/module/members/changes/approve/(:var)' => [
        'controller' => 'Admin:Changes@approveAction',
    ],
    '/%s/module/members/changes/reject/(:var)' => [
        'controller' => 'Admin:Changes@rejectAction',
    ],
    '/%s/module/members/history/(:var)' => [
        'controller' => 'Admin:Members@historyAction',
    ],
    '/%s/module/members/tags' => [
        'controller' => 'Admin:TagGroup@indexAction',
    ],
    '/%s/module/members/tags/add' => [
        'controller' => 'Admin:TagGroup@addAction',
    ],
    '/%s/module/members/tags/edit/(:var)' => [
        'controller' => 'Admin:TagGroup@editAction',
    ],
    '/%s/module/members/tags/save' => [
        'controller' => 'Admin:TagGroup@saveAction',
    ],
    '/%s/module/members/tags/delete/(:var)' => [
        'controller' => 'Admin:TagGroup@deleteAction',
    ],
    '/%s/module/members/tags/group/(:var)/add' => [
        'controller' => 'Admin:Tag@addAction',
    ],
    '/%s/module/members/tags/group/(:var)' => [
        'controller' => 'Admin:Tag@indexAction',
    ],
    '/%s/module/members/tags/tag/edit/(:var)' => [
        'controller' => 'Admin:Tag@editAction',
    ],
    '/%s/module/members/tags/tag/save' => [
        'controller' => 'Admin:Tag@saveAction',
    ],
    '/%s/module/members/tags/tag/delete/(:var)' => [
        'controller' => 'Admin:Tag@deleteAction',
    ],

    // ─── Admin area: Events extension ──────────────────────────────
    '/%s/module/members/events' => [
        'controller' => 'Admin:Event@indexAction',
    ],
    '/%s/module/members/events/create' => [
        'controller' => 'Admin:Event@createAction',
    ],
    '/%s/module/members/events/store' => [
        'controller' => 'Admin:Event@storeAction',
    ],
    '/%s/module/members/events/edit/(:var)' => [
        'controller' => 'Admin:Event@editAction',
    ],
    '/%s/module/members/events/update/(:var)' => [
        'controller' => 'Admin:Event@updateAction',
    ],
    '/%s/module/members/events/approve/(:var)' => [
        'controller' => 'Admin:Event@approveAction',
    ],
    '/%s/module/members/events/reject/(:var)' => [
        'controller' => 'Admin:Event@rejectAction',
    ],
    '/%s/module/members/events/delete/(:var)' => [
        'controller' => 'Admin:Event@deleteAction',
    ],
    '/%s/module/members/events/analytics' => [
        'controller' => 'Admin:Event@dashboardAction',
    ],
    '/%s/module/members/events/analytics/(:var)' => [
        'controller' => 'Admin:Event@analyticsAction',
    ],
    '/%s/module/members/events/parse-location' => [
        'controller' => 'Admin:Event@parseLocationAction',
    ],

    // ─── Web: Chat ─────────────────────────────────────────────────
    '/member/messages' => [
        'controller' => 'Chat@inboxAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/messages/with/(:var)' => [
        'controller' => 'Chat@withAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/messages/conversation/(:var)' => [
        'controller' => 'Chat@conversationAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/messages/send' => [
        'controller' => 'Chat@sendAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/messages/poll' => [
        'controller' => 'Chat@pollAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],

    // ─── Web: Site area ────────────────────────────────────────────
    '/member/profile' => [
        'controller' => 'Profile@indexAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/profile/edit' => [
        'controller' => 'Profile@editAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/profile/update' => [
        'controller' => 'Profile@updateAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/members' => [
        'controller' => 'Directory@indexAction',
    ],
    '/member/profile/(:var)' => [
        'controller' => 'Directory@profileAction',
    ],
    '/member/recovery' => [
        'controller' => 'Recovery@indexAction',
    ],
    '/member/recovery/process' => [
        'controller' => 'Recovery@processAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/recovery/success' => [
        'controller' => 'Recovery@successAction',
    ],
    '/member/recovery/confirm/(:var)' => [
        'controller' => 'Recovery@resetAction',
    ],
    '/member/register' => [
        'controller' => 'Register@indexAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/register/submit' => [
        'controller' => 'Register@saveAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/register/confirm/(:var)' => [
        'controller' => 'Register@confirmAction',
    ],
    '/member/register/success' => [
        'controller' => 'Register@successAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/register/resend' => [
        'controller' => 'Register@resendAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/login' => [
        'controller' => 'Auth@indexAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/login/submit' => [
        'controller' => 'Auth@loginAction',
        'middleware' => [GuestOnlyMiddleware::class],
    ],
    '/member/logout' => [
        'controller' => 'Auth@logoutAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],

    // ─── Web: Events extension (public) ────────────────────────────
    // The redirect route "/events/(:var)/go" MUST be registered
    // before "/events/(:var)", otherwise "go" would be captured as
    // the event id.
    // The "/events/archive" route MUST be registered before
    // "/events/(:var)", otherwise "archive" would be captured as
    // the event id.
    '/events' => [
        'controller' => 'Event@catalogAction',
    ],
    '/events/archive' => [
        'controller' => 'Event@archiveAction',
    ],
    '/events/(:var)/go' => [
        'controller' => 'Event@clickAction',
    ],
    '/events/(:var)' => [
        'controller' => 'Event@viewAction',
    ],

    // ─── Web: Events extension (member cabinet) ────────────────────
    '/member/events' => [
        'controller' => 'Event@indexAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/create' => [
        'controller' => 'Event@createAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/store' => [
        'controller' => 'Event@storeAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/edit/(:var)' => [
        'controller' => 'Event@editAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/update/(:var)' => [
        'controller' => 'Event@updateAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/delete/(:var)' => [
        'controller' => 'Event@deleteAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/analytics/(:var)' => [
        'controller' => 'Event@analyticsAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],
    '/member/events/parse-location' => [
        'controller' => 'Event@parseLocationAction',
        'middleware' => [AuthRequiredMiddleware::class],
    ],

    // ─── API v1: Auth ──────────────────────────────────────────────
    '/api/v1/members/auth/login' => [
        'controller' => 'Api:Auth@loginAction',
    ],
    '/api/v1/members/auth/recovery' => [
        'controller' => 'Api:Auth@recoveryAction',
    ],
    '/api/v1/members/auth/whoami' => [
        'controller' => 'Api:Auth@whoamiAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],

    // ─── API v1: Register ──────────────────────────────────────────
    '/api/v1/members/register' => [
        'controller' => 'Api:Register@saveAction',
    ],
    '/api/v1/members/register/resend' => [
        'controller' => 'Api:Register@resendAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],

    // ─── API v1: Own profile ───────────────────────────────────────
    '/api/v1/members/me' => [
        'controller' => 'Api:Profile@indexAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/me/update' => [
        'controller' => 'Api:Profile@updateAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/me/avatar' => [
        'controller' => 'Api:Profile@avatarAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/me/password' => [
        'controller' => 'Api:Profile@passwordAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],

    // ─── API v1: Directory ─────────────────────────────────────────
    '/api/v1/members/profile' => [
        'controller' => 'Api:Directory@profileAction',
    ],
    '/api/v1/members' => [
        'controller' => 'Api:Directory@indexAction',
    ],

    // ─── API v1: Chat ──────────────────────────────────────────────
    '/api/v1/members/chats' => [
        'controller' => 'Api:Chat@indexAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/chat/thread' => [
        'controller' => 'Api:Chat@threadAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/chat/send' => [
        'controller' => 'Api:Chat@sendAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/chat/start' => [
        'controller' => 'Api:Chat@startAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/chat/poll' => [
        'controller' => 'Api:Chat@pollAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/chat/unread' => [
        'controller' => 'Api:Chat@unreadAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],

    // ─── API v1: Events extension ──────────────────────────────────
    '/api/v1/members/events' => [
        'controller' => 'Api:Event@indexAction',
    ],
    '/api/v1/members/events/view/(:var)' => [
        'controller' => 'Api:Event@viewAction',
    ],
    '/api/v1/members/events/store' => [
        'controller' => 'Api:Event@storeAction',
        'methods' => ['POST'],
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/me/events' => [
        'controller' => 'Api:Event@myAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/me/events/(:var)/analytics' => [
        'controller' => 'Api:Event@analyticsAction',
        'middleware' => [JwtAuthMiddleware::class],
    ],
    '/api/v1/members/events/(:var)/click' => [
        'controller' => 'Api:Event@clickAction',
    ],
    '/api/v1/members/events/render/map' => [
        'controller' => 'Api:Event@mapAction',
    ],
];