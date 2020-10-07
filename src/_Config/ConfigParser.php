<?php

namespace Sim\Auth\Config;

class ConfigParser
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $blueprint_key = 'blueprints';

    /**
     * @var string
     */
    protected $users_key = 'users';

    /**
     * @var string
     */
    protected $roles_key = 'roles';

    /**
     * @var string
     */
    protected $pages_key = 'pages';

    /**
     * @var string
     */
    protected $user_role_key = 'user_role';

    /**
     * @var string
     */
    protected $role_page_perm_key = 'role_page_perm';

    /**
     * @var string
     */
    protected $user_page_perm_key = 'user_page_perm';

    /**
     * @var string
     */
    protected $sessions_key = 'sessions';

    /**
     * ConfigParser constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return static
     */
    public function up()
    {
        return $this;
    }
}