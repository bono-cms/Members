<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

return [
    '/profile' => [
        'controller' => 'Profile@indexAction'
    ],

    '/profile/edit' => [
        'controller' => 'Profile@editAction'
    ],

    '/profile/update' => [
        'controller' => 'Profile@updateAction'
    ],

    '/profile/update' => [
        'controller' => 'Profile@updateAction'
    ],
    
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
    
    '/confirm/(:var)' => [
        'controller' => 'Register@confirmAction'
    ],

    '/recovery' => [
        'controller' => 'Recovery@indexAction'
    ],
    
    '/recovery/confirm/(:var)' => [
        'controller' => 'Recovery@resetAction'
    ],
    
    '/register' => [
        'controller' => 'Register@indexAction'
    ],

    '/register/success' => [
        'controller' => 'Register@successAction'
    ],
    
    '/login' => [
        'controller' => 'Auth@indexAction'
    ],

    '/logout' => [
        'controller' => 'Auth@logoutAction'
    ]
];