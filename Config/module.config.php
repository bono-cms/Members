<?php

/**
 * Module configuration container
 */

return [
    'name' => 'Members',
    'description' => 'This module lets you manage membership system on your site',
    'menu' => [
        'name' => 'Members',
        'icon' => 'fas fa-users',
        'items' => [
            [
                'route' => 'Members:Admin:Members@indexAction',
                'name' => 'View all members'
            ]
        ]
    ]
];