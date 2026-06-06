<?php
declare(strict_types=1);
?>
<!-- Full-Viewport Auth Overlay Gate -->
<div class="auth-gate-overlay" id="auth-gate-overlay">
    <div class="auth-card-container">
        
        <!-- CARD 1: GUEST ONBOARDING MODAL -->
        <div class="auth-card active" id="guest-onboarding-card">
            <div class="auth-card-header">
                <h2>Welcome to Chat<span style="color: var(--accent-green);">Arena</span></h2>
                <p>Instantly browse, talk to strangers, or make new friends.</p>
            </div>
            
            <form id="guest-onboarding-form" onsubmit="return false;">
                <div class="input-group">
                    <label class="input-label" for="guest-username">Username</label>
                    <div style="position: relative;">
                        <input class="text-input" type="text" id="guest-username" placeholder="e.g. wanderer" minlength="3" maxlength="20" required autocomplete="off">
                        <span class="input-feedback-icon" id="guest-username-feedback"></span>
                    </div>
                    <span class="input-hint">Alphanumeric & underscores only. 3–20 chars.</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="guest-age">Age</label>
                    <input class="text-input" type="number" id="guest-age" min="1" max="150" required placeholder="Your age">
                    <span class="error-text" id="guest-age-error" style="display: none; color: var(--accent-red); font-size: 0.8rem; margin-top: 0.25rem;">You must be 13 or older.</span>
                </div>

                <div class="input-group">
                    <label class="input-label">Gender</label>
                    <div class="gender-chips-wrapper" id="guest-gender-container">
                        <button type="button" class="gender-chip" data-gender="F">♀ Female</button>
                        <button type="button" class="gender-chip" data-gender="M">♂ Male</button>
                        <button type="button" class="gender-chip" data-gender="O">⊘ Other</button>
                    </div>
                    <input type="hidden" id="guest-gender" name="gender" required>
                </div>

                <div class="auth-card-actions" style="margin-top: 1.5rem;">
                    <button class="btn btn-primary btn-pulse btn-full" id="btn-submit-guest" type="submit">
                        <span>Start as Guest</span>
                    </button>
                    
                    <div class="divider"><span>OR</span></div>
                    
                    <button class="btn btn-secondary btn-full" id="btn-switch-to-register" type="button">
                        <span>Get Started — Create Account</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- CARD 2: REGISTER FLOW (4 STEPS) -->
        <div class="auth-card" id="register-flow-card">
            <!-- Progress Bar -->
            <div class="register-progress-container">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="register-progress-bar" style="width: 25%;"></div>
                </div>
                <div class="progress-steps">
                    <span class="step-indicator active" data-step="1">1</span>
                    <span class="step-indicator" data-step="2">2</span>
                    <span class="step-indicator" data-step="3">3</span>
                    <span class="step-indicator" data-step="4">4</span>
                </div>
            </div>

            <!-- STEP 1: Basic Info -->
            <div class="register-step-pane active" id="register-step-1">
                <div class="auth-card-header">
                    <h3>Step 1: Basic Profile</h3>
                    <p>Setup your display identity.</p>
                </div>
                
                <div class="input-group">
                    <label class="input-label" for="reg-display-name">Display Name</label>
                    <div style="position: relative;">
                        <input class="text-input" type="text" id="reg-display-name" placeholder="Preferred display name" minlength="3" maxlength="20" autocomplete="off">
                        <span class="input-feedback-icon" id="reg-display-name-feedback"></span>
                    </div>
                    <span class="input-hint">3–20 characters. Alphanumeric & underscores.</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-age">Age</label>
                    <input class="text-input" type="number" id="reg-age" placeholder="Age" min="1">
                    <span class="error-text" id="reg-age-error" style="display: none; color: var(--accent-red); font-size: 0.8rem; margin-top: 0.25rem;">You must be 13 or older.</span>
                </div>

                <div class="input-group">
                    <label class="input-label">Gender</label>
                    <div class="gender-chips-wrapper" id="reg-gender-container">
                        <button type="button" class="gender-chip" data-gender="F">♀ Female</button>
                        <button type="button" class="gender-chip" data-gender="M">♂ Male</button>
                        <button type="button" class="gender-chip" data-gender="O">⊘ Other</button>
                    </div>
                    <input type="hidden" id="reg-gender" required>
                </div>

                <div class="step-actions">
                    <button class="btn btn-secondary" id="btn-reg-back-to-guest" type="button">Back</button>
                    <button class="btn btn-primary" id="btn-reg-next-1" type="button">Next Step</button>
                </div>
            </div>

            <!-- STEP 2: Account Details -->
            <div class="register-step-pane" id="register-step-2">
                <div class="auth-card-header">
                    <h3>Step 2: Account Details</h3>
                    <p>Securing your access details.</p>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-email">Email Address</label>
                    <input class="text-input" type="email" id="reg-email" placeholder="e.g. name@domain.com" autocomplete="email">
                    <span class="error-text" id="reg-email-error" style="display: none; color: var(--accent-red); font-size: 0.8rem; margin-top: 0.25rem;">Invalid or already registered email.</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-password">Password</label>
                    <input class="text-input" type="password" id="reg-password" placeholder="Min 8 characters" autocomplete="new-password">
                    
                    <!-- Password Strength Meter -->
                    <div class="pw-strength-container" style="margin-top: 0.5rem;">
                        <div class="pw-strength-track">
                            <div class="pw-strength-fill" id="pw-strength-meter"></div>
                        </div>
                        <span class="pw-strength-label" id="pw-strength-text">Weak</span>
                    </div>
                    <span class="input-hint">Must contain at least 1 number and 1 special character.</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-confirm-password">Confirm Password</label>
                    <input class="text-input" type="password" id="reg-confirm-password" placeholder="Confirm password" autocomplete="new-password">
                    <span class="error-text" id="reg-password-mismatch" style="display: none; color: var(--accent-red); font-size: 0.8rem; margin-top: 0.25rem;">Passwords do not match.</span>
                </div>

                <div class="step-actions">
                    <button class="btn btn-secondary" id="btn-reg-back-1" type="button">Back</button>
                    <button class="btn btn-primary" id="btn-reg-next-2" type="button">Next Step</button>
                </div>
            </div>

            <!-- STEP 3: Email Verification -->
            <div class="register-step-pane" id="register-step-3">
                <div class="auth-card-header">
                    <h3>Step 3: Verification</h3>
                    <p>Enter the 6-digit OTP code sent to your email.</p>
                </div>

                <div class="otp-inputs-wrapper" id="otp-inputs-container">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                </div>
                <div id="otp-error-msg" style="color: var(--accent-red); font-size: 0.85rem; text-align: center; margin-top: 0.5rem; display: none;">Invalid verification code. Try again.</div>

                <div style="text-align: center; margin-top: 1.25rem;">
                    <span style="color: var(--text-secondary); font-size: 0.85rem;" id="otp-timer-wrapper">
                        Resend code in <span id="otp-timer">60</span>s
                    </span>
                    <button type="button" class="btn-link" id="btn-resend-otp" style="display: none; color: var(--accent-blue); background: none; border: none; font-size: 0.85rem; cursor: pointer; text-decoration: underline;">Resend code</button>
                </div>

                <div class="step-actions" style="margin-top: 1.5rem;">
                    <button class="btn btn-secondary" id="btn-reg-back-2" type="button">Back</button>
                    <button class="btn btn-primary" id="btn-reg-submit-otp" type="button">Verify OTP</button>
                </div>
            </div>

            <!-- STEP 4: Profile Setup -->
            <div class="register-step-pane" id="register-step-4">
                <div class="auth-card-header">
                    <h3>Step 4: Profile Customization</h3>
                    <p>Tell us a bit about yourself (optional).</p>
                </div>

                <div class="input-group" style="display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem;">
                    <div class="avatar-upload-preview" id="avatar-upload-preview-circle">
                        <span>+</span>
                    </div>
                    <label class="btn btn-secondary btn-small" for="reg-avatar-file" style="margin-top: 0.5rem; cursor: pointer;">
                        Choose Profile Photo
                    </label>
                    <input type="file" id="reg-avatar-file" accept="image/jpeg,image/png,image/webp" style="display: none;">
                    <span class="input-hint">Max 2MB. JPEG, PNG, WebP only.</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-bio">Bio (160 characters max)</label>
                    <textarea class="text-input" id="reg-bio" maxlength="160" rows="3" placeholder="Tell other users a bit about yourself..."></textarea>
                    <span class="input-hint" style="text-align: right;" id="reg-bio-counter">160 remaining</span>
                </div>

                <div class="input-group">
                    <label class="input-label" for="reg-interests">Interests / Tags</label>
                    <input class="text-input" type="text" id="reg-interests" placeholder="e.g. music, coding, gaming (max 5 tags, comma separated)">
                </div>

                <div class="step-actions" style="margin-top: 1.5rem;">
                    <button class="btn-link" id="btn-skip-profile" type="button" style="color: var(--text-secondary); cursor: pointer;">Skip for now</button>
                    <button class="btn btn-primary" id="btn-complete-registration" type="button">Finish Setup</button>
                </div>
            </div>
        </div>

        <!-- SUCCESS MODAL (Step 4 Complete state) -->
        <div class="auth-card" id="success-flow-card" style="text-align: center; justify-content: center; align-items: center;">
            <div class="success-icon-circle">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h2 style="font-size: 1.75rem; margin-top: 1rem; color: var(--accent-green);">Registration Complete!</h2>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Setting up your workspace dashboard...</p>
        </div>

        <!-- LOGIN CARD (For switching/returning users) -->
        <div class="auth-card" id="login-card">
            <div class="auth-card-header">
                <h2>Login to Chat<span style="color: var(--accent-blue);">Arena</span></h2>
                <p>Welcome back! Please enter your details.</p>
            </div>

            <form id="login-form" onsubmit="return false;">
                <div class="input-group">
                    <label class="input-label" for="login-email">Email Address</label>
                    <input class="text-input" type="email" id="login-email" placeholder="e.g. name@domain.com" required autocomplete="email">
                </div>

                <div class="input-group">
                    <label class="input-label" for="login-password">Password</label>
                    <input class="text-input" type="password" id="login-password" placeholder="Enter password" required autocomplete="current-password">
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); cursor: pointer;">
                        <input type="checkbox" id="login-remember"> Remember Me
                    </label>
                    <button type="button" class="btn-link" id="btn-login-forgot" style="font-size: 0.85rem; color: var(--accent-blue); background: none; border: none; cursor: pointer;">Forgot Password?</button>
                </div>

                <div class="auth-card-actions">
                    <button class="btn btn-primary btn-full" id="btn-submit-login" type="submit">
                        <span>Sign In</span>
                    </button>
                    
                    <div class="divider"><span>OR</span></div>
                    
                    <button class="btn btn-secondary btn-full" id="btn-login-to-guest" type="button">
                        <span>Back to Guest Entrance</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Bottom Footer for Login switching -->
        <div class="auth-card-footer-switch" id="auth-footer-switch-view">
            <span>Already have an account? <button type="button" id="btn-switch-to-login" class="btn-link" style="color: var(--accent-blue); font-weight: 500; cursor: pointer; text-decoration: underline; background: none; border: none; font-size: 0.9rem;">Sign In</button></span>
        </div>
    </div>
</div>
