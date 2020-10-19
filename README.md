# Simplicity Authentication
A library for authentication.

## Install
**composer**
```php 
composer require mmdm/sim-auth
```

Or you can simply download zip file from github and extract it, 
then put file to your project library and use it like other libraries.

Just add line below to autoload files:

```php
require_once 'path_to_library/autoloader.php';
```

and you are good to go.

### But Wait

It has some dependency libraries that do not have `autoloader` 
and some have `autoloader` but you should `include` them yourself. 
Why rough it when `composer` is here to help us. Please use 
composer to enjoy this library ☻

## Architecture

This library is mostly for role-based and resource-based architecture 
together. Look at below information:

**Collation:**

It should be `utf8mb4_unicode_ci` because it is a very nice collation. 
For more information about differences between `utf8` and `utf8mb4` 
in `general` and `unicode` please see 
[this link][1] from `stackoverflow`

Note: Please use `utf8mb4` collations.

**Table:**

- users

    This table contains all users.
    
    Least columns of this table should be:
        
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - username (VARCHAR(20) NOT NULL)
    
    - password (VARCHAR(255) NOT NULL)

- api_keys

    This table is to store all api users and the keys.
    
    Least columns of this table should be:
    
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - username (VARCHAR(20) NOT NULL)
    
    - api_key (VARCHAR(255) NOT NULL)

- roles

    This table contains all roles.
    
    Least columns of this table should be:
    
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - name (VARCHAR(20))
    
    - description (VARCHAR(100))
    
    - is_admin (TINYINT(1) UNSIGNED NOT NULL DEFAULT 0)

- resources

    This table contains all resources.
    
    Least columns of this table should be:
    
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - name (VARCHAR(20))
    
    - description (VARCHAR(100))

- sessions

    This table contains all UUIDs that generate for a user.
    
    Least columns of this table should be:
    
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - uuid (VARCHAR(36))
    
    - user_id (INT(11) UNSIGNED NOT NULL)
    
    - ip_address (VARCHAR(16))
    
    - device (TEXT)
    
    - browser (TEXT)
    
    - platform (TEXT)
    
    - expire_at (INT(11) UNSIGNED)
    
    - created_at (INT(11) UNSIGNED)
        
**Table Relations:**

- user_role

    This table contains all users and their roles.
    
    Least columns of this table should be:
    
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - user_id (INT(11) UNSIGNED NOT NULL)
    
    - role_id (INT(11) UNSIGNED NOT NULL)  
    
    **Constraints:**
    
    - ADD CONSTRAINT fk_urp_u FOREIGN KEY(user_id) REFERENCES users(id)
    
    - ADD CONSTRAINT fk_urp_r FOREIGN KEY(role_id) REFERENCES roles(id)

- user_res_perm

    This table contains all users, resources and their permission.
    
    Least columns of this table should be:

    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - user_id (INT(11) UNSIGNED NOT NULL)
    
    - resource_id (INT(11) UNSIGNED NOT NULL)
    
    - perm_id (INT(11) UNSIGNED NOT NULL)
    
    - is_allow (TINYINT(1) UNSIGNED NOT NULL DEFAULT 1) 
    
    **Constraints:**
    
    - ADD CONSTRAINT fk_upp_u FOREIGN KEY(user_id) REFERENCES users(id)
    
    - ADD CONSTRAINT fk_upp_pa FOREIGN KEY(resource_id) REFERENCES resources(id)
                    
- role_res_perm

    This table contains all roles, resources and their permission.
    
    Least columns of this table should be:

    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - role_id (INT(11) UNSIGNED NOT NULL)
    
    - resource_id (INT(11) UNSIGNED NOT NULL)
    
    - perm_id (INT(11) UNSIGNED NOT NULL)
    
    **Constraints:**
    
    - ADD CONSTRAINT fk_rpp_r FOREIGN KEY(role_id) REFERENCES roles(id)
    
    - ADD CONSTRAINT fk_rpp_pa FOREIGN KEY(resource_id) REFERENCES resources(id)

## How to use

First of all you need a `PDO` connection like below:

```php
$host = '127.0.0.1';
$db = 'database name';
$user = 'username';
$pass = 'password';
// this is very nice collation to use
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    // add this option to show exception on any bad condition
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
```

[Optionally] Second you need two keys to protect stored credentials. 
These two keys should be two base64 coded strings. Just generate 
two passwords and encode them to base64 strings. 
For more info about these two keys see [this link][2]

### Available Connections

- DBAuth

- APIAuth

## Shared Methods

#### AbstractBaseAuth

All connections have below methods:

