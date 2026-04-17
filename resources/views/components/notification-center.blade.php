@props([
    'messages' => [],
])

@php
    $queuedMessages = collect();

    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $flashValue = session($type);

        if (is_string($flashValue) && trim($flashValue) !== '') {
            $queuedMessages->push([
                'type' => $type,
                'message' => $flashValue,
            ]);
        }
    }

    if ($errors->any()) {
        foreach ($errors->all() as $errorMessage) {
            $queuedMessages->push([
                'type' => 'error',
                'message' => $errorMessage,
            ]);
        }
    }

    foreach ((array) $messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $queuedMessages->push([
            'type' => (string) ($message['type'] ?? 'info'),
            'message' => (string) ($message['message'] ?? ''),
            'title' => (string) ($message['title'] ?? ''),
            'duration' => (int) ($message['duration'] ?? 0),
        ]);
    }

    $queuedMessages = $queuedMessages
        ->filter(fn ($message) => trim((string) ($message['message'] ?? '')) !== '')
        ->values();
@endphp

<div id="kk-notification-root" class="fixed inset-x-0 top-0 z-[120] pointer-events-none">
    <div id="kk-notification-stack" class="mx-auto flex max-w-md flex-col gap-3 px-4 pt-4 sm:mr-4 sm:ml-auto"></div>
</div>

<div
    id="kk-confirm-overlay"
    class="fixed inset-0 z-[130] hidden bg-slate-950/45 px-4 py-6"
    aria-hidden="true"
