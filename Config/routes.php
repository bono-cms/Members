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
    '/profile' => [
        'controller' => 'Profile@indexAction'
    ],

    '/profile/edit' => [
        'controller' => 'Profile@editAction'
    ],

    '/profile/update' => [
        'controller' => 'Profile@updateAction'
    ],
    
    // Recovery
    '/recovery' => [
        'controller' => 'Recovery@indexAction'
    ],

    '/recovery/process' => [
        'controller' => 'Recovery@processAction'
    ],

    '/recovery/success' => [
        'controller' => 'Recovery@successAction'
    ],
    
    '/recovery/confirm/(:var)' => [
        'controller' => 'Recovery@resetAction'
    ],
    
    // Register
    '/register' => [
        'controller' => 'Register@indexAction'
    ],

    '/register/confirm/(:var)' => [
        'controller' => 'Register@confirmAction'
    ],

    '/register/success' => [
        'controller' => 'Register@successAction'
    ],
    
    // Auth
    '/login' => [
        'controller' => 'Auth@indexAction'
    ],

    '/logout' => [
        'controller' => 'Auth@logoutAction'
    ]
];