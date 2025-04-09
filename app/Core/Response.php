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

    public function json($data, int $statusCode = 200)
    {
        $this->statusCode = $statusCode;
        $this->headers['Content-Type'] = 'application/json';
        $this->content = json_encode($data);

        if ($this->testMode) {
            return $this->prepareTestResponse();
        }

        return $this->send();
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

    protected function prepareTestResponse(): array
    {
        if ($this->validationErrors !== null) {
            return [
                'status' => 422,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => ['errors' => $this->validationErrors]
            ];
        }
        $this->testResponse = [
            'status' => $this->statusCode,
            'headers' => $this->headers,
            'body' => json_decode($this->content, true),
        ];
        return $this->testResponse;
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
    public function validationErrors(array $errors, int $statusCode = 422)
    {
        $this->statusCode = $statusCode;
        $this->headers['Content-Type'] = 'application/json';
        $this->content = json_encode(['errors' => $errors]);

        if ($this->testMode) {
            $this->prepareTestResponse();
        }

        return $this;
    }
    public function setValidationErrors(array $errors): void
    {
        $this->validationErrors = $errors;
    }

    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }
}
