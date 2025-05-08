<?php

namespace App\Core;

use App\Core\Response;
use App\Core\BaseRequest;

class RouteService
{
    protected static ?self $instance = null;

    protected array $routes = [];
    protected string $requestMethod;
    protected string $requestUri;
    protected Response $response;
    protected array $currentGroup = [];
    protected array $namedRoutes = [];

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->initializeRequest();
    }

    /**
     * Get the singleton instance of RouteService
     */
    public static function getInstance(): self
    {
        return app('route'); // ดึง instance จาก container โดยตรง
    }

    /**
     * Handle static method calls
     */
    public static function __callStatic($method, $arguments)
    {
        $instance = self::getInstance();
        if (method_exists($instance, $method)) {
            return $instance->$method(...$arguments);
        }
        throw new \BadMethodCallException("Method {$method} does not exist in RouteService.");
    }

    /**
     * Initialize request values from server superglobal
     */
    protected function initializeRequest(): void
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

    }

    /**
     * Add a route to the routing table
     */
    public function addRoute(string $method, string $uri, $controller, ?string $action = null, array $middlewares = [], array $wheres = []): void
    {
        $uri = ($this->currentGroup['prefix'] ?? '') . $uri; // เพิ่ม prefix
        $middlewares = array_merge($this->currentGroup['middleware'] ?? [], $middlewares);

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'controller' => $controller,
            'action' => $action,
            'middlewares' => $middlewares,
            'wheres' => $wheres,
        ];
    }

    /**
     * HTTP GET route
     */
    public static function get(string $uri, $action): void
    {
        self::getInstance()->addRoute('GET', $uri, $action);
    }

    /**
     * HTTP POST route
     */
    public static function post(string $uri, $action): void
    {
        self::getInstance()->addRoute('POST', $uri, $action);
    }

    /**
     * HTTP PUT route
     */
    public static function put(string $uri, $action): void
    {
        self::getInstance()->addRoute('PUT', $uri, $action);
    }

    /**
     * HTTP DELETE route
     */
    public static function delete(string $uri, $action): void
    {
        self::getInstance()->addRoute('DELETE', $uri, $action);
    }

    /**
     * Define a group of routes with shared attributes
     */
    public static function group(array $attributes, callable $callback): void
    {
        $instance = self::getInstance();
        $previousGroup = $instance->currentGroup ?? [];

        // รวม prefix และ middleware
        $instance->currentGroup = [
            'prefix' => ($previousGroup['prefix'] ?? '') . ($attributes['prefix'] ?? ''),
            'middleware' => array_merge($previousGroup['middleware'] ?? [], $attributes['middleware'] ?? []),
        ];

        // เรียก callback เพื่อกำหนด route ภายใต้ group นี้
        $callback();

        // คืนค่า group เดิมหลังจาก callback ทำงานเสร็จ
        $instance->currentGroup = $previousGroup;
    }

    /**
     * Handle the current request
     */
    public function handleRequest(): void
    {
        foreach ($this->routes as $route) {
            if ($this->matchesRoute($route)) {
                $matches = $this->getRouteMatches($route['uri'], $route['wheres'] ?? []);
                $this->executeRoute($route, $matches);

                // ตรวจสอบว่าอยู่ในโหมดทดสอบหรือไม่
                if (app()->isTesting()) {
                    return; // ให้ response ถูกจัดการใน `prepareTestResponse()`
                }

                return;
            }
        }

        // หากไม่พบ route ที่ตรงกัน
        $this->response->json(['msg' => 'Not Found'], 404);
    }

    /**
     * Check if the current request matches a route
     */
    protected function matchesRoute(array $route): bool
    {
        $pattern = $this->convertUriToPattern($route['uri'], $route['wheres'] ?? []);
        return $this->requestMethod === $route['method'] && preg_match($pattern, $this->requestUri);
    }

    /**
     * Convert route URI to regex pattern
     */
    protected function convertUriToPattern(string $uri, array $wheres = []): string
    {
        return "#^" . preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($wheres) {
            $param = $matches[1];
            $pattern = $wheres[$param] ?? '[^/]+';
            return "(?P<{$param}>{$pattern})";
        }, $uri) . "$#";
    }

    /**
     * Get route parameter matches
     */
    protected function getRouteMatches(string $uri, array $wheres = []): array
    {

        $pattern = $this->convertUriToPattern($uri, $wheres);
        preg_match($pattern, $this->requestUri, $matches);
        array_shift($matches);
        return $matches;
    }

    /**
     * Execute a route with its middlewares
     */
    protected function executeRoute(array $route, array $matches): void
    {
        $this->executeMiddlewares($route['middlewares'] ?? []);

        if (is_callable($route['controller'])) {
            // กรณี callback
            call_user_func_array($route['controller'], $matches);
        } elseif (is_string($route['controller']) && strpos($route['controller'], '@') !== false) {
            // กรณี 'Controller@Action'
            [$controllerClass, $action] = explode('@', $route['controller']);
            $this->executeControllerAction($controllerClass, $action, $matches);
        } elseif (is_array($route['controller']) && count($route['controller']) === 2) {
            // กรณี [Controller::class, 'action']
            [$controllerClass, $action] = $route['controller'];
            $this->executeControllerAction($controllerClass, $action, $matches);
        } else {
            throw new \RuntimeException("Invalid route configuration: " . print_r($route, true));
        }
    }

    /**
     * Execute route middlewares
     */
    protected function executeMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (class_exists($middleware)) {
                $middlewareInstance = new $middleware();
                $middlewareInstance->handle($_SERVER);
            }
        }
    }

    /**
     * Set a name for the last added route
     */
    public function name(string $name): self
    {
        $lastRouteKey = array_key_last($this->routes);
        if ($lastRouteKey !== null) {
            $this->namedRoutes[$name] = $this->routes[$lastRouteKey];
        }
        return $this;
    }

    /**
     * Get a route by its name
     */
    public function getRouteByName(string $name): ?array
    {
        return $this->namedRoutes[$name] ?? null;
    }

    protected function resolveMethodParameters(
        string $controllerClass,
        string $method,
        array $matches,
        ?BaseRequest $request = null
    ): array {
        $reflection = new \ReflectionMethod($controllerClass, $method);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $this->resolveParameter($parameter, $matches, $request);
        }

        return $parameters;
    }

    protected function createRequestObject(string $controllerClass, string $action): ?BaseRequest
    {
        $requestClass = $this->resolveRequestClass($controllerClass, $action);

        if ($requestClass) {
            return new $requestClass();
        }

        return null;
    }

    protected function executeControllerAction(string $controllerClass, string $action, array $matches): void
    {
        // Resolve controller class ผ่าน container
        $controller = app()->resolveClass($controllerClass);

        // สร้าง request object (ถ้าจำเป็น)
        $request = $this->createRequestObject($controllerClass, $action);

        // Resolve พารามิเตอร์ทั้งหมด
        $parameters = $this->resolveMethodParameters($controllerClass, $action, $matches, $request);

        // เรียกใช้งาน method พร้อมพารามิเตอร์
        $controller->$action(...$parameters);
    }

    protected function resolveRequestClass(string $controllerClass, string $action): ?string
    {
        $method = new \ReflectionMethod($controllerClass, $action);

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                if (class_exists($className) && is_subclass_of($className, BaseRequest::class)) {
                    return $className;
                }
            }
        }

        return null;
    }

    protected function resolveParameter(
        \ReflectionParameter $parameter,
        array &$matches,
        ?BaseRequest $request = null
    ) {
        $type = $parameter->getType();

        if (!$type) {
            return array_shift($matches);
        }

        if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), BaseRequest::class)) {
            return $request ?? app($type->getName());
        }

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return app($type->getName());
        }

        if (!empty($matches)) {
            $value = array_shift($matches);
            if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                settype($value, $type->getName());
            }
            return $value;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException("Unable to resolve parameter {$parameter->getName()}");
    }

    public function prepareTestRequest(string $method, string $uri, array $data = [], array &$server = []): void
    {
        $this->requestMethod = $method;
        $this->requestUri = $uri;

        // ตั้งค่า $_SERVER
        $server = array_merge($_SERVER, $server);
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri;

        // จัดการข้อมูล request
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (isset($server['CONTENT_TYPE']) && $server['CONTENT_TYPE'] === 'application/json') {
                file_put_contents('php://input', json_encode($data));
            } else {
                $_POST = $data;
            }
        } elseif ($method === 'GET') {
            $_GET = $data;
        }
    }
}
