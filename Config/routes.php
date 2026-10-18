<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

use Members\Middleware\GuestOnlyMiddleware;
use Members\Middleware\AuthRequiredMiddleware;

return [
    // Admin area
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
    
    // Changes
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

    // Tag groups
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

    // Tags (group-scoped)
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
    
    // Chat
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
    
    // Site area
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
    
    // Directory
    '/members' => [
        'controller' => 'Directory@indexAction'
    ],

    '/member/profile/(:var)' => [
        'controller' => 'Directory@profileAction'
    ],
    
    // Recovery
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
    
    // Register
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
    
    // Auth
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
    ]
];