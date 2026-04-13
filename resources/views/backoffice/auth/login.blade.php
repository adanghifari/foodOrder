<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Backoffice</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --rich-black: #040404;
            --philippine-bronze: #6A2B09;
            --alloy-orange: #C5620B;
            --rajah: #FCB861;
            --auro-metal-saurus: #6F7781;
            --delay-one: 0s;
            --delay-two: 0s;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(140deg, var(--rich-black), #140a05);
            position: relative;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: -25vmax;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            background:
                radial-gradient(42vmax 42vmax at 78% 22%, rgba(197, 98, 11, 0.42), transparent 68%),
                radial-gradient(38vmax 38vmax at 14% 78%, rgba(106, 43, 9, 0.48), transparent 66%);
            animation: drift-one 7s ease-in-out infinite alternate;
            animation-delay: var(--delay-one);
        }

        body::after {
            background:
                radial-gradient(34vmax 34vmax at 20% 18%, rgba(252, 184, 97, 0.22), transparent 70%),
                radial-gradient(46vmax 46vmax at 86% 84%, rgba(106, 43, 9, 0.32), transparent 72%);
            animation: drift-two 9s ease-in-out infinite alternate;
            animation-delay: var(--delay-two);
        }

        @keyframes drift-one {
            0% {
                transform: translate3d(-2%, -1%, 0) scale(1);
            }
            100% {
                transform: translate3d(3%, 3%, 0) scale(1.14);
            }
        }

        @keyframes drift-two {
            0% {
                transform: translate3d(2%, -2%, 0) scale(1);
            }
            100% {
                transform: translate3d(-3%, 2%, 0) scale(1.2);
            }
        }

        .title {
            font-family: 'Space Grotesk', sans-serif;
        }

        .login-shell {
            opacity: 0;
            transform: translateY(14px) scale(0.985);
            transition: opacity 280ms ease, transform 280ms ease;
            position: relative;
            z-index: 10;
        }

        .is-loading {
            opacity: 0.7;
            cursor: wait;
        }

        body.ready .login-shell {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        body.nav-leave .login-shell {
            opacity: 0;
            transform: translateY(-12px) scale(0.985);
        }

        @media (prefers-reduced-motion: reduce) {
            .login-shell {
                opacity: 1;
                transform: none;
                transition: none;
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-[#FCB861]">
    <div class="login-shell w-full max-w-md rounded-2xl border border-[#FCB861]/30 bg-[#040404]/70 backdrop-blur-md shadow-lg p-6">
        <h1 class="title text-2xl font-extrabold text-[#FCB861] mb-6">Login Backoffice</h1>
        <form id="backoffice-login-form" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-[#f7d5a6] mb-1">Username</label>
                <input id="username" name="username" type="text" required autocomplete="username" class="w-full border border-[#6F7781]/60 bg-[#6A2B09]/20 text-[#FCB861] placeholder:text-[#f7d5a6]/70 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#FCB861]/40" placeholder="Masukkan username">
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#f7d5a6] mb-1">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full border border-[#6F7781]/60 bg-[#6A2B09]/20 text-[#FCB861] placeholder:text-[#f7d5a6]/70 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#FCB861]/40" placeholder="Masukkan password">
            </div>
            <p id="login-error" class="hidden text-sm text-red-300"></p>
            <button id="login-submit" type="submit" class="w-full bg-[#C5620B] hover:bg-[#FCB861] text-[#040404] font-bold py-3 rounded-xl transition">Masuk</button>
        </form>
        <a id="to-backoffice" href="/backoffice" class="block text-center mt-4 text-[#f7d5a6] hover:text-[#FCB861] text-sm">Kembali</a>
    </div>
    <script>
        (function syncBackgroundPhase() {
            const nowSeconds = Date.now() / 1000;
            document.body.style.setProperty('--delay-one', `-${nowSeconds % 7}s`);
            document.body.style.setProperty('--delay-two', `-${nowSeconds % 9}s`);
        })();

        window.requestAnimationFrame(function () {
            document.body.classList.add('ready');
        });

        const toBackofficeLink = document.getElementById('to-backoffice');
        if (toBackofficeLink) {
            toBackofficeLink.addEventListener('click', function (event) {
                event.preventDefault();
                document.body.classList.add('nav-leave');
                window.setTimeout(function () {
                    window.location.href = toBackofficeLink.href;
                }, 240);
            });
        }

        const loginForm = document.getElementById('backoffice-login-form');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const loginSubmit = document.getElementById('login-submit');
        const loginError = document.getElementById('login-error');

        if (loginForm && usernameInput && passwordInput && loginSubmit && loginError) {
            loginForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                loginError.classList.add('hidden');
                loginError.textContent = '';
                loginSubmit.classList.add('is-loading');
                loginSubmit.disabled = true;
                loginSubmit.textContent = 'Memproses...';

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    const response = await fetch('/backoffice/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            username: usernameInput.value.trim(),
                            password: passwordInput.value
                        })
                    });

                    const result = await response.json().catch(function () {
                        return {};
                    });

                    if (!response.ok || result?.status !== 'success') {
                        const message = result?.message || 'Login gagal. Periksa username/password.';
                        throw new Error(message);
                    }

                    document.body.classList.add('nav-leave');
                    window.setTimeout(function () {
                        window.location.href = '/backoffice/dashboard';
                    }, 240);
                } catch (error) {
                    loginError.textContent = error.message || 'Terjadi kesalahan saat login.';
                    loginError.classList.remove('hidden');
                } finally {
                    loginSubmit.classList.remove('is-loading');
                    loginSubmit.disabled = false;
                    loginSubmit.textContent = 'Masuk';
                }
            });
        }
    </script>
</body>
</html>
