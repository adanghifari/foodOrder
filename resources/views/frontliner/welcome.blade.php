<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KedaiKlik</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --cream: #fffaf3;
            --paper: #fffdf9;
            --espresso: #6f2f0f;
            --cinnamon: #c96a22;
            --peach: #ffcb81;
            --leaf: #6a8a43;
        }

        body {
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, rgba(255, 203, 129, 0.35), transparent 38%),
                linear-gradient(180deg, #fffdf8 0%, #fff7ea 100%);
        }

        .hero-shell {
            background:
                radial-gradient(circle at right top, rgba(255, 203, 129, 0.35), transparent 30%),
                linear-gradient(140deg, rgba(255,255,255,0.98), rgba(255,248,237,0.92));
        }

        .wave-band {
            clip-path: ellipse(84% 100% at 50% 100%);
        }
    </style>
</head>
<body class="min-h-screen text-[var(--espresso)]">
    <main class="mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-6 sm:px-6 lg:px-10">
        <section class="hero-shell relative w-full overflow-hidden rounded-[2rem] border border-[rgba(111,47,15,0.08)] shadow-[0_25px_80px_rgba(111,47,15,0.12)]">
            <div class="absolute inset-x-0 bottom-0 h-[34%] bg-[var(--cinnamon)] wave-band"></div>
            <div class="absolute -bottom-10 right-[-8%] h-52 w-52 rounded-full bg-[rgba(255,203,129,0.55)] blur-2xl"></div>

            <div class="relative grid min-h-[720px] items-center gap-10 px-6 pb-24 pt-10 sm:px-10 lg:grid-cols-[1.05fr_0.95fr] lg:px-14 lg:pb-28 lg:pt-12">
                <div class="relative z-10 max-w-xl mt-10 flex flex-col items-start gap-6 sm:gap-8">
                
                    <div class="mb-5 display-flex items-center rounded-full border border-[rgba(111,47,15,0.12)] bg-white/80 px-4 py-2 text-[0.72rem] font-bold uppercase tracking-[0.28em] text-[var(--cinnamon)]">
                        Dine In Ordering
                    </div>

                    <div class="flex flex-col gap-5">
                        <img
                            src="/images/KedaiKlikLogo.png"
                            alt="KedaiKlik"
                            class="h-5rem w-full max-w-[24rem] shrink-0 drop-shadow-[0_18px_18px_rgba(111,47,15,0.14)] sm:max-w-[24rem]"
                        >
                    </div>
                    <p class="mb-20 max-w-lg text-lg leading-8 text-[rgba(111,47,15,0.72)] sm:text-xl">
                        Rasa juara, pesan cukup dari meja. Buka menu, pilih hidangan, dan lanjutkan order tanpa langkah yang ribet.
                    </p>


                    <div class="mt-8 flex">
                            <a
                                href="/menu"
                                class="inline-flex items-center justify-center rounded-2xl bg-[#e08a3c] px-7 py-4 text-sm font-bold uppercase tracking-[0.18em] text-white shadow-[0_18px_38px_rgba(224,138,60,0.28)] transition hover:translate-y-[-2px] hover:bg-[#ea9951]"
                            >
                                Go To Menu
                            </a>
                    </div>

                </div>

                <div class="relative flex items-end justify-center lg:justify-end">
                    <div class="absolute right-[8%] top-[10%] hidden h-20 w-20 rounded-full bg-[rgba(106,138,67,0.18)] blur-xl lg:block"></div>
                    <div class="absolute left-[10%] top-[24%] hidden h-16 w-16 rounded-full bg-[rgba(201,106,34,0.16)] blur-xl lg:block"></div>

                    <div class="relative flex w-full max-w-[34rem] items-end justify-center">
                        <div class="absolute inset-x-10 bottom-5 h-20 rounded-full bg-[rgba(111,47,15,0.14)] blur-2xl"></div>
                        <div class="absolute left-0 top-10 hidden w-28 rounded-[1.6rem] bg-white/90 p-3 shadow-[0_18px_38px_rgba(111,47,15,0.1)] lg:block">
                            <img src="/images/ayam bakar.jpg" alt="Ayam bakar" class="h-24 w-full rounded-[1rem] object-cover">
                        </div>
                        <div class="absolute right-0 top-0 hidden w-28 rounded-[1.6rem] bg-white/90 p-3 shadow-[0_18px_38px_rgba(111,47,15,0.1)] lg:block">
                            <img src="/images/sate ayam.jpg" alt="Sate ayam" class="h-24 w-full rounded-[1rem] object-cover">
                        </div>

                        <img
                            src="/images/hidangan.png"
                            alt="Hidangan KedaiKlik"
                            class="mt-100 relative z-10 w-full max-w-[32rem] object-contain drop-shadow-[0_22px_30px_rgba(111,47,15,0.2)]"
                        >
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
