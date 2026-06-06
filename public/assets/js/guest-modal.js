/**
 * Guest Login Modal logic
 */
document.addEventListener('DOMContentLoaded', () => {
    const guestForm = document.getElementById('guest-onboarding-form');
    if (!guestForm) return; // Modal not rendered if logged in

    const usernameInput = document.getElementById('guest-username');
    const ageInput = document.getElementById('guest-age');
    const genderContainer = document.getElementById('guest-gender-container');
    const genderHidden = document.getElementById('guest-gender');
    const ageError = document.getElementById('guest-age-error');
    const usernameFeedback = document.getElementById('guest-username-feedback');

    const btnSubmit = document.getElementById('btn-submit-guest');
    const btnSwitchRegister = document.getElementById('btn-switch-to-register');

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Username auto-suggest prefill
    const randomSuffix = Math.floor(1000 + Math.random() * 9000);
    usernameInput.value = `wanderer_${randomSuffix}`;

    // Validate username availability
    let checkTimeout = null;
    const checkUsername = async (val) => {
        if (val.length < 3) {
            usernameFeedback.className = 'input-feedback-icon';
            return;
        }

        try {
            const res = await fetch(`api/check-username?u=${encodeURIComponent(val)}`);
            const data = await res.json();
            if (data.available) {
                usernameFeedback.className = 'input-feedback-icon valid';
            } else {
                usernameFeedback.className = 'input-feedback-icon invalid';
            }
        } catch (err) {
            usernameFeedback.className = 'input-feedback-icon';
        }
    };

    usernameInput.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        // Force alphanumeric & underscore only
        e.target.value = val.replace(/[^a-zA-Z0-9_]/g, '');

        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => checkUsername(e.target.value), 400);
    });

    // Run first availability check on prefilled username
    checkUsername(usernameInput.value);

    // Gender selection
    genderContainer.addEventListener('click', (e) => {
        const chip = e.target.closest('.gender-chip');
        if (!chip) return;

        genderContainer.querySelectorAll('.gender-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
        genderHidden.value = chip.getAttribute('data-gender');
    });

    // Age validation
    ageInput.addEventListener('input', () => {
        const age = parseInt(ageInput.value, 10);
        if (age < 13) {
            ageError.style.display = 'block';
        } else {
            ageError.style.display = 'none';
        }
    });

    // Form submission
    guestForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = usernameInput.value.trim();
        const age = parseInt(ageInput.value, 10);
        const gender = genderHidden.value;

        if (username.length < 3 || username.length > 20) {
            alert('Username must be 3-20 characters long.');
            return;
        }

        if (age < 13) {
            ageError.style.display = 'block';
            return;
        }

        if (!gender) {
            alert('Please select a gender chip.');
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span>Entering Arena...</span>';

        const formData = new FormData();
        formData.append('username', username);
        formData.append('age', age.toString());
        formData.append('gender', gender);

        try {
            const res = await fetch('guest/start', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (res.ok && data.success) {
                // Success! Close modal and refresh to load the ChatArena state
                location.reload();
            } else {
                alert(data.error || 'Failed to enter as guest.');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<span>Start as Guest</span>';
            }
        } catch (err) {
            alert('A network error occurred. Please try again.');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<span>Start as Guest</span>';
        }
    });
});
