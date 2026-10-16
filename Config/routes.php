<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

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
        'controller' => 'Profile@indexAction'
    ],

    '/member/profile/edit' => [
        'controller' => 'Profile@editAction'
    ],

    '/member/profile/update' => [
        'controller' => 'Profile@updateAction'
    ],
    
    // Recovery
    '/member/recovery' => [
        'controller' => 'Recovery@indexAction'
    ],

    '/member/recovery/process' => [
        'controller' => 'Recovery@processAction'
    ],

    '/member/recovery/success' => [
        'controller' => 'Recovery@successAction'
    ],
    
    '/member/recovery/confirm/(:var)' => [
        'controller' => 'Recovery@resetAction'
    ],
    
    // Register
    '/member/register' => [
        'controller' => 'Register@indexAction'
    ],

    '/member/register/confirm/(:var)' => [
        'controller' => 'Register@confirmAction'
    ],

    '/member/register/success' => [
        'controller' => 'Register@successAction'
    ],
    
    // Auth
    '/member/login' => [
        'controller' => 'Auth@indexAction'
    ],

    '/member/logout' => [
        'controller' => 'Auth@logoutAction'
    ]
];