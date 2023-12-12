<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface MemberRepositoryInterface
{
    public function create(array $data): Model;
    public function update(string $id, array $data): Model;
    public function delete(string $id): Model;
}
