@extends('layouts.app')
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
			<header style="display: flex; justify-content: space-between; align-items: end; vertical-align: middle;">
				<h3>Update</h3>
			</header>
			<form id="purchase_form" method="POST" action="{{ route('update-checkout') }}">
				@csrf
				@auth
				<div class="card">
					<div class="card-header">{{ __('Billing Details') }}</div>
					<div class="card-body">
						<div class="form-group row">
							<label for="billing_address" class="col-md-4 col-form-label text-md-right">{{ __('Address') }}</label>

							<div class="col-md-6">
								<input id="billing_address" type="text" class="form-control @error('billing_address') is-invalid @enderror" name="billing_address" value="{{ isset($user) ? $user->address_1 : old('billing_address') }}" required autocomplete="billing_address">

								@error('billing_address')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>

						<div class="form-group row">
							<label for="billing_address_2" class="col-md-4 col-form-label text-md-right">{{ __('Address Line 2 (optional)') }}</label>

							<div class="col-md-6">
								<input id="billing_address_2" type="text" class="form-control @error('billing_address_2') is-invalid @enderror" name="billing_address_2" value="{{ isset($user) ? $user->address_2 : old('billing_address_2') }}" autocomplete="billing_address_2">

								@error('billing_address_2')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>
						<div class="form-group row">
							<label for="billing_city" class="col-md-4 col-form-label text-md-right">{{ __('City')}}</label>

							<div class="col-md-6">
								<input id="billing_city" type="text" class="form-control @error('billing_city') is-invalid @enderror" name="billing_city" value="{{ isset($user) ? $user->city : old('billing_city') }}" required autocomplete="billing_city">

								@error('billing_city')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>
						<div class="form-group row">
							<label for="billing_zip_code" class="col-md-4 col-form-label text-md-right">{{ __('Zip / Postal Code')}}</label>

							<div class="col-md-6">
								<input id="billing_zip_code" type="text" class="form-control @error('billing_zip_code') is-invalid @enderror" name="billing_zip_code" value="{{ isset($user) ? $user->zip_code : old('billing_zip_code') }}" required autocomplete="billing_zip_code">

								@error('billing_zip_code')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>
						<div class="form-group row">
							<label for="billing_country" class="col-md-4 col-form-label text-md-right">{{ __('Country')}}</label>

							<div class="col-md-6">
								<select id="billing_country" name="billing_country" class="form-control">
									<option value="AF">Afghanistan</option>
									<option value="AX">Åland Islands</option>
									<option value="AL">Albania</option>
									<option value="DZ">Algeria</option>
									<option value="AS">American Samoa</option>
									<option value="AD">Andorra</option>
									<option value="AO">Angola</option>
									<option value="AI">Anguilla</option>
									<option value="AQ">Antarctica</option>
									<option value="AG">Antigua and Barbuda</option>
									<option value="AR">Argentina</option>
									<option value="AM">Armenia</option>
									<option value="AW">Aruba</option>
									<option value="AU">Australia</option>
									<option value="AT">Austria</option>
									<option value="AZ">Azerbaijan</option>
									<option value="BS">Bahamas</option>
									<option value="BH">Bahrain</option>
									<option value="BD">Bangladesh</option>
									<option value="BB">Barbados</option>
									<option value="BY">Belarus</option>
									<option value="BE">Belgium</option>
									<option value="BZ">Belize</option>
									<option value="BJ">Benin</option>
									<option value="BM">Bermuda</option>
									<option value="BT">Bhutan</option>
									<option value="BO">Bolivia, Plurinational State of</option>
									<option value="BQ">Bonaire, Sint Eustatius and Saba</option>
									<option value="BA">Bosnia and Herzegovina</option>
									<option value="BW">Botswana</option>
									<option value="BV">Bouvet Island</option>
									<option value="BR">Brazil</option>
									<option value="IO">British Indian Ocean Territory</option>
									<option value="BN">Brunei Darussalam</option>
									<option value="BG">Bulgaria</option>
									<option value="BF">Burkina Faso</option>
									<option value="BI">Burundi</option>
									<option value="KH">Cambodia</option>
									<option value="CM">Cameroon</option>
									<option value="CA">Canada</option>
									<option value="CV">Cape Verde</option>
									<option value="KY">Cayman Islands</option>
									<option value="CF">Central African Republic</option>
									<option value="TD">Chad</option>
									<option value="CL">Chile</option>
									<option value="CN">China</option>
									<option value="CX">Christmas Island</option>
									<option value="CC">Cocos (Keeling) Islands</option>
									<option value="CO">Colombia</option>
									<option value="KM">Comoros</option>
									<option value="CG">Congo</option>
									<option value="CD">Congo, the Democratic Republic of the</option>
									<option value="CK">Cook Islands</option>
									<option value="CR">Costa Rica</option>
									<option value="CI">Côte d'Ivoire</option>
									<option value="HR">Croatia</option>
									<option value="CU">Cuba</option>
									<option value="CW">Curaçao</option>
									<option value="CY">Cyprus</option>
									<option value="CZ">Czech Republic</option>
									<option value="DK">Denmark</option>
									<option value="DJ">Djibouti</option>
									<option value="DM">Dominica</option>
									<option value="DO">Dominican Republic</option>
									<option value="EC">Ecuador</option>
									<option value="EG">Egypt</option>
									<option value="SV">El Salvador</option>
									<option value="GQ">Equatorial Guinea</option>
									<option value="ER">Eritrea</option>
									<option value="EE">Estonia</option>
									<option value="ET">Ethiopia</option>
									<option value="FK">Falkland Islands (Malvinas)</option>
									<option value="FO">Faroe Islands</option>
									<option value="FJ">Fiji</option>
									<option value="FI">Finland</option>
									<option value="FR">France</option>
									<option value="GF">French Guiana</option>
									<option value="PF">French Polynesia</option>
									<option value="TF">French Southern Territories</option>
									<option value="GA">Gabon</option>
									<option value="GM">Gambia</option>
									<option value="GE">Georgia</option>
									<option value="DE">Germany</option>
									<option value="GH">Ghana</option>
									<option value="GI">Gibraltar</option>
									<option value="GR">Greece</option>
									<option value="GL">Greenland</option>
									<option value="GD">Grenada</option>
									<option value="GP">Guadeloupe</option>
									<option value="GU">Guam</option>
									<option value="GT">Guatemala</option>
									<option value="GG">Guernsey</option>
									<option value="GN">Guinea</option>
									<option value="GW">Guinea-Bissau</option>
									<option value="GY">Guyana</option>
									<option value="HT">Haiti</option>
									<option value="HM">Heard Island and McDonald Islands</option>
									<option value="VA">Holy See (Vatican City State)</option>
									<option value="HN">Honduras</option>
									<option value="HK">Hong Kong</option>
									<option value="HU">Hungary</option>
									<option value="IS">Iceland</option>
									<option value="IN">India</option>
									<option value="ID">Indonesia</option>
									<option value="IR">Iran, Islamic Republic of</option>
									<option value="IQ">Iraq</option>
									<option value="IE">Ireland</option>
									<option value="IM">Isle of Man</option>
									<option value="IL">Israel</option>
									<option value="IT">Italy</option>
									<option value="JM">Jamaica</option>
									<option value="JP">Japan</option>
									<option value="JE">Jersey</option>
									<option value="JO">Jordan</option>
									<option value="KZ">Kazakhstan</option>
									<option value="KE">Kenya</option>
									<option value="KI">Kiribati</option>
									<option value="KP">Korea, Democratic People's Republic of</option>
									<option value="KR">Korea, Republic of</option>
									<option value="KW">Kuwait</option>
									<option value="KG">Kyrgyzstan</option>
									<option value="LA">Lao People's Democratic Republic</option>
									<option value="LV">Latvia</option>
									<option value="LB">Lebanon</option>
									<option value="LS">Lesotho</option>
									<option value="LR">Liberia</option>
									<option value="LY">Libya</option>
									<option value="LI">Liechtenstein</option>
									<option value="LT">Lithuania</option>
									<option value="LU">Luxembourg</option>
									<option value="MO">Macao</option>
									<option value="MK">Macedonia, the former Yugoslav Republic of</option>
									<option value="MG">Madagascar</option>
									<option value="MW">Malawi</option>
									<option value="MY">Malaysia</option>
									<option value="MV">Maldives</option>
									<option value="ML">Mali</option>
									<option value="MT">Malta</option>
									<option value="MH">Marshall Islands</option>
									<option value="MQ">Martinique</option>
									<option value="MR">Mauritania</option>
									<option value="MU">Mauritius</option>
									<option value="YT">Mayotte</option>
									<option value="MX">Mexico</option>
									<option value="FM">Micronesia, Federated States of</option>
									<option value="MD">Moldova, Republic of</option>
									<option value="MC">Monaco</option>
									<option value="MN">Mongolia</option>
									<option value="ME">Montenegro</option>
									<option value="MS">Montserrat</option>
									<option value="MA">Morocco</option>
									<option value="MZ">Mozambique</option>
									<option value="MM">Myanmar</option>
									<option value="NA">Namibia</option>
									<option value="NR">Nauru</option>
									<option value="NP">Nepal</option>
									<option value="NL">Netherlands</option>
									<option value="NC">New Caledonia</option>
									<option value="NZ">New Zealand</option>
									<option value="NI">Nicaragua</option>
									<option value="NE">Niger</option>
									<option value="NG">Nigeria</option>
									<option value="NU">Niue</option>
									<option value="NF">Norfolk Island</option>
									<option value="MP">Northern Mariana Islands</option>
									<option value="NO">Norway</option>
									<option value="OM">Oman</option>
									<option value="PK">Pakistan</option>
									<option value="PW">Palau</option>
									<option value="PS">Palestinian Territory, Occupied</option>
									<option value="PA">Panama</option>
									<option value="PG">Papua New Guinea</option>
									<option value="PY">Paraguay</option>
									<option value="PE">Peru</option>
									<option value="PH">Philippines</option>
									<option value="PN">Pitcairn</option>
									<option value="PL">Poland</option>
									<option value="PT">Portugal</option>
									<option value="PR">Puerto Rico</option>
									<option value="QA">Qatar</option>
									<option value="RE">Réunion</option>
									<option value="RO">Romania</option>
									<option value="RU">Russian Federation</option>
									<option value="RW">Rwanda</option>
									<option value="BL">Saint Barthélemy</option>
									<option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
									<option value="KN">Saint Kitts and Nevis</option>
									<option value="LC">Saint Lucia</option>
									<option value="MF">Saint Martin (French part)</option>
									<option value="PM">Saint Pierre and Miquelon</option>
									<option value="VC">Saint Vincent and the Grenadines</option>
									<option value="WS">Samoa</option>
									<option value="SM">San Marino</option>
									<option value="ST">Sao Tome and Principe</option>
									<option value="SA">Saudi Arabia</option>
									<option value="SN">Senegal</option>
									<option value="RS">Serbia</option>
									<option value="SC">Seychelles</option>
									<option value="SL">Sierra Leone</option>
									<option value="SG">Singapore</option>
									<option value="SX">Sint Maarten (Dutch part)</option>
									<option value="SK">Slovakia</option>
									<option value="SI">Slovenia</option>
									<option value="SB">Solomon Islands</option>
									<option value="SO">Somalia</option>
									<option value="ZA">South Africa</option>
									<option value="GS">South Georgia and the South Sandwich Islands</option>
									<option value="SS">South Sudan</option>
									<option value="ES">Spain</option>
									<option value="LK">Sri Lanka</option>
									<option value="SD">Sudan</option>
									<option value="SR">Suriname</option>
									<option value="SJ">Svalbard and Jan Mayen</option>
									<option value="SZ">Swaziland</option>
									<option value="SE">Sweden</option>
									<option value="CH">Switzerland</option>
									<option value="SY">Syrian Arab Republic</option>
									<option value="TW">Taiwan, Province of China</option>
									<option value="TJ">Tajikistan</option>
									<option value="TZ">Tanzania, United Republic of</option>
									<option value="TH">Thailand</option>
									<option value="TL">Timor-Leste</option>
									<option value="TG">Togo</option>
									<option value="TK">Tokelau</option>
									<option value="TO">Tonga</option>
									<option value="TT">Trinidad and Tobago</option>
									<option value="TN">Tunisia</option>
									<option value="TR">Turkey</option>
									<option value="TM">Turkmenistan</option>
									<option value="TC">Turks and Caicos Islands</option>
									<option value="TV">Tuvalu</option>
									<option value="UG">Uganda</option>
									<option value="UA">Ukraine</option>
									<option value="AE">United Arab Emirates</option>
									<option value="GB">United Kingdom</option>
									<option value="US" selected>United States</option>
									<option value="UM">United States Minor Outlying Islands</option>
									<option value="UY">Uruguay</option>
									<option value="UZ">Uzbekistan</option>
									<option value="VU">Vanuatu</option>
									<option value="VE">Venezuela, Bolivarian Republic of</option>
									<option value="VN">Viet Nam</option>
									<option value="VG">Virgin Islands, British</option>
									<option value="VI">Virgin Islands, U.S.</option>
									<option value="WF">Wallis and Futuna</option>
									<option value="EH">Western Sahara</option>
									<option value="YE">Yemen</option>
									<option value="ZM">Zambia</option>
									<option value="ZW">Zimbabwe</option>
								</select>

								@error('billing_country')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>
						<div class="form-group row">
							<label for="billing_state" class="col-md-4 col-form-label text-md-right">{{ __('State / Province')}}</label>

							<div class="col-md-6">
								<input id="billing_state" type="text" class="form-control @error('billing_state') is-invalid @enderror" name="billing_state" value="{{ isset($user) ? $user->state : old('billing_state') }}" required autocomplete="billing_state">

								@error('billing_state')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>
					</div>
				</div>
				<br>
				<div class="payment-icons">
					<img src="{{ asset('img/mastercard.png') }}">
					<img src="{{ asset('img/visa.png') }}">
					<img src="{{ asset('img/americanexpress.png') }}">
				</div>
				<br>
				<div class="card">
					<div class="card-header">
						<span style="display: inline-block; vertical-align: top; margin-right: 10px;">
							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="22" viewBox="0 0 18 28" aria-hidden="true">
								<path d="M5 12h8V9c0-2.203-1.797-4-4-4S5 6.797 5 9v3zm13 1.5v9c0 .828-.672 1.5-1.5 1.5h-15C.672 24 0 23.328 0 22.5v-9c0-.828.672-1.5 1.5-1.5H2V9c0-3.844 3.156-7 7-7s7 3.156 7 7v3h.5c.828 0 1.5.672 1.5 1.5z"></path>
							</svg>                      
						</span>
						<span>
							{{ __('Credit Card Info. This is a secure SSL encrypted payment.') }}
						</span>
					</div>
					<div class="card-body">
						<div class="form-group row">
							<label for="name_on_card" class="col-md-4 col-form-label text-md-right">{{ __('Name on the Card') }}</label>
							<div class="col-md-6">
								<input id="name_on_card" type="text" class="form-control @error('name_on_card') is-invalid @enderror" name="name_on_card" required autocomplete="name_on_card">

								@error('name_on_card')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>

						
						<div class="form-group row">
							<label for="card_number" class="col-md-4 col-form-label text-md-right">{{ __('Card Number') }}</label>

							<div class="col-md-6">
								<input x-autocompletetype="cc-number" type="text" class="form-control cc-number" name="card_number" placeholder="XXXX-XXXX-XXXX-XXXX" required>

								@error('card_number')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>

						<div class="form-group row">
							<label for="card_exp" class="col-md-4 col-form-label text-md-right">{{ __('Expiration Date') }}</label>

							<div class="col-md-6">
								<input placeholder="MM / YY" type="text" class="form-control cc-exp" name="card_exp" x-autocompletetype="cc-exp" placeholder="Expires MM/YY" required maxlength="9">

								@error('card_exp')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>

						<div class="form-group row">
							<label for="card_cvc" class="col-md-4 col-form-label text-md-right">{{ __('CVC') }}</label>

							<div class="col-md-6">
								<input placeholder="Security Code" type="password" class="form-control cc-cvc" name="card_cvc" x-autocompletetype="cc-csc" required  autocomplete="off">

								@error('card_cvc')
								<span class="invalid-feedback" role="alert">
									<strong>{{ $message }}</strong>
								</span>
								@enderror
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-6 offset-md-4">
								<p class="validation"></p>
							</div>
						</div>

						<div class="form-group row mb-0">
							<div class="col-md-6 offset-md-4">
								<button id="purchase_btn" class="btn btn-primary">
									Update
								</button>
								<br>
							</div>
						</div>
					</div>
				</div>
				@endauth
			</form>
		</div>
	</div>