>
    <div class="flex min-h-full items-center justify-center">
        <div
            id="kk-confirm-dialog"
            class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="kk-confirm-title"
            aria-describedby="kk-confirm-message"
        >
            <div id="kk-confirm-badge" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-[0.18em]"></div>
            <h2 id="kk-confirm-title" class="mt-3 text-lg font-extrabold text-slate-900"></h2>
            <p id="kk-confirm-message" class="mt-2 text-sm leading-6 text-slate-600"></p>
            <div class="mt-5 flex items-center justify-end gap-3">
                <button
                    id="kk-confirm-cancel"
                    type="button"
                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                >
                    Batal
                </button>
                <button
                    id="kk-confirm-submit"
                    type="button"
                    class="inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-bold text-white transition"
                >
                    Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const initialMessages = @json($queuedMessages);

        function createNotificationApi() {
            const root = document.getElementById('kk-notification-root');
            const stack = document.getElementById('kk-notification-stack');
            const confirmOverlay = document.getElementById('kk-confirm-overlay');
            const confirmBadge = document.getElementById('kk-confirm-badge');
            const confirmTitle = document.getElementById('kk-confirm-title');
            const confirmMessage = document.getElementById('kk-confirm-message');
            const confirmCancel = document.getElementById('kk-confirm-cancel');
            const confirmSubmit = document.getElementById('kk-confirm-submit');

            if (!root || !stack || !confirmOverlay || !confirmBadge || !confirmTitle || !confirmMessage || !confirmCancel || !confirmSubmit) {
                return null;
            }

            const toneMap = {
                success: {
                    accent: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    icon: '✓',
                    badge: 'bg-emerald-100 text-emerald-700',
                    button: 'bg-emerald-600 hover:bg-emerald-700',
                    label: 'Sukses',
                },
                error: {
                    accent: 'border-red-200 bg-red-50 text-red-700',
                    icon: '!',
                    badge: 'bg-red-100 text-red-700',
                    button: 'bg-red-600 hover:bg-red-700',
                    label: 'Error',
                },
                warning: {
                    accent: 'border-amber-200 bg-amber-50 text-amber-700',
                    icon: '!',
                    badge: 'bg-amber-100 text-amber-700',
                    button: 'bg-amber-500 hover:bg-amber-600',
                    label: 'Perhatian',
                },
                info: {
                    accent: 'border-sky-200 bg-sky-50 text-sky-700',
                    icon: 'i',
                    badge: 'bg-sky-100 text-sky-700',
                    button: 'bg-sky-600 hover:bg-sky-700',
                    label: 'Info',
                },
            };

            function resolveTone(type) {
                return toneMap[String(type || 'info').toLowerCase()] || toneMap.info;
            }

            function show(options) {
                const settings = options || {};
                const tone = resolveTone(settings.type);
                const title = String(settings.title || '').trim();
                const message = String(settings.message || '').trim();
                const duration = Number(settings.duration || 4200);

                if (message === '') {
                    return null;
                }

                const toast = document.createElement('article');
                toast.className = 'pointer-events-auto overflow-hidden rounded-2xl border px-4 py-3 shadow-xl backdrop-blur transition duration-200 ' + tone.accent;
                toast.innerHTML = `
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-full bg-white/80 text-sm font-black">${tone.icon}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-extrabold">${title || tone.label}</p>
                            <p class="mt-1 text-sm leading-5">${message}</p>
                        </div>
                        <button type="button" class="kk-toast-close inline-flex h-8 w-8 flex-none items-center justify-center rounded-full text-base font-bold text-current/70 transition hover:bg-white/60 hover:text-current" aria-label="Tutup notifikasi">×</button>
                    </div>
                `;

                stack.appendChild(toast);

                function removeToast() {
                    toast.classList.add('opacity-0', '-translate-y-2');
                    window.setTimeout(function () {
                        toast.remove();
                    }, 180);
                }

                const closeButton = toast.querySelector('.kk-toast-close');
                if (closeButton) {
                    closeButton.addEventListener('click', removeToast);
                }

                if (duration > 0) {
                    window.setTimeout(removeToast, duration);
                }

                return toast;
            }

            let activeResolver = null;
            let singleButtonMode = false;

            function closeConfirm(result) {
                confirmOverlay.classList.add('hidden');
                confirmOverlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                singleButtonMode = false;

                if (activeResolver) {
                    activeResolver(result);
                    activeResolver = null;
                }
            }

            function confirm(options) {
                const settings = options || {};
                const tone = resolveTone(settings.type || 'warning');
                singleButtonMode = Boolean(settings.singleButton);

                confirmBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-[0.18em] ' + tone.badge;
                confirmBadge.textContent = settings.badge || tone.label;
                confirmTitle.textContent = String(settings.title || 'Konfirmasi aksi');
                confirmMessage.textContent = String(settings.message || 'Tindakan ini akan dijalankan.');
                confirmSubmit.textContent = String(settings.confirmText || 'Lanjutkan');
                confirmCancel.textContent = String(settings.cancelText || 'Batal');
                confirmSubmit.className = 'inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-bold text-white transition ' + tone.button;
                confirmCancel.classList.toggle('hidden', singleButtonMode);

                confirmOverlay.classList.remove('hidden');
                confirmOverlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');

                return new Promise(function (resolve) {
                    activeResolver = resolve;
                });
            }

            confirmCancel.addEventListener('click', function () {
                closeConfirm(false);
            });

            confirmSubmit.addEventListener('click', function () {
                closeConfirm(true);
            });

            confirmOverlay.addEventListener('click', function (event) {
                if (event.target === confirmOverlay) {
                    if (singleButtonMode) {
                        return;
                    }
                    closeConfirm(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !confirmOverlay.classList.contains('hidden')) {
                    if (singleButtonMode) {
                        return;
                    }
                    closeConfirm(false);
                }
            });

            document.addEventListener('submit', function (event) {
                const form = event.target instanceof HTMLFormElement ? event.target : null;

                if (!form || !form.matches('[data-notify-confirm]') || form.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();

                confirm({
                    type: form.dataset.confirmType || 'warning',
                    badge: form.dataset.confirmBadge || '',
                    title: form.dataset.confirmTitle || 'Konfirmasi aksi',
                    message: form.dataset.confirmMessage || 'Tindakan ini akan dijalankan.',
                    confirmText: form.dataset.confirmButton || 'Lanjutkan',
                    cancelText: form.dataset.cancelButton || 'Batal',
                }).then(function (approved) {
                    if (!approved) {
                        return;
                    }

                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });

            return { show, confirm };
        }

        if (!window.KedaiKlikNotify) {
            window.KedaiKlikNotify = createNotificationApi();
        }

        if (!window.KedaiKlikNotify) {
            return;
        }

        initialMessages.forEach(function (message, index) {
            window.setTimeout(function () {
                window.KedaiKlikNotify.show(message);
            }, index * 120);
        });
    })();
</script>
