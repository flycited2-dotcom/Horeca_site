<?php

use App\Actions\Storefront\FollowRedirect;
use App\Http\Middleware\RememberUtm;
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
        // On the server the request passes the nginx of the host and the nginx of the store
        // container (TZ §17): the address of the buyer and HTTPS come in X-Forwarded-*
        // headers, and only these private networks may send them.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        $middleware->web(append: [RememberUtm::class]);
        // A signed-in customer who opens the login or registration page goes to the account (TZ §11).
        $middleware->redirectUsersTo(fn (): string => route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Any 404 first asks the redirects table: the old address of a product matches the
        // product route and never reaches the fallback route (TZ §6.6, §8).
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => app(FollowRedirect::class)->handle($request));
    })->create();
