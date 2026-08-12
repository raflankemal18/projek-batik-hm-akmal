<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $code = 422;
}
