<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /* ================= AUTH ================= */

    // POST /api/login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Wrong credentials'], 401);
        }

        $token = $admin->createToken('admin')->plainTextToken;

        return response()->json([
            'message' => 'Logged in',
            'token' => $token,
            'admin' => $admin->append('photo_url'),
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    // GET /api/me
    public function me(Request $request)
    {
        return $request->user()->append('photo_url');
    }

    /* ================= CRUD ================= */

    // GET /api/admins
    public function index()
    {
        return Admin::all()->append('photo_url');
    }

    // POST /api/admins
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:admins,username',
            'password' => 'required|string|min:6',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('admins', 'public');
        }

        $data['password'] = Hash::make($data['password']);

        $admin = Admin::create($data);

        return response()->json($admin->append('photo_url'), 201);
    }

    // GET /api/admins/{id}
    public function show(Admin $admin)
    {
        return $admin->append('photo_url');
    }

    // POST /api/admins/{id}  (with _method=PUT) OR PUT /api/admins/{id}
    public function update(Request $request, Admin $admin)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:admins,username,' . $admin->id,
            'password' => 'sometimes|nullable|string|min:6',
            'photo' => 'sometimes|nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($admin->photo && Storage::disk('public')->exists($admin->photo)) {
                Storage::disk('public')->delete($admin->photo);
            }
            $data['photo'] = $request->file('photo')->store('admins', 'public');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $admin->update($data);

        return $admin->fresh()->append('photo_url');
    }

    // DELETE /api/admins/{id}
    public function destroy(Request $request, Admin $admin)
    {
        if ($request->user()->id === $admin->id) {
            return response()->json(['message' => 'You cannot delete yourself'], 403);
        }

        if ($admin->photo && Storage::disk('public')->exists($admin->photo)) {
            Storage::disk('public')->delete($admin->photo);
        }

        $admin->tokens()->delete();
        $admin->delete();

        return response()->json(['message' => 'Deleted']);
    }
}