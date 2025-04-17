document.querySelector('input[name="phoneNumber"]').addEventListener('input', function() {
    const phoneInput = this;
    const phoneError = document.getElementById('phone-error');

    if (!phoneInput.checkValidity()) {
        phoneInput.setCustomValidity(''); // Clear the custom validity message
    } else {
        phoneInput.setCustomValidity('');
        phoneError.textContent = ''; // Clear the error message
    }
});


document.querySelector('input[name="zipCode"]').addEventListener('input', function() {
    const zipInput = this;
    const zipError = document.getElementById('Zip-error');

    if (!zipInput.checkValidity()) {
        zipInput.setCustomValidity(''); // Clear the custom validity message
    } else {
        zipInput.setCustomValidity('');
        zipError.textContent = ''; // Clear the error message
    }
});


document.querySelector('input[name="orderNumber"]').addEventListener('input', function() {
    const OrderInput = this;
    const OrderError = document.getElementById('Order-error');

    if (!OrderInput.checkValidity()) {
        OrderInput.setCustomValidity(''); // Clear the custom validity message
    } else {
        OrderInput.setCustomValidity('');
        OrderError.textContent = ''; // Clear the error message
    }
});