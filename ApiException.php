<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $statusCode = match (true) {
            $this->code >= 100 && $this->code < 600 => $this->code,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        $response = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        if (config('app.debug')) {
            $response['trace'] = $this->getTrace();
        }

        return response()->json($response, $statusCode);
    }
}
