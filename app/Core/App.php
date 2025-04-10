<?php

namespace App\Core;

use ReflectionMethod;
use App\Core\Middleware;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use App\Core\BaseRequest;
use ReflectionIntersectionType;
use App\Exceptions\ValidationException;
use RuntimeException;
use ReflectionClass;

class App
{
    protected array $routes = [];
    protected string $requestMethod;
    protected string $requestUri;
    protected Middleware $globalMiddleware;
    protected Response $response;
    protected array $container = [];
    protected array $singletons = [];
    protected bool $isTestMode = false;

    public function __construct()
    {
        $this->initializeRequest();
        $this->globalMiddleware = new Middleware();
        $this->response = response();
    }

    /**
     * Register a class or binding in the container
     *
     * @param string $abstract The abstract identifier
     * @param mixed $concrete The concrete implementation
     * @param bool $singleton Whether to treat as singleton
     */
    public function register(string $abstract, $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if ($singleton) {
            $this->singletons[$abstract] = $concrete;
        } else {
            $this->container[$abstract] = $concrete;
        }
    }

    /**
     * Register a singleton binding
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->register($abstract, $concrete, true);
    }

    /**
     * Bind a dependency (alias for register)
     */
    public function bind(string $abstract, $concrete): void
    {
        $this->register($abstract, $concrete);
    }

    /**
     * Initialize request values from server superglobal
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

    /**
     * Add a route to the routing table
     */
    public function addRoute(
        string $method,
        string $uri,
        $controller,
        ?string $action = null,
        array $middlewares = []
    ): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'controller' => $controller,
            'action' => $action,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Add a group of routes with common prefix and middlewares
     */
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

    /**
     * Run the application
     */
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

    /**
     * Handle a test request
     */
    public function test(
        string $method,
        string $uri,
        array $data = [],
        array $server = []
    ): array {
        $this->isTestMode = true;
        $this->prepareTestRequest($method, $uri, $data, $server);

        try {
            $this->globalMiddleware->handle($server);

            foreach ($this->routes as $route) {
                if ($this->matchesRoute($route)) {
                    $matches = $this->getRouteMatches($route['uri']);
                    $response = $this->executeRoute($route, $matches, $data);
                    return $this->response->prepareTestResponse();
                }
            }

            return $this->response->json(['msg' => 'Not Found'], 404)->prepareTestResponse();
        } finally {
            $this->isTestMode = false;
        }
    }

