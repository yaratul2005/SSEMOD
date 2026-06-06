/**
 * Account Registration & Login multi-step flow state machine
 */
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('auth-gate-overlay');
    if (!overlay) return; // Authenticated

    const guestCard = document.getElementById('guest-onboarding-card');
    const registerCard = document.getElementById('register-flow-card');
    const loginCard = document.getElementById('login-card');
    const successCard = document.getElementById('success-flow-card');
    const footerSwitch = document.getElementById('auth-footer-switch-view');

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Switch view triggers
    const btnSwitchRegister = document.getElementById('btn-switch-to-register');
    const btnRegBackToGuest = document.getElementById('btn-reg-back-to-guest');
    const btnSwitchLogin = document.getElementById('btn-switch-to-login');
    const btnLoginToGuest = document.getElementById('btn-login-to-guest');

    btnSwitchRegister.addEventListener('click', () => {
        guestCard.classList.remove('active');
        registerCard.classList.add('active');
        footerSwitch.style.display = 'none';
        goToStep(1);
    });

    btnRegBackToGuest.addEventListener('click', () => {
        registerCard.classList.remove('active');
        guestCard.classList.add('active');
        footerSwitch.style.display = 'block';
    });

    btnSwitchLogin.addEventListener('click', () => {
        guestCard.classList.remove('active');
        registerCard.classList.remove('active');
        loginCard.classList.add('active');
        footerSwitch.style.display = 'none';
    });

    btnLoginToGuest.addEventListener('click', () => {
        loginCard.classList.remove('active');
        guestCard.classList.add('active');
        footerSwitch.style.display = 'block';
    });

    // Step state tracking
    let currentStep = 1;
    const steps = [
        document.getElementById('register-step-1'),
        document.getElementById('register-step-2'),
        document.getElementById('register-step-3'),
        document.getElementById('register-step-4')
    ];
    const indicators = document.querySelectorAll('.step-indicator');
    const progressBar = document.getElementById('register-progress-bar');

    function goToStep(stepNum) {
        currentStep = stepNum;
        
        // Update panes
        steps.forEach((pane, idx) => {
            if (idx === stepNum - 1) {
                pane.classList.add('active');
            } else {
                pane.classList.remove('active');
            }
        });

        // Update progress bar & indicators
        const progressPct = (stepNum / 4) * 100;
        progressBar.style.width = `${progressPct}%`;

        indicators.forEach((indicator, idx) => {
            const indStep = parseInt(indicator.getAttribute('data-step'), 10);
            if (indStep < stepNum) {
                indicator.className = 'step-indicator completed';
            } else if (indStep === stepNum) {
                indicator.className = 'step-indicator active';
            } else {
                indicator.className = 'step-indicator';
            }
        });
    }

    // Step 1: Basic Info
    const regDisplayName = document.getElementById('reg-display-name');
    const regAge = document.getElementById('reg-age');
    const regGenderContainer = document.getElementById('reg-gender-container');
    const regGenderHidden = document.getElementById('reg-gender');
    const regAgeError = document.getElementById('reg-age-error');
    const regDisplayNameFeedback = document.getElementById('reg-display-name-feedback');

    // Pre-fill Step 1 if guest username is in Guest modal input
    const guestUsername = document.getElementById('guest-username');
    if (guestUsername) {
        regDisplayName.value = guestUsername.value;
    }

    // Display name availability check
    let nameCheckTimeout = null;
    regDisplayName.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        e.target.value = val.replace(/[^a-zA-Z0-9_]/g, '');

        clearTimeout(nameCheckTimeout);
        nameCheckTimeout = setTimeout(async () => {
            if (e.target.value.length < 3) return;
            try {
                const res = await fetch(`api/check-username?u=${encodeURIComponent(e.target.value)}`);
                const data = await res.json();
                if (data.available) {
                    regDisplayNameFeedback.className = 'input-feedback-icon valid';
                } else {
                    regDisplayNameFeedback.className = 'input-feedback-icon invalid';
                }
            } catch (err) {}
        }, 400);
    });

    regGenderContainer.addEventListener('click', (e) => {
        const chip = e.target.closest('.gender-chip');
        if (!chip) return;
        regGenderContainer.querySelectorAll('.gender-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
        regGenderHidden.value = chip.getAttribute('data-gender');
    });

    regAge.addEventListener('input', () => {
        if (parseInt(regAge.value, 10) < 13) {
            regAgeError.style.display = 'block';
        } else {
            regAgeError.style.display = 'none';
        }
    });

    document.getElementById('btn-reg-next-1').addEventListener('click', () => {
        const name = regDisplayName.value.trim();
        const age = parseInt(regAge.value, 10);
        const gender = regGenderHidden.value;

        if (name.length < 3) {
            alert('Display Name must be at least 3 characters.');
            return;
        }
        if (isNaN(age) || age < 13 || age > 99) {
            alert('Age must be between 13 and 99.');
            return;
        }
        if (!gender) {
            alert('Please select a gender chip.');
            return;
        }

        goToStep(2);
    });

    // Step 2: Account Details
    const regEmail = document.getElementById('reg-email');
    const regPassword = document.getElementById('reg-password');
    const regConfirmPassword = document.getElementById('reg-confirm-password');
    
    const emailError = document.getElementById('reg-email-error');
    const pwMeter = document.getElementById('pw-strength-meter');
    const pwLabel = document.getElementById('pw-strength-text');
    const pwMismatch = document.getElementById('reg-password-mismatch');

    // Password strength check
    regPassword.addEventListener('input', () => {
        const pw = regPassword.value;
        let score = 0;
        
        if (pw.length >= 8) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;

        pwMeter.className = 'pw-strength-fill';
        if (pw === '') {
            pwLabel.textContent = 'Weak';
        } else if (score === 1) {
            pwMeter.classList.add('weak');
            pwLabel.textContent = 'Weak';
            pwLabel.style.color = 'var(--accent-red)';
        } else if (score === 2) {
            pwMeter.classList.add('fair');
            pwLabel.textContent = 'Fair';
            pwLabel.style.color = 'var(--accent-yellow)';
        } else if (score === 3) {
            pwMeter.classList.add('strong');
            pwLabel.textContent = 'Strong';
            pwLabel.style.color = 'var(--accent-green)';
        }
    });

    regConfirmPassword.addEventListener('input', () => {
        if (regPassword.value !== regConfirmPassword.value) {
            pwMismatch.style.display = 'block';
        } else {
            pwMismatch.style.display = 'none';
        }
    });

    document.getElementById('btn-reg-back-1').addEventListener('click', () => {
        goToStep(1);
    });

    // Step 2 Submission & OTP Send
    document.getElementById('btn-reg-next-2').addEventListener('click', async () => {
        const email = regEmail.value.trim();
        const pwd = regPassword.value;
        const confirm = regConfirmPassword.value;

        if (!email.includes('@') || email.length < 5) {
            emailError.style.display = 'block';
            return;
        } else {
            emailError.style.display = 'none';
        }

        if (pwd.length < 8 || !/[0-9]/.test(pwd) || !/[^a-zA-Z0-9]/.test(pwd)) {
            alert('Password must be at least 8 characters long, and contain 1 number and 1 special character.');
            return;
        }

        if (pwd !== confirm) {
            pwMismatch.style.display = 'block';
            return;
        }

        const nextBtn = document.getElementById('btn-reg-next-2');
        nextBtn.disabled = true;
        nextBtn.textContent = 'Saving draft...';

        const formData = new FormData();
        formData.append('display_name', regDisplayName.value.trim());
        formData.append('age', regAge.value.toString());
        formData.append('gender', regGenderHidden.value);
        formData.append('email', email);
        formData.append('password', pwd);
        formData.append('confirm_password', confirm);

        try {
            // Validate & save session draft
            const draftRes = await fetch('auth/register', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const draftData = await draftRes.json();
            if (!draftRes.ok) {
                alert(draftData.error || 'Registration step 2 failed.');
                nextBtn.disabled = false;
                nextBtn.textContent = 'Next Step';
                return;
            }

            // Dispatch OTP
            const otpRes = await fetch('auth/send-verification', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const otpData = await otpRes.json();
            if (otpRes.ok && otpData.success) {
                goToStep(3);
                startOtpTimer();
            } else {
                alert(otpData.error || 'Failed to dispatch verification email.');
            }
        } catch (err) {
            alert('Network error encountered.');
        } finally {
            nextBtn.disabled = false;
            nextBtn.textContent = 'Next Step';
        }
    });

    // Step 3: Verification (OTP Boxes)
    const otpBoxes = document.querySelectorAll('.otp-box');
    const otpContainer = document.getElementById('otp-inputs-container');
    const otpError = document.getElementById('otp-error-msg');
    const otpTimerEl = document.getElementById('otp-timer');
    const otpTimerWrapper = document.getElementById('otp-timer-wrapper');
    const btnResendOtp = document.getElementById('btn-resend-otp');

    // Auto focus transitions
    otpBoxes.forEach((box, idx) => {
        box.addEventListener('input', (e) => {
            // Ensure only number
            box.value = box.value.replace(/[^0-9]/g, '');

            if (box.value !== '' && idx < otpBoxes.length - 1) {
                otpBoxes[idx + 1].focus();
            }
        });

        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && box.value === '' && idx > 0) {
                otpBoxes[idx - 1].focus();
            }
        });
    });

    // OTP Paste Distribution support
    otpContainer.addEventListener('paste', (e) => {
        e.preventDefault();
        const clipboard = (e.clipboardData || window.clipboardData).getData('text').trim();
        if (/^[0-9]{6}$/.test(clipboard)) {
            otpBoxes.forEach((box, idx) => {
                box.value = clipboard[idx];
            });
            otpBoxes[5].focus();
        }
    });

    // Resend countdown timer
    let otpTimerInterval = null;
    function startOtpTimer() {
        clearInterval(otpTimerInterval);
        btnResendOtp.style.display = 'none';
        otpTimerWrapper.style.display = 'inline';
        
        let seconds = 60;
        otpTimerEl.textContent = seconds.toString();
        
        otpTimerInterval = setInterval(() => {
            seconds--;
            otpTimerEl.textContent = seconds.toString();
            if (seconds <= 0) {
                clearInterval(otpTimerInterval);
                otpTimerWrapper.style.display = 'none';
                btnResendOtp.style.display = 'inline';
            }
        }, 1000);
    }

    btnResendOtp.addEventListener('click', async () => {
        btnResendOtp.disabled = true;
        btnResendOtp.textContent = 'Resending...';

        try {
            const res = await fetch('auth/send-verification', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                startOtpTimer();
            } else {
                alert(data.error || 'Resend failed.');
            }
        } catch (err) {
            alert('Network failure.');
        } finally {
            btnResendOtp.disabled = false;
            btnResendOtp.textContent = 'Resend code';
        }
    });

    document.getElementById('btn-reg-back-2').addEventListener('click', () => {
        goToStep(2);
    });

    // Submit OTP verification
    document.getElementById('btn-reg-submit-otp').addEventListener('click', async () => {
        let code = '';
        otpBoxes.forEach(box => code += box.value);

        if (code.length !== 6) {
            alert('Please enter all 6 verification digits.');
            return;
        }

        const verifyBtn = document.getElementById('btn-reg-submit-otp');
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying...';

        const formData = new FormData();
        formData.append('code', code);

        try {
            const res = await fetch('auth/verify-otp', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (res.ok && data.success) {
                otpError.style.display = 'none';
                goToStep(4);
            } else {
                otpError.style.display = 'block';
                // Clean input boxes
                otpBoxes.forEach(box => box.value = '');
                otpBoxes[0].focus();
            }
        } catch (err) {
            alert('OTP Verification network failure.');
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify OTP';
        }
    });

    // Step 4: Profile Customization
    const bioText = document.getElementById('reg-bio');
    const bioCounter = document.getElementById('reg-bio-counter');
    const avatarInput = document.getElementById('reg-avatar-file');
    const avatarPreview = document.getElementById('avatar-upload-preview-circle');

    bioText.addEventListener('input', () => {
        const remaining = 160 - bioText.value.length;
        bioCounter.textContent = `${remaining} remaining`;
    });

    // Image Preview and validation
    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('File size exceeds 2MB limit.');
            avatarInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.style.backgroundImage = `url('${e.target.result}')`;
            avatarPreview.querySelector('span').style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    // Save final registration data (skip or submit)
    const completeRegistration = async (skip = false) => {
        const completeBtn = document.getElementById('btn-complete-registration');
        completeBtn.disabled = true;
        completeBtn.textContent = 'Completing...';

        const formData = new FormData();
        if (!skip) {
            formData.append('bio', bioText.value.trim());
            formData.append('interests', document.getElementById('reg-interests').value.trim());
            if (avatarInput.files[0]) {
                formData.append('avatar', avatarInput.files[0]);
            }
        }

        try {
            const res = await fetch('auth/complete-profile', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (res.ok && data.success) {
                // Transition to success card
                registerCard.classList.remove('active');
                successCard.classList.add('active');
                
                // Direct redirection
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 2000);
            } else {
                alert(data.error || 'Failed to complete registration setup.');
                completeBtn.disabled = false;
                completeBtn.textContent = 'Finish Setup';
            }
        } catch (err) {
            alert('Network failure occurred.');
            completeBtn.disabled = false;
            completeBtn.textContent = 'Finish Setup';
        }
    };

    document.getElementById('btn-complete-registration').addEventListener('click', () => completeRegistration(false));
    document.getElementById('btn-skip-profile').addEventListener('click', () => completeRegistration(true));

    // Handle Login Card submission
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('login-email').value.trim();
        const pwd = document.getElementById('login-password').value;
        const remember = document.getElementById('login-remember').checked;

        const btn = document.getElementById('btn-submit-login');
        btn.disabled = true;
        btn.innerHTML = '<span>Signing In...</span>';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', pwd);
        if (remember) {
            formData.append('remember', '1');
        }

        try {
            const res = await fetch('auth/login', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (res.ok && data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Login verification failed.');
                btn.disabled = false;
                btn.innerHTML = '<span>Sign In</span>';
            }
        } catch (err) {
            alert('A network error occurred.');
            btn.disabled = false;
            btn.innerHTML = '<span>Sign In</span>';
        }
    });

    document.getElementById('btn-login-forgot').addEventListener('click', () => {
        alert('Password recovery is disabled in this preview environment.');
    });
});
