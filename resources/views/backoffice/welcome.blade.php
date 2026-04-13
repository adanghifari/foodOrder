<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik Backoffice</title>
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

        .headline {
            font-family: 'Space Grotesk', sans-serif;
        }

        .glass {
            background: rgba(4, 4, 4, 0.62);
            border: 1px solid rgba(252, 184, 97, 0.25);
            backdrop-filter: blur(8px);
        }

        .page-shell {
            transition: opacity 240ms ease, transform 240ms ease;
        }

        body.nav-leave .page-shell {
            opacity: 0;
            transform: translateY(14px) scale(0.985);
        }

        @media (prefers-reduced-motion: reduce) {
            .page-shell {
                transition: none;
            }
        }

    </style>
</head>
<body class="min-h-screen text-[#FCB861] flex items-center">
    <main class="page-shell max-w-5xl mx-auto w-full px-5 py-6 md:py-8 relative z-10">
        <section class="glass rounded-3xl p-6 md:p-8 shadow-2xl shadow-black/20">
            <header class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-[#FCB861]/20">
                <p class="inline-flex items-center gap-2 text-xs md:text-sm font-bold tracking-[0.2em] text-[#FCB861]">
                    <span class="inline-block w-2 h-2 rounded-full bg-[#C5620B]"></span>
                    BACKOFFICE KEDAIKLIK
                </p>
            </header>

            <div class="grid lg:grid-cols-12 gap-5 md:gap-6 items-stretch mt-6">
                <div class="lg:col-span-7 flex flex-col justify-center">
                    <h1 class="headline text-3xl md:text-4xl leading-tight font-bold text-[#FCB861]">
                        Selamat datang Admin!
                    </h1>
                    <p class="mt-2 text-sm md:text-base font-semibold text-[#f7d5a6]">
                        Masuk dan kelola operasional KedaiKlik.
                    </p>
                    <div class="mt-5">
                        <a id="to-login" href="/backoffice/login" class="inline-flex justify-center items-center rounded-2xl bg-[#C5620B] hover:bg-[#FCB861] text-[#040404] font-extrabold px-6 py-3 transition">
                            Masuk Sebagai Staff
                        </a>
                    </div>
                </div>

                <aside class="lg:col-span-5 bg-[#FCB861] rounded-2xl p-4 md:p-5 text-[#040404] h-full">
                    <p class="text-xs font-bold tracking-wider text-[#6A2B09]">RINGKASAN AKSES</p>
                    <ul class="mt-3 space-y-2.5">
                        <li class="rounded-xl border border-[#6A2B09]/30 p-3">
                            <p class="text-sm font-extrabold">Manajemen Menu</p>
                            <p class="text-xs text-[#6A2B09] mt-1">Tambah, ubah, hapus.</p>
                        </li>
                        <li class="rounded-xl border border-[#6A2B09]/30 p-3">
                            <p class="text-sm font-extrabold">Pemantauan Pesanan</p>
                            <p class="text-xs text-[#6A2B09] mt-1">Antrian dan status.</p>
                        </li>
                        <li class="rounded-xl border border-[#6A2B09]/30 p-3">
                            <p class="text-sm font-extrabold">Overview</p>
                            <p class="text-xs text-[#6A2B09] mt-1">Grafik dan statistik.</p>
                        </li>
                    </ul>
                </aside>
            </div>
        </section>
    </main>
    <script>
        (function syncBackgroundPhase() {
            const nowSeconds = Date.now() / 1000;
            document.body.style.setProperty('--delay-one', `-${nowSeconds % 7}s`);
            document.body.style.setProperty('--delay-two', `-${nowSeconds % 9}s`);
        })();

        const toLoginLink = document.getElementById('to-login');
        if (toLoginLink) {
            toLoginLink.addEventListener('click', function (event) {
                event.preventDefault();
                document.body.classList.add('nav-leave');
                window.setTimeout(function () {
                    window.location.href = toLoginLink.href;
                }, 240);
            });
        }
    </script>
</body>
</html>
