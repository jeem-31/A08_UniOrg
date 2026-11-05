<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();
        return view('student.profile.show', compact('user'));
    }

    public function edit()
    {
        $user = auth()->user();
        return view('student.profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'student_id' => 'required|string|unique:users,student_id,' . $user->id,
            'course' => 'nullable|string',
            'year_level' => 'nullable|string',
            'section' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'year_enrolled' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'photo' => 'nullable|image|max:2048',
        ]);

        // Combine name parts into name (academic fields are read-only in UI but still accepted if sent)
        $validated['name'] = trim(implode(' ', array_filter([
            $validated['first_name'] ?? '',
            $validated['middle_name'] ?? '',
            $validated['last_name'] ?? '',
            $validated['suffix'] ?? '',
        ])));

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                Storage::disk(storage_disk())->delete($user->photo);
            }
            $validated['photo'] = $request->file('photo')->store('profiles', storage_disk());
        }

        $user->update($validated);

        return redirect()->route('profile.show')
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

        return redirect()->route('profile.show')
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
