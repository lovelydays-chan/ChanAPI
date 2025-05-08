<?php

namespace App\Core;

use ReflectionClass;
use RuntimeException;

class App
{
    protected Middleware $globalMiddleware;
    protected Response $response;
    protected array $container = [];
    protected array $singletons = [];
    protected bool $isTestMode = false;
    protected static ?self $instance = null;
    protected array $aliases = [];
    protected RouteService $routeService;

    public function __construct()
    {
        $this->globalMiddleware = new Middleware();
        $this->response = response();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a class or binding in the container
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
        $this->singletons[$abstract] = $concrete;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Bind a dependency (alias for register)
     */
    public function bind(string $abstract, $concrete): void
    {
        $this->register($abstract, $concrete);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function addGlobalMiddleware($middleware): void
    {
        $this->globalMiddleware->register($middleware);
    }

    public function getGlobalMiddleware(): Middleware
    {
        return $this->globalMiddleware;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        $this->globalMiddleware->handle($_SERVER);

        $this->routeService = $this->make('route');

        $this->routeService->handleRequest();
    }

    /**
     * Handle a test request
     */
    public function test(string $method, string $uri, array $data = [], array $server = []): array
    {
        $this->isTestMode = true;

        try {
            // ใช้ RouteService เพื่อเตรียม request
            $routeService = $this->make('route');
            $routeService->prepareTestRequest($method, $uri, $data, $server);

            // เรียกใช้ middleware ระดับ global
            $this->globalMiddleware->handle($server);

            // ใช้ RouteService เพื่อจัดการ request
            $routeService->handleRequest();

            // ดึง response ที่เตรียมไว้สำหรับการทดสอบ
            return $this->response->prepareTestResponse();
        } finally {
            $this->isTestMode = false;
        }
    }

    public function make(string $abstract)
    {
        if (isset($this->singletons[$abstract])) {
            $concrete = $this->singletons[$abstract];
            if ($concrete instanceof \Closure) {
                $this->singletons[$abstract] = $concrete(); // สร้าง instance และเก็บไว้
            }
            return $this->singletons[$abstract];
        }

        throw new \RuntimeException("Service {$abstract} is not registered in the container.");
    }

    public function resolveClass(string $class, array $constructorArgs = [])
    {
        if (isset($this->aliases[$class])) {
            $class = $this->aliases[$class];
        }

        if (isset($this->container[$class]) && $this->container[$class] instanceof \Closure) {
            return $this->container[$class]();
        }

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

    protected function buildClass(string $class, array $constructorArgs = [])
    {
        if (isset($this->aliases[$class])) {
            $class = $this->aliases[$class];
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Class {$class} does not exist");
        }

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
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $parameters[] = $this->resolveClass($type->getName());
            } else {
                throw new RuntimeException("Unable to resolve parameter {$param->getName()}");
            }
        }

        return $reflection->newInstanceArgs($parameters);
    }

    public function get($abstract)
    {
        return $this->resolveClass($abstract);
    }

    public function isTesting(): bool
    {
        return $this->isTestMode || getenv('APP_ENV') === 'testing';
    }
}
