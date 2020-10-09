<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IStorageException;

class InvalidStorageTypeException extends Exception implements IStorageException
{

}