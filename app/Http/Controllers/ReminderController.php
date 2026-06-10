<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index()
    {
        return Reminder::orderBy('time')->get(['id', 'time', 'label', 'enabled']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'label' => 'nullable|string|max:60',
        ]);

        $reminder = Reminder::create([
            'time' => $data['time'],
            'label' => $data['label'] ?? '',
            'enabled' => true,
        ]);

        return response()->json($reminder->only('id', 'time', 'label', 'enabled'), 201);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $data = $request->validate([
            'time' => ['sometimes', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'label' => 'sometimes|nullable|string|max:60',
            'enabled' => 'sometimes|boolean',
        ]);

        $reminder->update($data);

        return response()->json($reminder->only('id', 'time', 'label', 'enabled'));
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();

        return response()->json(['ok' => true]);
    }
}
