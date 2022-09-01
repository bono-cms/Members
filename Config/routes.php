<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

return array(
    '/profile' => array(
        'controller' => 'Profile@indexAction'
    ),

    '/profile/update' => array(
        'controller' => 'Profile@updateAction'
    ),
    
    '/%s/module/members' => array(
        'controller' => 'Admin:Members@indexAction'
    ),

    '/%s/module/members/save' => array(
        'controller' => 'Admin:Members@saveAction'
    ),
    
    '/%s/module/members/add' => array(
        'controller' => 'Admin:Members@addAction'
    ),

    '/%s/module/members/edit/(:var)' => array(
        'controller' => 'Admin:Members@editAction'
    ),

    '/%s/module/members/delete/(:var)' => array(
        'controller' => 'Admin:Members@deleteAction'
    ),
    
    '/confirm/(:var)' => array(
        'controller' => 'Register@confirmAction'
    ),

    '/recovery' => array(
        'controller' => 'Recovery@indexAction'
    ),
    
    '/recovery/confirm/(:var)' => array(
        'controller' => 'Recovery@resetAction'
    ),
    
    '/register' => array(
        'controller' => 'Register@indexAction'
    ),
    
    '/login' => array(
        'controller' => 'Auth@indexAction'
    ),

    '/logout' => array(
        'controller' => 'Auth@logoutAction'
    ),
    
    '/recovery' => array(
        'controller' => 'Recovery@indexAction'
    )
);
