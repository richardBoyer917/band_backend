<?php

namespace App\Http\Controllers;

use App\Models\Team; // Adjust the namespace according to your model's location
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function createOrUpdateTeam(Request $request)
    {
        $data = $request->all();

        try {
            $existingTeam = Team::first(); // Get the first team entry

            if ($existingTeam) {
                $existingTeam->update($data);
                return response()->json(['message' => 'Team data successfully updated!'], 200);
            } else {
                $newTeam = Team::create($data);
                return response()->json(['message' => 'Team data successfully saved!'], 200);
            }
        } catch (\Exception $e) {
            \Log::error('Error saving or updating team data: ' . $e->getMessage());
            return response()->json(['error' => 'Error saving or updating team data'], 400);
        }
    }

    public function getTeam()
    {
        try {
            $team = Team::all(); // Fetch all team entries
            return response()->json($team, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }
}
