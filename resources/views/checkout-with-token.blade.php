@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
                        <header style="display: flex; justify-content: space-between; align-items: end; vertical-align: middle;">
                <h1>App Checkout</h1><a class="btn btn-primary" href="{{ route('purchase') }}">Edit</a>
            </header>
            <form id="purchase_form" method="POST" action="{{ route('purchase-checkout-api') }}">
                <input type="hidden" name="token" id="token" value="{{ $token }}">
                <input type="hidden" name="device_id" id="device_id" value="{{ $device_id }}"> 
                @livewire('checkout-card', ['licenses' => $licenses])
                <br>
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
                                    <option value="US" selected>United States</option>
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
                                <input id="card_number" x-autocompletetype="cc-number" type="text" class="form-control cc-number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required>
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
                                <input id="card_exp" placeholder="MM / YY" type="text" class="form-control cc-exp" name="card_exp" x-autocompletetype="cc-exp" placeholder="Expires MM/YY" required maxlength="9">
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
                                <input id="card_cvc" placeholder="Security Code" type="password" class="form-control cc-cvc" name="card_cvc" x-autocompletetype="cc-csc" required  autocomplete="off">
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
                                <button id="purchase_btn" class="btn btn-primary">
                                    {{ __('Purchase') }}
                                </button>
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <div class="form-group row mb-0">
                                <div class="form-group row mb-0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endauth
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/payment.js') }}"></script>
<script type="text/javascript">
    function getQueryParam(param) {
        let params = new URLSearchParams(window.location.search);
        return params.get(param);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const token = getQueryParam('token');
        console.log('Token from URL:', token);
        const deviceId = document.getElementById('device_id').value;
        console.log('Device ID from form:', deviceId);

        document.getElementById('token').value = token;

        document.getElementById('purchase_form').addEventListener('submit', function(e) {
            e.preventDefault();

            const cardHolderName = document.getElementById('name_on_card');
            const number = document.querySelector('.cc-number');
            const exp = document.querySelector('.cc-exp');
            const cvc = document.querySelector('.cc-cvc');
            const validation = document.querySelector('.validation');
            const purchaseBtn = document.getElementById('purchase_btn');

            if (cardHolderName.value === "") {
                validation.style.color = "red";
                validation.innerText = "The Name on the Card can't be empty.";
                return false;
            } else {
                validation.innerText = "";
            }

            if (exp.value.length != 7) {
                validation.style.color = "red";
                validation.innerText = "Please fill correctly the Expiration Date: MM / YY.";
                return false;
            } else {
                validation.innerText = "";
            }

            $(document.querySelectorAll('input')).toggleClass('invalid', false);
            $(validation).removeClass('passed failed');

            var cardType = Payment.fns.cardType($(number).val());

            $(number).toggleClass('invalid', !Payment.fns.validateCardNumber($(number).val()));
            $(exp).toggleClass('invalid', !Payment.fns.validateCardExpiry(Payment.cardExpiryVal(exp)));
            $(cvc).toggleClass('invalid', !Payment.fns.validateCardCVC($(cvc).val(), cardType));

            if (document.querySelectorAll('.invalid').length) {
                $(validation).addClass('failed');
            } else {
                $(validation).addClass('passed');

                purchaseBtn.style.backgroundColor = "grey";
                purchaseBtn.innerText = "Sending...";
                purchaseBtn.style.cursor = "not-allowed";
                purchaseBtn.style.pointerEvents = "none";
                console.log('Device ID BEING SENT', deviceId);

                const formData = {
                    token: token,
                    billing_address: document.getElementById('billing_address').value,
                    billing_address_2: document.getElementById('billing_address_2').value,
                    billing_city: document.getElementById('billing_city').value,
                    billing_zip_code: document.getElementById('billing_zip_code').value,
                    billing_country: document.getElementById('billing_country').value,
                    billing_state: document.getElementById('billing_state').value,
                    name_on_card: document.getElementById('name_on_card').value,
                    card_number: document.getElementById('card_number').value,
                    card_exp: document.getElementById('card_exp').value,
                    card_cvc: document.getElementById('card_cvc').value,
                    products: {
                        monthly_sp1_d1: '1'
                    },
                    device_id: document.getElementById('device_id').value,
                };

                console.log('Form Data before submitting:', formData);

                fetch('{{ route('purchase-checkout-api') }}', {
                    method: 'POST',
                    body: JSON.stringify(formData),
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { throw new Error(text); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.location.href = '{{ route("checkout.success") }}';
                    } else {
                        validation.style.color = "red";
                        validation.innerText = data.error || 'An error occurred during the purchase process.';
                        purchaseBtn.style.backgroundColor = "#3490dc";
                        purchaseBtn.innerText = "Purchase";
                        purchaseBtn.style.cursor = "pointer";
                        purchaseBtn.style.pointerEvents = "all";
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    validation.style.color = "red";
                    validation.innerText = 'An error occurred during the purchase process. Please check the console for more details.';
                    purchaseBtn.style.backgroundColor = "#3490dc";
                    purchaseBtn.innerText = "Purchase";
                    purchaseBtn.style.cursor = "pointer";
                    purchaseBtn.style.pointerEvents = "all";
                });

                return false;
            }
        });
    });
</script>

<style type="text/css">
.scrollable-window {
    height: 150px; /* Adjust the height as needed */
    overflow-y: auto; /* Allows vertical scrolling */
    margin-bottom: 20px; /* Adds some space below the scrollable area */
    border: 1px solid #ccc; /* Adds a border around the scrollable window */
    padding: 10px;
    background-color: #f8f9fa; /* Light background color for the scrollable area */
}

.scrollable-window::-webkit-scrollbar {
    width: 6px;
}

.scrollable-window::-webkit-scrollbar-thumb {
    background-color: darkgrey;
    border-radius: 10px;
}
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

.payment-icons {
    margin-top: 40px;
}

.payment-icons img {
    max-height: 32px;
}
</style>

<script>
function copy_billing_data() {
    document.getElementById('billing_address').value = document.getElementById('address').value;
    document.getElementById('billing_address_2').value = document.getElementById('address_2').value;
    document.getElementById('billing_city').value = document.getElementById('city').value;
    document.getElementById('billing_zip_code').value = document.getElementById('zip_code').value;
    document.getElementById('billing_state').value = document.getElementById('state').value;
    document.getElementById('billing_country').selectedIndex = document.getElementById('country').selectedIndex;
}
</script>
@endsection
