<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('created_at', 'desc')->get();
        return Inertia::render('admin/Banner', [
            'banners' => $banners
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:2048',
            'is_active' => 'boolean'
        ]);

        $path = $request->file('image')->store('banners', 'public');

        Banner::create([
            'title' => $request->title,
            'image_url' => '/storage/' . $path,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Banner created successfully');
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean'
        ]);

        $data = [
            'title' => $request->title,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image')) {
            if ($banner->image_url) {
                $oldPath = str_replace('/storage/', '', $banner->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('banners', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $banner->update($data);

        return redirect()->back()->with('success', 'Banner updated successfully');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image_url) {
            $oldPath = str_replace('/storage/', '', $banner->image_url);
            Storage::disk('public')->delete($oldPath);
        }
        
        $banner->delete();

        return redirect()->back()->with('success', 'Banner deleted successfully');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        return redirect()->back()->with('success', 'Banner status updated');
    }
}