</div>

@push('scripts')
<script src="js/payment.js"></script>
<script type="text/javascript">

	const cardHolderName = document.getElementById('name_on_card');
	
	var J = Payment.J,
	//numeric = document.querySelector('[data-numeric]'),
	number = document.querySelector('.cc-number'),
	exp = document.querySelector('.cc-exp'),
	cvc = document.querySelector('.cc-cvc'),
	validation = document.querySelector('.validation');

	//Payment.restrictNumeric(numeric);
	Payment.formatCardNumber(number, 16);
	Payment.formatCardExpiry(exp);
	Payment.formatCardCVC(cvc);

	const purchaseBtn = document.getElementById('purchase_btn');

	if(purchaseBtn)
	{
		purchaseBtn.style.backgroundColor = "#3490dc";
		purchaseBtn.innerText = "Purchase";
		purchaseBtn.style.cursor = "pointer";
		purchaseBtn.style.pointerEvents = "all";

		purchaseBtn.addEventListener('click', async (e) => {
			e.preventDefault();

			if(cardHolderName.value == "")
			{
				validation.style.color = "red";
				validation.innerText = "The Name on the Card can't be empty.";
				return false;
			} else {
				validation.innerText = "";
			}

			if(exp.value.length != 7)
			{
				validation.style.color = "red";
				validation.innerText = "Please fill correctly the Expiration Date: MM / YY.";
				return false;
			} else {
				validation.innerText = "";
			}
			
			J.toggleClass(document.querySelectorAll('input'), 'invalid');
			J.removeClass(validation, 'passed failed');

			var cardType = Payment.fns.cardType(J.val(number));

			J.toggleClass(number, 'invalid', !Payment.fns.validateCardNumber(J.val(number)));
			J.toggleClass(exp, 'invalid', !Payment.fns.validateCardExpiry(Payment.cardExpiryVal(exp)));

			J.toggleClass(cvc, 'invalid', !Payment.fns.validateCardCVC(J.val(cvc), cardType));

			if (document.querySelectorAll('.invalid').length) {
				J.addClass(validation, 'failed');
			} else {
				J.addClass(validation, 'passed');

				purchaseBtn.style.backgroundColor = "grey";
				purchaseBtn.innerText = "Sending...";
				purchaseBtn.style.cursor = "not-allowed";
				purchaseBtn.style.pointerEvents = "none";

				document.getElementById("purchase_form").submit();
			}

			return false;
		});
	}
