<?php

namespace App\Services\Chat;

use App\Repositories\Chat\Team\TeamRepository;

class TeamService
{
    public function __construct(
        protected TeamRepository $teamRepository
    ) {
    }

    public function getUserTeams($owner_id)
    {
        return $this->teamRepository->getAccessibleTeams($owner_id);
    }

}