<?php

/**
 * Module configuration container
 */

return array(
    'name' => 'Members',
    'description' => 'This module lets you manage membership system on your site',
    'menu' => array(
        'name' => 'Members',
        'icon' => 'fas fa-users',
        'items' => array(
            array(
                'route' => 'Members:Admin:Members@indexAction',
                'name' => 'View all members'
            )
        )
    )
);