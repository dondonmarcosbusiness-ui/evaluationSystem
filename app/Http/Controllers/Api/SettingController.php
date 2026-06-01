<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array'
        ]);

        $prevStatus = \App\Models\Setting::where('key', 'evaluation_status')->value('value');
        $newStatus = $request->settings['evaluation_status'] ?? null;

        foreach ($request->settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Trigger notification if status changed to 'open'
        if ($newStatus === 'open' && $prevStatus !== 'open') {
            \App\Jobs\NotifyStudentsJob::dispatch();
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