```php
__construct(PDO $pdo_instance, ?array $config = null);
```

`$pdo_instance`: Argument is `PDO` connection that explained above.

`$config`: To change the `Architecture` mentioned above, you can 
pass and array to change the config or `null` for default behavior.

#### `setConfig(array $config, bool $merge_config = false)`

With this method you can change `Architecture` signatures. If you 
need to merge default configuration and passed configuration, 
you can pass `true` as seconds parameter.

#### `runConfig()`

The configuration can build internally by library. Thi function 
parse the configuration of library.

**Note:** If you build tables from configuration by yourself, 
there is no need to call this method, but please before any 
table creation, call this method to make your tables.

#### `addResources(array $resources)`

Add some resources to database.

$resources array has following structure:

```php
// an array of arrays 
[
  // this array is columns of resource table and their values
  [
    column1 => value1,
    column2 => value2,
  ],
  [
    column1 => value3,
    column2 => value4,
  ],
  ...
]
```

#### `removeResources(array $resources)`

Remove some resources from database.

**Note:** $resources should be array of resources' name 
or resources' id.

#### `hasResource($resource): bool`

Check if a resource is exists or not.

**Note:** $resources should be array of resources' name 
or resources' id.

#### `getResources(): array`

Get all resources.

**Note:** It returns all columns of resources table.

#### `getResourcesNames(): array`

Get all resources `name` column.

#### `addRoles(array $roles)`

Add some roles to database.

$roles array has following structure:

```php
[
  // this array is columns of role table and their values
  [
    column1 => value1,
    column2 => value2,
  ],
  [
    column1 => value3,
    column2 => value4,
  ],
  ...
]
```

#### `removeRoles(array $roles)`

Remove some roles from database.

**Note:** $roles should be array of roles' name or roles' id.

#### `hasRole(string $role): bool`

Check if a role is exists or not. You must pass the role name as 
parameter. It is because of convenient to use constants as roles' 
names.

#### `getRoles(): array`

Get all roles.

**Note:** It returns all columns of roles table.

#### `getAdminRoles(): array`

Get all admin roles.

**Note:** It returns all columns of roles table.

#### `getRolesName(): array`

Get all roles `name` column.

#### `getAdminRolesName(): array`

Get all admin's roles `name` column.

#### `allowRole($resource, array $permission, $role)`

Make a role to allow a resource with a specific permission.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass role's `name` or `id`.

#### `disallowRole($resource, array $permission, $role)`

Make a role to disallow a resource with a specific permission.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass role's `name` or `id`.

#### AbstractAuth

Some connections (that will say in each connection) have 
below methods:

```php
__construct(
    PDO $pdo_instance,
    string $namespace = 'default',
    array $crypt_keys = [],
    int $storage_type = IAuth::STORAGE_DB,
    ?array $config = null
);
```

`$pdo_instance`: See constructor of [AbstractBaseAuth][8].

`$namespace`: You can specify a namespace to separate each logic of 
your application. For example a `home` namespace for users login and an 
`admin` namespace for administrators logic.

`$crypt_keys`: To protect your cookies and sessions information, you 
can specify two keys on below structure:

```
[
    'main' => main key of encryption,
    'assured' => assured key of encryption
]
```

If you don't need any encryption (that is not suggested), you can pass 
an empty array as value of the parameter.

`$storage_type`: The storage type tell authentication library to 
store needed information in where. There are three types of storage:

- IAuth::STORAGE_SESSION

    This storage type, store data in the php session

- IAuth::STORAGE_COOKIE

    This storage type, store data in the cookie

- IAuth::STORAGE_DB (Suggested)

    This storage type, store data in the database with a uuid

(They are available under `Sim\Auth\Interfaces` namespace)

`$config`: See constructor of [AbstractBaseAuth][8].

#### `getStatus(): int`

There are four types of status for better management of 
authentication:

- IAuth::STATUS_NONE

    When there is no session/cookie or anything, the status is none

- IAuth::STATUS_ACTIVE

    When a user logged in to his/her account, the status will be active

- IAuth::STATUS_EXPIRE

    After a user login it'll set a session/cookie for expiration time 
    of login. If it expires, it'll change status to expire.

- IAuth::STATUS_SUSPEND

    After a user login it'll set a session/cookie for suspend time 
    of login. If there is no request for a while, it'll change 
    status to suspend.

(They are available under `Sim\Auth\Interfaces` namespace)

#### `isLoggedIn(): bool`

Check if a user is logged in or not.

#### `isExpired(): bool`

Check if a user's session/cookie is expired or not.

#### `isSuspended(): bool`

Check if a user's session/cookie is suspended or not.

