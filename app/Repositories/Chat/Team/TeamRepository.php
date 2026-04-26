<?php



namespace App\Repositories\Chat\Team;

use App\Models\Team;
use App\Repositories\Contracts\Chat\Team\TeamRepositoryInterface;

class TeamRepository implements TeamRepositoryInterface
{
    public function getAccessibleTeams($owner_id): object
    {
        return Team::accessibleBy($owner_id)
            ->with([
                'members:id,name,email',
                'chats:id,team_id,name,type'
            ])
            ->latest()
            ->get();
    }
}