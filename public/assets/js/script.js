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
    const toast = (message, { actionLabel = '', onAction = null, timeout = 5000 } = {}) => {
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
        const dismiss = (distance = 0) => {
            if (distance !== 0) item.style.setProperty('--toast-swipe', `${distance > 0 ? '110%' : '-110%'}`);
            item.classList.add('is-leaving');
            window.setTimeout(remove, 180);
        };
        let pointerStart = null;
        item.addEventListener('pointerdown', (event) => { pointerStart = event.clientX; });
        item.addEventListener('pointerup', (event) => {
            if (pointerStart === null) return;
            const distance = event.clientX - pointerStart;
            pointerStart = null;
            if (Math.abs(distance) >= 60) dismiss(distance);
        });
        item.addEventListener('pointercancel', () => { pointerStart = null; });
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
        window.setTimeout(() => dismiss(), timeout);
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
        const menus = document.querySelectorAll('.account-menu');
        if (menus.length === 0) return;
        document.addEventListener('click', (event) => {
            menus.forEach((menu) => { if (!menu.contains(event.target)) menu.open = false; });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            menus.forEach((menu) => {
                if (!menu.open) return;
                menu.open = false;
                menu.querySelector('summary')?.focus();
            });
        });
    };

    /* ---------- Cross-page notifications ---------- */
    const initNotifications = () => {
        const root = document.querySelector('[data-notification-menu]');
        if (!root) return;
        const list = root.querySelector('[data-notification-list]');
        const count = root.querySelector('[data-notification-count]');
        const unreadLabel = root.querySelector('[data-notification-unread-label]');
        let refreshing = false;
        const post = async (url, values = {}) => {
            const body = new URLSearchParams({ csrf_token: root.dataset.csrf, ...values });
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body,
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('notification_action_failed');
            return response.json();
        };
        const actionButton = (label, callback, tone = 'secondary') => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `btn btn-${tone} btn-sm`;
            button.textContent = label;
            button.addEventListener('click', callback);
            return button;
        };
        const notificationPreview = (notification) => {
            const sender = notification.sender_username ? ` dari ${notification.sender_username}` : '';
            const letter = notification.surat_id ? ` untuk surat #${notification.surat_id}` : '';
            if (notification.kind === 'EDIT_REQUEST') return `Permintaan edit${sender}${letter}`;
            if (notification.kind === 'PENGAJUAN') return `Pengajuan baru${sender}${letter}`;
            if (notification.kind === 'SURAT_BARU') return `Surat masuk baru${letter}`;
            return `Notifikasi baru${sender}${letter}`;
        };
        const refresh = async () => {
            if (refreshing) return;
            refreshing = true;
            try {
                const response = await fetch(root.dataset.listUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!response.ok) return;
                const result = await response.json();
                const items = Array.isArray(result.items) ? result.items : [];
                const unread = items.filter((item) => Number(item.is_read) === 0);
                if (count) {
                    count.textContent = String(unread.length);
                    count.hidden = unread.length === 0;
                }
                if (unreadLabel) unreadLabel.textContent = `${unread.length} belum dibaca`;
                if (list) {
                    list.replaceChildren();
                    if (items.length === 0) {
                        const empty = document.createElement('p');
                        empty.className = 'notification-empty';
                        empty.textContent = 'Belum ada notifikasi.';
                        list.append(empty);
                    }
                    items.forEach((notification) => {
                        const row = document.createElement('article');
                        row.className = `notification-item${Number(notification.is_read) === 0 ? ' is-unread' : ''}`;
                        row.setAttribute('role', 'listitem');
                        const message = document.createElement('p');
                        message.className = 'notification-item__message';
                        message.textContent = notificationPreview(notification);
                        row.append(message);
                        if (notification.sender_username) {
                            const sender = document.createElement('span');
                            sender.className = 'notification-item__meta';
                            sender.textContent = `Dari ${notification.sender_username}`;
                            row.append(sender);
                        }
                        const actions = document.createElement('div');
                        actions.className = 'notification-item__actions';
                        const pendingEdit = notification.kind === 'EDIT_REQUEST' && notification.request_status === 'PENDING';
                        if (pendingEdit && root.dataset.isAdmin === '1') {
                            for (const [decision, label, tone] of [['approve', 'Setujui', 'primary'], ['reject', 'Tolak', 'quiet-danger']]) {
                                actions.append(actionButton(label, async () => {
                                    try {
                                        await post(`${root.dataset.decisionBase}/${encodeURIComponent(notification.edit_request_id)}/keputusan`, { decision });
                                        await refresh();
                                    } catch { toast('Keputusan tidak dapat disimpan. Muat ulang halaman dan coba lagi.'); }
                                }, tone));
                            }
                        }
                        if (!pendingEdit) {
                            const view = document.createElement('a');
                            view.className = 'notification-item__link';
                            view.href = `${root.dataset.historyUrl}?id=${encodeURIComponent(notification.id)}#notification-${encodeURIComponent(notification.id)}`;
                            view.textContent = 'Buka detail notifikasi';
                            actions.append(view);
                        }
                        row.append(actions);
                        list.append(row);
                    });
                }
                // Popup ditentukan server (per sesi login), jadi refresh halaman tidak mengulang popup.
                const popup = Array.isArray(result.popup) ? result.popup : [];
                popup.slice(0, 5).forEach((notification) => {
                    toast(notificationPreview(notification), {
                        timeout: 5000,
                        actionLabel: 'Lihat',
                        onAction: () => window.location.assign(`${root.dataset.historyUrl}?id=${encodeURIComponent(notification.id)}#notification-${encodeURIComponent(notification.id)}`),
                    });
                });
            } catch {
                // Keep the rest of the page usable when notification polling is unavailable.
            } finally {
                refreshing = false;
            }
        };
        refresh();
        window.setInterval(() => {
            if (!document.hidden) refresh();
        }, 20000);
        document.addEventListener('visibilitychange', refresh);
    };

    const initPostSaveConfirmation = () => {
        const savedLetter = document.querySelector('[data-post-save-confirm]');
        if (!savedLetter) return;
        if (window.confirm('Surat berhasil disimpan. Buka detail surat sekarang?')) {
            window.location.assign(savedLetter.dataset.detailUrl);
        }
    };

    const initBulkSelection = () => {
        const selectAll = document.querySelector('[data-select-all]');
        const checkboxes = [...document.querySelectorAll('[data-select-letter]')];
        const toolbar = document.querySelector('[data-bulk-actions]');
        const selectedCount = document.querySelector('[data-selected-count]');
        if (!selectAll || checkboxes.length === 0 || !toolbar) return;
        const update = () => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            toolbar.hidden = selected === 0;
            if (selectedCount) selectedCount.textContent = String(selected);
            selectAll.checked = selected > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
        };
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            update();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));
        update();
    };

    const initExportDialog = () => {
        const dialog = document.querySelector('[data-export-dialog]');
        const openButton = document.querySelector('[data-export-open]');
        const form = dialog?.querySelector('[data-export-form]');
        const idField = dialog?.querySelector('[data-export-id]');
        const searchButton = dialog?.querySelector('[data-export-search]');
        const status = dialog?.querySelector('[data-export-search-status]');
        const results = dialog?.querySelector('[data-export-results]');
        const selectedList = dialog?.querySelector('[data-export-selected-list]');
        const selectedCount = dialog?.querySelector('[data-export-selected-count]');
        if (!dialog || !openButton || !form || !idField || !searchButton || !status || !results || !selectedList) return;

        const selected = new Map();
        const closeButtons = dialog.querySelectorAll('[data-export-close]');
        const renderSelected = () => {
            selectedList.replaceChildren();
            if (selectedCount) selectedCount.textContent = String(selected.size);
            if (selected.size === 0) {
                const empty = document.createElement('li');
                empty.className = 'export-empty';
                empty.textContent = 'Belum ada surat dipilih.';
                selectedList.append(empty);
                return;
            }

            selected.forEach((letter, id) => {
                const row = document.createElement('li');
                row.className = 'export-selected__item';
                const label = document.createElement('span');
                label.textContent = `#${id} · B-${String(letter.no).padStart(4, '0')} · ${letter.surat_dari || 'Asal tidak diisi'}`;
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'selected_ids[]';
                hiddenId.value = String(id);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-secondary btn-sm';
                remove.textContent = 'Lepas';
                remove.setAttribute('aria-label', `Lepas surat #${id} dari ekspor`);
                remove.addEventListener('click', () => {
                    selected.delete(id);
                    renderSelected();
                    const checkbox = results.querySelector(`[data-export-result-id="${id}"]`);
                    if (checkbox) checkbox.checked = false;
                    status.textContent = `Surat #${id} dilepas dari daftar ekspor.`;
                });
                row.append(label, hiddenId, remove);
                selectedList.append(row);
            });
        };

        const search = async () => {
            const id = idField.value.trim();
            if (!/^[1-9]\d*$/.test(id)) {
                status.textContent = 'Masukkan ID surat berupa angka positif.';
                results.replaceChildren();
                idField.focus();
                return;
            }

            searchButton.disabled = true;
            status.textContent = 'Mencari surat…';
            results.replaceChildren();
            try {
                const searchUrl = new URL(dialog.dataset.searchUrl, window.location.href);
                searchUrl.searchParams.set('id', id);
                const response = await fetch(searchUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Pencarian surat gagal.');
                const letter = result.item;
                const item = document.createElement('li');
                const label = document.createElement('label');
                label.className = 'export-search-result';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = selected.has(Number(letter.id));
                checkbox.setAttribute('data-export-result-id', String(letter.id));
                checkbox.setAttribute('aria-label', `Pilih surat #${letter.id}`);
                const text = document.createElement('span');
                const heading = document.createElement('strong');
                heading.textContent = `#${letter.id} · B-${String(letter.no).padStart(4, '0')}`;
                const detail = document.createElement('small');
                detail.textContent = `${letter.tanggal_masuk || 'Tanggal tidak diisi'} · ${letter.surat_dari || 'Asal tidak diisi'}`;
                const subject = document.createElement('span');
                subject.textContent = letter.hal || 'Hal surat tidak diisi';
                text.append(heading, detail, subject);
                label.append(checkbox, text);
                item.append(label);
                results.append(item);
                checkbox.addEventListener('change', () => {
                    const letterId = Number(letter.id);
                    if (checkbox.checked && !selected.has(letterId) && selected.size >= 500) {
                        checkbox.checked = false;
                        status.textContent = 'Ekspor pilihan dibatasi maksimal 500 surat.';
                        return;
                    }
                    if (checkbox.checked) selected.set(letterId, letter);
                    else selected.delete(letterId);
                    renderSelected();
                    status.textContent = checkbox.checked ? 'Surat ditambahkan ke daftar ekspor.' : 'Surat dilepas dari daftar ekspor.';
                });
                status.textContent = 'Pilih surat ini untuk memasukkannya ke ekspor.';
            } catch (error) {
                status.textContent = error instanceof Error ? error.message : 'Pencarian surat gagal.';
            } finally {
                searchButton.disabled = false;
            }
        };

        const weekField = dialog.querySelector('[data-export-week]');
        const monthField = dialog.querySelector('[data-export-month]');
        const weekInput = dialog.querySelector('[data-export-week-input]');
        const weekHint = dialog.querySelector('[data-export-week-hint]');
        const formatDay = (date) => date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const updateWeekHint = () => {
            if (!weekInput || !weekHint) return;
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(weekInput.value);
            if (!match) {
                weekHint.textContent = 'Pilih tanggal mana pun di minggu yang diinginkan.';
                return;
            }
            const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            const monday = new Date(date);
            monday.setDate(date.getDate() - ((date.getDay() + 6) % 7));
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            weekHint.textContent = `Minggu yang diekspor: Senin ${formatDay(monday)} sampai Minggu ${formatDay(sunday)}.`;
        };
        const updateRangeFields = () => {
            const range = form.querySelector('input[name="range"]:checked')?.value;
            if (weekField) weekField.hidden = range !== 'week';
            if (monthField) monthField.hidden = range !== 'month';
        };
        form.querySelectorAll('input[name="range"]').forEach((radio) => radio.addEventListener('change', updateRangeFields));
        weekInput?.addEventListener('input', updateWeekHint);
        updateRangeFields();
        updateWeekHint();

        openButton.addEventListener('click', () => {
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
            (form.querySelector('input[name="range"]:checked') ?? idField).focus();
        });
        closeButtons.forEach((button) => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        form.addEventListener('submit', () => {
            window.setTimeout(() => {
                if (dialog.open) dialog.close();
            }, 0);
        });
        searchButton.addEventListener('click', search);
        idField.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                search();
            }
        });
        renderSelected();
    };

    const initNotificationHistory = () => {
        const root = document.querySelector('[data-notification-menu]');
        const forms = document.querySelectorAll('[data-notification-review-form]');
        if (!root || forms.length === 0) return;
        forms.forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const body = new FormData(form);
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body,
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (!response.ok) throw new Error('decision_failed');
                    window.location.reload();
                } catch {
                    toast('Keputusan tidak dapat disimpan. Muat ulang halaman dan coba lagi.');
                }
            });
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
            let message = form.dataset.confirmMessage || '';
            const confirmationField = form.dataset.confirmInput
                ? form.querySelector(`[name="${form.dataset.confirmInput}"]`)
                : null;
            if (confirmationField && confirmationField.value.trim() !== '') {
                message += `\n\nPesan yang akan dikirim:\n${confirmationField.value.trim()}`;
            }
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

        const MAX_TARGETS = 5;
        const targetTemplate = document.querySelector('[data-target-template]');
        const countLabel = list.parentElement.querySelector('[data-disposition-count]');
        const completionOptions = [...list.parentElement.querySelectorAll('.completion-option input[type="radio"]')];
        const completionProof = list.parentElement.querySelector('[data-completion-proof]');
        const completionRadios = list.parentElement.querySelectorAll('input[name="selesai_tahap"]');
        const updateCompletionProof = () => {
            const selected = list.parentElement.querySelector('input[name="selesai_tahap"]:checked');
            const complete = selected && selected.value !== '0';
            if (completionProof) {
                completionProof.hidden = !complete;
                if (!complete) {
                    const field = completionProof.querySelector('input');
                    if (field) field.value = '';
                }
            }
        };
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
                const targetRows = [...step.querySelectorAll('[data-target-row]')];
                targetRows.forEach((row, rowIndex) => {
                    const field = row.querySelector('[data-disposition-target]');
                    field.id = `d${stage}_tujuan_${rowIndex + 1}`;
                    field.name = `disposisi[${stage}][tujuan][]`;
                    field.setAttribute('aria-label', `Tujuan ${rowIndex + 1} disposisi ${stage}`);
                    row.querySelector('[data-remove-target]')?.setAttribute('aria-label', `Hapus tujuan ${rowIndex + 1} disposisi ${stage}`);
                });
                const addTarget = step.querySelector('[data-add-target]');
                if (addTarget) addTarget.disabled = targetRows.length >= MAX_TARGETS;
                const targetCount = step.querySelector('[data-target-count]');
                if (targetCount) targetCount.textContent = `${targetRows.length} dari ${MAX_TARGETS} tujuan`;
            });
            addButton.disabled = steps.length >= 5;
            completionOptions.forEach((option) => {
                option.disabled = Number(option.value) > steps.length;
                if (option.disabled && option.checked) {
                    const pending = list.parentElement.querySelector('input[type="radio"][name="selesai_tahap"][value="0"]');
                    if (pending) pending.checked = true;
                }
            });
            if (countLabel) countLabel.textContent = `${steps.length} dari 5 tahap`;
            updateCompletionProof();
        };

        completionRadios.forEach((radio) => radio.addEventListener('change', updateCompletionProof));

        /* Tujuan manual: tambah/hapus baris, maksimal 5 per tahap. */
        const addTargetRow = (group, value = '') => {
            const targetList = group.querySelector('[data-target-list]');
            if (!targetList || targetList.querySelectorAll('[data-target-row]').length >= MAX_TARGETS) return null;
            const source = targetTemplate?.content.firstElementChild ?? targetList.querySelector('[data-target-row]');
            if (!source) return null;
            const row = source.cloneNode(true);
            const input = row.querySelector('[data-disposition-target]');
            input.value = value;
            targetList.append(row);
            return input;
        };

        list.addEventListener('click', (event) => {
            const addTarget = event.target.closest('[data-add-target]');
            if (addTarget) {
                const input = addTargetRow(addTarget.closest('[data-target-group]'));
                update();
                input?.focus();
                return;
            }
            const removeTarget = event.target.closest('[data-remove-target]');
            if (!removeTarget) return;
            const row = removeTarget.closest('[data-target-row]');
            const group = removeTarget.closest('[data-target-group]');
            const rows = group.querySelectorAll('[data-target-row]');
            if (rows.length <= 1) {
                const only = row.querySelector('[data-disposition-target]');
                only.value = '';
                only.focus();
                return;
            }
            const previous = row.previousElementSibling ?? row.nextElementSibling;
            row.remove();
            update();
            previous?.querySelector('[data-disposition-target]')?.focus();
        });

        list.addEventListener('keydown', (event) => {
            const field = event.target.closest?.('[data-disposition-target]');
            if (!field || event.key !== 'Enter') return;
            event.preventDefault();
            if (field.value.trim() === '') return;
            const input = addTargetRow(field.closest('[data-target-group]'));
            update();
            input?.focus();
        });

        // Koma atau titik koma memisahkan beberapa tujuan: pecah menjadi baris terpisah.
        list.addEventListener('change', (event) => {
            const field = event.target.closest?.('[data-disposition-target]');
            if (!field || !/[,;]/.test(field.value)) return;
            const parts = field.value.split(/[,;]/).map((part) => part.trim().replace(/\s+/g, ' ')).filter(Boolean);
            const group = field.closest('[data-target-group]');
            field.value = parts.shift() ?? '';
            let dropped = 0;
            parts.forEach((part) => {
                if (!addTargetRow(group, part)) dropped += 1;
            });
            update();
            if (dropped > 0) toast(`Maksimal ${MAX_TARGETS} tujuan per disposisi. ${dropped} tujuan berlebih tidak ditambahkan.`);
        });

        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-disposition]');
            if (!removeButton) return;
            const step = removeButton.closest('[data-disposition-step]');
            const removedStage = [...list.querySelectorAll('[data-disposition-step]')].indexOf(step) + 1;
            const selectedCompletion = list.parentElement.querySelector('input[name="selesai_tahap"]:checked');
            const previousCompletion = selectedCompletion?.value || '0';
            const following = step.nextElementSibling;
            step.remove();
            if (Number(previousCompletion) >= removedStage) {
                const pending = list.parentElement.querySelector('input[type="radio"][name="selesai_tahap"][value="0"]');
                if (pending) pending.checked = true;
            }
            update();
            toast('Tahap disposisi dihapus. Perubahan baru tersimpan setelah Anda menyimpan surat.', {
                actionLabel: 'Urungkan',
                timeout: 8000,
                onAction: () => {
                    if (list.querySelectorAll('[data-disposition-step]').length >= 5) return;
                    list.insertBefore(step, following && following.parentNode === list ? following : null);
                    update();
                    if (Number(previousCompletion) > 0) {
                        const restored = list.parentElement.querySelector(`input[name="selesai_tahap"][value="${previousCompletion}"]`);
                        if (restored && !restored.disabled) {
                            restored.checked = true;
                            updateCompletionProof();
                        }
                    }
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

    /* ---------- Feedback photos: up to 5, previews, client-side downscale ---------- */
    const initFeedbackPhotos = () => {
        const box = document.querySelector('[data-feedback-photos]');
        const input = box?.querySelector('input[type="file"]');
        const listEl = box?.querySelector('[data-photo-list]');
        if (!box || !input || !listEl || typeof DataTransfer === 'undefined') return;
        const maxFiles = Number(box.dataset.maxFiles) || 5;
        const maxBytes = Number(box.dataset.maxBytes) || 5242880;
        const maxTotal = Number(box.dataset.maxTotal) || 12582912;
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        let files = [];
        const size = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

        const shrink = (file) => new Promise((resolve) => {
            if (file.size <= 1048576) { resolve(file); return; }
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(url);
                const scale = Math.min(1, 1920 / Math.max(img.width, img.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                const ctx = canvas.getContext('2d');
                if (!ctx) { resolve(file); return; }
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => {
                    if (!blob || blob.size >= file.size) { resolve(file); return; }
                    resolve(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
                }, 'image/jpeg', 0.82);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });

        const sync = () => {
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            listEl.replaceChildren();
            files.forEach((file, index) => {
                const li = document.createElement('li');
                const img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                const text = document.createElement('span');
                text.className = 'photo-name';
                text.textContent = file.name;
                const meta = document.createElement('span');
                meta.className = 'photo-size';
                meta.textContent = size(file.size);
                text.append(meta);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-secondary btn-sm';
                remove.textContent = 'Hapus';
                remove.setAttribute('aria-label', `Hapus foto ${file.name}`);
                remove.addEventListener('click', () => { files.splice(index, 1); sync(); });
                li.append(img, text, remove);
                listEl.append(li);
            });
        };

        input.addEventListener('change', async () => {
            const picked = [...input.files];
            const problems = [];
            for (const original of picked) {
                if (files.length >= maxFiles) { problems.push(`Maksimal ${maxFiles} foto.`); break; }
                if (!allowed.includes(original.type)) { problems.push(`${original.name}: format harus JPEG, PNG, atau WebP.`); continue; }
                const file = await shrink(original);
                if (file.size > maxBytes) { problems.push(`${original.name}: lebih dari 5 MB.`); continue; }
                if (files.reduce((sum, item) => sum + item.size, 0) + file.size > maxTotal) { problems.push('Total ukuran foto melebihi 12 MB.'); break; }
                files.push(file);
            }
            sync();
            if (problems.length) toast([...new Set(problems)].join(' '));
        });
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
        initNotifications();
        initPostSaveConfirmation();
        initBulkSelection();
        initExportDialog();
        initNotificationHistory();
        initSearchShortcut();
        initNetworkReadings();
        initDeveloperMetrics();
        initConfirmDialog();
        initLoadingButtons();
        initAlerts();
        initValidation();
        initPasswordToggle();
        initDispositions();
        initFeedbackPhotos();
        initManualSync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
