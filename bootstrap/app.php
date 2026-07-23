<?php

use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Exceptions\OrderPaymentNotAllowedException;
use App\Exceptions\SeatGenerationConflictException;
use App\Exceptions\SeatsNotAvailableException;
use App\Http\Middleware\RestrictToAllowedIps;
use App\Http\Middleware\VerifyInternalApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RestrictToAllowedIps::class,
            VerifyInternalApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Endpoint not found.',
                'path' => $request->path(),
            ], 404);
        });
        $exceptions->render(function (SeatsNotAvailableException $e, Request $request) {
            return response()->json([
                'error' => 'seats_not_available',
                'message' => $e->getMessage(),
            ], 409);
        });
        $exceptions->render(function (OrderPaymentNotAllowedException $e, Request $request) {
            return response()->json([
                'error' => 'order_payment_not_allowed',
                'message' => $e->getMessage(),
            ], 409);
        });
        $exceptions->render(function (InvalidOrderStatusTransitionException $e, Request $request) {
            return response()->json([
                'error' => 'invalid_order_status_transition',
                'message' => $e->getMessage(),
                'from' => $e->from->value,
                'to' => $e->to->value,
            ], 409);
        });
        $exceptions->render(function (SeatGenerationConflictException $e, Request $request) {
            return response()->json([
                'error' => 'seat_generation_conflict',
                'message' => $e->getMessage(),
            ], 409);
        });
    })->create();
