@extends('layouts.app')
@section('content')
<div style="flex-direction: column" class="mt-4 relative flex items-top min-h-screen sm:items-center sm:pt-0">
    <h1>Updated your card information!</h1>
    <p>
      We appreciate your business!
      If you have any questions, please email
      <a href="mailto:info@tezca.net">info@tezca.net</a>.
    </p>
	<a href="{{route('home')}}" class="btn btn-primary mb-4">Go to home</a>
</div>
@endsection