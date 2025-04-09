<?php

namespace App\Middleware;


class AuthMiddleware
{
    public function handle($request, $next)
    {
        // Check if the request has an Authorization header
        if (!isset($request['headers']['Authorization'])) {
            return $this->unauthorizedResponse();
        }

        // Extract the token from the Authorization header
        $token = str_replace('Bearer ', '', $request['headers']['Authorization']);

        // Validate the token (this is a placeholder, implement your own logic)
        if (!$this->isValidToken($token)) {
            return $this->unauthorizedResponse();
        }

        // Proceed to the next middleware or request handler
        return $next($request);
    }

    private function isValidToken($token)
    {
        // Implement your token validation logic here
        // For example, check against a database or decode a JWT
        return true; // Placeholder for valid token
    }

    private function unauthorizedResponse()
    {
        return [
            'status' => 401,
            'message' => 'Unauthorized'
        ];
    }
}