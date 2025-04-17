<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

	<!-- icon of the app -->
	<link rel="shortcut icon" type="image/png" href="{{ asset('img/icon.png') }}"/>
	
    <!-- this is the title name of the app -->
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- for the charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>




    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">



    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
					<img src="{{ asset('img/logo.png') }}" height="20%" width="20%"></img>
					{{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        <!-- Authentication Links -->
                        <!--
						<li class="nav-item">
                            <a class="nav-link square-button" href="{{ route('home') }}">Home</a>
                        </li>
						-->
                        @guest
                        @if (Route::has('login'))
                        <li class="nav-item">
                            <a class="nav-link square-button" href="{{ route('login') }}">{{ __('Login') }}</a>

                        </li>
                        @endif
                        @if (Route::has('register'))
                        <li class="nav-item">
                            <a class="nav-link square-button" href="{{ route('register') }}">{{ __('Register') }}</a>
                        </li>
                        @endif

                        @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                {{ Auth::user()->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
								<a class="dropdown-item"  href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                                <a class="dropdown-item" href="{{ route('profile') }}">
                                    {{ __('Profile') }}
                                </a>
                                <a class="dropdown-item" href="{{ route('device-register') }}">
                                    {{ __('Register my device') }}
                                </a>
                                <a class="dropdown-item" href="{{ route('device-manager') }}">
                                    {{ __('Manage my devices') }}
                                </a>
								<a class="dropdown-item" href="{{ route('purchase') }}">
                                    {{ __('Purchase') }}
                                </a>
								<a class="dropdown-item" href="{{ route('subscription-manager') }}">
                                    {{ __('Manage my subscriptions') }}
								</a>
								@if(Auth::user() && Auth::user()->email == config('app.admin_email'))
                                <a class="dropdown-item" href="{{ route('admin-control-center') }}">
                                    {{ __('Admin control center') }}
                                </a>
                                @endif
		
                                <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();
                                    document.getElementById('logout-form').submit();">
                                    {{ __('Logout') }}
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </li>
                        @endguest
                    </ul>
                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
						
                    </ul>
                </div>

                <!-- temp log out button -->
                @auth
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="nav-link square-button" type="submit" class="btn btn-primary">Logout</button>
                </form>
                @endauth
            </div>
        </nav>
        <main class="py-4">
            @yield('content')
        </main>
    </div>
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-/bQdsTh/da6pkI1MST/rWKFNjaCP5gBSY4sEBT38Q/9RBh9AH40zEOg7Hlq2THRZ"
        crossorigin="anonymous"></script>
	
	</body>
	
</html>