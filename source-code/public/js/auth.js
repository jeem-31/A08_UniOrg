let isAnimating = false;

// Detect mobile viewport (where panels are stacked instead of side-by-side)
function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

// Update mobile header branding background image and subtitle smoothly
function updateMobileBranding(mode) {
    const bgLogin = document.getElementById('mobileBrandBgLogin');
    const bgRegister = document.getElementById('mobileBrandBgRegister');
    const subtitle = document.getElementById('mobileBrandSubtitle');

    if (mode === 'register') {
        if (bgLogin) {
            bgLogin.classList.remove('opacity-100', 'scale-100');
            bgLogin.classList.add('opacity-0', 'pointer-events-none', 'scale-105');
        }
        if (bgRegister) {
            bgRegister.classList.remove('opacity-0', 'pointer-events-none', 'scale-105');
            bgRegister.classList.add('opacity-100', 'scale-100');
        }
        if (subtitle) {
            subtitle.style.opacity = '0';
            setTimeout(() => {
                subtitle.textContent = 'Student Registration';
                subtitle.style.opacity = '1';
            }, 150);
        }
        document.title = 'UniOrg - Register';
    } else {
        if (bgRegister) {
            bgRegister.classList.remove('opacity-100', 'scale-100');
            bgRegister.classList.add('opacity-0', 'pointer-events-none', 'scale-105');
        }
        if (bgLogin) {
            bgLogin.classList.remove('opacity-0', 'pointer-events-none', 'scale-105');
            bgLogin.classList.add('opacity-100', 'scale-100');
        }
        if (subtitle) {
            subtitle.style.opacity = '0';
            setTimeout(() => {
                subtitle.textContent = 'Laguna State Polytechnic University';
                subtitle.style.opacity = '1';
            }, 150);
        }
        document.title = 'UniOrg - Login';
    }
}

// Initialize based on mode
function initializeAuthPage(mode) {
    updateMobileBranding(mode);
    if (mode === 'register') {
        // Show register view immediately without animation
        document.getElementById('loginFormPanel').style.display = 'none';
        document.getElementById('loginBrandPanel').style.display = 'none';
        document.getElementById('registerFormPanel').classList.remove('hide-right');
        document.getElementById('registerBrandPanel').classList.remove('hide-left');
    }
}

// Clean up mobile animation helper classes
function clearMobileAnimClasses(el) {
    el.classList.remove('m-anim-out-left', 'm-anim-out-right', 'm-anim-in-right', 'm-anim-in-left');
}

// Switch to Register
function switchToRegister(e) {
    e.preventDefault();
    if (isAnimating) return;
    isAnimating = true;

    updateMobileBranding('register');

    const loginForm = document.getElementById('loginFormPanel');
    const registerForm = document.getElementById('registerFormPanel');

    // ----- MOBILE: sequential slide (out then in) -----
    if (isMobileViewport()) {
        clearMobileAnimClasses(loginForm);
        clearMobileAnimClasses(registerForm);

        // Keep brand panels in sync so a later resize to desktop stays consistent
        document.getElementById('registerBrandPanel').classList.remove('hide-left');
        document.getElementById('loginBrandPanel').classList.add('hide-right');

        // 1) Slide current login form out to the left
        loginForm.classList.add('m-anim-out-left');

        const onLoginOut = () => {
            loginForm.removeEventListener('animationend', onLoginOut);
            clearMobileAnimClasses(loginForm);
            loginForm.classList.add('hide-left'); // removes it from layout

            // 2) Bring register form in from the right
            registerForm.classList.remove('hide-right');
            registerForm.classList.add('m-anim-in-right');

            // Scroll to top so the new form is fully in view
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const onRegisterIn = () => {
                registerForm.removeEventListener('animationend', onRegisterIn);
                clearMobileAnimClasses(registerForm);
                isAnimating = false;
            };
            registerForm.addEventListener('animationend', onRegisterIn);
        };
        loginForm.addEventListener('animationend', onLoginOut);
        return;
    }

    // ----- DESKTOP: simultaneous cross-slide (original behavior) -----
    // Hide login form (left) to left
    loginForm.classList.add('hide-left');
    
    // Hide login brand (right) to right
    document.getElementById('loginBrandPanel').classList.add('hide-right');

    setTimeout(() => {
        // Show register brand (left) from left
        document.getElementById('registerBrandPanel').classList.remove('hide-left');
        
        // Show register form (right) from right
        registerForm.classList.remove('hide-right');
        
        setTimeout(() => {
            isAnimating = false;
        }, 800);
    }, 100);
}

