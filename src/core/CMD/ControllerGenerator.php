<?php

namespace Pluto\Core\CMD;

use Pluto\Core\Controller;

class ControllerGenerator
{

    private $controllerMethods = [];
    private $controller = null;
    private $moduleName = null;

    private $backendDir = __DIR__ . '/../../../backend';
    private $controllerDir = null;
    private $controllerFile = null;
    private $response = null;
    private $endpoint = null;

    public function __construct(string $moduleName, array $options)
    {
        $this->moduleName = $moduleName;
        $this->response = $options["type"];
        $this->endpoint = $options["endpoint"];
        $this->controllerDir = $this->backendDir . '/controller';
        $this->getControllerMethods();

        $this->touchFile();
    }

    public function getController()
    {
        return $this->controller;
    }

    private function getControllerMethods()
    {
        $reflection = new \ReflectionClass(Controller::class);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_ABSTRACT) as $reflectionMethod) {
            $name = $reflectionMethod->getName();
            $_parameters = $reflectionMethod->getParameters();
            $parameters = '';
            foreach ($_parameters as $parameter) {
                $parameterType = $parameter->getType();
                assert($parameterType instanceof \ReflectionNamedType);
                $parameterType = $parameterType->getName();
                $defaultValue = match (true) {
                    $parameterType == "array" => '[]',
                    $parameterType == "object" => '{}',
                    default => $parameter->getDefaultValue()
                };
                $parameters .= "{$parameterType} " . '$' . "{$parameter->getName()} = {$defaultValue}, ";
            }
            $returnType = $reflectionMethod->getReturnType();
            $access = match (true) {
                $reflectionMethod->isPrivate() => 'private',
                $reflectionMethod->isProtected() => 'protected',
                $reflectionMethod->isPublic() => 'public',
                default => 'public'
            };

            $isFinal = $reflectionMethod->isFinal() ? 'final ' : '';
            $isStatic = $reflectionMethod->isStatic() ? 'static ' : '';
            $this->controllerMethods[] = (object)[
                "name" => $name,
                "parameters" => $parameters,
                "returnType" => $returnType,
                "access" => $access,
                "isFinal" => $isFinal,
                "isStatic" => $isStatic
            ];
        }
    }

    private function controllerTemplate()
    {
        $template = '<?php

namespace Pluto\Controller;

use Pluto\Core\Route; 
use Pluto\Core\Controller; 
use Pluto\Core\Error;
use Pluto\Model\\' . $this->moduleName . ' as Model' . $this->moduleName . ';

class ' . $this->moduleName . ' extends Controller
{
    private $model = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = Model'.$this->moduleName.'::class;
    }
    {{methods}}
}
?>';
        return $template;
    }

    private function createMethods()
    {
        $methods = [];
        foreach ($this->controllerMethods as $method) {
            $responseText = '';
            $errorText = '';
            if ($this->response === 'template') {
                $responseText = 'return $this->response->template($data, "' . \strtolower($this->moduleName) . '/index");';
                $errorText = 'return $this->response->template([],\'errors/404\', 404);';
            } else {
                $responseText = 'return $this->response->success($data);';
                $errorText = 'return $this->response->error("'.$this->moduleName.' not found", 404);';
            }
            $methods[] = '
    #[Route(method:"GET", endpoint:"/' . $this->endpoint . '", response:"' . $this->response . '")]
    ' . $method->isFinal . '' . $method->isStatic . '' . $method->access . ' function ' . $method->name . '(' . $method->parameters . '): \\' . $method->returnType . '
    {
        try {
            $data = [];
            ' . $responseText . '
        } catch (\Throwable $th) {
            new Error($th);
            '.$errorText.'
        }
    }';
        }
        $controller = $this->controllerTemplate();
        $controller = str_replace('{{methods}}', implode('\n\n', $methods), $controller);
        return $controller;
    }

    private function touchFile()
    {
        if (!file_exists($this->controllerDir)) {
            mkdir($this->controllerDir, recursive: true);
        }
        $this->controllerFile = $this->controllerDir  . '/' . $this->moduleName . '.php';

        file_put_contents($this->controllerFile, $this->createMethods());
    }
}
