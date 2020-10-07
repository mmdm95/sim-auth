<?php

/**
 * Please do not change keys to prevent
 * any problem :)
 */
return [
    'blueprints' => [

        /**
         * lib table alias name => [
         *   'table_name' => actual table's name
         *   'columns' => [columns' name array],
         *   'types' => [
         *     column's name from columns section above => the sql type etc.
         *     ...
         *   ],
         *   ...
         * ],
         * ...
         *
         * Note:
         *   Please do not change keys and just
         *   change values of them
         */
        'users' => [
            'table_name' => 'users',
            'columns' => [
                'id'
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
            ]
        ],
        'roles' => [
            'table_name' => 'roles',
            'columns' => [
                'id', 'name', 'description', 'is_admin'
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'name' => 'VARCHAR(20)',
                'description' => 'VARCHAR(100)',
                'is_admin' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
            ]
        ],
        'pages' => [
            'table_name' => 'pages',
            'columns' => [
                'id', 'name', 'description',
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'name' => 'VARCHAR(20)',
                'description' => 'VARCHAR(100)',
            ]
        ],
        'user_role' => [
            'table_name' => 'user_role',
            'columns' => [
                'id', 'user_id', 'role_id',
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'user_id' => 'INT(11) UNSIGNED NOT NULL',
                'role_id' => 'INT(11) UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'ADD CONSTRAINT fk_urp_u FOREIGN KEY(user_id) REFERENCES users(id)',
                'ADD CONSTRAINT fk_urp_r FOREIGN KEY(role_id) REFERENCES roles(id)',
            ]
        ],
        'role_page_perm' => [
            'table_name' => 'role_page_perm',
            'columns' => [
                'id', 'role_id', 'page_id', 'perm_id',
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'role_id' => 'INT(11) UNSIGNED NOT NULL',
                'page_id' => 'INT(11) UNSIGNED NOT NULL',
                'perm_id' => 'INT(11) UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'ADD CONSTRAINT fk_rpp_r FOREIGN KEY(role_id) REFERENCES roles(id)',
                'ADD CONSTRAINT fk_rpp_pa FOREIGN KEY(page_id) REFERENCES pages(id)',
            ]
        ],
        'user_page_perm' => [
            'table_name' => 'user_page_perm',
            'columns' => [
                'id', 'user_id', 'page_id', 'perm_id', 'is_allow'
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'user_id' => 'INT(11) UNSIGNED NOT NULL',
                'page_id' => 'INT(11) UNSIGNED NOT NULL',
                'perm_id' => 'INT(11) UNSIGNED NOT NULL',
                'is_allow' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1',
            ],
            'constraints' => [
                'ADD CONSTRAINT fk_upp_u FOREIGN KEY(user_id) REFERENCES users(id)',
                'ADD CONSTRAINT fk_upp_pa FOREIGN KEY(page_id) REFERENCES pages(id)',
            ]
        ],
        'sessions' => [
            'table_name' => 'sessions',
            'columns' => [
                'id', 'uuid', 'user_id', 'ip_address', 'device', 'browser', 'expire_at', 'created_at'
            ],
            'types' => [
                'id' => 'INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
                'user_id' => 'INT(11) UNSIGNED NOT NULL',
            ],
        ],
    ],
    /**
     * All not admin roles.
     * ATTENTION: NOT ADMIN ROLES
     *
     * It'll fill database when you run [up] method
     */
    'roles' => [],
    /**
     * All admin roles.
     * ATTENTION: ADMIN ROLES
     *
     * It'll fill database when you run [up] method
     */
    'admin_roles' => [],
];
