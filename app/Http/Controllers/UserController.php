<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles:id,name,description')
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', (bool) $request->input('status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
            'roles' => Role::orderBy('id')->get(['id', 'name', 'description']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $this->syncRoles($user, $data['roles'] ?? []);

            return $user;
        });

        return redirect()->route('users.index')
            ->with('success', "User {$user->email} created.");
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->withSelfProtection($user);

        DB::transaction(function () use ($data, $user) {
            $changes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ];
            if (! empty($data['password'])) {
                $changes['password'] = Hash::make($data['password']);
            }
            $user->update($changes);
            $this->syncRoles($user, $data['roles'] ?? []);
        });

        return redirect()->route('users.index')
            ->with('success', "User {$user->email} updated.");
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $email = $user->email;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User {$email} deleted.");
    }

    /** Replace the user's role assignments (role + optional project scope). */
    private function syncRoles(User $user, array $roles): void
    {
        $user->roles()->detach();

        foreach ($roles as $assignment) {
            $user->roles()->syncWithoutDetaching([
                $assignment['role_id'] => ['project_id' => $assignment['project_id'] ?? null],
            ]);
        }
    }
}
