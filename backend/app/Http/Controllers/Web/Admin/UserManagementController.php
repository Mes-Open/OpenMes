<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Crew;
use App\Models\Skill;
use App\Models\User;
use App\Models\WageGroup;
use App\Models\Worker;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $users = User::with(['roles', 'workstation', 'worker'])->orderBy('created_at', 'desc')->get();
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles        = Role::all();
        $workstations = Workstation::with('line')->orderBy('name')->get();
        $crews        = Crew::active()->orderBy('name')->get();
        $wageGroups   = WageGroup::active()->orderBy('name')->get();
        $skills       = Skill::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'workstations', 'crews', 'wageGroups', 'skills'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'username'             => 'required|string|max:255|unique:users',
            'email'                => 'required|string|email|max:255|unique:users',
            'password'             => ['required', 'confirmed', Password::defaults()],
            'role'                 => 'required_if:account_type,user|nullable|exists:roles,name',
            'account_type'         => 'required|in:user,workstation',
            'workstation_id'       => 'nullable|exists:workstations,id|required_if:account_type,workstation',
            'worker_code'          => 'nullable|string|max:50|unique:workers,code',
            'worker_phone'         => 'nullable|string|max:50',
            'worker_crew_id'       => 'nullable|exists:crews,id',
            'worker_wage_group_id' => 'nullable|exists:wage_groups,id',
            'skills'               => 'nullable|array',
            'skills.*.id'          => 'required|exists:skills,id',
            'skills.*.level'       => 'nullable|integer|min:1|max:5',
        ]);

        $user = User::create([
            'name'                  => $validated['name'],
            'username'              => $validated['username'],
            'email'                 => $validated['email'],
            'password'              => Hash::make($validated['password']),
            'account_type'          => $validated['account_type'],
            'workstation_id'        => $validated['account_type'] === 'workstation' ? $validated['workstation_id'] : null,
            'force_password_change' => $request->boolean('force_password_change'),
        ]);

        if ($validated['account_type'] === 'user' && !empty($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        // Create worker profile when code is provided and account is personal
        if (!empty($validated['worker_code']) && $validated['account_type'] === 'user') {
            $worker = Worker::create([
                'code'          => $validated['worker_code'],
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'phone'         => $validated['worker_phone'] ?? null,
                'crew_id'       => $validated['worker_crew_id'] ?? null,
                'wage_group_id' => $validated['worker_wage_group_id'] ?? null,
                'is_active'     => true,
            ]);

            $worker->skills()->sync(
                collect($request->input('skills', []))
                    ->mapWithKeys(fn($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
            );

            $user->update(['worker_id' => $worker->id]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Account created successfully.');
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        $user->load('worker.skills');
        $roles        = Role::all();
        $workstations = Workstation::with('line')->orderBy('name')->get();
        $crews        = Crew::active()->orderBy('name')->get();
        $wageGroups   = WageGroup::active()->orderBy('name')->get();
        $skills       = Skill::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'workstations', 'crews', 'wageGroups', 'skills'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'username'             => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'                => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password'             => ['nullable', 'confirmed', Password::defaults()],
            'role'                 => 'required_if:account_type,user|nullable|exists:roles,name',
            'account_type'         => 'required|in:user,workstation',
            'workstation_id'       => 'nullable|exists:workstations,id|required_if:account_type,workstation',
            'worker_code'          => 'nullable|string|max:50|unique:workers,code,' . ($user->worker_id ?? 'NULL'),
            'worker_phone'         => 'nullable|string|max:50',
            'worker_crew_id'       => 'nullable|exists:crews,id',
            'worker_wage_group_id' => 'nullable|exists:wage_groups,id',
            'skills'               => 'nullable|array',
            'skills.*.id'          => 'required|exists:skills,id',
            'skills.*.level'       => 'nullable|integer|min:1|max:5',
        ]);

        $updateData = [
            'name'                  => $validated['name'],
            'username'              => $validated['username'],
            'email'                 => $validated['email'],
            'account_type'          => $validated['account_type'],
            'workstation_id'        => $validated['account_type'] === 'workstation' ? $validated['workstation_id'] : null,
            'force_password_change' => $request->boolean('force_password_change'),
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($request, $user, $validated, $updateData) {
            $user->update($updateData);
            if ($validated['account_type'] === 'user' && !empty($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            } else {
                $user->syncRoles([]);
            }

            if ($validated['account_type'] === 'user' && !empty($validated['worker_code'])) {
                $workerData = [
                    'code'          => $validated['worker_code'],
                    'name'          => $validated['name'],
                    'email'         => $validated['email'],
                    'phone'         => $validated['worker_phone'] ?? null,
                    'crew_id'       => $validated['worker_crew_id'] ?? null,
                    'wage_group_id' => $validated['worker_wage_group_id'] ?? null,
                ];

                if ($user->worker) {
                    $user->worker->update($workerData);
                    $worker = $user->worker;
                } else {
                    $worker = Worker::create(array_merge($workerData, ['is_active' => true]));
                    $user->update(['worker_id' => $worker->id]);
                }

                $worker->skills()->sync(
                    collect($request->input('skills', []))
                        ->mapWithKeys(fn($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
                );
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
