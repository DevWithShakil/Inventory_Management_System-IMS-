<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:admin,staff',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'staff',
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => $user
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

       if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Email or Password does not match with our record.',
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        $user->tokens()->delete();
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'User logged in successfully',
            'token' => $token,
            'user' => $user
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function user(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully',
            'user' => $request->user()
        ]);
    }

// AuthController.php

public function updateProfile(Request $request)
{
    $user = $request->user();

    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
        'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Image Validation
        'current_password' => 'nullable|required_with:new_password',
        'new_password' => 'nullable|min:6|confirmed',
    ]);

    $user->name = $request->name;
    $user->email = $request->email;

    // 🔥 Avatar Upload Logic
    if ($request->hasFile('avatar')) {
        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        // Store new avatar
        $path = $request->file('avatar')->store('uploads/avatars', 'public');
        $user->avatar = $path;
    }

    if ($request->filled('current_password')) {
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'errors' => ['current_password' => ['Current password does not match.']]
            ], 422);
        }
        $user->password = Hash::make($request->new_password);
    }

    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Profile updated successfully.',
        'user' => $user
    ]);
}
}
