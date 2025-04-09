<?php

namespace App\Requests;

use App\Core\BaseRequest;

class UserUpdateRequest extends BaseRequest
{
    protected $rules = [
        'name' => 'required|min:3|max:50',
        'email' => 'required|email',
    ];

    protected $messages = [
        'name.required' => 'The name field is mandatory.',
        'name.min' => 'The name must have at least 3 characters.',
        'name.max' => 'The name must not exceed 50 characters.',
        'email.required' => 'We need your email address.',
        'email.email' => 'The email must be a valid email address.',
    ];
}