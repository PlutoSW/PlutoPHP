<?php

namespace Pluto\Core;

class Response
{
    private string|array $response = [];
    private int $code = 200;
    public $context;
    public function __construct($context=null) {
        $this->context = $context;
    }
    public function success($message, $code = 200): Response
    {
        $this->response["status"] = true;
        $this->response["message"] = $message;
        $this->response["code"] = $code;
        $this->code = $code;

        return $this;
    }
    public function error($message, $code = 500): Response
    {
        $this->response = [];
        $this->response["status"] = false;
        $this->response["message"] =  $message;
        $this->response["code"] = $code;
        $this->code = $code;

        return $this;
    }
    public function template($context, $page, $code = 200): Response
    {
        $this->code = $code;
        System::$global->controller = $this->context;
        $this->response = Template\Template::view($page, $context);
        return $this;
    }
    public function json(): void
    {
        http_response_code($this->code);
        header("Content-Type: application/json; charset=utf-8");
        exit(json_encode($this->response));
    }
    public function send(): void
    {
        http_response_code($this->code);
        header("Content-Type: text/html; utf-8");
        if(!\is_string($this->response)){
            $this->json();
            return;
        }
        exit($this->response);
    }

    public function redirect($url)
    {
        (new Router())->go($url);
        exit;
    }

    public function __get($name)
    {
        return $this->response[$name];
    }
}
