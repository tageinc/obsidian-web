<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        if (!$user->hasVerifiedEmail()) {
            Auth::logout();
            return redirect('/login')->with('error', 'You need to verify your email address to access this page.');
        }
        return redirect($this->redirectTo);
    }



public function apiLogin(Request $request)
    {
        Log::info('Login attempt', ['request' => $request->all()]);
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            Log::info('Auth attempt successful', ['credentials' => $credentials]);
            
            $user = Auth::user();
            Log::info('User authenticated', ['user' => $user]);

            // Create Sanctum token
            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json(['status' => 'success', 'user' => $user, 'token' => $token], 200);
        } else {
            Log::warning('Invalid credentials', ['credentials' => $credentials]);
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $email = $request->input('email');
        session()->flash('login_email', $email);
        Log::info('Email flashed to session: ' . $email);

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors([
                $this->username() => [trans('auth.failed')],
            ]);
    }
}
