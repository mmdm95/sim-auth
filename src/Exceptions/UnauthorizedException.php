<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class UnauthorizedException extends Exception implements IAuthException
{

}