<?php

namespace Pluto;

class Response
{
    protected string $content = '';
    protected string $cacheTime;
    protected string $ts = '';
    protected bool $cachable = false;
    public function __construct()
    {
        $this->cacheTime = \getenv('ASSET_CACHE_TIME');
        if ($this->cacheTime) {
            $this->ts = gmdate("D, d M Y H:i:s", time() + (int)$this->cacheTime) . " GMT";
            $this->cachable = true;
        }
    }

    /**
     * Sets the raw content of the response.
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content = ""): self
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
        if ($this->cachable) {
            header("Expires: $this->ts");
            header("Pragma: cache");
            header("Cache-Control: max-age=$this->cacheTime");
        }
        $this->content = $script;
        return $this;
    }

    public function css(string $css): self
    {
        $this->setStatusCode(200);
        header('Content-Type: text/css');
        if ($this->cachable) {
            header("Expires: $this->ts");
            header("Pragma: cache");
            header("Cache-Control: max-age=$this->cacheTime");
        }
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
    public function view(string $view, array $data = [], int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->content = view($view, $data);
        return $this;
    }

    public function redirect(string $url): self
    {
        $this->setStatusCode(302);
        header('Location: ' . $url);
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
