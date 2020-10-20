<?php

use Sim\Auth\DBAuth;

// include with composer
include_once '../../vendor/autoload.php';
// or include using autoloader class of each library
//include_once '../../autoloader.php';
// other libraries included

// OK this is not useful when you have a lot of library
// so..... use composer instead :)

$host = '127.0.0.1';
$db = 'test';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

try {
    $mainCryptKey = 'ZjZvO0toUytAMTpbcXo4Q2Ezc0E5JDUtVVJkdGNqMlc0bTA3aS4=';
    $assuredCryptKey = 'dFxFMyw0OklBdjlrITI/LTgrZ3JuZTZfWjUvJnBSVy4wTyQpMTdxc04lfkhdUUB4';
    $cryptKeys = [
        'main' => $mainCryptKey,
        'assured' => $assuredCryptKey,
    ];

    $dbAuth = new DBAuth($pdo, 'default', $cryptKeys);
//    $admin = new DBAuth($pdo, 'admin');

    try {
        // add up tables from config
//        $dbAuth->runConfig();

        // add role tested - passed
//        $dbAuth->addRoleToUser(['the role name'], 'the username');

        // test suspend time setting - passed
//        $dbAuth->setSuspendTime(60);

        // login testing - passed
//        $dbAuth->login([
//            'username' => 'the username',
//            'password' => 'the password',
//        ]);

        // resume testing - passed
//        if (!$dbAuth->isLoggedIn() || $dbAuth->isSuspended()) {
//            $dbAuth->resume();
//        }

        // logout testing - passed
//        $dbAuth->logout();

        // destroy uuid - passed
//        $dbAuth->destroySession('session uuid');

        echo PHP_EOL;
        var_dump('logged in? ', $dbAuth->isLoggedIn());
        var_dump('expired? ', $dbAuth->isExpired());
        var_dump('suspended? ', $dbAuth->isSuspended());
        var_dump('none status? ', $dbAuth->isNone());
    } catch (\Sim\Auth\Exceptions\IncorrectPasswordException $e) {
        echo $e->getMessage();
    } catch (\Sim\Auth\Exceptions\InvalidUserException $e) {
        echo $e->getMessage();
    } catch (\Sim\Auth\Interfaces\IDBException $e) {
        echo $e->getMessage();
    }

    /********************************************************************/

    $apiAuth = new \Sim\Auth\APIAuth($pdo);

    try {
        // add role tested - passed
//        $apiAuth->addRoleToUser(['the role name'], 'the username');

//        $apiAuth->validate([
//            'username' => 'the username',
//            'api_key' => 'the api key',
//        ]);
    } catch (\Sim\Auth\Exceptions\IncorrectAPIKeyException $e) {
        echo $e->getMessage();
    } catch (\Sim\Auth\Exceptions\InvalidUserException $e) {
        echo $e->getMessage();
    } catch (\Sim\Auth\Interfaces\IDBException $e) {
        echo $e->getMessage();
    }
} catch (\Sim\Auth\Interfaces\IDBException $e) {
    echo 'Message: ' . $e->getMessage() . PHP_EOL;
    echo 'Line:' . $e->getLine() . PHP_EOL;
    echo 'File: ' . $e->getFile() . PHP_EOL;
} catch (\Sim\Auth\Interfaces\IStorageException $e) {
    echo 'Message: ' . $e->getMessage() . PHP_EOL;
    echo 'Line:' . $e->getLine() . PHP_EOL;
    echo 'File: ' . $e->getFile() . PHP_EOL;
} catch (\Sim\Crypt\Exceptions\CryptException $e) {
    echo 'Message: ' . $e->getMessage() . PHP_EOL;
    echo 'Line:' . $e->getLine() . PHP_EOL;
    echo 'File: ' . $e->getFile() . PHP_EOL;
}
