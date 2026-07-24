<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Show the user's profile page.
     */
    public function show()
    {
        $user = auth()->user()->load('addresses', 'defaultShippingAddress', 'defaultBillingAddress');
        return view('profile.index', compact('user'));
    }

    /**
     * Show the edit profile page.
     */
    public function edit()
    {
        $user = auth()->user();
        return view('profile.edit', compact('user'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store new photo
            $photoPath = $request->file('profile_photo')->store(
                'profile-photos/' . $user->id,
                'public'
            );
            $data['profile_photo_path'] = $photoPath;
        }

        // Update user profile
        $user->update($data);

        return redirect()->route('profile.show')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Show the change password page.
     */
    public function showChangePassword()
    {
        return view('profile.change-password');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Password changed successfully!');
    }

    /**
     * Delete the user's profile photo.
     */
    public function deletePhoto()
    {
        $user = auth()->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);

            return redirect()->back()
                ->with('success', 'Profile photo deleted successfully!');
        }

        return redirect()->back()
            ->with('error', 'No profile photo to delete.');
    }
}
