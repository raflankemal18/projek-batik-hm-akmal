<?php

namespace App\Exceptions;

use Exception;

class EmptyCartException extends Exception
{
    protected $code = 422;
}
