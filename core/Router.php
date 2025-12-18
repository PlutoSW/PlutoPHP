<?php

namespace Pluto;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $middleware = []
    ) {}
}

class Router
{
    protected array $routes = [];
    protected Request $request;
    protected Response $response;
    protected ?string $globalMiddleware = null;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function setGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddleware = $middlewareClass;
    }


    public function registerRoutesFromController(string $controllerClass)
    {
        $reflectionClass = new \ReflectionClass($controllerClass);

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $this->addRoute($route->method, $route->path, [$controllerClass, $method->getName()], $route->middleware);
            }
        }
    }

    public function addRoute(string $method, string $path, $callback, array $middleware = [])
    {
        $this->routes[\strtoupper($method)][$path]['callback'] = $callback;
        $this->routes[\strtoupper($method)][$path]['middleware'] = $middleware;
    }

    public function dispatch()
    {

        $path = $this->request->getPath();
        $method = $this->request->getMethod();
        $routeInfo = $this->routes[$method][$path] ?? false;
        $callback = $routeInfo['callback'] ?? false;

        if ($callback === false) {

            foreach ($this->routes[$method] ?? [] as $routePath => $cb) {
                if (strpos($routePath, '{') !== false) {

                    $pattern = preg_replace_callback(
                        '/\{(\?)?(?:<(\w+)>)?\$?(\w+)\}/',
                        function ($matches) {
                            $optional = $matches[1] === '?';
                            $type = $matches[2] ?? 'any';
                            $name = $matches[3];

                            $regexPart = match ($type) {
                                'int' => '[0-9]+',
                                'str' => '[a-zA-Z]+',
                                'alpha' => '[a-zA-Z]+',
                                'alphanum' => '[a-zA-Z0-9]+',
                                default => '.+',
                            };

                            return ($optional ? '(?:' : '') . '(?P<' . $name . '>' . $regexPart . ')' . ($optional ? ')?' : '');
                        },
                        $routePath
                    );

                    if (preg_match("#^$pattern$#", $path, $matches)) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        $callback = $cb['callback'];
                        $middleware = $cb['middleware'];
                        return $this->executeCallback($callback, $params, $middleware);
                    }
                }
            }
            $this->response->setStatusCode(404);
            return $this->response->view('errors.404', ['view' => $path]);
        }
        $middleware = $routeInfo['middleware'] ?? [];

        return $this->executeCallback($callback, [], $middleware);
    }


    private function executeCallback($callback, array $params = [], array $middleware = [])
    {
        $finalRequest = function ($request) use ($callback, $params) {
            if (is_array($callback)) {
                $controller = new $callback[0]($this->request, $this->response);
                $method = $callback[1];
                return call_user_func_array([$controller, $method], $params);
            }
            return call_user_func($callback, ...array_values($params));
        };

        $middlewaresToRun = [];
        if ($this->globalMiddleware && class_exists($this->globalMiddleware)) {
            $middlewaresToRun[] = $this->globalMiddleware;
        }

        if (count($middleware)) {
            $middlewaresToRun = array_merge($middlewaresToRun, $middleware);
        }
        $pipeline = array_reduce(
            array_reverse($middlewaresToRun),
            function ($next, $middlewareClass) {
                return function ($request) use ($next, $middlewareClass) {
                    return (new $middlewareClass)->handle($request, $next);
                };
            },
            $finalRequest
        );

        return $pipeline($this->request);
    }
}
