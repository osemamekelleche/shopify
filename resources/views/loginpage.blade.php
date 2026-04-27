<html>

<head>
    <title>Login</title>
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
</head>

<body>
    <p>
        <label>Password: </label>
        <input type="password" name="password" id="password" placeholder="type the password" autofocus />
        <input type="button" id="login" name="login" value="Login" />
    </p>

    <script>
        const loginUrl = @js($loginUrl);
        const redirectUrl = @js($redirectUrl);
        /** @type HTMLButtonElement */
        const loginBtn = document.getElementById('login');
        /** @type HTMLInputElement */
        const passwordInput = document.getElementById('password');

        const getXsrfToken = () => {
            let _csrfToken = '';
            const start = 'XSRF-TOKEN=';

            const cookies = document.cookie.split(';');

            for (let cookie of cookies) {
                if (cookie.startsWith(start)) {
                    _csrfToken = decodeURIComponent(cookie.replace(start, ''));
                    break;
                }
            }
            return _csrfToken;
        }



        const toggleDisabledAndText = (disabled = false, text = "Login") => {
            passwordInput.disabled = disabled;
            loginBtn.disabled = disabled;
            loginBtn.value = text;
            if (!disabled) {
                passwordInput.focus();
            }
        }

        async function digestMessage(message) {
            const msgUint8 = new TextEncoder().encode(message); // encode as (utf-8) Uint8Array
            const hashBuffer = await window.crypto.subtle.digest("SHA-256", msgUint8); // hash the message
            if (Uint8Array.prototype.toHex) {
                // Use toHex if supported.
                return new Uint8Array(hashBuffer).toHex(); // Convert ArrayBuffer to hex string.
            }
            // If toHex() is not supported, fall back to an alternative implementation.
            const hashArray = Array.from(new Uint8Array(hashBuffer)); // convert buffer to byte array
            return hashArray
                .map((b) => b.toString(16).padStart(2, "0"))
                .join(""); // convert bytes to hex string
        }



        const handleLogin = async () => {
            if (passwordInput.value === '') {
                passwordInput.focus();
                alert('You must enter a password');
                return;
            }
            const password = await digestMessage(passwordInput.value);

            toggleDisabledAndText(true, "Authenticating...");

            try {
                // 1. Wait for reCAPTCHA to be ready
                await new Promise((resolve) => grecaptcha.ready(resolve));

                // 2. Get the token using await
                const token = await grecaptcha.execute(@js($recaptchaSiteKey), {
                    action: 'submit'
                });

                // 3. Warm up the session (ensures cookies exist if they were deleted)
                // We use HEAD to minimize data transfer
                await fetch(loginUrl, {
                    method: 'HEAD',
                    credentials: 'include',
                    headers: {
                        'X-XSRF-TOKEN': getXsrfToken()
                    }
                });

                // 4. Send the actual login request
                const response = await fetch(loginUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': getXsrfToken()
                    },
                    body: JSON.stringify({
                        password,
                        token
                    }),
                    credentials: 'include'
                });

                if (response.ok) {
                    window.location.href = redirectUrl;
                } else {
                    const data = await response.json();
                    alert(data.error || 'Access Denied');
                    toggleDisabledAndText();
                }

            } catch (err) {
                console.error('Security/Network Error:', err);
                alert('An error occurred during login. Please refresh.');
                toggleDisabledAndText();
            }
        };

        loginBtn.onclick = handleLogin;

        // Allow pressing "Enter" to login
        passwordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleLogin();
        });
    </script>
</body>

</html>
