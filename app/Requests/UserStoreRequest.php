<?php

namespace App\Requests;

use App\Core\BaseRequest;

class UserStoreRequest extends BaseRequest
{
    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
    ];

    protected $messages = [
        'name.required' => 'The name field is required.',
        'email.required' => 'The email field is required.',
        'email.email' => 'The email must be a valid email address.',
        'email.unique' => 'The email has already been taken.',
        'password.required' => 'The password field is required.',
        'password.min' => 'The password must be at least 8 characters.',
    ];
}