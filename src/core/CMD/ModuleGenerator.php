<?php

namespace Pluto\Core\CMD;

class ModuleGenerator
{

    public function __construct(string $name, array $options)
    {
        $name = ucwords($name);
        $modulePath = __DIR__ . '/../../../backend/controller/' . $name . '.php';
        if (file_exists($modulePath)) {
            echo "\033[33mThere is a module with this name. Type 'y' if you want to reset the module: ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) != 'y') {
                echo "ABORTING!\033[0m\n";
                exit;
            } else {
                echo "Module reset!\033[0m\n";
            }
        }
        new ControllerGenerator($name, $options);
        new ModelGenerator($name, $options);
        if ($options["type"] == "template") {
            new TemplateGenerator($name);
        }
    }
}
