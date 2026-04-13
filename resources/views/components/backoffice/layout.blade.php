@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - Backoffice KedaiKlik</title>
    <link rel="icon" type="image/png" href="/images/KedaiKlikLogo.png">
    <link rel="apple-touch-icon" href="/images/KedaiKlikLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --rich-black: #040404;
            --philippine-bronze: #6A2B09;
            --alloy-orange: #C5620B;
            --rajah: #FCB861;
            --auro-metal-saurus: #6F7781;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .title-font {
            font-family: 'Space Grotesk', sans-serif;
        }

        #bo-sidebar {
            transition: width 240ms ease, transform 240ms ease;
        }

        #bo-main {
            transition: margin-left 240ms ease;
        }

        .sidebar-handle {
            position: fixed;
            top: 6rem;
            left: 0.5rem;
            z-index: 60;
            width: 1.75rem;
            height: 3rem;
            border-radius: 0 999px 999px 0;
            border: 1px solid rgba(106, 43, 9, 0.9);
            border-left: 0;
            background: #6A2B09;
            color: #FCB861;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            transition: left 240ms ease, background-color 180ms ease;
        }

        .sidebar-handle:hover {
            background: #C5620B;
            color: #FCB861;
        }

        body.sidebar-mobile-open .sidebar-handle {
            left: 16rem;
        }

        @media (min-width: 1024px) {
            #bo-main {
                margin-left: 16rem;
            }

            .sidebar-handle {
                left: 16rem;
            }

            body.sidebar-collapsed #bo-sidebar {
                width: 4.5rem;
            }

            body.sidebar-collapsed #bo-main {
                margin-left: 4.5rem;
            }

            body.sidebar-collapsed .sidebar-handle {
                left: 4.5rem;
            }

            body.sidebar-collapsed .sidebar-label,
            body.sidebar-collapsed .sidebar-logo-text,
            body.sidebar-collapsed .sidebar-profile-name {
                display: none;
            }

            body.sidebar-collapsed .sidebar-nav-link {
                justify-content: center;
            }

            body.sidebar-collapsed .sidebar-icon {
                width: 1rem;
                height: 1rem;
            }

            body.sidebar-collapsed #bo-profile-toggle {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-white text-slate-900">
    <div class="min-h-screen flex bg-white">
        <x-backoffice.sidebar />

        <button id="bo-sidebar-toggle" type="button" class="sidebar-handle" aria-label="Toggle sidebar">
            <span id="bo-sidebar-toggle-icon">&gt;</span>
        </button>

        <div id="bo-main" class="min-w-0 w-full">
            <header class="h-16 border-b border-slate-200 bg-white px-4 md:px-6 flex items-center gap-3 sticky top-0 z-30">
                <div class="min-w-0">
                    <h1 class="title-font text-lg md:text-xl font-bold text-[var(--rich-black)] truncate">{{ $pageTitle }}</h1>
                    @if (!empty($pageSubtitle))
                        <p class="text-xs md:text-sm text-slate-500 truncate">{{ $pageSubtitle }}</p>
                    @endif
                </div>
            </header>

            <main class="px-4 py-5 md:px-6 md:py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-notification-center />

    <script>
        const sidebar = document.getElementById('bo-sidebar');
        const sidebarToggle = document.getElementById('bo-sidebar-toggle');
        const sidebarToggleIcon = document.getElementById('bo-sidebar-toggle-icon');
        const profileToggle = document.getElementById('bo-profile-toggle');
        const profileMenu = document.getElementById('bo-profile-menu');
        const logoutButton = document.getElementById('bo-logout-btn');
        const sidebarStateKey = 'boSidebarCollapsed';

        function isDesktop() {
            return window.matchMedia('(min-width: 1024px)').matches;
        }

        function openMobileSidebar() {
            if (!sidebar) {
                return;
            }
            sidebar.classList.remove('-translate-x-full');
            document.body.classList.add('sidebar-mobile-open');
        }

        function closeMobileSidebar() {
            if (!sidebar) {
                return;
            }
            sidebar.classList.add('-translate-x-full');
            document.body.classList.remove('sidebar-mobile-open');
        }

        function updateSidebarToggleIcon() {
            if (!sidebarToggleIcon) {
                return;
            }

            if (isDesktop()) {
                sidebarToggleIcon.textContent = document.body.classList.contains('sidebar-collapsed') ? '>' : '<';
            } else {
                sidebarToggleIcon.textContent = document.body.classList.contains('sidebar-mobile-open') ? '<' : '>';
            }
        }

        function persistSidebarState() {
            if (!isDesktop()) {
                return;
            }

            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem(sidebarStateKey, isCollapsed ? '1' : '0');
        }

        function hydrateSidebarState() {
            if (!isDesktop()) {
                return;
            }

            const savedState = localStorage.getItem(sidebarStateKey);
            if (savedState === '1') {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                if (isDesktop()) {
                    document.body.classList.toggle('sidebar-collapsed');
                    persistSidebarState();
                } else {
                    if (document.body.classList.contains('sidebar-mobile-open')) {
                        closeMobileSidebar();
                    } else {
                        openMobileSidebar();
                    }
                }

                updateSidebarToggleIcon();
            });
        }

        if (sidebar) {
            const navLinks = sidebar.querySelectorAll('a.sidebar-nav-link');

            navLinks.forEach(function (link) {
                link.addEventListener('click', function (event) {
                    if (isDesktop() && document.body.classList.contains('sidebar-collapsed')) {
                        event.preventDefault();
                        document.body.classList.remove('sidebar-collapsed');
                        persistSidebarState();
                        updateSidebarToggleIcon();
                    }
                });
            });
        }

        window.addEventListener('resize', function () {
            if (isDesktop()) {
                closeMobileSidebar();
                hydrateSidebarState();
            }
            updateSidebarToggleIcon();
        });

        document.addEventListener('click', function (event) {
            if (!sidebar || !profileToggle || !profileMenu) {
                return;
            }

            if (!isDesktop() && !sidebar.contains(event.target) && sidebarToggle && !sidebarToggle.contains(event.target)) {
                closeMobileSidebar();
                updateSidebarToggleIcon();
            }

            if (!profileToggle.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.add('hidden');
            }
        });

        if (profileToggle && profileMenu) {
            profileToggle.addEventListener('click', function (event) {
                event.stopPropagation();

                if (isDesktop() && document.body.classList.contains('sidebar-collapsed')) {
                    document.body.classList.remove('sidebar-collapsed');
                    profileMenu.classList.add('hidden');
                    persistSidebarState();
                    updateSidebarToggleIcon();
                    return;
                }

                profileMenu.classList.toggle('hidden');
            });
        }

        if (logoutButton) {
            logoutButton.addEventListener('click', async function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                try {
                    await fetch('/backoffice/logout', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                } catch (error) {
                    // Ignore logout API errors and continue local cleanup.
                } finally {
                    window.location.href = '/backoffice/login';
                }
            });
        }

        hydrateSidebarState();
        updateSidebarToggleIcon();
    </script>
</body>
</html>
