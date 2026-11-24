<?php

use App\Exceptions\UserError;
use App\Http\Middleware\ConvertShamsiDates;
use App\Http\Middleware\EnsureCentral;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ExportTable;
use App\Http\Middleware\InitializeTenancy;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StripNumberCommas;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // a clinic's pages, the reseller panel and the system administrator panel each have their own sign-in page
        $middleware->redirectGuestsTo(fn () => app(Tenancy::class)->tenant ? route('login') : (request()->is('reseller', 'reseller/*') ? route('reseller.login') : route('admin.login')));
        $middleware->redirectUsersTo(fn () => app(Tenancy::class)->tenant ? route('dashboard') : route('admin.home'));

        // the clinic is chosen from the address before anything reads the database
        $middleware->web(
            append: [SetLocale::class, ExportTable::class, StripNumberCommas::class, ConvertShamsiDates::class, EnsureUserIsActive::class, \App\Http\Middleware\LogSupervisor::class, \App\Http\Middleware\LockUntilApproved::class],
            prepend: [InitializeTenancy::class],
        );
        $middleware->alias(['central' => EnsureCentral::class, 'tenant' => EnsureTenant::class, 'staff' => \App\Http\Middleware\StaffMay::class]);
        // the address is checked before anyone is asked to sign in
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureCentral::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A problem the user can fix: go back to the form and show the message.
        $exceptions->render(function (UserError $e, Request $request) {
            // a form sent from a pop-up (quick add) gets the message back as data
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('danger', $e->getMessage());
        });
        $exceptions->dontReport(UserError::class);
    })->create();