#### `isNone(): bool`

Check if there is no status (if status is none or not).

#### `extendSuspendTime()`

If it need to extend suspend time to start over the time, this 
method helps.

#### `setExpiration($timestamp)`

Set expiration time for user's login.

**Note:** Just enter the amount of time you need from now in seconds like 300.

**Note:** Also can pass string time like [+5 days]

#### `getExpiration(): int`

Get login expiration time.

**Note:** Default expiration time is `31536000` seconds that is 
`1 year`.

#### `setSuspendTime($timestamp)`

Set suspend time for user's login.

**Note:** Just enter the amount of time you need from now in seconds like 300.

**Note:** Also can pass string time like [+5 days]

#### `getSuspendTime(): int`

Get login suspend time.

**Note:** Default suspend time is `1800` seconds that is 
`30 minutes`.

#### `setStorageType(int $type)`

Set storage type of storing data. Storage types are explained 
above.

#### `getStorageType(): int`

Get storage type for storing data.

**Note:** Default storage type is `IAuth::STORAGE_DB`.

#### `setNamespace(string $namespace)`

Set namespace to separate your logic.

#### `getNamespace(): string`

Get current namespace.

**Note:** Default namespace is `default`.

#### `loginWithID(int $id)`

Make a login with user's id.

#### `resume()`

If there were a login before, it resumes that login.

#### `logout()`

Make user logout and delete and destroy any stored data.

#### `getSessionUUID($username = null): array`

Get a user's UUIDs.

**Note:** It only works with [IAuth::STORAGE_DB] otherwise 
it'll not work.

**Note:** You can pass user's `username` or `id` to get UUIDs or 
pass `null` or nothing to get current user's UUIDs.

#### `destroySession(string $session_uuid): bool`

Destroy a UUID session from database.

**Note:** It only works with [IAuth::STORAGE_DB] otherwise 
it'll not work.

#### `getCurrentUser(): ?array`

Get current user's credentials.

#### `isAllow($resource, int $permission, $username = null): bool`

Check if a user is allow to have a permission to a specific resource 
or not.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

Permissions are one of below:

- IAuth::PERMISSION_CREATE

- IAuth::PERMISSION_READ

- IAuth::PERMISSION_UPDATE

- IAuth::PERMISSION_DELETE

(They are available under `Sim\Auth\Interfaces` namespace)

#### `allowUser($resource, array $permission, $username = null)`

Make a user to allow a resource with a specific permission.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

#### `disallowUser($resource, array $permission, $username = null)`

Make a user to disallow a resource with a specific permission.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

#### `getUserRole($username): array`

Get a user's roles.

**Note:** You can pass user's `username` or `id`.

#### `getCurrentUserRole(): array`

Get current user's roles.

#### `addRoleToUser(array $role, $username = null)`

Add some roles to a user.

**Note:** You should pass an array of roles' name.

**Note:** You can pass user's `username` or `id` to add role to 
or pass `null` or nothing to add roles to current user.

#### `isAdmin($username = null): bool`

Check if a user is admin or not.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

#### `quoteSingleName(string $name): string`

Quote a column or table name or anything that needed to quote.

**Note:** It is tested with `mysql` but not with `sql server`.

### Usage of each connection

#### `DBAuth`

Contains all of [AbstractAuth][3] and below extra information.

```php
// this is constructor
$auth = new DBAuth (
    PDO $pdo_instance,
    string $namespace = 'default',
    array $crypt_keys = [],
    $algo = PASSWORD_BCRYPT,
    int $storage_type = IAuth::STORAGE_DB,
    ?array $config = null
);
```

`$pdo_instance`: See constructor of [AbstractAuth][3].

`$namespace`: See constructor of [AbstractAuth][3].

`$crypt_keys`: See constructor of [AbstractAuth][3].

`$algo`: Algorithm to verify the password. It could be one of 
`PASSWORD_DEFAULT` or `PASSWORD_BCRYPT` to verify with 
`password_verify` function or other strings to verify with 
`hash` function.

`$storage_type`: See constructor of [AbstractAuth][3].

`$config`: See constructor of [AbstractAuth][3].

#### `login(array $credentials, string $extra_query = null, array $bind_values = [])`

`$credentials` should be like below structure:

```php
// keys MUST be same as below
[
  'username' => provided username,
  'password' => provided password,
]
```

If there are more conditions to check for a user, you can pass them 
by `$extra_query` parameter and it MUST be parameterized.

**Note:** You should know that the login will check inside of a 
joined tables of `users` and `roles`. So if there is a condition, 
it's better to have the table's name before any column. 

