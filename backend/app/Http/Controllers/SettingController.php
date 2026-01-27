<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;


class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::first();
        return response()->json([
            'status' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif, webp|max:2048',
        ]);

        $settings = Setting::first();
        $data = $request->except(['logo']);

        if ($request->hasFile('logo')) {
            if ($settings->logo && Storage::disk('public')->exists($settings->logo)) {
                Storage::disk('public')->delete($settings->logo);
            }
            $path = $request->file('logo')->store('uploads/settings', 'public');
            $data['logo'] = $path;
        }

        $settings->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }
}
