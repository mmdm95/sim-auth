<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class InvalidUserException extends Exception implements IAuthException
{

}