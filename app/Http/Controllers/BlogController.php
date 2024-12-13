<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\Site;
use App\Models\Equipment;
use App\Models\Three;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    public function getBlogs()
    {
        try {
            $blogs = Blog::orderBy('queue', 'desc')->get();
            foreach ($blogs as $blog) {
                $blog->equipment_names = $blog->equipment->pluck('name')->toArray();
            }
            return response()->json($blogs, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }

    public function insertBlog(Request $request)
    {
        $data = $request->all();

        $data['video'] = $request->hasFile('video') ? url('storage/' . $request->file('video')->store('uploads/blog', 'public')) : null;
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filePath[] = url('storage/' . $file->store('uploads/blog', 'public'));
                $data['images']=$filePath;
            }
        } else {
            $data['images'] = [];
        }
        try {
            $blog=Blog::create($data);
            if ($request->has('equipment') && is_array($request->input('equipment'))) {
                $blog->equipment()->attach($request->input('equipment'));
            }
            if($request->has('site')){
                $blog->site_id = $request->input('site');
            }
            if($request->has('d_id')){
                $blog->three_id = $request->input('d_id');
            }
            $blog->save();
            return response()->json(['message' => 'Successfully saved!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error saving data'], 500);
        }
    }

    public function updateBlog(Request $request, $id)
    {
        try {
            $blog = Blog::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Blog not found'], 404);
        }

        $data = $request->all();
        
        if ($request->hasFile('images')) {
            if ($blog->images) {
                foreach ($blog->images as $oldImage) {
                    \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $oldImage));
                }
            }    
            $filePaths = [];
            foreach ($request->file('images') as $file) {
                $filePaths[] = url('storage/' . $file->store('uploads/blog', 'public'));
            }
            $data['images'] = $filePaths; 
        } else {
            $data['images'] = $blog->images; 
        }
        
        if ($request->hasFile('video')) {
            if ($blog->video) {
                \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $blog->video));
            }
            $data['video'] = url('storage/' . $request->file('video')->store('uploads/blog', 'public')); 
        } else {
            $data['video'] = $blog->video;
        }
        
        try {
            $blog->update($data);

            if ($request->input('site') && $request->input('site') != $blog->site_id) {
                $blog->site_id = $request->input('site');
            }

            if ($request->input('d_id') && $request->input('d_id') != $blog->three_id) {
                $blog->three_id = $request->input('d_id');
            }
            $blog->save();

            if ($request->input('equipment')) {
                $oldEquipments = $blog->equipment;
                foreach ($oldEquipments as $oldEquip) {
                    $oldEquip->blogs()->detach($blog->id);
                }
                foreach ($request->input('equipment') as $equipId) {
                    $equipment = Equipment::find($equipId);
                    if ($equipment) {
                        $equipment->blogs()->attach($blog->id);
                    }
                }
            }

            return response()->json(['message' => 'Blog successfully updated!', 'blog' => $blog], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error updating blog data: ' . $e->getMessage()], 400);
        }
    }


    public function deleteBlog($id)
    {
        try {
            $blog = Blog::find($id);
            if (!$blog) {
                return response()->json(['error' => 'Blog not found'], 404);
            }
            
            if ($blog) {
                $blog->equipment()->detach(); 
                if ($blog->images) {
                    foreach (($blog->images) as $oldFile) {
                        \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $oldFile));
                    }
                }
                if ($blog->video) {
                    \Storage::disk('public')->delete(str_replace(url('storage') . '/', '', $blog->video));
                }
                $blog->delete();
                return response()->json(Blog::all(), 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error deleting blog'], 400);
        }
    }

    public function getBlogByID($id)
    {
        try {
            $blog = Blog::with(['site', 'equipment', 'three'])->find($id);
            if (!$blog) {
                return response()->json(['error' => 'Blog not found'], 404);
            }

            return response()->json($blog, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }

    public function insertSolution(Request $request)
    {
        $validated = $request->validate([
            'idd' => 'required|integer',
            'content' => 'required|string',
            'imageContent' => 'nullable|array',
            'images.*' => 'nullable|file|mimes:jpg,jpeg,png,gif'
        ]);
        
        try {
            $images = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $file) {
                    $path = url('storage/' . $file->store('uploads/blog/solution', 'public'));
                    
                    $images[] = [
                        'image' => $path, 
                        'title' => $request->input("imageContent.$index"),
                    ];
                }
            }
            
            $data = [
                'content' => $validated['content'],
                'images' => $images, 
            ];
            
            $case = Blog::find($validated['idd']);
            if ($case) {
                $updatedSolutions = array_merge($case->solution ?? [], [$data]);

                $case->update([
                    'solution' => $updatedSolutions,
                ]);

                return response()->json(['message' => 'Successfully saved!'], 200);
            } else {
                return response()->json(['error' => 'Case not found'], 404);
            }
        } catch (\Exception $e) {
            \Log::error('Error saving data:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error saving data'], 400);
        }
    }

    public function getBlogsWithCheckbox(Request $request)
    {
        $checkboxValue = $request->query('checkboxValue');
        $casesNum = $request->query('casesNum');

        if (!$checkboxValue) {
            return response()->json(['error' => 'Checkbox value is required'], 400);
        }

        try {
            $blogs = Blog::whereJsonContains('checkbox', $checkboxValue)->select('video', 'name', 'venue','id')->orderBy('queue', 'desc')->limit($casesNum)->get();
            return response()->json($blogs);
        } catch (\Exception $error) {
            \Log::error('Error fetching blogs: ', [$error]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function getBlogByType(Request $request)
    {
        $caseType = $request->query('caseType');
        try {
            $data = Blog::where('blog_type', $caseType)->select('video', 'name', 'venue')->limit(1)->get();
            return response()->json($data);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Error fetching data'], 400);
        }
    }

}
