<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\AccountingPeriodClosedException $e, \Illuminate\Http\Request $request) {
            \Filament\Notifications\Notification::make()
                ->title('Periode Akuntansi Sudah Ditutup')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            if ($request->hasHeader('X-Livewire')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'accounting_period' => $e->getMessage(),
                ]);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create()
    ->usePublicPath(
        dirname(__DIR__).'/public'
    );