// Switch to Login
function switchToLogin(e) {
    e.preventDefault();
    if (isAnimating) return;
    isAnimating = true;

    updateMobileBranding('login');

    const loginForm = document.getElementById('loginFormPanel');
    const registerForm = document.getElementById('registerFormPanel');

    // ----- MOBILE: sequential slide (out then in) -----
    if (isMobileViewport()) {
        clearMobileAnimClasses(loginForm);
        clearMobileAnimClasses(registerForm);

        // Keep brand panels in sync so a later resize to desktop stays consistent
        document.getElementById('registerBrandPanel').classList.add('hide-left');
        document.getElementById('loginBrandPanel').classList.remove('hide-right');

        // 1) Slide current register form out to the right
        registerForm.classList.add('m-anim-out-right');

        const onRegisterOut = () => {
            registerForm.removeEventListener('animationend', onRegisterOut);
            clearMobileAnimClasses(registerForm);
            registerForm.classList.add('hide-right'); // removes it from layout

            // 2) Bring login form in from the left
            loginForm.classList.remove('hide-left');
            loginForm.classList.add('m-anim-in-left');

            // Scroll to top so the new form is fully in view
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const onLoginIn = () => {
                loginForm.removeEventListener('animationend', onLoginIn);
                clearMobileAnimClasses(loginForm);
                isAnimating = false;
            };
            loginForm.addEventListener('animationend', onLoginIn);
        };
        registerForm.addEventListener('animationend', onRegisterOut);
        return;
    }

    // ----- DESKTOP: simultaneous cross-slide (original behavior) -----
    // Hide register form (right) to right
    registerForm.classList.add('hide-right');
    
    // Hide register brand (left) to left
    document.getElementById('registerBrandPanel').classList.add('hide-left');

    setTimeout(() => {
        // Show login form (left) from left
        loginForm.classList.remove('hide-left');
        
        // Show login brand (right) from right
        document.getElementById('loginBrandPanel').classList.remove('hide-right');
        
        setTimeout(() => {
            isAnimating = false;
        }, 800);
    }, 100);
}

// Course/Section logic
function updateSectionOptions() {
    const courseSelect = document.getElementById('course');
    const sectionSelect = document.getElementById('section');
    
    if (!courseSelect || !sectionSelect) return;
    
    const selectedCourse = courseSelect.value;
    const bsitOnlyOptions = sectionSelect.querySelectorAll('.bsit-only');
    
    if (selectedCourse === 'BSCS') {
        bsitOnlyOptions.forEach(option => {
            option.style.display = 'none';
            option.disabled = true;
        });
        
        if (['C', 'D', 'E'].includes(sectionSelect.value)) {
            sectionSelect.value = '';
        }
    } else {
        bsitOnlyOptions.forEach(option => {
            option.style.display = 'block';
            option.disabled = false;
        });
    }
}

