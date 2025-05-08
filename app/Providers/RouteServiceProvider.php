<?php

namespace App\Providers;

use App\Core\App;
use App\Core\RouteService;

class RouteServiceProvider
{
    public function boot(App $app): void
    {
        $app->singleton('route', function () use ($app) {
            return new RouteService($app->getResponse());
        });

        $routeService = $app->make('route');
        $this->loadRoutes($routeService);
    }

    protected function loadRoutes(RouteService $routeService): void
    {
        $routeService->group(['prefix' => '/api'], function () {
            require_once __DIR__ . '/../Routes/api.php';
        });
    }
}
