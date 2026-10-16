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

    '/member/logout' => [
        'controller' => 'Auth@logoutAction',
        'middleware' => [AuthRequiredMiddleware::class]
    ]
];