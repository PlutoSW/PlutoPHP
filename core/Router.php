<?php

namespace Pluto;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET'
    ) {}
}

class Router
{
    protected array $routes = [];
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function registerRoutesFromController(string $controllerClass)
    {
        $reflectionClass = new \ReflectionClass($controllerClass);

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $this->addRoute($route->method, $route->path, [$controllerClass, $method->getName()]);
            }
        }
    }

    public function addRoute(string $method, string $path, $callback)
    {
        $this->routes[\strtoupper($method)][$path] = $callback;
    }

    public function dispatch()
    {
        $path = $this->request->getPath();
        $method = $this->request->getMethod();
        $callback = $this->routes[$method][$path] ?? false;
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
                        $callback = $cb;
                        return $this->executeCallback($callback, $params);
                    }
                }
            }
            $this->response->setStatusCode(404);
            return $this->response->view('errors.404', ['view' => $path]);
        }

        return $this->executeCallback($callback);
    }

    private function executeCallback($callback, array $params = [])
    {
        if (is_array($callback)) {
            $controller = new $callback[0]($this->request, $this->response);
            $method = $callback[1];
            $reflectionMethod = new \ReflectionMethod($controller, $method);
            $methodParams = $reflectionMethod->getParameters();

            $args = [];
            foreach ($methodParams as $param) {
                if (isset($params[$param->getName()])) {
                    $args[] = $params[$param->getName()];
                }
            }

            return call_user_func_array([$controller, $method], $args);
        }

        return call_user_func($callback, ...$params);
    }
}
