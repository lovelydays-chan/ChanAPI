<?php
namespace App\Core;

class Middleware {
    protected $middlewares = [];

    public function register($middleware) {
        $this->middlewares[] = $middleware;
    }

    public function handle($request) {
        foreach ($this->middlewares as $middleware) {
            $middlewareInstance = new $middleware();
            $middlewareInstance->handle($request);
        }
    }
}