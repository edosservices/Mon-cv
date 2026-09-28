<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', [
            'staff' => User::where('tenant_id', auth()->user()->tenant_id)->whereHas('role', fn ($query) => $query->where('slug', UserRole::Staff->value))->with('permissions')->get(),
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::min(8)],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'role_id' => Role::where('slug', UserRole::Staff->value)->firstOrFail()->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);
        $user->permissions()->sync($data['permissions'] ?? []);
        $audit->record('staff.created', $user, null, ['email' => $user->email]);

        return back()->with('status', 'Collaborateur ajouté.');
    }
}
