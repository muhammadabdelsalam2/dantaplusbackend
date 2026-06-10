<?php

namespace App\Http\Controllers;

use App\Services\Chat\TeamService;
use Illuminate\Http\Request;

class TeamController extends Controller
{

    //
    public function __construct(
        protected TeamService $teamService
    ) {
    }

    public function index(Request $request)
    {
        // dd($request->user());
        $user = $request->user();
        $teams = $this->teamService->getUserTeams($user->id);

        return response()->json([
            'status' => true,
            'data' => $teams
        ]);
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'name'        => ['required', 'string', 'max:255'],
        'member_ids'  => ['nullable', 'array'],
        'member_ids.*'=> ['exists:users,id'],
    ]);

    $validated['owner_id'] = $request->user()->id;

    $team = $this->teamService->createTeam($validated);

    return response()->json([
        'status' => true,
        'data'   => $team
    ], 201);
}
}