// Form validation and AJAX submission
function validateRegisterForm(e) {
    e.preventDefault();
    
    // Skip the AJAX submission when the client-side system check already
    // blocked registration (prevents a duplicate toast/loading state)
    if (window.registrationBlocked) {
        window.registrationBlocked = false;
        return false;
    }

    // Block submission when the selected department does not match the course.
    // The server enforces the same rule, this is just for instant feedback.
    const courseField = document.getElementById('course');
    const departmentSelect = document.getElementById('department_id');
    const courseValue = courseField ? courseField.value : '';
    const expectedDepartmentId = getExpectedDepartmentId(courseValue);
    if (expectedDepartmentId !== null && departmentSelect && departmentSelect.value !== String(expectedDepartmentId)) {
        let mismatchMessage = 'Department does not match this course.';
        const option = departmentSelect.querySelector('option[value="' + expectedDepartmentId + '"]');
        if (option) {
            mismatchMessage += ' It belongs to ' + option.textContent.trim() + '.';
        }
        mismatchMessage += ' Please select the correct department.';
        showError('department_id', mismatchMessage);
        return false;
    }
    
    // Don't clear errors immediately - only clear when starting new submission
    const password = document.getElementById('password').value;
    const passwordConfirmation = document.getElementById('password_confirmation').value;
    
    let hasError = false;
    
    if (password.length < 8) {
        showError('password', 'Password must be at least 8 characters');
        hasError = true;
    }
    
    if (password !== passwordConfirmation) {
        showError('password_confirmation', 'Passwords do not match');
        hasError = true;
    }
    
    if (hasError) {
        return false;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('registerForm');
    const formData = new FormData(form);
    
    // Clear previous errors only when actually submitting
    document.querySelectorAll('#registerForm .error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('#registerForm input, #registerForm select').forEach(input => {
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
    });
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Creating Account...';
    
    // Submit via AJAX
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        if (response.status === 419) {
            // CSRF token expired (session rotated or app redeployed mid-form)
            showToast('Your session has expired. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph ph-user-plus mr-2 text-[#D7AC28]"></i> Create Account';
            setTimeout(() => window.location.reload(), 1500);
            return null;
        }
        return response.json();
    })
    .then(data => {
        if (data === null) return; // handled above (session expiry)
        if (data.success && data.needs_otp) {
            // Account is not created yet - show the email OTP verification step
            showToast(data.message || 'Verification code sent!', 'success');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph ph-user-plus mr-2 text-[#D7AC28]"></i> Create Account';
            showOtpStep(formData.get('email') || '');
            return;
        }
        if (data.success) {
            showToast(data.message || 'Registration successful!', 'success');
            setTimeout(() => {
                window.location.href = data.redirect || '/login';
            }, 1500);
        } else if (data.errors) {
            // Show validation errors - only show first error to avoid multiple toasts
            let firstError = true;
            for (const [field, messages] of Object.entries(data.errors)) {
                const errorMessage = Array.isArray(messages) ? messages[0] : messages;
                const fieldElement = document.getElementById(field);
                if (fieldElement) {
                    // Remove all border classes first
                    fieldElement.classList.remove('border-gray-300', 'border-green-500', 'border-red-500');
                    // Add error classes
                    fieldElement.classList.add('error', 'border-red-500');
                    // Force the style
                    fieldElement.style.borderColor = '#ef4444';
                    
                    if (firstError) {
                        fieldElement.focus();
                        showToast(errorMessage, 'error');
                        firstError = false;
                    }
                }
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph ph-user-plus mr-2 text-[#D7AC28]"></i> Create Account';
        }
    })
    .catch(error => {
        console.error('Registration error:', error);
        showToast('An error occurred. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ph ph-user-plus mr-2 text-[#D7AC28]"></i> Create Account';
    });
}

// ============================================================
// OTP EMAIL VERIFICATION (Registration Step 3)
// ============================================================

let resendCountdownTimer = null;

function showOtpStep(email) {
    const otpStep = document.getElementById('otpStep');
    const registerForm = document.getElementById('registerForm');
    if (!otpStep || !registerForm) return;

    const emailDisplay = document.getElementById('otpEmailDisplay');
    if (emailDisplay && email) {
        emailDisplay.textContent = email;
    }

    registerForm.classList.add('hidden');
    otpStep.classList.remove('hidden');

    // Update the step indicator: steps 1 & 2 done, step 3 active
    const progressBar = document.getElementById('progressBar');
    const step1Label = document.getElementById('step1Label');
    const step2Label = document.getElementById('step2Label');
    const step3Label = document.getElementById('step3Label');

    if (progressBar) progressBar.style.width = '100%';
    if (step1Label) {
        step1Label.classList.remove('text-[#26488C]', 'font-semibold');
        step1Label.classList.add('text-gray-500');
    }
    if (step2Label) {
        step2Label.classList.remove('text-[#26488C]', 'font-semibold');
        step2Label.classList.add('text-gray-500');
    }
    if (step3Label) {
        step3Label.classList.remove('text-gray-500');
        step3Label.classList.add('text-[#26488C]', 'font-semibold');
    }

    clearOtpDigits();
    hideOtpError();
    startResendCountdown(60);

    const firstDigit = document.getElementById('otpDigit1');
    if (firstDigit) {
        firstDigit.focus();
    }
}

function goBackToInfoStep() {
    const otpStep = document.getElementById('otpStep');
    const registerForm = document.getElementById('registerForm');
    if (!otpStep || !registerForm) return;

    otpStep.classList.add('hidden');
    registerForm.classList.remove('hidden');

    // Step indicator back to step 2
    const progressBar = document.getElementById('progressBar');
    const step2Label = document.getElementById('step2Label');
    const step3Label = document.getElementById('step3Label');

    if (progressBar) progressBar.style.width = '66%';
    if (step2Label) {
        step2Label.classList.remove('text-gray-500');
        step2Label.classList.add('text-[#26488C]', 'font-semibold');
    }
    if (step3Label) {
        step3Label.classList.remove('text-[#26488C]', 'font-semibold');
        step3Label.classList.add('text-gray-500');
    }

    if (resendCountdownTimer) {
        clearInterval(resendCountdownTimer);
        resendCountdownTimer = null;
    }
    const countdown = document.getElementById('resendCountdown');
    const resendBtn = document.getElementById('resendOtpBtn');
    if (countdown) countdown.textContent = '';
    if (resendBtn) resendBtn.disabled = false;

    hideOtpError();
}

function setupOtpDigitBehavior() {
    const digits = document.querySelectorAll('.otp-digit');
    if (!digits.length) return;

    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            const value = input.value.replace(/\D/g, '');
            input.value = value;
            if (value && index < digits.length - 1) {
                digits[index + 1].focus();
            }
            if (value.length === digits.length) {
                verifyOtpCode();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && index > 0) {
                e.preventDefault();
                digits[index - 1].focus();
                digits[index - 1].value = '';
            }
            if (e.key === 'ArrowLeft' && index > 0) {
                e.preventDefault();
                digits[index - 1].focus();
            }
            if (e.key === 'ArrowRight' && index < digits.length - 1) {
                e.preventDefault();
                digits[index + 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, digits.length);
            pasted.split('').forEach((char, i) => {
                digits[i].value = char;
            });
            const lastIndex = Math.min(pasted.length, digits.length) - 1;
            if (lastIndex >= 0) {
                digits[lastIndex].focus();
            }
            if (pasted.length === digits.length) {
                verifyOtpCode();
            }
        });

        input.addEventListener('focus', () => input.select());
    });
}

