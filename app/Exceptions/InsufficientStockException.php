<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $code = 422;

    /**
     * @param  array  $context  Structured detail for the stock.rejected audit
     *                          log entry — logged from the global exception
     *                          handler (bootstrap/app.php), not here, because
     *                          this exception is always thrown from inside a
     *                          DB::transaction() that's about to roll back;
     *                          logging via AuditLogService (which relies on
     *                          DB::afterCommit) at the throw site would mean
     *                          the log entry gets discarded along with the
     *                          rolled-back transaction.
     */
    public function __construct(string $message = '', public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
