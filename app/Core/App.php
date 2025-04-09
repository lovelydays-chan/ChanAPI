<?php

namespace App\Core;

use App\Core\Middleware;
use ReflectionMethod;
use ReflectionNamedType;

class App
{
    protected array $routes = [];
    protected string $requestMethod;
    protected string $requestUri;
    protected Middleware $globalMiddleware;
    protected Response $response;

    public function __construct()
    {
        $this->initializeRequest();
        $this->globalMiddleware = new Middleware();
        $this->response = response();
    }

    /**
     * กำหนดค่าเริ่มต้นของ request
     */
    protected function initializeRequest(): void
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function addGlobalMiddleware($middleware): void
    {
        $this->globalMiddleware->register($middleware);
    }

    public function addRoute(string $method, string $uri, $controller, ?string $action = null, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'controller' => $controller,
            'action' => $action,
            'middlewares' => $middlewares,
        ];
    }

    public function addGroup(string $prefix, array $middlewares, array $routes): void
    {
        foreach ($routes as $route) {
            $this->addRoute(
                $route['method'],
                $prefix . $route['uri'],
                $route['controller'],
                $route['action'] ?? null,
                array_merge($middlewares, $route['middlewares'] ?? [])
            );
        }
    }

    public function run(): void
    {
        $this->globalMiddleware->handle($_SERVER);

        foreach ($this->routes as $route) {
            if ($this->matchesRoute($route)) {
                $matches = $this->getRouteMatches($route['uri']);
                $this->executeRoute($route, $matches);
                return;
            }
        }

        $this->response->json(['msg' => 'Not Found'], 404);
    }

    public function handle(string $method, string $uri, array $data = [], array $server = [])
    {
        $this->prepareTestRequest($method, $uri, $data, $server);
        $this->globalMiddleware->handle($server);

        foreach ($this->routes as $route) {
            if ($this->matchesRoute($route)) {
                $matches = $this->getRouteMatches($route['uri']);
                return $this->executeRoute($route, $matches, $data);
            }
        }

        return $this->response->json(['msg' => 'Not Found'], 404);
    }

    /**
     * เตรียม request สำหรับการทดสอบ
     */
    protected function prepareTestRequest(string $method, string $uri, array $data, array &$server): void
    {
        $this->requestMethod = $method;
        $this->requestUri = $uri;
        $this->response->asTest();

        $server = array_merge($_SERVER, $server);
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri;

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $server['CONTENT_TYPE'] = $server['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded';
        }
    }

    /**
     * ตรวจสอบว่า request ตรงกับ route หรือไม่
     */
    protected function matchesRoute(array $route): bool
    {
        $pattern = $this->convertUriToPattern($route['uri']);
        return $this->requestMethod === $route['method'] && preg_match($pattern, $this->requestUri);
    }

    /**
     * แปลง URI เป็น regex pattern
     */
    protected function convertUriToPattern(string $uri): string
    {
        $pattern = preg_replace('/\{\w+\}/', '([a-zA-Z0-9_-]+)', $uri);
        return "#^" . $pattern . "$#";
    }

    /**
     * ดึงค่าที่ match จาก URI
     */
    protected function getRouteMatches(string $uri): array
    {
        $pattern = $this->convertUriToPattern($uri);
        preg_match($pattern, $this->requestUri, $matches);
        array_shift($matches);
        return $matches;
    }

    /**
     * ประมวลผล route
     */
    protected function executeRoute(array $route, array $matches, array $requestData = [])
    {
        $this->executeMiddlewares($route['middlewares'] ?? []);

        if (is_callable($route['controller'])) {
            return call_user_func_array($route['controller'], array_merge($matches, [$requestData]));
        }

        return $this->executeControllerAction(
            $route['controller'],
            $route['action'],
            $matches,
            $requestData
        );
    }

    /**
     * ประมวลผล middleware ทั้งหมด
     */
    protected function executeMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            (new $middleware())->handle($_SERVER);
        }
    }

    /**
     * ประมวลผล controller action
     */
    protected function executeControllerAction(string $controller, string $action, array $matches, array $requestData)
    {
        $controllerInstance = new $controller();
        $request = $this->createRequestObject($controller, $action, $requestData);
        $parameters = $this->resolveParameters($controller, $action, $matches, $request);
        return $controllerInstance->$action(...$parameters);
    }

    /**
     * สร้าง request object
     */
    protected function createRequestObject(string $controller, string $action, array $requestData): BaseRequest
    {
        $requestClass = $this->resolveRequestClass($controller, $action);
        return new $requestClass($requestData);
    }

    protected function resolveRequestClass(string $controller, string $action): string
    {
        $method = new ReflectionMethod($controller, $action);

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                if (class_exists($className) && is_a($className, BaseRequest::class, true)) {
                    return $className;
                }
            }
        }

        return \App\Core\Request::class;
    }


    protected function resolveParameters(string $controller, string $action, array $matches, ?BaseRequest $request = null): array
    {
        $reflection = new ReflectionMethod($controller, $action);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $this->resolveParameter($parameter, $matches, $request);
        }

        return $parameters;
    }

    protected function resolveParameter(\ReflectionParameter $parameter, array &$matches, ?BaseRequest $request)
    {
        $type = $parameter->getType();

        if (!$type) {
            return array_shift($matches) ?? $parameter->getDefaultValue();
        }

        if ($type instanceof ReflectionNamedType) {
            if (is_subclass_of($type->getName(), BaseRequest::class)) {
                return $request ?? new ($type->getName())();
            }

            if (!$type->isBuiltin()) {
                return new ($type->getName())();
            }
        }

        return array_shift($matches) ?? $parameter->getDefaultValue();
    }

    public function isTesting(): bool
    {
        return getenv('APP_ENV') === 'testing';
    }
}