function getOtpCode() {
    return Array.from(document.querySelectorAll('.otp-digit'))
        .map(d => d.value)
        .join('');
}

function clearOtpDigits() {
    document.querySelectorAll('.otp-digit').forEach(d => { d.value = ''; });
}

function showOtpError(message) {
    const errorEl = document.getElementById('otpError');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
    document.querySelectorAll('.otp-digit').forEach(d => {
        d.classList.add('border-red-500');
        d.classList.remove('border-gray-300');
    });
}

function hideOtpError() {
    const errorEl = document.getElementById('otpError');
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
    document.querySelectorAll('.otp-digit').forEach(d => {
        d.classList.remove('border-red-500');
        d.classList.add('border-gray-300');
    });
}

function verifyOtpCode() {
    const code = getOtpCode();
    if (code.length !== 6) {
        showOtpError('Please enter the complete 6-digit code.');
        return;
    }

    const btn = document.getElementById('verifyOtpBtn');
    const url = btn ? btn.dataset.url : '';
    const email = document.getElementById('otpEmailDisplay') ? document.getElementById('otpEmailDisplay').textContent.trim() : '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Verifying...';
    }

    hideOtpError();

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email: email, otp: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Email verified! Registration complete.', 'success');
            if (btn) {
                btn.innerHTML = '<i class="ph ph-check-circle mr-2 text-[#C4973B]"></i> Verified';
            }
            setTimeout(() => {
                window.location.href = data.redirect || '/org/login';
            }, 1800);
        } else {
            let message = 'Verification failed. Please try again.';
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                if (Array.isArray(first) && first.length) {
                    message = first[0];
                } else if (typeof first === 'string') {
                    message = first;
                }
            }
            showOtpError(message);
            clearOtpDigits();
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-check-circle mr-2 text-[#C4973B]"></i> Verify Code';
            }
            const firstDigit = document.getElementById('otpDigit1');
            if (firstDigit) firstDigit.focus();
        }
    })
    .catch(error => {
        console.error('OTP verification error:', error);
        showToast('An error occurred. Please try again.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-check-circle mr-2 text-[#C4973B]"></i> Verify Code';
        }
    });
}