    /**
     * Prepare the request for testing
     */
    protected function prepareTestRequest(
        string $method,
        string $uri,
        array $data,
        array &$server
    ): void {
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
     * Check if the current request matches a route
     */
    protected function matchesRoute(array $route): bool
    {
        $pattern = $this->convertUriToPattern($route['uri']);
        return $this->requestMethod === $route['method'] && preg_match($pattern, $this->requestUri);
    }

    /**
     * Convert route URI to regex pattern
     */
    protected function convertUriToPattern(string $uri): string
    {
        $pattern = preg_replace('/\{\w+\}/', '([a-zA-Z0-9_-]+)', $uri);
        return "#^" . $pattern . "$#";
    }

    /**
     * Get route parameter matches
     */
    protected function getRouteMatches(string $uri): array
    {
        $pattern = $this->convertUriToPattern($uri);
        preg_match($pattern, $this->requestUri, $matches);
        array_shift($matches);
        return $matches;
    }

    /**
     * Execute a route with its middlewares
     */
    protected function executeRoute(
        array $route,
        array $matches,
        array $requestData = []
    ) {
        $this->executeMiddlewares($route['middlewares'] ?? []);

        try {
            if (is_callable($route['controller'])) {
                return call_user_func_array(
                    $route['controller'],
                    array_merge($matches, [$requestData])
                );
            }

            return $this->executeControllerAction(
                $route['controller'],
                $route['action'],
                $matches,
                $requestData
            );
        } catch (ValidationException $e) {
            return $this->response->validationErrors($e->getErrors());
        }
    }

    /**
     * Execute route middlewares
     */
    protected function executeMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->resolveClass($middleware)->handle($_SERVER);
        }
    }

    /**
     * Execute a controller action
     */
    protected function executeControllerAction(
        string $controllerClass,
        string $action,
        array $matches,
        array $requestData
    ) {
        $controller = $this->resolveClass($controllerClass);
        $request = $this->createRequestObject($controllerClass, $action, $requestData);
        $parameters = $this->resolveMethodParameters($controllerClass, $action, $matches, $request);

        return $controller->$action(...$parameters);
    }

    /**
     * Create a request object for the controller action
     */
    protected function createRequestObject(
        string $controllerClass,
        string $action,
        array $requestData
    ): BaseRequest {
        $requestClass = $this->resolveRequestClass($controllerClass, $action);
        return new $requestClass($requestData);
    }

    /**
     * Resolve the request class for a controller action
     */
    protected function resolveRequestClass(
        string $controllerClass,
        string $action
    ): string {
        $method = new ReflectionMethod($controllerClass, $action);

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

    /**
     * Resolve method parameters for dependency injection
     */
    protected function resolveMethodParameters(
        string $class,
        string $method,
        array $matches,
        ?BaseRequest $request = null
    ): array {
        $reflection = new ReflectionMethod($class, $method);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $this->resolveParameter($parameter, $matches, $request);
        }

        return $parameters;
    }

    /**
     * Resolve a single parameter
     */
    protected function resolveParameter(
        ReflectionParameter $parameter,
        array &$matches,
        ?BaseRequest $request = null
    ) {
        $type = $parameter->getType();

        if (!$type) {
            return $this->resolveUntypedParameter($parameter, $matches);
        }

        if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), BaseRequest::class)) {
            return $request ?? $this->resolveClass($type->getName());
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->resolveClass($type->getName());
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            return $this->resolveUnionOrIntersectionType($type, $parameter, $matches);
        }

        if (!empty($matches)) {
            $value = array_shift($matches);
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                settype($value, $type->getName());
            }
            return $value;
        }

        return $this->resolveDefaultValue($parameter);
    }

    /**
     * Resolve union or intersection type parameter
     */
    protected function resolveUnionOrIntersectionType(
        $type,
        ReflectionParameter $parameter,
        array &$matches
    ) {
        foreach ($type->getTypes() as $subType) {
            if ($subType instanceof ReflectionNamedType && !$subType->isBuiltin()) {
                try {
                    return $this->resolveClass($subType->getName());
                } catch (RuntimeException $e) {
                    continue;
                }
            }
        }

        return $this->resolveDefaultValue($parameter);
    }

    /**
     * Resolve untyped parameter
     */
    protected function resolveUntypedParameter(
        ReflectionParameter $parameter,
        array &$matches
    ) {
        if (!empty($matches)) {
            return array_shift($matches);
        }
        return $this->resolveDefaultValue($parameter);
    }

    /**
     * Get parameter default value or null
     */
    protected function resolveDefaultValue(ReflectionParameter $parameter)
    {
        return $parameter->isDefaultValueAvailable()
            ? $parameter->getDefaultValue()
            : null;
    }

    /**
     * Resolve a class from the container or through autowiring
     */
    protected function resolveClass(string $class, array $constructorArgs = [])
    {
        if (isset($this->singletons[$class])) {
            if (is_object($this->singletons[$class])) {
                return $this->singletons[$class];
            }
            $this->singletons[$class] = $this->buildClass($this->singletons[$class], $constructorArgs);
            return $this->singletons[$class];
        }

        if (isset($this->container[$class])) {
            $concrete = $this->container[$class];
            return is_callable($concrete) ? $concrete() : $this->buildClass($concrete, $constructorArgs);
        }

        return $this->buildClass($class, $constructorArgs);
    }

    /**
     * Build a class instance with dependency injection
     */
    protected function buildClass(string $class, array $constructorArgs = [])
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return empty($constructorArgs) ? new $class() : new $class(...$constructorArgs);
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $param) {
            $parameters[] = $this->resolveParameter($param, $constructorArgs, null);
        }

        return $reflection->newInstanceArgs($parameters);
    }

    /**
     * Check if running in test mode
     */
    public function isTesting(): bool
    {
        return $this->isTestMode || getenv('APP_ENV') === 'testing';
    }
}
