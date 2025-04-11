<?php
namespace App\Repository;

use App\Models\User;
use App\Core\BaseRepository;

class UserRepository extends BaseRepository
{
    public function model()
    {
        return new User();
    }

    public function findByEmail($email)
    {
        return $this->model->where('email', '=', $email)->first();
    }
}
