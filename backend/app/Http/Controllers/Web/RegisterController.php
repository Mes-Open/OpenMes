<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class RegisterController extends Controller
{
    public function show()
    {
        if (!$this->registrationEnabled()) {
            abort(404);
        }

        return view('auth.register');
    }

    public function store(RegisterRequest $request)
    {
        if (!$this->registrationEnabled()) {
            abort(404);
        }

        $key = 'register:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many registration attempts. Please try again later.']);
        }
        RateLimiter::hit($key, 60);

        $user = User::create([
            'name'                  => $request->name,
            'username'              => $request->username,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'account_type'          => 'user',
            'force_password_change' => false,
        ]);

        $user->assignRole('Operator');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('operator.select-line')
            ->with('success', 'Account created successfully. Welcome to OpenMES!');
    }

    private function registrationEnabled(): bool
    {
        $row = DB::table('system_settings')->where('key', 'allow_registration')->first();

        return json_decode($row->value ?? 'false', true) === true;
    }
}
