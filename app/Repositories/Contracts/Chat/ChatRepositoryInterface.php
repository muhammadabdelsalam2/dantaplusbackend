<?php



namespace App\Repositories\Contracts\Chat\Team;

use App\Models\Team;

interface TeamRepositoryInterface
{
    function Get(int $userId): object;
}