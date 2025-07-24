<?php

namespace Pluto\Core\CMD;

use Pluto\Core\Model;

class ModelGenerator
{

    private $modelMethods = [];
    private $model = null;
    private $moduleName = null;

    private $backendDir = __DIR__ . '/../../../backend';
    private $modelDir = null;
    private $modelFile = null;
    private $options = null;


    public function __construct($moduleName, array $options)
    {
        $this->moduleName = $moduleName;
        $this->modelDir = $this->backendDir . '/model';
        $this->options = $options;

        $this->getModelMethods();
        $this->touchFile();
    }

    public function getModel()
    {
        return $this->model;
    }


    private function getModelMethods()
    {
        $reflection = new \ReflectionClass(Model::class);
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
                    $parameterType == "bool" => $parameter->getDefaultValue() ? 'true' : 'false',
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
            $this->modelMethods[] = (object)[
                "name" => $name,
                "parameters" => rtrim($parameters, ', '),
                "returnType" => $returnType,
                "access" => $access,
                "isFinal" => $isFinal,
                "isStatic" => $isStatic
            ];
        }
    }

    private function modelTemplate()
    {
        $template = '<?php

namespace Pluto\Model;

use Pluto\Core\System;

class ' . $this->moduleName . ' extends \Pluto\Core\Model
{
    static $_tablename = "' . $this->options["tablename"] . '";
}
?>';
        return $template;
    }

    private function createMethods()
    {
        $this->model = $this->modelTemplate();
        return $this->model;
    }

    private function touchFile()
    {
        if (!file_exists($this->backendDir)) {
            mkdir($this->backendDir);
        }

        if (!file_exists($this->modelDir)) {
            mkdir($this->modelDir);
        }

        $this->modelFile = $this->modelDir  . '/' . $this->moduleName . '.php';

        file_put_contents($this->modelFile, $this->createMethods());
    }
}
