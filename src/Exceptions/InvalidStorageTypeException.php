<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class InvalidStorageTypeException extends Exception implements IAuthException
{

}