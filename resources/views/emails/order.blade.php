@component('mail::message')
# Hello {{$name}}

## Thank's for your order!.

<p>
	Invoice #<span style="font-weight: bold">{{$invoice}}</span>.
</p>

@component('mail::table')
|Products|Duration (days)|Quantity|Total|
|:-------|:-------------:|:------:|----:|
@foreach($products as $product)
| {{ $product["product"]["name"] }} |{{ $product["product"]["duration_days"] }}| {{ $product["quantity"] }}  | ${{$product["product"]["price"]}}|
@endforeach
||||**${{number_format($total)}}**|
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
