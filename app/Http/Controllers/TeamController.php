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
}
