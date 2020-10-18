<?php

namespace Sim\Auth;

use PDO;
use Sim\Auth\Abstracts\AbstractBaseAuth;
use Sim\Auth\Exceptions\IncorrectAPIKeyException;
use Sim\Auth\Exceptions\InvalidUserException;
use Sim\Auth\Interfaces\IAuthValidator;
use Sim\Auth\Interfaces\IAuthVerifier;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Verifiers\Verifier;

class APIAuth extends AbstractBaseAuth implements IAuthValidator
{
    /**
     * @var IAuthVerifier
     */
    protected $verifier;

    /**
     * APIAuth constructor.
     * @param PDO $pdo_instance
     * @param int $algo
     * @param array|null $config
     * @throws IDBException
     */
    public function __construct(
        PDO $pdo_instance,
        $algo = PASSWORD_BCRYPT,
        ?array $config = null
    )
    {
        parent::__construct($pdo_instance, $config);

        $this->verifier = new Verifier($algo);
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
        $apiColumns = $this->config_parser->getTablesColumn($this->api_keys_key);

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
        $user = $this->db->getFrom(
            $this->tables[$this->api_keys_key],
            $where,
            $apiColumns['api_key'],
            $bind_values
        );

        if (count($user) !== 1) {
            throw new InvalidUserException('User is not valid!');
        }

        $apiKey = $user[0][$apiColumns['api_key']];

        // verify password with user's password in db
        $verified = $this->verifier->verify($credentials['password'], $apiKey);

        if (!$verified) {
            throw new IncorrectAPIKeyException('API key is not valid!');
        }

        return true;
    }
}