function startResendCountdown(seconds) {
    const btn = document.getElementById('resendOtpBtn');
    const countdown = document.getElementById('resendCountdown');
    if (!btn) return;

    if (resendCountdownTimer) {
        clearInterval(resendCountdownTimer);
    }

    btn.disabled = true;
    let remaining = seconds;

    if (countdown) countdown.textContent = `(${remaining}s)`;

    resendCountdownTimer = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(resendCountdownTimer);
            resendCountdownTimer = null;
            btn.disabled = false;
            if (countdown) countdown.textContent = '';
        } else if (countdown) {
            countdown.textContent = `(${remaining}s)`;
        }
    }, 1000);
}

function resendOtpCode() {
    const btn = document.getElementById('resendOtpBtn');
    const url = btn ? btn.dataset.url : '';
    const email = document.getElementById('otpEmailDisplay') ? document.getElementById('otpEmailDisplay').textContent.trim() : '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]');

    if (!btn) return;
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Sending...';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'A new code has been sent to your email.', 'success');
            clearOtpDigits();
            hideOtpError();
            startResendCountdown(60);
        } else {
            showToast(data.error || data.message || 'Unable to resend the code. Please try again later.', 'error');
            startResendCountdown(30);
        }
        btn.innerHTML = original;
        btn.disabled = true;
    })
    .catch(error => {
        console.error('Resend OTP error:', error);
        showToast('An error occurred. Please try again.', 'error');
        btn.innerHTML = original;
        btn.disabled = false;
        startResendCountdown(30);
    });
}

// ============================================================
// COURSE → DEPARTMENT MATCHING
// ============================================================

// Uses the same keyword map the server enforces (window.courseDepartmentMap,
// injected from OrgAuthController::courseDepartmentMap()).
function getExpectedDepartmentId(course) {
    if (!course) return null;
    const c = String(course).toLowerCase();
    const map = window.courseDepartmentMap || {};
    for (const deptId of Object.keys(map)) {
        const keywords = map[deptId] || [];
        for (const kw of keywords) {
            if (c.includes(kw)) {
                return parseInt(deptId, 10);
            }
        }
    }
    return null;
}

function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (field) {
        // Remove all border classes first
        field.classList.remove('border-gray-300', 'border-green-500', 'border-red-500');
        // Add error classes
        field.classList.add('error', 'border-red-500');
        // Force the style
        field.style.borderColor = '#ef4444';
        field.focus();
    }
    showToast(message, 'error');
}

function showToast(message, type = 'error') {
    // Remove any existing toasts first
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.setAttribute('x-data', '{ show: true }');
    toast.setAttribute('x-show', 'show');
    toast.setAttribute('x-transition:enter', 'transition ease-out duration-300');
    toast.setAttribute('x-transition:enter-start', 'opacity-0 transform translate-x-8');
    toast.setAttribute('x-transition:enter-end', 'opacity-100 transform translate-x-0');
    toast.setAttribute('x-transition:leave', 'transition ease-in duration-200');
    toast.setAttribute('x-transition:leave-start', 'opacity-100 transform translate-x-0');
    toast.setAttribute('x-transition:leave-end', 'opacity-0 transform translate-x-8');
    toast.setAttribute('x-init', 'setTimeout(() => show = false, 4000)');
    
    // Set styling based on type
    const borderClass = type === 'error' ? 'border-red-500' : 'border-green-500';
    const iconClass = type === 'error' ? 'ph-warning-circle text-red-600' : 'ph-check-circle text-green-600';
    const iconBgClass = type === 'error' ? 'bg-red-100' : 'bg-green-100';
    const title = type === 'error' ? 'Error!' : 'Success!';
    
    // Keep the toast-notification class so its z-index stays above open modals.
    toast.className = `toast-notification fixed top-6 right-6 bg-white border-l-4 ${borderClass} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md`;
    toast.style.display = 'none';
    
    toast.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full ${iconBgClass} flex items-center justify-center">
                <i class="ph ${iconClass} text-xl"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">${title}</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Show the toast with animation
    setTimeout(() => {
        toast.style.display = 'flex';
        toast.style.animation = 'slideInRight 0.3s ease-out';
    }, 100);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease-out reverse';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, 4000);
}

