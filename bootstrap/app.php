<?php

use App\Http\Middleware\PreventSearchIndexing;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\EnsureAccountActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(PreventSearchIndexing::class);
        $middleware->appendToGroup('web', EnsureAccountActive::class);
        $middleware->alias(['role' => RequireRole::class]);
        $middleware->redirectGuestsTo('/account');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $error, Request $request) {
            if ($error->getStatusCode() !== 409 || ! $request->boolean('return_to_listing') || $request->expectsJson()) {
                return null;
            }

            $destination = match ($request->route()?->getName()) {
                'provider.offer', 'provider.reschedule' => route('provider.activity', $request->route('activity')).'#trial-management',
                'admin.decision', 'admin.lifecycle' => route('admin.activity', $request->route('activity')),
                default => null,
            };

            return $destination === null ? null : redirect()->to($destination)->withErrors(['listing' => $error->getMessage()]);
        });
    })
    ->create();
