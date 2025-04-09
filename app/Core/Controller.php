<?php

namespace App\Core;

class Controller
{
    protected $request;
    protected $response;

    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    protected function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function validate($data, $rules)
    {
        // Implement validation logic using the Validator class
        $validator = new Validator();
        return $validator->validate($data, $rules);
    }

    protected function authorize($user, $permission)
    {
        // Implement authorization logic
        // This could check user roles or permissions
    }
}