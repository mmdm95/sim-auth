<?php

namespace Sim\Auth\Exceptions;

use Exception;
use Sim\Auth\Interfaces\IAuthException;

class PermissionDeniedException extends Exception implements IAuthException
{

}