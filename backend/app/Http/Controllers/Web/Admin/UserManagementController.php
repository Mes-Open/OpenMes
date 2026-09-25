<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Worker;
use App\Models\Workstation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users. Rows live-sync via the `users` shape (safe
     * columns only); role + workstation names come as props keyed by id.
     */
    public function index()
    {
        $users = User::with('roles')->get(['id']);

        return Inertia::render('admin/users/Index', [
            'userRoles' => $users->mapWithKeys(fn ($u) => [$u->id => $u->roles->pluck('name')->first()]),
            'workstationNames' => Workstation::pluck('name', 'id'),
            'currentUserId' => auth()->id(),
            'formOptions' => Inertia::optional(fn () => $this->formData()),
        ]);
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $options = $this->formData();

        return Inertia::render('admin/users/Create', array_merge($options, [
            'formOptions' => $options,
            'currentUserId' => auth()->id(),
            'userRoles' => User::with('roles')->get(['id'])->mapWithKeys(fn ($u) => [$u->id => $u->roles->pluck('name')->first()]),
            'workstationNames' => Workstation::pluck('name', 'id'),
        ]));
    }

    public function show(User $user)
    {
        return redirect()->route('admin.users.edit', $user);
    }

    private function workforce(): \App\Extension\Contracts\WorkforceProvider
    {
        return app(\App\Extension\Contracts\WorkforceProvider::class);
    }

    /** Shared option lists for the create/edit forms. */
    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('name')->pluck('name'),
            'workstations' => Workstation::with('line:id,name')->orderBy('name')->get(['id', 'name', 'line_id'])
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->line ? "{$w->name} ({$w->line->name})" : $w->name]),
            // Crews, wage groups and skills are workforce administration, which
            // not every installation records. Asked through the contract rather
            // than queried here, so a system without them simply offers no such
            // pickers instead of failing on missing tables.
            'crews' => $this->workforce()->crewOptions(),
            'wageGroups' => $this->workforce()->wageGroupOptions(),
            'skills' => $this->workforce()->skillOptions(),
        ];
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_type' => $validated['account_type'],
                'workstation_id' => $validated['workstation_id'] ?? null,
                'force_password_change' => $request->boolean('force_password_change'),
            ]);

            if ($validated['account_type'] === 'user' && ! empty($validated['role'])) {
                $user->assignRole($validated['role']);
            } elseif ($validated['account_type'] === 'workstation') {
                $user->assignRole('Operator');
            }

            // Create worker profile when code is provided and account is personal
            if (! empty($validated['worker_code']) && $validated['account_type'] === 'user') {
                $worker = Worker::create([
                    'code' => $validated['worker_code'],
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['worker_phone'] ?? null,
                    'crew_id' => $validated['worker_crew_id'] ?? null,
                    'wage_group_id' => $validated['worker_wage_group_id'] ?? null,
                    'is_active' => true,
                ]);

                if (Worker::hasModuleRelation('skills')) {
                    $worker->skills()->sync(
                        collect($request->input('skills', []))
                            ->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
                    );
                }

                $user->update(['worker_id' => $worker->id]);
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', __('Account created successfully.'));
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        $user->load(Worker::hasModuleRelation('skills') ? 'worker.skills' : 'worker');

        return Inertia::render('admin/users/Edit', array_merge($this->formData(), [
            'assignments' => [
                'lines' => $user->lines()->get(['lines.id', 'lines.name'])->map(fn ($line) => $line->only('id', 'name')),
                'station' => ($user->worker?->workstation ?? $user->workstation)?->only('id', 'name', 'line_id'),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'account_type' => $user->account_type,
                'workstation_id' => $user->workstation_id,
                'force_password_change' => (bool) $user->force_password_change,
                'role' => $user->roles->pluck('name')->first(),
                'worker' => $user->worker ? [
                    'code' => $user->worker->code,
                    'phone' => $user->worker->phone,
                    'crew_id' => $user->worker->crew_id,
                    'wage_group_id' => $user->worker->wage_group_id,
                    'skills' => Worker::hasModuleRelation('skills')
                        ? $user->worker->skills->map(fn ($s) => ['id' => $s->id, 'level' => $s->pivot->level ?? 1])
                        : [],
                ] : null,
            ],
        ]));
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'account_type' => $validated['account_type'],
            'workstation_id' => $validated['workstation_id'] ?? null,
            'force_password_change' => $request->boolean('force_password_change'),
        ];

        $passwordChanged = ! empty($validated['password']);

        if ($passwordChanged) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        DB::transaction(function () use ($request, $user, $validated, $updateData, $passwordChanged) {
            $user->update($updateData);

            // An administrator setting somebody's password is almost always a
            // response to something — a leak, a departure, a suspected
            // compromise. Leaving their tokens alive would mean the reset
            // changed nothing for whoever already held one. Browser sessions
            // are ended by AuthenticateSession, which compares against the
            // hash this update just replaced.
            if ($passwordChanged) {
                $user->tokens()->delete();
            }

            if ($validated['account_type'] === 'user' && ! empty($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            } elseif ($validated['account_type'] === 'workstation') {
                $user->syncRoles(['Operator']);
            } else {
                $user->syncRoles([]);
            }

            if ($validated['account_type'] === 'user' && ! empty($validated['worker_code'])) {
                $workerData = [
                    'code' => $validated['worker_code'],
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['worker_phone'] ?? null,
                    'crew_id' => $validated['worker_crew_id'] ?? null,
                    'wage_group_id' => $validated['worker_wage_group_id'] ?? null,
                ];

                if ($user->worker) {
                    $user->worker->update($workerData);
                    $worker = $user->worker;
                } else {
                    $worker = Worker::create(array_merge($workerData, ['is_active' => true]));
                    $user->update(['worker_id' => $worker->id]);
                }

                if (Worker::hasModuleRelation('skills')) {
                    $worker->skills()->sync(
                        collect($request->input('skills', []))
                            ->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
                    );
                }
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
