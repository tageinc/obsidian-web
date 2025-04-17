document.addEventListener('DOMContentLoaded', function() {
    // Your JavaScript code here
    document.querySelector('input[name="Email"]').addEventListener('input', function() {
        const emailInput = this;
        const emailError = document.getElementById('email-error');

        if (!emailInput.checkValidity()) {
            emailInput.setCustomValidity('Please enter a valid email address.');
            emailError.textContent = 'Please enter a valid email address.';
        } else {
            emailInput.setCustomValidity('');
            emailError.textContent = '';
        }
    });
});
