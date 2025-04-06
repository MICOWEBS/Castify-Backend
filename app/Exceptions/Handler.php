<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return $this->handleApiException($request, $e);
            }
        });
    }

    /**
     * Handle API exceptions and return standardized JSON responses.
     */
    private function handleApiException(Request $request, Throwable $exception): JsonResponse
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response = [
            'success' => false,
            'message' => 'Server error, please try again later.',
        ];

        if ($exception instanceof ValidationException) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            $response['message'] = 'The given data was invalid.';
            $response['errors'] = $exception->validator->errors()->getMessages();
        } elseif ($exception instanceof AuthenticationException) {
            $status = Response::HTTP_UNAUTHORIZED;
            $response['message'] = 'Unauthenticated.';
        } elseif ($exception instanceof ModelNotFoundException) {
            $status = Response::HTTP_NOT_FOUND;
            $model = strtolower(class_basename($exception->getModel()));
            $response['message'] = "No {$model} found with the specified identifier.";
        } elseif ($exception instanceof NotFoundHttpException) {
            $status = Response::HTTP_NOT_FOUND;
            $response['message'] = 'The requested resource was not found.';
        }

        if (config('app.debug')) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        Log::error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json($response, $status);
    }
} 