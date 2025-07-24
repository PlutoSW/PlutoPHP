<?php

namespace Pluto\Core;

use Pluto\Core\Error;
use Pluto\Core\Response;

#[\Attribute]
class Route
{
    public function __construct(
        private string $method = 'GET',
        private string $endpoint = '/',
        private string $response = 'send',
        private bool $withToken = false,
        private array $hooks = ['before' => null, 'after' => null],
        private array $callback = [],
        private string $permText = ''
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getResponse(): string
    {
        return $this->response;
    }

    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function getWithToken(): bool
    {
        return $this->withToken;
    }

    public function getCallback(): callable|array
    {
        return $this->callback;
    }

    public function setMethod(string $method): Route
    {
        $this->method = $method;
        return $this;
    }

    public function setEndpoint(string $endpoint): Route
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function setResponse(string $response): Route
    {
        $this->response = ($response == "template") ? "send" : "json";
        return $this;
    }

    public function setHooks(array $hooks): Route
    {
        $this->hooks = $hooks;
        return $this;
    }

    public function setCallback(callable|array $callback): Route
    {
        $this->callback = $callback;
        return $this;
    }
    public function setWithToken(bool $withToken): Route
    {
        $this->withToken = $withToken;
        return $this;
    }
    public function setPermText(string $permText): Route
    {
        $this->permText = $permText;
        return $this;
    }
    public function getPermText(): string
    {
        return $this->permText;
    }
}

class Router
{
    private array $routes = [];
    public $currentPage = null;
    public $withToken = false;
    public $responseType = null;

    public function __construct()
    {
        $controllers = \glob(__DIR__ . '/../../backend/controller/*.php');
        foreach ($controllers as $controller) {
            $explode = explode('backend/controller', $controller);
            $class = 'Pluto\\Controller\\' . str_replace('/', '\\', ltrim($explode[1], '/'));
            $class = explode('.', $class)[0];
            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }
            foreach ($reflection->getMethods() as $reflectionMethod) {
                $routeAttributes = $reflectionMethod->getAttributes(Route::class);

                foreach ($routeAttributes as $routeAttribute) {
                    $argument = $routeAttribute->getArguments();
                    if (!count($argument)) {
                        continue;
                    }
                    $this->addRoute(
                        (new Route())
                            ->setMethod($argument['method'])
                            ->setEndpoint($argument['endpoint'])
                            ->setWithToken(isset($argument['withToken']) ? $argument['withToken'] : false)
                            ->setResponse($argument['response'])
                            ->setHooks((isset($argument['hooks'])) ? $argument['hooks'] : ['before' => null, 'after' => null])
                            ->setPermText((isset($argument['permissionText'])) ? $argument['permissionText'] : "")
                            ->setCallback([$class, $reflectionMethod->name])
                    );
                }
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function getMethods()
    {
        $methods = [];

        foreach ($this->routes as $key => $value) {
            foreach ($value as $key => $value) {
                $expController = \explode("\\", $value["callback"][0]);
                $controller = \end($expController);
                if (!isset($methods[$controller])) {
                    $methods[$controller] = [$value["permissionText"] => []];
                }
                if(!isset($methods[$controller][$value["permissionText"]])){
                    $methods[$controller][$value["permissionText"]] = [];
                }
                $method = $methods[$controller][$value["permissionText"]];
                if (!\in_array($value["callback"][1], $method)) {
                    $methods[$controller][$value["permissionText"]][] = $value["callback"][1];
                }
            }
        }
        return $methods;
    }

    static function reqMode()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    static function path(): string
    {
        $uri = \rtrim(explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0], '/');
        $uri = ($uri == "") ? "/" : $uri;
        return $uri;
    }

    public function addRoute(Route $route)
    {
        $endpoint = $route->getEndpoint();

        $pattern = preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) {
                $placeholder = $matches[1];
                if (preg_match('/^<(\w+)>(.+)$/', $placeholder, $typedMatches)) {
                    $type = $typedMatches[1];
                    $name = $typedMatches[2];

                    $regex = match (strtolower($type)) {
                        'int', 'integer' => '\d+',
                        'alpha' => '[a-zA-Z]+',
                        'alphanum' => '[a-zA-Z0-9]+',
                        default => '[^/]+',
                    };

                    return "(?P<{$name}>{$regex})";
                }
                return "(?P<{$placeholder}>[^/]+)";
            },
            $endpoint
        );
        $pattern = "#^$pattern$#";

        $this->routes[$route->getMethod()][$pattern] = [
            'callback'  => $route->getCallback(),
            'hooks'  => $route->getHooks(),
            'responseType' => $route->getResponse(),
            'withToken' => $route->getWithToken(),
            "permissionText" => $route->getPermText()
        ];
    }
    public function resolve($allHooks = [])
    {
        $viewError = "404 Not Found";
        try {
            $method = self::reqMode();
            $path = self::path();
            $pathExplode = explode('/', $path);
            \array_shift($pathExplode);
            foreach ($this->routes[$method] ?? [] as $pattern => $data) {
                $callback = $data['callback'];
                $responseType = $data['responseType'];
                $withToken = $data['withToken'];
                $hooks = isset($data['hooks']) ? $data['hooks'] : ["before" => null, "after" => null];
                if (preg_match($pattern, $path, $matches)) {
                    $params = [...$pathExplode, ...array_filter(
                        $matches,
                        fn($key) => !is_numeric($key),
                        ARRAY_FILTER_USE_KEY
                    )];

                    [$class, $method] = $callback;
                    $this->currentPage = "/" . $method;
                    $this->withToken = $withToken;

                    if (\is_callable($hooks["before"])) {
                        $hooks["before"]($this);
                    }
                    if (\is_callable($allHooks["before"])) {
                        $allHooks["before"]($this);
                    }
                    $controller = new $class($params);
                    if (!$controller::hasPermission($class, $method)) {
                        if ($responseType == "send") {
                            return (new Response())->template([], "no_auth", 403)->send();
                        } else {
                            return (new Response())->error("Permission denied", 403)->json();
                        }
                    }

                    $response = ($controller->$method())->{$responseType}();
                    if (\is_callable($hooks["after"])) {
                        $hooks["after"]($this);
                    }
                    if (\is_callable($allHooks["after"])) {
                        $allHooks["after"]($this);
                    }
                    return $response;
                }
            }
            if ($_SERVER['HTTP_SEC_FETCH_MODE'] == "navigate") {
                return (new Response())->template([],  "404", 404)->send();
            } else {
                return (new Response())->error("404 Not Found", 404)->json();
            }
        } catch (\Throwable $th) {
            $viewError .= ": " . $th->getMessage();
            throw new Error($viewError, $th->getCode(), $th);
        }
        //throw new Error($viewError, 404);
    }

    public function go($path)
    {
        \header('Location: ' . $path);
        exit();
    }
}
