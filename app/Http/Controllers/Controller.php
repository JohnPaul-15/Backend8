<?php

namespace App\Http\Controllers;
//
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Send a success JSON response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, string $message = '', int $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Send an error JSON response
     *
     * @param string $message
     * @param int $statusCode
     * @param array $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message = '', int $statusCode = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Send a not found JSON response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found')
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Send an unauthorized JSON response
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, 401);
    }
}