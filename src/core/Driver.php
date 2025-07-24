<?php

namespace Pluto\Core;

use Pluto\Core\Error;

enum DriverRequestType: string
{
    case POST = 'POST';
    case GET = 'GET';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
}

abstract class Driver
{
    private $url;
    private $headers;
    private $data;
    private $method;
    private $response;
    private $response_code;
    private $response_headers;
    private $connection;


    public function __construct()
    {
    }

    /**
     * Get the value of url
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the value of url
     *
     * @return  self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }


    /**
     * Get the value of headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the value of headers
     *
     * @return  self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }
    public function addHeader(string $header): self
    {
        $this->headers[] = $header;

        return $this;
    }
    public function removeHeader(string $header): self
    {
        $this->headers = array_filter($this->headers, function ($value) use ($header) {
            return $value !== $header;
        });

        return $this;
    }
    /**
     * Get the value of data
     */
    public function getData(): array|string
    {
        return $this->data;
    }

    /**
     * Set the value of data
     *
     * @return  self
     */
    public function setData(array|string $data): self
    {
        if (is_array($data)) {
            $this->data = \json_encode($data);
            if (System::json_validate($this->data) === false) {
                throw new Error("Invalid JSON data", 500);
            }
        } else {
            $this->data = $data;
        }

        return $this;
    }

    /**
     * Get the value of method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Set the value of method
     *
     * @return  self
     */
    public function setMethod(DriverRequestType $method): self
    {
        $this->method = $method->value;

        return $this;
    }


    protected function connect(): self
    {
        $this->connection = curl_init();
        curl_setopt($this->connection, CURLOPT_URL, $this->url);
        curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, true);
        if (\is_array($this->headers)) {
            curl_setopt($this->connection, CURLOPT_HTTPHEADER, $this->headers);
        }
        curl_setopt($this->connection, CURLOPT_CUSTOMREQUEST, $this->method);
        if ($this->data) {
            curl_setopt($this->connection, CURLOPT_POSTFIELDS, $this->data);
        }
        return $this;
    }

    protected function exec(): self
    {
        $this->connect();
        $this->response = curl_exec($this->connection);
        $this->response_code = curl_getinfo($this->connection, CURLINFO_HTTP_CODE);
        $this->response_headers = curl_getinfo($this->connection);
        curl_close($this->connection);
        return $this;
    }

    /**
     * Get the value of response
     */
    public function getResponse(): string|object
    {
        if (\strpos($this->response_headers["content_type"], "application/json") !== false) {
            return \json_decode($this->response);
        }
        return $this->response;
    }

    /**
     * Get the value of response_code
     */
    public function getResponseCode(): int
    {
        return $this->response_code;
    }

    /**
     * Get the value of response_headers
     */
    public function getResponseHeaders(): array
    {
        return $this->response_headers;
    }

    /**
     * Get the value of connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
