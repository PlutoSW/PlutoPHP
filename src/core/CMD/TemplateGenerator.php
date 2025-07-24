<?php

namespace Pluto\Core\CMD;


class TemplateGenerator
{
    private $moduleName = null;
    private $template = null;

    private $templateFile = null;
    private $frontendDir = __DIR__ . '/../../../frontend';
    private $templateDir = null;
    private $moduleDir = null;
    public function __construct($moduleName)
    {
        $this->templateDir = $this->frontendDir . '/templates';
        $this->moduleName = strtolower($moduleName);

        $this->touchFile();
    }

    public function getTemplate()
    {
        return $this->template;
    }


    private function controllerTemplate()
    {
        $this->template = '{% extends \'../layout.html\' %}
{% block title %}' . \ucwords($this->moduleName) . ' Page{% endblock %}

{% block content %}

        <div>
            Example ' . \ucwords($this->moduleName) . ' Page
        </div>
{% endblock %}
';
        return $this->template;
    }

    private function touchFile()
    {

        if (!file_exists($this->templateDir)) {
            mkdir($this->templateDir, recursive: true);
            \file_put_contents($this->templateDir . '/layout.html', '<!DOCTYPE html>
<html lang="{{self::$language->getLanguage()}}">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>{% @block title %}</title>
		{% each $styles as $style %}<link rel="stylesheet" href="{{ $style }}"/>
		{% endeach %}

	</head>
	<body>
		
		{% @block content %}
		
		{% each $scripts as $script %}<script src="{{ $script }}"></script>
		{% endeach %}

	</body>
</html>
');
           
            \mkdir($this->frontendDir . '/assets/css/', recursive: true);
            \mkdir($this->frontendDir . '/assets/js/', recursive: true);
            \mkdir($this->templateDir . '/errors');
            \touch($this->frontendDir . '/assets/js/script.js');
            \touch($this->frontendDir . '/assets/css/style.css');

            \file_put_contents($this->templateDir . '/errors/404.html', '<h2>404 - Not Found</h2>');
            \file_put_contents($this->templateDir . '/errors/500.html', '<h2>500 - Internal Server Error</h2>');
            \file_put_contents($this->templateDir . '/errors/403.html', '<h2>401 - Unauthorized</h2>');
        
        }

        $this->moduleDir = __DIR__ . '/../../../frontend/templates/' . strtolower($this->moduleName);

        if (!file_exists($this->moduleDir)) {
            mkdir($this->moduleDir);
        }

        $this->templateFile = $this->moduleDir  . '/index.html';


        file_put_contents($this->templateFile, $this->controllerTemplate());
    }
}
