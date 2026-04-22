<?php



namespace App\Repositories\Contracts\Chat\Team;

use App\Models\Team;

interface TeamRepositoryInterface
{
    function getAccessibleTeams(int $userId): object;
}