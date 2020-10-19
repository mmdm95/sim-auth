<?php

namespace Sim\Auth;

use PDO;
use Sim\Auth\Abstracts\AbstractAPIAuth;
use Sim\Auth\Exceptions\IncorrectAPIKeyException;
use Sim\Auth\Exceptions\InvalidUserException;
use Sim\Auth\Interfaces\IAuthValidator;
use Sim\Auth\Interfaces\IAuthVerifier;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Verifiers\SimpleVerifier;

class APIAuth extends AbstractAPIAuth implements IAuthValidator
{
    /**
     * @var IAuthVerifier
     */
    protected $verifier;

    /**
     * APIAuth constructor.
     * @param PDO $pdo_instance
     * @param array|null $config
     * @throws IDBException
     */
    public function __construct(
        PDO $pdo_instance,
        ?array $config = null
    )
    {
        parent::__construct($pdo_instance, $config);

        $this->verifier = new SimpleVerifier();
    }

    /**
     * Credentials structure is as below:
     * [
     *   'username' => provided username,
     *   'api_key' => provided api key,
     * ]
     *
     * {@inheritdoc}
     * @throws IDBException
     * @throws IncorrectAPIKeyException
     * @throws InvalidUserException
     */
    public function validate(
        array $credentials,
        string $extra_query = null,
        array $bind_values = []
    ): bool
    {
        if (!isset($credentials['username'], $credentials['api_key']) || empty($credentials['username']) || empty($credentials['api_key'])) {
            throw new \InvalidArgumentException('Provided credentials does not have correct structure.');
        }

        $apiColumns = $this->config_parser->getTablesColumn($this->api_keys_key);
        $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);

        $where = "{$apiColumns['username']}=:__api_auth_username_value__";
        if (!empty($extra_query)) {
            $where .= " AND ({$extra_query})";
            $bind_values = array_merge($bind_values, [
                '__api_auth_username_value__' => $credentials['username'],
            ]);
        } else {
            $bind_values = [
                '__api_auth_username_value__' => $credentials['username'],
            ];
        }

        // get user from database
        $user = $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->api_keys_key],
            $this->tables[$this->api_key_role_key],
            "{$this->db->quoteName($this->tables[$this->api_keys_key])}.{$this->db->quoteName($apiColumns['id'])}" .
            "=" .
            "{$this->db->quoteName($this->tables[$this->api_key_role_key])}.{$this->db->quoteName($apiKeyRoleColumns['api_key_id'])}",
            $where,
            $apiColumns['api_key'],
            $bind_values
        );

        if (count($user) !== 1) {
            throw new InvalidUserException('User is not valid!');
        }

        $apiKey = $user[0][$apiColumns['api_key']];

        // verify password with user's password in db
        $verified = $this->verifier->verify($credentials['api_key'], $apiKey);

        if (!$verified) {
            throw new IncorrectAPIKeyException('API key is not valid!');
        }

        return true;
    }

    /**
     * @param string $api_key
     * @param string|null $extra_query
     * @param array $bind_values
     * @return bool
     * @throws IDBException
     * @throws IncorrectAPIKeyException
     */
    public function validateAPI(
        string $api_key,
        string $extra_query = null,
        array $bind_values = []
    ): bool
    {
        if (!isset($api_key) || empty($api_key)) {
            throw new \InvalidArgumentException('API key is not valid!');
        }

        $apiColumns = $this->config_parser->getTablesColumn($this->api_keys_key);
        $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);

        $where = "{$apiColumns['api_key']}=:__api_auth_api_key_value__";
        if (!empty($extra_query)) {
            $where .= " AND ({$extra_query})";
            $bind_values = array_merge($bind_values, [
                '__api_auth_api_key_value__' => $api_key,
            ]);
        } else {
            $bind_values = [
                '__api_auth_api_key_value__' => $api_key,
            ];
        }

        // get user from database
        $user = $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->api_keys_key],
            $this->tables[$this->api_key_role_key],
            "{$this->db->quoteName($this->tables[$this->api_keys_key])}.{$this->db->quoteName($apiColumns['id'])}" .
            "=" .
            "{$this->db->quoteName($this->tables[$this->api_key_role_key])}.{$this->db->quoteName($apiKeyRoleColumns['api_key_id'])}",
            $where,
            $apiColumns['api_key'],
            $bind_values
        );

        if (count($user) !== 1) {
            throw new IncorrectAPIKeyException('Api key is not valid!');
        }

        return true;
    }
}