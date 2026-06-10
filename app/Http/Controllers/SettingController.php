<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'budget' => (int) Setting::get('budget', 0) ?: null,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'budget' => 'nullable|integer|min:0|max:1000000000000',
        ]);

        if (array_key_exists('budget', $data)) {
            Setting::put('budget', (int) ($data['budget'] ?? 0));
        }

        return $this->index();
    }
}
