<?php

use App\Actions\Storefront\FollowRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // Any 404 first asks the redirects table: the old address of a product matches the
        // product route and never reaches the fallback route (TZ §6.6, §8).
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => app(FollowRedirect::class)->handle($request));
    })->create();
