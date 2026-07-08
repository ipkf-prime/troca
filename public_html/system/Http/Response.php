<?php

namespace IPKF\Http;

class Response
{
    protected string $content = '';

    protected int $statusCode = 200;

    protected array $headers = [];

    public function send(string $content = ''): self
    {
        $this->content = $content;

        return $this;
    }

    public function json(array $data): self
    {
        $this->header('Content-Type', 'application/json; charset=UTF-8');
        $this->content = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';

        return $this;
    }

    public function redirect(string $url): self
    {
        $this->status(302);
        $this->header('Location', $url);

        return $this;
    }

    public function status(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function emit(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->content;
    }
}
