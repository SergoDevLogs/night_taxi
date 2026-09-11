<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Все ошибки для api/* и JSON-запросов отдаём в JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 422 — ValidationErrorResponse
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = ['field' => $field, 'issue' => $msg];
                }
            }

            return response()->json([
                'error'   => 'validation_failed',
                'message' => 'Ошибка валидации входных данных',
                'details' => $details,
            ], 422);
        });

        // 401 — AuthenticationException (нет токена / токен неверный)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'error'   => 'unauthenticated',
                'message' => 'Требуется авторизация',
            ], 401);
        });

        // 404 — NotFoundHttpException (сюда же попадает ModelNotFoundException от findOrFail)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'error'   => 'not_found',
                'message' => 'Ресурс не найден',
            ], 404);
        });

        // 400 — ручной abort(400, '...') в PackingController
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e->getStatusCode() !== 400) {
                return null;
            }

            return response()->json([
                'error'   => 'bad_request',
                'message' => $e->getMessage() ?: 'Некорректный запрос',
            ], 400);
        });

        // 500 — всё, что не перехвачено выше
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof NotFoundHttpException
                || $e instanceof HttpException) {
                return null;
            }

            \Log::error($e);

            return response()->json([
                'error'   => 'internal_error',
                'message' => 'Внутренняя ошибка сервера',
            ], 500);
        });
    })->create();
