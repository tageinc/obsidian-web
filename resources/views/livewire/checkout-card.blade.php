<div>
	<table id="checkout_table">
		<thead>
			<tr>
				<th>Item Name</th>
				<th>Item Price</th>
				<th>Quantity</th>
			</tr>
		</thead>
		<tbody>
			@forelse($licenses['licenses'] as $index => $license)
			<tr>
				<td>{{$license['name']}}</td>
				<td>${{$license['price']}}</td>
				<input type="hidden" name="products[{{$license['type']}}]" value="{{$license['quantity']}}">
				<td>{{$license['quantity']}}</td>
			</tr>
			@empty
			<tr>
				<td colspan="3">No items on card.</td>
			</tr>
			@endforelse
		</tbody>
		<tfoot>
			<tr>
				<th colspan="3">Total: <span id="checkout_total">${{$licenses['total']}}</span></th>
			</tr>
		</tfoot>
	</table>
</div>
