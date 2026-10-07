<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Invalid email or password.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        do {
            $studentId = now()->format('Y').'-'.random_int(100000, 999999);
        } while (User::where('student_id', $studentId)->exists());

        $user = DB::transaction(function () use ($data, $studentId) {
            $user = User::create([
                'full_name' => $data['full_name'],
                'student_id' => $studentId,
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'verification_status' => str_ends_with(strtolower($user->email), '.edu') || str_ends_with(strtolower($user->email), '.edu.ph') ? 'Verified' : 'Unverified',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('status', 'Complete your student profile to get started.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
