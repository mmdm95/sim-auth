<?php

use Sim\Auth\DBAuth;

// include with composer
include_once '../../vendor/autoload.php';

$err_response = function ($message, $line, $file) {
    return json_encode([
        'message' => $message,
        'line' => $line,
        'file' => $file,
    ]);
};

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

// all of our endpoints start with /person
// everything else results in a 404 Not Found
if ($uri[1] !== 'api') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

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
//        $dbAuth->destroySession('d95d2a65-2221-4e92-862a-8e1cf64ab0f6');

//        echo PHP_EOL;
//        var_dump('logged in? ', $dbAuth->isLoggedIn());
//        var_dump('expired? ', $dbAuth->isExpired());
//        var_dump('suspended? ', $dbAuth->isSuspended());
//        var_dump('none status? ', $dbAuth->isNone());

        $validations = \Sim\Auth\Helpers\BasicAuth::parse();

        echo json_encode(array_merge([
            'success' => 'It was a successful try',
        ], $validations, [$_SERVER['HTTP_X_API_KEY'] ?? '', \Sim\Auth\Utils\APIUtil::generateAPIKey()]));
    } catch (\Sim\Auth\Exceptions\IncorrectPasswordException $e) {
        echo  $err_response($e->getMessage(), $e->getLine(), $e->getFile());
    } catch (\Sim\Auth\Exceptions\InvalidUserException $e) {
        echo  $err_response($e->getMessage(), $e->getLine(), $e->getFile());
    } catch (\Sim\Auth\Interfaces\IDBException $e) {
        echo  $err_response($e->getMessage(), $e->getLine(), $e->getFile());
    }
} catch (\Sim\Auth\Interfaces\IDBException $e) {
    echo $err_response($e->getMessage(), $e->getLine(), $e->getFile());
} catch (\Sim\Auth\Interfaces\IStorageException $e) {
    echo $err_response($e->getMessage(), $e->getLine(), $e->getFile());
} catch (\Sim\Crypt\Exceptions\CryptException $e) {
    echo $err_response($e->getMessage(), $e->getLine(), $e->getFile());
}
