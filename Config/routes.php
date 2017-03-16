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
    
    '/register' => array(
        'controller' => 'Register@indexAction'
    ),
    
    '/login' => array(
        'controller' => 'Login@indexAction'
    ),

    '/recovery' => array(
        'controller' => 'Recovery@indexAction'
    )
);
