const mobileMenu = document.querySelector('[data-school-menu]');

if (mobileMenu) {
    const closeMenu = (restoreFocus = false) => {
        mobileMenu.open = false;

        if (restoreFocus) {
            mobileMenu.querySelector('summary').focus();
        }
    };

    mobileMenu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileMenu.open) {
            closeMenu(true);
        }
    });

    document.addEventListener('click', (event) => {
        if (mobileMenu.open && !mobileMenu.contains(event.target)) {
            closeMenu();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) {
            closeMenu();
        }
    });
}

const navGroups = document.querySelectorAll('[data-nav-group]');

if (navGroups.length > 0) {
    const hoverPointer = window.matchMedia('(hover: hover) and (pointer: fine)');

    const closeNavGroups = (except = null) => {
        for (const group of navGroups) {
            if (group !== except) {
                group.open = false;
            }
        }
    };

    for (const group of navGroups) {
        const summary = group.querySelector('summary');

        group.addEventListener('pointerenter', () => {
            if (!hoverPointer.matches) {
                return;
            }

            closeNavGroups(group);
            group.open = true;
        });

        group.addEventListener('pointerleave', () => {
            if (hoverPointer.matches) {
                group.open = false;
            }
        });

        group.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && group.open) {
                group.open = false;
                summary.focus();
            }
        });

        summary.addEventListener('click', (event) => {
            if (!hoverPointer.matches || event.detail === 0) {
                closeNavGroups(group);

                return;
            }

            // Pointer users already opened the group on hover, so a click keeps it open.
            event.preventDefault();
            closeNavGroups(group);
            group.open = true;
        });
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-nav-group]')) {
            closeNavGroups();
        }
    });
}

for (const form of document.querySelectorAll('[data-school-search]')) {
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-search-status]');

    form.addEventListener('submit', (event) => {
        status.hidden = false;

        if (!navigator.onLine) {
            event.preventDefault();
            status.textContent = 'Koneksi internet terputus. Periksa koneksi, lalu coba cari kembali.';
            form.removeAttribute('aria-busy');
            button.disabled = false;
            return;
        }

        status.textContent = 'Mencari informasi sekolah…';
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
    });

    window.addEventListener('pageshow', () => {
        form.removeAttribute('aria-busy');
        button.disabled = false;
        status.hidden = true;
        status.textContent = '';
    });
}

for (const image of document.querySelectorAll('[data-school-image]')) {
    const showFallback = () => {
        image.hidden = true;
        image.closest('[data-school-photo]').querySelector('[data-photo-fallback]').hidden = false;
    };

    image.addEventListener('error', showFallback);

    if (image.complete && image.naturalWidth === 0) {
        showFallback();
    }
}
