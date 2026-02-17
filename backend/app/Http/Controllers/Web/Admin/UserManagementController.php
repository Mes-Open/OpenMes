<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::with(['roles', 'workstation'])->orderBy('created_at', 'desc')->get();
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles = Role::all();
        $workstations = Workstation::with('line')->orderBy('name')->get();
        return view('admin.users.create', compact('roles', 'workstations'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|exists:roles,name',
            'account_type' => 'required|in:user,workstation',
            'workstation_id' => 'nullable|exists:workstations,id|required_if:account_type,workstation',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'account_type' => $validated['account_type'],
            'workstation_id' => $validated['account_type'] === 'workstation' ? $validated['workstation_id'] : null,
            'force_password_change' => $request->boolean('force_password_change'),
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'Account created successfully.');
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $workstations = Workstation::with('line')->orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles', 'workstations'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|exists:roles,name',
            'account_type' => 'required|in:user,workstation',
            'workstation_id' => 'nullable|exists:workstations,id|required_if:account_type,workstation',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'account_type' => $validated['account_type'],
            'workstation_id' => $validated['account_type'] === 'workstation' ? $validated['workstation_id'] : null,
            'force_password_change' => $request->boolean('force_password_change'),
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync role
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
