@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Profile') }} </div>

                <div class="card-body">
                    <!-- Display the user's current name, email, and password -->

                    <!-- Form for updating the name -->
                    <form method="POST" action="{{ route('profile.updateName') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-2 mt-2"> <!-- Increased margin-top and margin-bottom -->
                            <label for="name" class="form-label">{{ __('Current name') }}: {{ $user->name }}</label>
                            <input type="text" class="form-control small-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ $user->name }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Update Name') }}</button>
                    </form>

                    <!-- Form for updating the phone number -->
                    <form method="POST" action="{{ route('profile.updatePhoneNumber') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-2 mt-4"> <!-- Increased margin-top and margin-bottom -->
                            <label for="phone_number" class="form-label">{{ __('Current phone number') }}: {{ $user->phone_number }}</label>
                            <input type="phone_numbermail" class="form-control small-input @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" value="{{ $user->phone_number }}" required>
                            @error('phone_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Update Phone #') }}</button>
                    </form>

                    <!-- Form for updating the email -->
                    <form method="POST" action="{{ route('profile.updateEmail') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-2 mt-4"> <!-- Increased margin-top and margin-bottom -->
                            <label for="email" class="form-label">{{ __('Current email') }}: {{ $user->email }}</label>
                            <input type="email" class="form-control small-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ $user->email }}" required>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Update Email') }}</button>
                    </form>

                    <!-- Form for updating the password -->
                    <form method="POST" action="{{ route('profile.updatePassword') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-2 mt-5"> <!-- Increased margin-top and margin-bottom -->
                            <label for="password" class="form-label">{{ __('New Password') }}</label>
                            <input type="password" class="form-control small-input @error('password') is-invalid @enderror" id="password" name="password" required>
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2"> <!-- Only increased margin-bottom -->
                            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                            <input type="password" class="form-control small-input" id="password_confirmation" name="password_confirmation" required>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Update Password') }}</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection