<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Meja</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</head>
<body class="min-h-screen bg-gray-100 lg:bg-[radial-gradient(circle_at_top,_#fff7ed,_#f1f5f9_55%)] flex justify-center p-4 lg:p-6">
    <main class="w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-gray-100 p-5 sm:p-6">
        <a href="/kedai" class="inline-flex items-center text-sm font-bold text-[#C8641E] hover:text-[#A85318]">← Kembali</a>

        <h1 class="mt-3 text-2xl font-extrabold text-gray-800">Scan QR lalu Pesan!</h1>
        <p class="mt-2 text-sm text-gray-500">Arahkan kamera ke QR meja. Setelah terbaca, Anda akan langsung masuk ke menu.</p>

        <section class="mt-5">
            <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-black">
                <video id="qr-video" class="w-full aspect-video object-cover" autoplay muted playsinline></video>
            </div>
            <p id="scan-status" class="mt-3 text-xs font-semibold text-slate-600">Menyiapkan kamera...</p>
        </section>
    </main>

    <script>
        (function () {
            const video = document.getElementById('qr-video');
            const statusEl = document.getElementById('scan-status');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const returnTo = new URL(window.location.href).searchParams.get('return_to') || '/menu';

            function setStatus(message, isError) {
                if (!statusEl) return;
                statusEl.textContent = message;
                statusEl.className = 'mt-3 text-xs font-semibold ' + (isError ? 'text-red-600' : 'text-slate-600');
            }

            function goToTable(tableId) {
                const numeric = Number(tableId || 0);
                if (!Number.isInteger(numeric) || numeric <= 0) {
                    setStatus('Nomor meja tidak valid.', true);
                    return;
                }

                const target = new URL('/scan', window.location.origin);
                target.searchParams.set('tableId', String(numeric));
                target.searchParams.set('return_to', returnTo);
                window.location.href = target.toString();
            }

            function extractTableId(rawValue) {
                const raw = String(rawValue || '').trim();
                if (raw === '') return null;

                if (/^\d{1,3}$/.test(raw)) {
                    return Number(raw);
                }

                try {
                    const url = new URL(raw);
                    const fromParam = url.searchParams.get('tableId');
                    if (fromParam && /^\d{1,3}$/.test(fromParam)) {
                        return Number(fromParam);
                    }

                    const matchMenuPath = url.pathname.match(/\/menu\/(\d{1,3})$/);
                    if (matchMenuPath) {
                        return Number(matchMenuPath[1]);
                    }
                } catch (error) {
                    const matchPlain = raw.match(/(?:tableId=|\/menu\/)(\d{1,3})/i);
                    if (matchPlain) {
                        return Number(matchPlain[1]);
                    }
                }

                return null;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setStatus('Browser ini tidak mendukung akses kamera.', true);
                return;
            }

            const hasBarcodeDetector = 'BarcodeDetector' in window;
            const detector = hasBarcodeDetector ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
            let stream = null;
            let active = true;

            async function loop() {
                if (!active || !video || !ctx) return;

                if (video.readyState >= 2) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    try {
                        let decodedValue = '';

                        if (detector) {
                            const barcodes = await detector.detect(canvas);
                            if (barcodes.length > 0) {
                                decodedValue = String(barcodes[0].rawValue || '');
                            }
                        } else if (window.jsQR) {
                            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                            const qrCode = window.jsQR(imageData.data, imageData.width, imageData.height, {
                                inversionAttempts: 'dontInvert',
                            });
                            if (qrCode && qrCode.data) {
                                decodedValue = String(qrCode.data);
                            }
                        }

                        const tableId = extractTableId(decodedValue);
                        if (tableId) {
                            setStatus('QR terdeteksi. Mengarahkan ke menu...', false);
                            active = false;
                            if (stream) {
                                stream.getTracks().forEach(function (track) { track.stop(); });
                            }
                            goToTable(tableId);
                            return;
                        }
                    } catch (error) {
                        setStatus('Gagal membaca QR. Coba arahkan ulang kamera.', true);
                    }
                }

                requestAnimationFrame(loop);
            }

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            }).then(function (mediaStream) {
                stream = mediaStream;
                video.srcObject = mediaStream;
                setStatus('Arahkan kamera ke QR meja.', false);
                requestAnimationFrame(loop);
            }).catch(function () {
                setStatus('Akses kamera ditolak atau tidak tersedia.', true);
            });
        })();
    </script>
</body>
</html>
