<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class IncorrectAPIKeyException extends Exception implements IAuthException
{

}