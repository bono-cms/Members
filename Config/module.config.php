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
            ],

            [
                'route' => 'Members:Admin:Changes@indexAction',
                'name' => 'Review pending changes'
            ],

            [
                'route' => 'Members:Admin:TagGroup@indexAction',
                'name' => 'Manage tags'
            ]
        ]
    ],
    'moderation' => [
        'enabled' => true,
        'fields' => [
            // Fields that never enter the change pipeline. They are
            // handled separately (password reset flow, registration)
            // and cannot be staged or applied through the profile
            // edit form.
            'reserved' => ['id', 'password', 'remove_avatar', 'email', 'login'],

            // Fields applied immediately, never staged
            'skip' => ['phone', 'password'],

            // Translation fields that go through moderation
            'translatable' => ['bio', 'title', 'name'],

            // Base (non-translated) fields that go through moderation
            'base' => ['address', 'avatar', 'company']
        ]
    ],
    
    'api' => [
        'jwt_secret' => 'REPLACE-ME-with-a-long-random-string-at-least-32-chars',
        'jwt_ttl'    => 604800, // 7 days
    ]
];