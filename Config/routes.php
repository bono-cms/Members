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
    '/%s/module/members' => array(
        'controller' => 'Admin:Members@indexAction'
    ),

    '/confirm/(:var)' => array(
        'controller' => 'Register@confirmAction'
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