// Login form validation and AJAX submission
function validateLoginForm(e) {
    e.preventDefault();

    if (window.UniOrgTurnstile && !window.UniOrgTurnstile.requireToken('loginForm')) return;
    
    const form = document.getElementById('loginForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Clear previous errors
    document.querySelectorAll('#loginForm input').forEach(input => {
        input.classList.remove('border-red-500', 'error');
    });
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Logging in...';
    
    // Submit via AJAX
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const welcomeMessage = data.user_name ? `Welcome, ${data.user_name}!` : (data.message || 'Login successful!');
            showToast(welcomeMessage, 'success');
            setTimeout(() => {
                window.location.href = data.redirect || '/dashboard';
            }, 1500);
        } else if (data.errors) {
            // Show validation errors
            let firstError = true;
            for (const [field, messages] of Object.entries(data.errors)) {
                const errorMessage = Array.isArray(messages) ? messages[0] : messages;
                const fieldElement = document.getElementById('login_' + field) || document.getElementById(field);
                if (fieldElement) {
                    fieldElement.classList.add('error');
                    fieldElement.classList.add('border-red-500');
                    fieldElement.classList.remove('border-gray-300');
                    if (firstError) {
                        fieldElement.focus();
                        showToast(errorMessage, 'error');
                        firstError = false;
                    }
                }
            }
            if (firstError) showToast(data.message || Object.values(data.errors)[0]?.[0] || 'Login failed.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        } else {
            showToast(data.message || 'Login failed. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        console.error('Login error:', error);
        showToast('An error occurred. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    })
    .finally(() => window.UniOrgTurnstile?.reset('loginForm'));
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get current mode from data attribute
    const authWrapper = document.querySelector('.auth-wrapper');
    const currentMode = authWrapper ? authWrapper.dataset.mode : 'login';
    
    // Initialize page
    initializeAuthPage(currentMode);
    
    // Attach event listeners
    const switchToRegisterBtn = document.getElementById('switchToRegister');
    const switchToLoginBtn = document.getElementById('switchToLogin');
    const courseSelect = document.getElementById('course');
    const registerFormElem = document.getElementById('registerForm');
    const loginFormElem = document.getElementById('loginForm');
    
    if (switchToRegisterBtn) {
        switchToRegisterBtn.addEventListener('click', switchToRegister);
    }
    
    if (switchToLoginBtn) {
        switchToLoginBtn.addEventListener('click', switchToLogin);
    }
    
    if (courseSelect) {
        courseSelect.addEventListener('change', updateSectionOptions);
    }
    
    if (registerFormElem) {
        registerFormElem.addEventListener('submit', validateRegisterForm);
    }
    
    if (loginFormElem) {
        loginFormElem.addEventListener('submit', validateLoginForm);
    }

    // OTP email verification (registration step 3)
    setupOtpDigitBehavior();
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    const otpBackBtn = document.getElementById('otpBackBtn');

    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener('click', verifyOtpCode);
    }
    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', resendOtpCode);
    }
    if (otpBackBtn) {
        otpBackBtn.addEventListener('click', goBackToInfoStep);
    }

    // Keep panel state clean when the viewport crosses the mobile/desktop breakpoint.
    // This prevents stale animation classes/inline transforms from breaking the layout
    // when a user switches forms on mobile and then resizes to desktop (or vice-versa).
    let wasMobile = isMobileViewport();
    window.addEventListener('resize', function() {
        const nowMobile = isMobileViewport();
        if (nowMobile === wasMobile) return;
        wasMobile = nowMobile;

        const loginForm = document.getElementById('loginFormPanel');
        const registerForm = document.getElementById('registerFormPanel');
        if (!loginForm || !registerForm) return;

        // Clear any in-progress mobile animation helper classes
        clearMobileAnimClasses(loginForm);
        clearMobileAnimClasses(registerForm);
        isAnimating = false;
    });
});


// Prevent back button after logout
(function() {
    if (window.history && window.history.pushState) {
        // Replace current history state
        window.history.pushState(null, null, window.location.href);
        
        // Listen for back button
        window.addEventListener('popstate', function() {
            // Push state again to prevent going back
            window.history.pushState(null, null, window.location.href);
        });
    }
    
    // Also prevent caching
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Page was loaded from cache (back button)
            window.location.reload();
        }
    });
})();
