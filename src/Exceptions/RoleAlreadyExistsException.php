<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class RoleAlreadyExistsException extends Exception implements IAuthException
{

}