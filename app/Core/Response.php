<?php

namespace App\Core;

class Response
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected string $content = '';
    protected bool $testMode = false;
    protected ?array $testResponse = null;
    protected $validationErrors = null;

    public function json($data, int $statusCode = 200): self
    {
        $this->statusCode = $statusCode;
        $this->headers['Content-Type'] = 'application/json';
        $this->content = is_string($data) ? $data : json_encode($data);
        return $this;
    }

    public function paginate($data, array $pagination, int $statusCode = 200)
    {
        if (!isset($pagination['total'], $pagination['current_page'], $pagination['per_page'], $pagination['last_page'])) {
            throw new \InvalidArgumentException('Invalid pagination data provided.');
        }

        $response = [
            'msg' => 'success',
            'status' => true,
            'totalRecord' => $pagination['total'],
            'currentPage' => $pagination['current_page'],
            'perPage' => $pagination['per_page'],
            'lastPage' => $pagination['last_page'],
            'data' => $data,
        ];

        return $this->json($response, $statusCode);
    }

    public function send()
    {
        if ($this->testMode) {
            return $this->prepareTestResponse();
        }

        $this->sendHeaders();
        $this->sendContent();
        exit;
    }

    protected function sendHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $key => $value) {
                header("$key: $value");
            }
        }
    }

    protected function sendContent(): void
    {
        echo $this->content;
    }

    public function prepareTestResponse(): array
    {
        $body = $this->content;
        if ($this->headers['Content-Type'] === 'application/json') {
            $body = json_decode($this->content, true);
        }

        return [
            'status' => $this->statusCode,
            'headers' => $this->headers,
            'body' => $body,
            'errors' => $this->validationErrors
        ];
    }

    public function asTest(): self
    {
        $this->testMode = true;
        return $this;
    }
    public function isTesting(): bool
    {
        return $this->testMode;
    }

    public function getTestResponse(): ?array
    {
        return $this->testResponse;
    }
    public function validationErrors(array $errors): self
    {
        $this->validationErrors = $errors;
        return $this->json(['errors' => $errors], 422);
    }
    public function setValidationErrors(array $errors): void
    {
        $this->validationErrors = $errors;
    }

    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }
    public function withCors(array $options = []): self
    {
        $this->headers['Access-Control-Allow-Origin'] = $options['origin'] ?? '*';
        $this->headers['Access-Control-Allow-Methods'] = $options['methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS';
        $this->headers['Access-Control-Allow-Headers'] = $options['headers'] ?? 'Content-Type, Authorization';
        $this->headers['Access-Control-Allow-Credentials'] = isset($options['credentials']) && $options['credentials'] ? 'true' : 'false';
        return $this;
    }
}
