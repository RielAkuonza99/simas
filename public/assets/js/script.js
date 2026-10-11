/*
 * Sistem Surat Masuk BAPPERIDA — interface behaviour.
 *
 * Presentation only. No request is built or sent here except the two that
 * already existed: the live metrics poll on the settings page and the manual
 * sync batches on the sync page. Every feature is a small init function; each
 * one returns early when its markup is not on the page.
 */
(() => {
    'use strict';

    const mobileQuery = window.matchMedia('(max-width: 1023.98px)');

    /* ---------- Toasts ---------- */
    const toast = (message, { actionLabel = '', onAction = null, timeout = 6000 } = {}) => {
        const region = document.querySelector('[data-toast-region]');
        if (!region) return;

        const item = document.createElement('div');
        item.className = 'toast';
        item.setAttribute('role', 'status');
        const text = document.createElement('span');
        text.className = 'toast__text';
        text.textContent = message;
        item.append(text);

        const remove = () => item.remove();
        if (actionLabel && onAction) {
            const action = document.createElement('button');
            action.type = 'button';
            action.className = 'toast__action';
            action.textContent = actionLabel;
            action.addEventListener('click', () => {
                onAction();
                remove();
            });
            item.append(action);
        }
        region.append(item);
        window.setTimeout(remove, timeout);
    };

    /* ---------- Loading bar between pages ---------- */
    const initLoadbar = () => {
        const loadbar = document.querySelector('[data-navigation-loadbar]');
        if (!loadbar) return;

        let progressTimer = null;
        let loadingStartedAt = 0;
        const setProgress = (progress) => loadbar.style.setProperty('--navigation-progress', `${progress}%`);
        const beginLoading = () => {
            window.clearInterval(progressTimer);
            loadbar.classList.remove('is-complete');
            loadbar.classList.add('is-loading', 'is-visible');
            loadingStartedAt = performance.now();
            setProgress(8);
            progressTimer = window.setInterval(() => {
                const current = Number.parseFloat(loadbar.style.getPropertyValue('--navigation-progress')) || 8;
                setProgress(Math.min(90, current + Math.max(1, (90 - current) * 0.08)));
            }, 120);
        };
        const finishLoading = () => {
            const finish = () => {
                window.clearInterval(progressTimer);
                setProgress(100);
                loadbar.classList.remove('is-loading');
                loadbar.classList.add('is-complete');
                window.setTimeout(() => {
                    loadbar.classList.remove('is-visible', 'is-complete');
                    setProgress(0);
                }, 260);
            };
            window.setTimeout(finish, Math.max(0, 180 - (performance.now() - loadingStartedAt)));
        };

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

            const destination = new URL(link.href, location.href);
            if (destination.origin !== location.origin) return;
            if (destination.pathname === location.pathname && destination.search === location.search) return;
            beginLoading();
        }, true);

        beginLoading();
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) finishLoading();
        });
        if (navigator.onLine === false) loadbar.classList.add('is-offline');
        window.addEventListener('online', () => loadbar.classList.remove('is-offline'));
        window.addEventListener('offline', () => loadbar.classList.add('is-offline'));

        if (document.readyState === 'complete') {
            loadbar.classList.add('is-complete');
            finishLoading();
        } else {
            window.addEventListener('load', finishLoading, { once: true });
        }
    };

    /* ---------- Sidebar: drawer on mobile, collapsible rail on desktop ---------- */
    const initSidebar = () => {
        const app = document.querySelector('.app');
        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-mobile-nav-backdrop]');
        const sidebar = document.querySelector('.sidebar');
        if (!app || !sidebarToggle || !sidebar) return;

        const update = () => {
            const isMobile = mobileQuery.matches;
            const isOpen = isMobile ? app.classList.contains('mobile-nav-open') : !app.classList.contains('sidebar-collapsed');
            const label = isMobile
                ? (isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi')
                : (isOpen ? 'Tutup navigasi samping' : 'Buka navigasi samping');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
            sidebarToggle.setAttribute('aria-label', label);
            sidebarToggle.setAttribute('title', label);
            if (backdrop) backdrop.hidden = !isMobile || !isOpen;
            sidebar.inert = isMobile && !isOpen;
            if (isMobile) {
                sidebar.setAttribute('aria-hidden', String(!isOpen));
            } else {
                sidebar.removeAttribute('aria-hidden');
            }
        };
        const setMobileOpen = (isOpen, restoreFocus = false) => {
            app.classList.toggle('mobile-nav-open', isOpen);
            document.body.classList.toggle('mobile-nav-open', isOpen);
            update();
            if (isOpen) {
                sidebar.querySelector('a[href]')?.focus();
            } else if (restoreFocus) {
                sidebarToggle.focus();
            }
        };

        update();
        sidebarToggle.addEventListener('click', () => {
            if (mobileQuery.matches) {
                setMobileOpen(!app.classList.contains('mobile-nav-open'));
                return;
            }
            app.classList.toggle('sidebar-collapsed');
            update();
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            const collapsed = app.classList.contains('sidebar-collapsed') ? '1' : '0';
            document.cookie = `bapperida_sidebar_collapsed=${collapsed}; path=/; SameSite=Lax${secure}`;
        });
        backdrop?.addEventListener('click', () => setMobileOpen(false, true));
        sidebar.addEventListener('click', (event) => {
            if (mobileQuery.matches && event.target.closest('a[href]')) setMobileOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (!mobileQuery.matches || !app.classList.contains('mobile-nav-open')) return;
            if (event.key === 'Escape') {
                setMobileOpen(false, true);
                return;
            }
            if (event.key !== 'Tab') return;

            const focusable = [...sidebar.querySelectorAll('a[href]')].filter((el) => el.getClientRects().length > 0);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const outside = !sidebar.contains(document.activeElement);
            if (event.shiftKey && (document.activeElement === first || outside)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || outside)) {
                event.preventDefault();
                first.focus();
            }
        });
        mobileQuery.addEventListener('change', () => {
            app.classList.remove('mobile-nav-open');
            document.body.classList.remove('mobile-nav-open');
            update();
        });
    };

    /* ---------- Account menu ---------- */
    const initAccountMenu = () => {
        const menu = document.querySelector('.account-menu');
        if (!menu) return;
        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) menu.open = false;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            }
        });
    };

    /* ---------- "/" focuses the search box ---------- */
    const initSearchShortcut = () => {
        const field = document.querySelector('[data-search-shortcut]');
        if (!field) return;
        document.addEventListener('keydown', (event) => {
            if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
            const target = event.target;
            if (target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"], dialog[open]')) return;
            event.preventDefault();
            field.focus();
            field.select?.();
        });
    };

    /* ---------- Browser connection readings (settings) ---------- */
    const initNetworkReadings = () => {
        const state = document.querySelector('[data-network-state]');
        const type = document.querySelector('[data-network-type]');
        const rtt = document.querySelector('[data-network-rtt]');
        if (!state && !type && !rtt) return;
        const connection = navigator.connection ?? navigator.mozConnection ?? navigator.webkitConnection;

        const update = () => {
            if (state) state.textContent = navigator.onLine ? 'Online menurut browser' : 'Offline menurut browser';
            if (type) {
                const effective = connection?.effectiveType;
                const downlink = Number(connection?.downlink);
                const details = [];
                if (typeof effective === 'string' && effective !== '') details.push(effective.toUpperCase());
                if (Number.isFinite(downlink) && downlink > 0) details.push(`${downlink} Mbps`);
                type.textContent = details.length ? `${details.join(' · ')} (perkiraan)` : 'Tidak tersedia di browser ini';
            }
            if (rtt) {
                const roundTrip = Number(connection?.rtt);
                rtt.textContent = Number.isFinite(roundTrip) && roundTrip > 0 ? `sekitar ${Math.round(roundTrip)} ms` : 'Tidak tersedia';
            }
        };
        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        connection?.addEventListener?.('change', update);
    };

    /* ---------- Live developer metrics (settings, admin) ---------- */
    const initDeveloperMetrics = () => {
        const root = document.querySelector('[data-devtools-live]');
        if (!root) return;

        const updatedLabel = root.querySelector('[data-live-updated]');
        const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
        let request = null;
        const formatBytes = (bytes) => {
            if (!Number.isFinite(bytes)) return 'Tidak tersedia';
            const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
            let value = bytes;
            let unit = 0;
            while (value >= 1024 && unit < units.length - 1) {
                value /= 1024;
                unit++;
            }
            return `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${units[unit]}`;
        };
        const refresh = async () => {
            if (request) return;
            request = (async () => {
                const response = await fetch(root.dataset.metricsUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('metrics_unavailable');
                const metrics = await response.json();

                root.querySelectorAll('[data-live-metric]').forEach((element) => {
                    const key = element.dataset.liveMetric;
                    const value = key.startsWith('queue_') ? metrics.queue[key.slice(6)] : metrics.current[key];
                    if (value === null || value === undefined) {
                        element.textContent = 'Tidak tersedia';
                    } else if (element.dataset.liveFormat === 'bytes') {
                        element.textContent = formatBytes(Number(value));
                    } else if (element.dataset.liveFormat === 'milliseconds') {
                        element.textContent = `${numberFormat.format(Number(value))} ms`;
                    } else {
                        element.textContent = numberFormat.format(Number(value));
                    }
                });

                const diskFree = Number(metrics.current.disk_free_bytes);
                const diskTotal = Number(metrics.current.disk_total_bytes);
                const hasDisk = diskTotal > 0 && Number.isFinite(diskFree);
                const usedPercent = hasDisk ? Math.max(0, Math.min(100, Math.round((1 - diskFree / diskTotal) * 100))) : null;
                const diskUsed = root.querySelector('[data-live-disk-used]');
                if (diskUsed) diskUsed.textContent = hasDisk ? `${usedPercent}% terpakai.` : 'Kapasitas volume tidak tersedia.';
                if (usedPercent !== null) root.querySelector('[data-live-disk-bar]')?.style.setProperty('--value', `${usedPercent}%`);
                if (updatedLabel) {
                    updatedLabel.textContent = `Diperbarui ${new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date())}`;
                }
            })().catch(() => {
                if (updatedLabel) updatedLabel.textContent = 'Pembaruan metrik tertunda';
            }).finally(() => {
                request = null;
            });
            await request;
        };

        refresh();
        window.setInterval(() => {
            if (!document.hidden) refresh();
        }, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh();
        });
    };

    /* ---------- Buttons: loading state ---------- */
    const setLoading = (button) => {
        if (!button || button.classList.contains('is-loading')) return;
        button.dataset.originalHtml = button.innerHTML;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        const label = button.dataset.loadingText;
        if (label) button.textContent = label;
    };
    const clearLoading = (button) => {
        if (!button.classList.contains('is-loading')) return;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-busy');
        if (button.dataset.originalHtml !== undefined) button.innerHTML = button.dataset.originalHtml;
    };
    const initLoadingButtons = () => {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
            const button = event.submitter ?? form.querySelector('button[type="submit"][data-loading-text]');
            if (button && button.dataset.loadingText) {
                // Defer so the browser serialises the form before the button is touched.
                window.setTimeout(() => setLoading(button), 0);
            }
        });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) document.querySelectorAll('.btn.is-loading').forEach(clearLoading);
        });
    };

    /* ---------- Confirmation dialog for destructive or session-ending actions ---------- */
    const initConfirmDialog = () => {
        const dialog = document.querySelector('[data-confirm-dialog]');
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
            event.preventDefault();

            const title = form.dataset.confirmTitle || 'Yakin ingin melanjutkan?';
            const message = form.dataset.confirmMessage || '';
            const label = form.dataset.confirmLabel || 'Lanjutkan';
            const tone = form.dataset.confirmTone || 'danger';
            const submit = () => {
                setLoading(form.querySelector('button[type="submit"]'));
                HTMLFormElement.prototype.submit.call(form);
            };

            if (!dialog || typeof dialog.showModal !== 'function') {
                if (window.confirm(`${title}\n${message}`)) submit();
                return;
            }

            const accept = dialog.querySelector('[data-confirm-accept]');
            const cancel = dialog.querySelector('[data-confirm-cancel]');
            dialog.querySelector('[data-confirm-title]').textContent = title;
            dialog.querySelector('[data-confirm-text]').textContent = message;
            accept.textContent = label;
            accept.className = tone === 'danger' ? 'btn btn-danger' : 'btn btn-primary';
            dialog.querySelector('[data-confirm-icon]').classList.toggle('dialog__icon--neutral', tone !== 'danger');

            const cleanup = () => {
                accept.onclick = null;
                cancel.onclick = null;
                dialog.onclick = null;
            };
            accept.onclick = () => {
                cleanup();
                dialog.close('accepted');
                submit();
            };
            cancel.onclick = () => {
                cleanup();
                dialog.close('cancelled');
            };
            dialog.onclick = (clickEvent) => {
                if (clickEvent.target === dialog) {
                    cleanup();
                    dialog.close('cancelled');
                }
            };
            dialog.showModal();
        });
    };

    /* ---------- Alerts: dismiss button, focus error summary ---------- */
    const initAlerts = () => {
        document.querySelectorAll('[data-dismissible]').forEach((alert) => {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'alert__close';
            close.setAttribute('aria-label', 'Tutup pemberitahuan');
            close.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-x"></use></svg>';
            close.addEventListener('click', () => {
                alert.classList.add('is-leaving');
                window.setTimeout(() => alert.remove(), 180);
            });
            alert.append(close);
        });
        const summary = document.querySelector('[data-error-summary]');
        if (summary) {
            summary.scrollIntoView({ block: 'center' });
            summary.focus({ preventScroll: true });
        }
    };

    /* ---------- Inline field validation (replaces the browser bubble) ---------- */
    const initValidation = () => {
        const messageFor = (field) => {
            const { validity } = field;
            if (validity.valueMissing) return 'Kolom ini wajib diisi.';
            if (validity.typeMismatch && field.type === 'url') return 'Masukkan tautan lengkap, misalnya https://drive.google.com/...';
            if (validity.tooShort) return `Minimal ${field.minLength} karakter.`;
            if (validity.tooLong) return `Maksimal ${field.maxLength} karakter.`;
            if (validity.badInput) return 'Nilai belum valid.';
            return field.validationMessage;
        };
        const clear = (field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            const holder = field.closest('.field');
            holder?.querySelector('.field-error')?.remove();
        };
        const show = (field) => {
            clear(field);
            const holder = field.closest('.field');
            if (!holder) return;
            const error = document.createElement('div');
            error.className = 'field-error';
            error.id = `${field.id || 'field'}-error`;
            error.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-alert-circle"></use></svg><span></span>';
            error.querySelector('span').textContent = messageFor(field);
            holder.append(error);
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        };

        document.querySelectorAll('form[data-validate]').forEach((form) => {
            let focused = false;
            form.addEventListener('invalid', (event) => {
                event.preventDefault();
                const field = event.target;
                show(field);
                if (!focused) {
                    focused = true;
                    field.focus({ preventScroll: true });
                    field.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    window.setTimeout(() => { focused = false; }, 0);
                }
            }, true);
            const revalidate = (event) => {
                const field = event.target;
                if (!(field instanceof HTMLElement) || !field.classList.contains('is-invalid')) return;
                if (field.checkValidity()) clear(field);
            };
            form.addEventListener('input', revalidate);
            form.addEventListener('change', revalidate);
            form.addEventListener('focusout', (event) => {
                const field = event.target;
                if (field instanceof HTMLInputElement && field.type === 'url' && field.value !== '' && !field.checkValidity()) show(field);
            });
        });
    };

    /* ---------- Password visibility ---------- */
    const initPasswordToggle = () => {
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            button.addEventListener('click', () => {
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(reveal));
                button.setAttribute('aria-label', reveal ? 'Sembunyikan password' : 'Tampilkan password');
                button.querySelector('use')?.setAttribute('href', reveal ? '#i-eye-off' : '#i-eye');
            });
        });
    };

    /* ---------- Disposition steps in the letter form ---------- */
    const initDispositions = () => {
        const list = document.querySelector('[data-disposition-list]');
        const addButton = document.querySelector('[data-add-disposition]');
        const template = document.querySelector('[data-disposition-template]');
        if (!list || !addButton || !template) return;

        const countLabel = list.parentElement.querySelector('[data-disposition-count]');
        const update = () => {
            const steps = [...list.querySelectorAll('[data-disposition-step]')];
            steps.forEach((step, index) => {
                const stage = index + 1;
                const title = step.querySelector('[data-disposition-title]');
                title.textContent = `Disposisi ${stage}`;
                title.dataset.step = String(stage);
                step.querySelector('[data-remove-disposition]').setAttribute('aria-label', `Hapus disposisi ${stage}`);
                step.querySelectorAll('[data-disposition-field]').forEach((field) => {
                    const name = field.dataset.dispositionField;
                    const id = `d${stage}_${name === 'link_lampiran' ? 'lampiran' : name}`;
                    field.id = id;
                    field.name = `disposisi[${stage}][${name}]`;
                    step.querySelector(`[data-disposition-label="${name}"]`).htmlFor = id;
                });
            });
            addButton.disabled = steps.length >= 5;
            if (countLabel) countLabel.textContent = `${steps.length} dari 5 tahap`;
        };

        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-disposition]');
            if (!removeButton) return;
            const step = removeButton.closest('[data-disposition-step]');
            const following = step.nextElementSibling;
            step.remove();
            update();
            toast('Tahap disposisi dihapus. Perubahan baru tersimpan setelah Anda menyimpan surat.', {
                actionLabel: 'Urungkan',
                timeout: 8000,
                onAction: () => {
                    if (list.querySelectorAll('[data-disposition-step]').length >= 5) return;
                    list.insertBefore(step, following && following.parentNode === list ? following : null);
                    update();
                },
            });
        });
        addButton.addEventListener('click', () => {
            if (list.querySelectorAll('[data-disposition-step]').length >= 5) return;
            const step = template.content.firstElementChild.cloneNode(true);
            list.append(step);
            update();
            step.querySelector('[data-disposition-field]').focus();
        });
        update();
    };

    /* ---------- Manual metadata sync (sync page) ---------- */
    const initManualSync = () => {
        const form = document.querySelector('[data-manual-sync-form]');
        if (!form) return;

        const button = form.querySelector('[data-manual-sync]');
        const status = document.querySelector('[data-sync-run-status]');
        const forceSnapshot = form.querySelector('[data-force-snapshot]');
        let running = false;

        button?.addEventListener('click', async () => {
            if (running) return;
            running = true;
            button.disabled = true;
            button.classList.add('is-loading');
            status?.classList.add('is-busy');
            let processed = 0;
            let confirmed = 0;
            let failed = 0;
            let stopMessage = '';

            try {
                for (let attempt = 0; attempt < 100; attempt++) {
                    if (status) {
                        status.textContent = processed === 0
                            ? 'Memeriksa snapshot internal dan memulai sinkronisasi...'
                            : `Diproses ${processed} event, ${confirmed} terkonfirmasi.`;
                    }

                    const formData = new FormData(form);
                    if (forceSnapshot) forceSnapshot.value = '0';
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: formData,
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const result = await response.json();
                    if (response.status === 409) {
                        stopMessage = 'Supabase belum siap. Periksa status koneksi di atas.';
                        break;
                    }
                    if (!response.ok || !result.batch || !result.summary) {
                        stopMessage = 'Sinkronisasi berhenti karena server gagal memproses batch.';
                        break;
                    }

                    processed += Number(result.batch.processed) || 0;
                    confirmed += Number(result.batch.confirmed) || 0;
                    failed += Number(result.batch.failed) || 0;

                    if (result.batch.processed === 0) {
                        stopMessage = `Tidak ada event siap. ${Number(result.summary.PENDING) || 0} menunggu, ${Number(result.summary.FAILED) || 0} gagal.`;
                        break;
                    }
                    if (result.summary.PENDING === 0 && result.summary.FAILED === 0) break;
                    if (attempt === 99) {
                        stopMessage = 'Batas 100 event per aksi tercapai. Tekan sinkronkan lagi untuk melanjutkan antrean.';
                    }
                }
            } catch {
                stopMessage = 'Koneksi terputus. Muat ulang halaman untuk melihat status terbaru.';
            }

            if (status) {
                status.textContent = stopMessage || `Selesai: ${processed} event diproses, ${confirmed} terkonfirmasi, ${failed} gagal.`;
                status.classList.remove('is-busy');
            }
            running = false;
            button.disabled = false;
            button.classList.remove('is-loading');
            window.setTimeout(() => window.location.reload(), 900);
        });
    };

    const init = () => {
        initLoadbar();
        initSidebar();
        initAccountMenu();
        initSearchShortcut();
        initNetworkReadings();
        initDeveloperMetrics();
        initConfirmDialog();
        initLoadingButtons();
        initAlerts();
        initValidation();
        initPasswordToggle();
        initDispositions();
        initManualSync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
