<?php

namespace App\Http\Controllers;

use App\Models\Site; // Adjust the namespace according to your model's location
use App\Models\Blog; // Adjust the namespace according to your model's location
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index()
    {
        try {
            $data = Site::orderBy('queue', 'desc')->with('blogs')->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }

    public function getSixSites()
    {
        try {
            $data = Site::orderBy('queue', 'desc')->with('blogs')->take(6)->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['video'] = $request->file('video')? url('storage/' . $request->file('video')->store('uploads/site','public')):'';
        try {
            $site = Site::create($data);
            return response()->json(['message' => 'Successfully saved!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error saving data'], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $site = Site::findOrFail($id);
            $data = $request->all();
            $data['video'] = $request->file('video')
                ? url('storage/' . $request->file('video')->store('uploads/site', 'public')) // Adjust path as needed
                : $site->video;

                if ($request->file('video')) {
                    \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $site->video));
                }
            $site->update($data);

            return response()->json([
                'message' => 'site successfully updated!',
                'updatedsite' => $site,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error updating site: ' . $e->getMessage());
            return response()->json(['error' => 'Error updating site data'], 400);
        }
    }

    public function destroy($id)
    {
        try {
            $siteToDelete = Site::findOrFail($id);
            
            if ($siteToDelete->video) {
                \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $siteToDelete->video));
            }
            Blog::where('site_id', $id)->update(['site_id' => null]);
            $siteToDelete->delete();
            $remainingSites = Site::all();

            return response()->json($remainingSites);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error deleting site: ' . $e->getMessage()], 400);
        }
    }



    public function show($id)
    {
        try {
            $site = Site::with('blogs')->findOrFail($id);
            return response()->json($site);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Publication not found'], 404);
        }
    }
}
