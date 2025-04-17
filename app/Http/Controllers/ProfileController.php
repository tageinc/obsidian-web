<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user(); // Get the currently logged-in user
        return view('profile', compact('user'));
    }

    public function updateName(Request $request)
    {
        $user = Auth::user(); // Get the currently logged-in user

        // Validate the request data for updating the name
        $request->validate([
            'name' => 'required|string|max:255', // Example validation rule for name
        ]);
        

        // Update the user's name
        $user->name = $request->input('name');

        if ($user->save()) {
            return redirect()->route('profile')->with('success', 'Name updated successfully');
        } else {
            return redirect()->route('profile')->with('error', 'Failed to update name');
        }
    }


    public function updateNameApi(Request $request)
    {
        $user = Auth::user(); // Get the currently logged-in user

        // Validate the request data for updating the name
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Update the user's name
        $user->name = $request->input('name');

        if ($user->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Name updated successfully',
                'user' => $user,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update name',
            ], 500);
        }
    }


    public function updateEmail(Request $request)
    {
        $user = Auth::user(); // Get the currently logged-in user

        // Validate the request data for updating the email
        $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        // Get the user's current email
        $currentEmail = $user->email;
        

        // Update the user's email
        $user->email = $request->input('email');

        if ($user->save()) {
            // Update the email in the device_registers table
            DB::table('users')
                ->where('email', $currentEmail)
                ->update(['email' => $user->email]);

            return redirect()->route('profile')->with('success', 'Email updated successfully');
        } else {
            return redirect()->route('profile')->with('error', 'Failed to update email');
        }
    }

     public function updateEmailApi(Request $request)
{
    $user = Auth::user(); // Get the currently logged-in user

    // Validate the request data for updating the email
    $request->validate([
        'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
    ]);

    // Update the user's email
    $user->email = $request->input('email');

    if ($user->save()) {
        return response()->json([
            'success' => true,
            'message' => 'Email updated successfully',
            'user' => $user,
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update email',
        ], 500);
    }
}

    public function updatePhoneNumber(Request $request)
    {
        $user = Auth::user(); // Get the currently logged-in user

        // Validate the request data for updating the phone number
        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'max:255',
                // Add a regex pattern to match the phone number format
                'regex:/^[0-9]{10,15}$/', // Adjust the pattern as needed
                // Add a custom rule to ensure uniqueness for the user
                function ($attribute, $value, $fail) use ($user) {
                    $exists = User::where('phone_number', $value)
                                  ->where('id', '!=', $user->id)
                                  ->exists();
                    if ($exists) {
                        $fail('The phone number has already been taken.');
                    }
                },
            ],
        ]);

        // Get the user's current phone #
        $currentPhoneNumber = $user->phone_number;
        
        // Update the user's phone number
        $user->phone_number = $request->input('phone_number');

        if ($user->save()) {
            // Update the email in the device_registers table
            DB::table('users')
                ->where('phone_number', $currentPhoneNumber)
                ->update(['phone_number' => $user->phone_number]);

            return redirect()->route('profile')->with('success', 'Phone number updated successfully');
        } else {
            return redirect()->route('profile')->with('error', 'Failed to update phone number');
        }
    }

public function updatePhoneNumberApi(Request $request)
{
    $user = Auth::user(); // Get the currently logged-in user

    // Validate the request data for updating the phone number
    $request->validate([
        'phone_number' => [
            'required',
            'string',
            'max:15',
            'regex:/^[0-9]{10,15}$/', // Adjust the pattern as needed
            // Ensure uniqueness
            function ($attribute, $value, $fail) use ($user) {
                $exists = User::where('phone_number', $value)
                              ->where('id', '!=', $user->id)
                              ->exists();
                if ($exists) {
                    $fail('The phone number has already been taken.');
                }
            },
        ],
    ]);

    // Update the user's phone number
    $user->phone_number = $request->input('phone_number');

    if ($user->save()) {
        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully',
            'user' => $user,
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update phone number',
        ], 500);
    }
}


    public function updatePassword(Request $request)
    {
        $user = Auth::user(); // Get the currently logged-in user

        // Validate the request data for updating the password
        $request->validate([
            'password' => 'required|string|min:8|confirmed', // Password validation rules
        ]);

        // Update the user's password
        $user->password = Hash::make($request->input('password'));

        if ($user->save()) {
            return redirect()->route('profile')->with('success', 'Password updated successfully');
        } else {
            return redirect()->route('profile')->with('error', 'Failed to update password');
        }
    }
public function updatePasswordApi(Request $request)
{
    $user = Auth::user(); // Get the currently logged-in user

    // Validate the request data for updating the password
    $request->validate([
        'password' => 'required|string|min:8|confirmed', // Password validation rules
    ]);

    // Update the user's password
    $user->password = Hash::make($request->input('password'));

    if ($user->save()) {
        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update password',
        ], 500);
    }
}


}