</script>
@endpush

<style type="text/css">
input.invalid {
	border: 2px solid red;
}

.validation.failed:after {
	color: red;
	content: 'Validation failed';
}

.validation.passed:after {
	color: green;
	content: 'Validation passed';
}

#checkout_table {
	width: 100%;
}

#checkout_table td, #checkout_table th {
	text-align: left;
	border: 1px solid #eee;
	color: #666;
	vertical-align: middle;
	padding: .5em 1.387em;
	line-height: 25px;
}

#checkout_table th {
	background-color: #fafafa;
}

#checkout_table td {
	background-color: #fff;
}

#checkout_table tfoot th {
	background-color: #fff;
	text-align: right;
}

.paymen-icons {
	margin-top: 40px;
}

.payment-icons img {
	max-height: 32px;
}
</style>
<script>
	function copy_billing_data()
	{
		document.getElementById('billing_address').value = document.getElementById('address').value;
		document.getElementById('billing_address_2').value = document.getElementById('address_2').value;
		document.getElementById('billing_city').value = document.getElementById('city').value;
		document.getElementById('billing_zip_code').value = document.getElementById('zip_code').value;
		document.getElementById('billing_state').value = document.getElementById('state').value;
		document.getElementById('billing_country').selectedIndex = document.getElementById('country').selectedIndex;
	}
</script>
@endsection