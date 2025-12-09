<?php

namespace Pluto;

class Response
{
    protected string $content = '';

    /**
     * Sets the raw content of the response.
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content=""): self
    {
        $this->content = $content;
        return $this;
    }
    public function setStatusCode(int $code)
    {
        http_response_code($code);
    }

    /**
     * Return JSON response.
     *
     * @param mixed $data
     * @param int $statusCode
     * @return string
     * @return self
     */
    public function json(mixed $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        header('Content-Type: application/json');
        $this->content = json_encode($data, JSON_PRETTY_PRINT);
        return $this;
    }

    public function script(string $script): self
    {
        $this->setStatusCode(200);
        header('Content-Type: application/javascript');
        $this->content = $script;
        return $this;
    }

    public function css(string $css): self
    {
        $this->setStatusCode(200);
        header('Content-Type: text/css');
        $this->content = $css;
        return $this;
    }

    /**
     * Returns the View (HTML) response.
     *
     * @param string $view
     * @param array $data
     * @return string
     * @return self
     */
    public function view(string $view, array $data = []): self
    {
        $this->content = view($view, $data);
        return $this;
    }

    /**
     * When the response object is echoed as a string, it returns its content.
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