**Note:** You should quote your columns by yourself or use 
`quoteSingleName` that explained above.

```php
// for example check if user is activated
$auth->login([
    'username' => provided username,
    'password' => provided password,
], 'users.is_active=:active', [
    'active' => 1,
]);

// to see if login has succeed
$isLoggedIn = $auth->isLoggedIn();
```     

**Note:** To get the error of login, put it in try, catch block.

```php
try {
    $auth->login([
        'username' => provided username,
        'password' => provided password,
    ]);
} catch (\Sim\Auth\Exceptions\IncorrectPasswordException $e) {
    // do something according to error
    // eg.
    echo 'Username or password is incorrect!';
} catch (\Sim\Auth\Exceptions\InvalidUserException $e) {
    // do something according to error
    // eg.
    echo 'Username or password is incorrect!';
} catch (\Sim\Auth\Interfaces\IDBException $e) {
    // do something according to error
    // eg.
    echo 'Failed database connection!';
}
```

#### AbstractAPIAuth

Contains all of [AbstractBaseAuth][8].

```php
__construct(
    PDO $pdo_instance,
    ?array $config = null
);
```

`$pdo_instance`: See constructor of [AbstractBaseAuth][8].

`$config`: See constructor of [AbstractBaseAuth][8].

#### `isAllow($resource, int $permission, $username = null): bool`

Check if a user is allow to have a permission to a specific resource 
or not.

**Note:** You can pass resource's `name` or `id`.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

Permissions are one of below:

- IAuth::PERMISSION_CREATE

- IAuth::PERMISSION_READ

- IAuth::PERMISSION_UPDATE

- IAuth::PERMISSION_DELETE

(They are available under `Sim\Auth\Interfaces` namespace)

#### `getUserRole($username): array`

Get a user's roles.

**Note:** You can pass user's `username` or `id`.

#### `getCurrentUserRole(): array`

Get current user's roles.

#### `addRoleToUser(array $roles, $username = null)`

Add some roles to a user.

**Note:** You should pass an array of roles' name.

**Note:** You can pass user's `username` or `id` to add role to 
or pass `null` or nothing to add roles to current user.

#### `isAdmin($username = null): bool`

Check if a user is admin or not.

**Note:** You can pass user's `username` or `id` to check or pass 
`null` or nothing to check for current user.

#### `APIAuth`

Contains all of [AbstractAPIAuth][9] and below extra information.

#### `validate(array $credentials, string $extra_query = null, array $bind_values = []): bool`
                                                                  
`$credentials` should be like below structure:

```php
// keys MUST be same as below
[
  'username' => provided username,
  'api_key' => provided api key,
]
```

If there are more conditions to check for a user, you can pass them 
by `$extra_query` parameter and it MUST be parameterized.

**Note:** You should know that the verify will check inside of a 
joined tables of `api_keys` and `roles`. So if there is a condition, 
it's better to have the table's name before any column. 

**Note:** You should quote your columns by yourself or use 
`quoteSingleName` that explained before.

```php
// for example check if user is allowed or something
$auth->verify([
    'username' => provided username,
    'api_key' => provided api key,
], 'api_keys.allowed=:allow', [
    'allow' => 1,
]);
```     

**Note:** To get the error of verify, put it in try, catch block.

```php
try {
    $auth->verify([
        'username' => provided username,
        'api_key' => provided api key,
    ]);
} catch (\Sim\Auth\Exceptions\IncorrectAPIKeyException $e) {
    // do something according to error
    // eg.
    echo 'Username or api key is incorrect!';
} catch (\Sim\Auth\Exceptions\InvalidUserException $e) {
    // do something according to error
    // eg.
    echo 'Username or api key is incorrect!';
} catch (\Sim\Auth\Interfaces\IDBException $e) {
    // do something according to error
    // eg.
    echo 'Failed database connection!';
}
```

# Dependencies
There is some dependencies here including:
 
[Crypt][4] library. With this feature, if any session/cookie 
hijacking happens, they can't see actual data because it 
is encrypted.

[Cookie][5] library to manipulate cookies.

[Session][6] library to manipulate sessions.

[Agent][7] library to get user's device and browser information 
to store in `sessions` table

And some dependency from [Agent][7] library.

# License
Under MIT license.

[1]: https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
[2]: https://github.com/mmdm95/sim-crypt
[8]: #abstractbaseauth
[3]: #abstractauth
[4]: https://github.com/mmdm95/sim-crypt
[5]: https://github.com/mmdm95/sim-cookie
[6]: https://github.com/mmdm95/sim-session
[7]: https://github.com/jenssegers/agent
[9]: #abstractapiauth