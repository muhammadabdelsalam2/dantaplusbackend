<?php

namespace App\Services\Chat;

use App\Models\Team;
use App\Repositories\Chat\Team\TeamRepository;
use Illuminate\Support\Facades\DB;

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
    public function createTeam(array $data)
{
    return DB::transaction(function () use ($data) {
        $team = Team::create([
            'name'     => $data['name'],
            'owner_id' => $data['owner_id'],
            'clinic_id' => $data['clinic_id'],
        ]);

        // إضافة الـ owner كـ member تلقائياً
        $members = collect($data['member_ids'] ?? []);
        $members->push($data['owner_id']);

        $team->members()->syncWithoutDetaching(
            $members->unique()->mapWithKeys(fn($id) => [
                $id => ['joined_at' => now()]
            ])->toArray()
        );

        return $team->load('members:id,name,email');
    });
}

}
