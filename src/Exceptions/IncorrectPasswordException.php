<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class IncorrectPasswordException extends Exception implements IAuthException
{

}