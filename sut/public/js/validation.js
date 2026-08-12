// Client-side registration form validation
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('regForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        let valid = true;

        // DOB: must be in the past and >= 18 years old
        const dobInput = document.getElementById('dob');
        if (dobInput) {
            const dob = new Date(dobInput.value);
            const now = new Date();
            const age = Math.floor((now - dob) / (365.25 * 24 * 3600 * 1000));
            if (!dobInput.value || dob >= now) {
                setInvalid(dobInput, 'Date of birth cannot be in the future.');
                valid = false;
            } else if (age < 18) {
                setInvalid(dobInput, 'You must be at least 18 years old.');
                valid = false;
            } else {
                clearInvalid(dobInput);
            }
        }

        // Password match
        const pwd  = document.getElementById('password');
        const cpwd = document.getElementById('confirm_password');
        if (pwd && cpwd && pwd.value !== cpwd.value) {
            setInvalid(cpwd, 'Passwords do not match.');
            valid = false;
        } else if (cpwd) {
            clearInvalid(cpwd);
        }

        // SSN last 4
        const ssn = document.getElementById('ssn_last4');
        if (ssn && !/^\d{4}$/.test(ssn.value)) {
            setInvalid(ssn, 'Must be exactly 4 numeric digits.');
            valid = false;
        } else if (ssn) {
            clearInvalid(ssn);
        }

        // ZIP
        const zip = document.getElementById('zip');
        if (zip && !/^\d{5}(-\d{4})?$/.test(zip.value)) {
            setInvalid(zip, 'Enter a valid ZIP code (e.g. 62701).');
            valid = false;
        } else if (zip) {
            clearInvalid(zip);
        }

        if (!valid) e.preventDefault();
    });

    function setInvalid(el, msg) {
        el.classList.add('is-invalid');
        let fb = el.nextElementSibling;
        if (!fb || !fb.classList.contains('invalid-feedback')) {
            fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            el.parentNode.insertBefore(fb, el.nextSibling);
        }
        fb.textContent = msg;
        el.setAttribute('aria-describedby', (el.id || '') + '_err');
    }

    function clearInvalid(el) {
        el.classList.remove('is-invalid');
    }
});
