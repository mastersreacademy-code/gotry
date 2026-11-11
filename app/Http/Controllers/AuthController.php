<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Activity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)],
            'role' => ['required', Rule::in(['parent', 'provider'])],
        ]);
        try {
            $user = User::create($data);
        } catch (QueryException $exception) {
            // A concurrent signup can win after the unique validation query.
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)
                && User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email.']);
            }
            throw $exception;
        }
        Auth::login($user);
        $request->session()->regenerate();
        return $this->afterAuthentication($request, Auth::user());
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        if (! Auth::attempt([...$credentials, 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();
        return $this->afterAuthentication($request, Auth::user());
    }

    private function afterAuthentication(Request $request, User $user): RedirectResponse
    {
        $activityId = filter_var($request->input('activity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($user->role === 'parent' && $activityId !== false
            && Activity::whereKey($activityId)->where('status', 'pending_registrations')
                ->whereHas('provider', fn ($query) => $query->where('is_active', true))->exists()) {
            return redirect()->to(route('activities.show', ['activity' => $activityId]).'#trial');
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
