@component('mail::message')
# Hello {{$name}}

## You've been invited to "{{ $name }}'s" team.

<p>
	Use the <span style="font-weight: bold">Obsidian</span> software with your personal information.
</p>
<p>
	User: <span style="font-weight: bold">{{ $email }}</span><br>
	Password: <span style="font-weight: bold">{{ $password }}</span>
</p>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
