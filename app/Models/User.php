<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected $table = 'users'; // ชื่อตารางในฐานข้อมูล
    protected bool $allowDynamic = true;
    protected array $fillable = [
        'id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

}