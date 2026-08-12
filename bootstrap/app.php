<?php

use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Services\AuditLogService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,

        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (InsufficientStockException|EmptyCartException $e, Request $request) {
            // By the time this handler runs, the DB::transaction() the
            // exception was thrown from has already rolled back — logging
            // here (not at the throw site) is what makes the stock.rejected
            // entry survive the rollback. See InsufficientStockException's
            // docblock.
            if ($e instanceof InsufficientStockException && $e->context !== []) {
                AuditLogService::recordTransaction('stock.rejected', null, 'Checkout ditolak (stok habis)', $e->context);
            }

            return back()->withErrors(['stock' => $e->getMessage()]);
        });
    })->create();
