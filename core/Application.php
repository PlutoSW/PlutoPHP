<?php

namespace Pluto;

class Application
{
    protected Router $router;
    protected Request $request;
    protected Response $response;
    protected Lang $lang;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);

        $this->lang = new Lang($_ENV['APP_LANG'] ?? 'en', $_ENV['APP_FALLBACK_LANG'] ?? 'en');
        Lang::setInstance($this->lang);
        $GLOBALS['lang'] = $this->lang->getLocale();
    }

    public function run()
    {
        try {
            $this->systemRoute();
            $this->registerRoutesFromControllers(BASE_PATH . '/app/Controllers');

            $content = $this->router->dispatch();
            echo $content;
        } catch (\Throwable $e) {
            $content = $this->handleException($e);
            echo $content;
        }
    }

    private function registerRoutesFromControllers(string $dir)
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            if (substr($file->getFilename(), -4) === '.php') {
                $className = str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    str_replace(BASE_PATH . '/', '', $file->getPathname())
                );

                $className = str_replace('app\\', 'App\\', $className);
                if (class_exists($className)) {
                    $this->router->registerRoutesFromController($className);
                }
            }
        }
    }

    private function systemRoute()
    {
        $routes =
            [
                [
                    'get',
                    '/core/style/{$style}',
                    ['\Pluto\Template\PlutoUI', 'core_styles'],
                ],
                [
                    'get',
                    '/core/styles',
                    ['\Pluto\Template\PlutoUI', 'core_styles'],
                ],
                [
                    'get',
                    '/core/scripts',
                    ['\Pluto\Template\PlutoUI', 'core_scripts'],
                ],
                [
                    'get',
                    '/style/{$style}',
                    ['\Pluto\Template\PlutoUI', 'styles'],
                ],
                [
                    'get',
                    '/script/{$script}',
                    ['\Pluto\Template\PlutoUI', 'scripts'],
                ]
            ];
        foreach ($routes as $route) {
            $this->router->addRoute($route[0], $route[1], $route[2]);
        }
    }

    private function logException(\Throwable $e, string $errorId): void
    {
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/app.log';
        $errorMessage = sprintf(
            "[%s] [%s] %s in %s on line %d\n%s\n",
            date('Y-m-d H:i:s'),
            $errorId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($errorMessage, 3, $logFile);
    }

    private function handleException(\Throwable $e): Response
    {
        $this->response->setStatusCode(500);

        $isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $errorRandomID = uniqid('error_');
        $this->logException($e, $errorRandomID);

        if ($isDebug) {
            $log = ['file' => BASE_PATH . '/storage/logs/app.log'];
            ob_start();
            require_once BASE_PATH . '/core/Template/errors/500-debug.php';
            $content = ob_get_clean();
            $this->response->setContent($content);
        } else {
            $title = __('errors.500_title');
            $message = __('errors.500_message');
            $this->response->view('errors.500', ['title' => $title, 'message' => $message]);
        }

        return $this->response;
    }
}
