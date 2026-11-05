<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();
        return view('dean.profile.show', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        $user->update($validated);

        return redirect()->route('dean.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        if ($user->photo) {
            Storage::disk(storage_disk())->delete($user->photo);
        }

        $path = $request->file('photo')->store('profiles', storage_disk());
        $user->update(['photo' => $path]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully!',
                'photo_url' => storage_url($path),
            ]);
        }

        return redirect()->route('dean.profile.show')
            ->with('success', 'Profile photo updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'errors' => [
                        'current_password' => ['Current password is incorrect.']
                    ]
                ], 422);
            }
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully!'
            ]);
        }

        return back()->with('success', 'Password changed successfully.');
    }
}
