(function () {
    'use strict';

    var STORAGE_KEY = 'px_cookie_consent';
    var notice = document.getElementById('cookie-notice');
    var acceptBtn = document.getElementById('cookie-accept');

    function hasConsent() {
        try {
            return localStorage.getItem(STORAGE_KEY) === 'accepted';
        } catch (e) {
            return false;
        }
    }

    function hideNotice() {
        if (notice) {
            notice.classList.add('is-hidden');
            notice.setAttribute('aria-hidden', 'true');
        }
    }

    function acceptCookies() {
        try {
            localStorage.setItem(STORAGE_KEY, 'accepted');
        } catch (e) {
            /* ignore quota / private mode */
        }
        hideNotice();
    }

    if (hasConsent()) {
        hideNotice();
    } else if (acceptBtn) {
        acceptBtn.addEventListener('click', acceptCookies);
    }

    var header = document.querySelector('.site-header');
    var onScroll = function () {
        if (!header) {
            return;
        }
        header.style.boxShadow = window.scrollY > 10 ? '0 4px 24px rgba(0,0,0,.4)' : 'none';
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    document.querySelectorAll('.site-ticker').forEach(function (ticker) {
        var viewport = ticker.querySelector('.site-ticker__viewport');
        var track = ticker.querySelector('.site-ticker__track');
        if (!viewport || !track) {
            return;
        }

        track.style.animation = 'none';

        var offset = 0;
        var half = 0;
        var last = 0;
        var pxPerSec = 42;

        function measure() {
            half = track.scrollWidth / 2;
            if (half <= 0) {
                return;
            }
            offset = offset % half;
            track.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
        }

        function frame(now) {
            if (!last) {
                last = now;
            }
            var delta = Math.min(48, now - last) / 1000;
            last = now;
            if (half > 0) {
                // Rola para a esquerda: itens próximos (início) → mais distantes
                offset = (offset + pxPerSec * delta) % half;
                track.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
            }
            window.requestAnimationFrame(frame);
        }

        measure();
        window.addEventListener('resize', measure);
        window.requestAnimationFrame(frame);
    });

    document.querySelectorAll('.px-banner').forEach(function (slot) {
        var slides = Array.prototype.slice.call(slot.querySelectorAll('.px-banner__item'));
        if (slides.length === 0) {
            return;
        }
        if (slides.length === 1) {
            slides[0].classList.add('is-active');
            return;
        }

        var index = 0;
        var pairIndex = 0;
        var timer = null;

        function bannerWidthPx() {
            var fromData = parseInt(slot.getAttribute('data-px-width') || '', 10);
            if (fromData > 0) {
                return fromData;
            }
            return parseInt(window.getComputedStyle(slot).getPropertyValue('--px-w'), 10) || 0;
        }

        function canPack() {
            var w = bannerWidthPx();
            if (w <= 0) {
                return false;
            }
            return slot.clientWidth >= w * 2 + 12;
        }

        function clearTimer() {
            if (timer) {
                window.clearTimeout(timer);
                timer = null;
            }
        }

        function pairDuration() {
            var a = slides[pairIndex];
            var b = slides[(pairIndex + 1) % slides.length];
            var da = parseInt((a && a.getAttribute('data-duration')) || '5', 10) || 5;
            var db = parseInt((b && b.getAttribute('data-duration')) || '5', 10) || 5;
            return Math.max(1, Math.max(da, db)) * 1000;
        }

        function showPair() {
            slides.forEach(function (slide) {
                slide.classList.remove('is-active');
                slide.classList.remove('is-pack-visible');
            });
            var first = slides[pairIndex];
            var second = slides[(pairIndex + 1) % slides.length];
            if (first) {
                first.classList.add('is-pack-visible');
                first.classList.add('is-active');
            }
            if (second && second !== first) {
                second.classList.add('is-pack-visible');
                second.classList.add('is-active');
            }
        }

        function schedulePair() {
            clearTimer();
            if (slides.length <= 2) {
                return;
            }
            timer = window.setTimeout(function () {
                pairIndex = (pairIndex + 2) % slides.length;
                showPair();
                schedulePair();
            }, pairDuration());
        }

        function show(next) {
            slides[index].classList.remove('is-active');
            index = next;
            slides[index].classList.add('is-active');
            schedule();
        }

        function schedule() {
            clearTimer();
            var seconds = parseInt(slides[index].getAttribute('data-duration') || '5', 10);
            if (!seconds || seconds < 1) {
                seconds = 5;
            }
            timer = window.setTimeout(function () {
                show((index + 1) % slides.length);
            }, seconds * 1000);
        }

        function applyPackMode() {
            if (canPack()) {
                slot.classList.add('is-pack');
                showPair();
                schedulePair();
                return true;
            }
            slot.classList.remove('is-pack');
            slides.forEach(function (slide) {
                slide.classList.remove('is-pack-visible');
                slide.classList.remove('is-active');
            });
            if (!slides[index]) {
                index = 0;
            }
            slides[index].classList.add('is-active');
            schedule();
            return false;
        }

        function boot() {
            applyPackMode();
        }

        boot();
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(boot);
        });
        window.addEventListener('resize', function () {
            applyPackMode();
        });
    });

    document.querySelectorAll('[data-news-carousel]').forEach(function (carousel) {
        var slides = Array.prototype.slice.call(carousel.querySelectorAll('.news-carousel__slide'));
        var dots = Array.prototype.slice.call(carousel.querySelectorAll('.news-carousel__dot'));

        // Notícias com vídeo: 10 s (30s→40s, definidos no embed) e volta para a imagem.
        // Não depende de prefers-reduced-motion: Windows/Android ligam isso ao desativar animações do sistema.
        var VIDEO_PLAY_MS = 10000;
        var VIDEO_BUFFER_MS = 2000;

        function slideVideoThumb(slide) {
            if (!slide) {
                return null;
            }
            return slide.querySelector('[data-carousel-video]');
        }

        function stopVideo(slide) {
            var thumb = slideVideoThumb(slide);
            var holder = thumb ? thumb.querySelector('.card-thumb__video') : null;
            if (!holder) {
                return;
            }
            if (holder._videoTimer) {
                window.clearTimeout(holder._videoTimer);
                holder._videoTimer = null;
            }
            holder.classList.remove('is-playing');
            holder.innerHTML = '';
        }

        function startVideo(slide) {
            var thumb = slideVideoThumb(slide);
            var holder = thumb ? thumb.querySelector('.card-thumb__video') : null;
            if (!holder || holder.firstChild) {
                return;
            }
            var iframe = document.createElement('iframe');
            iframe.src = thumb.getAttribute('data-carousel-video') || '';
            iframe.setAttribute('allow', 'autoplay; encrypted-media');
            iframe.setAttribute('tabindex', '-1');
            iframe.setAttribute('title', '');
            iframe.addEventListener('load', function () {
                window.setTimeout(function () {
                    holder.classList.add('is-playing');
                }, 700);
                holder._videoTimer = window.setTimeout(function () {
                    stopVideo(slide);
                }, VIDEO_PLAY_MS + 700);
            });
            holder.appendChild(iframe);
        }

        if (slides.length < 2) {
            startVideo(slides[0]);
            return;
        }

        var index = 0;
        var touchStartX = null;
        var timer = null;
        var intervalMs = 5500;
        var paused = false;

        function show(next) {
            if (next === index || next < 0 || next >= slides.length) {
                return;
            }
            stopVideo(slides[index]);
            slides[index].classList.remove('is-active');
            dots[index].classList.remove('is-active');
            dots[index].setAttribute('aria-selected', 'false');
            index = next;
            slides[index].classList.add('is-active');
            dots[index].classList.add('is-active');
            dots[index].setAttribute('aria-selected', 'true');
            startVideo(slides[index]);
        }

        function stop() {
            if (timer) {
                window.clearTimeout(timer);
                timer = null;
            }
        }

        function schedule() {
            stop();
            if (paused) {
                return;
            }
            var delay = slideVideoThumb(slides[index]) ? VIDEO_PLAY_MS + VIDEO_BUFFER_MS : intervalMs;
            timer = window.setTimeout(function () {
                show((index + 1) % slides.length);
                schedule();
            }, delay);
        }

        function goTo(next) {
            show(next);
            schedule();
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var target = parseInt(dot.getAttribute('data-carousel-index') || '-1', 10);
                if (target >= 0) {
                    goTo(target);
                }
            });
        });

        carousel.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                goTo((index + 1) % slides.length);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goTo((index - 1 + slides.length) % slides.length);
            }
        });

        carousel.addEventListener('mouseenter', function () {
            paused = true;
            stop();
        });

        carousel.addEventListener('mouseleave', function () {
            paused = false;
            schedule();
        });

        carousel.addEventListener('focusin', function () {
            paused = true;
            stop();
        });

        carousel.addEventListener('focusout', function (event) {
            if (!carousel.contains(event.relatedTarget)) {
                paused = false;
                schedule();
            }
        });

        carousel.addEventListener('touchstart', function (event) {
            paused = true;
            stop();
            if (event.changedTouches && event.changedTouches[0]) {
                touchStartX = event.changedTouches[0].clientX;
            }
        }, { passive: true });

        carousel.addEventListener('touchend', function (event) {
            if (touchStartX === null || !event.changedTouches || !event.changedTouches[0]) {
                paused = false;
                schedule();
                return;
            }
            var delta = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(delta) < 40) {
                paused = false;
                schedule();
                return;
            }
            if (delta < 0) {
                goTo((index + 1) % slides.length);
            } else {
                goTo((index - 1 + slides.length) % slides.length);
            }
            paused = false;
        }, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else if (!paused) {
                schedule();
            }
        });

        startVideo(slides[index]);
        schedule();
    });

    document.querySelectorAll('[data-gp-panel]').forEach(function (panel) {
        var root = panel.querySelector('[data-gp-filters]');
        var grid = panel.querySelector('[data-gp-grid]');
        var empty = panel.querySelector('[data-gp-empty]');
        var clearBtn = panel.querySelector('[data-gp-clear]');
        var monthRoot = panel.querySelector('[data-gp-month]');
        var monthLabel = panel.querySelector('[data-gp-month-label]');
        var prevBtn = panel.querySelector('[data-gp-month-prev]');
        var nextBtn = panel.querySelector('[data-gp-month-next]');
        var serviceRoot = panel.querySelector('[data-gp-service]');
        var serviceChips = serviceRoot
            ? Array.prototype.slice.call(serviceRoot.querySelectorAll('[data-service-filter]'))
            : [];
        var chips = root ? Array.prototype.slice.call(root.querySelectorAll('.gp-filter')) : [];
        if (!grid) {
            return;
        }

        var todayGroup = panel.querySelector('[data-today-card]');
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.cal-card'));
        var monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        try {
            var fromPanel = JSON.parse(panel.getAttribute('data-month-names') || '[]');
            if (Array.isArray(fromPanel) && fromPanel.length === 12) {
                monthNames = fromPanel;
            }
        } catch (err) {
            // keep PT defaults
        }
        var selectedService = 'all';

        function parseMonthKey(key) {
            var parts = String(key || '').split('-');
            if (parts.length < 2) {
                return null;
            }
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            if (!year || !month || month < 1 || month > 12) {
                return null;
            }
            return { year: year, month: month };
        }

        function formatMonthKey(year, month) {
            return year + '-' + String(month).padStart(2, '0');
        }

        function shiftMonth(key, delta) {
            var parsed = parseMonthKey(key);
            if (!parsed) {
                return key;
            }
            var date = new Date(parsed.year, parsed.month - 1 + delta, 1);
            return formatMonthKey(date.getFullYear(), date.getMonth() + 1);
        }

        function monthLabelText(key) {
            var parsed = parseMonthKey(key);
            if (!parsed) {
                return key;
            }
            return monthNames[parsed.month - 1] + ' - ' + parsed.year;
        }

        var availableMonths = cards
            .map(function (card) { return card.getAttribute('data-month') || ''; })
            .filter(Boolean)
            .sort();
        availableMonths = availableMonths.filter(function (value, index, arr) {
            return arr.indexOf(value) === index;
        });

        var selectedMonth = monthRoot ? (monthRoot.getAttribute('data-month') || '') : '';
        if (!selectedMonth) {
            var now = new Date();
            selectedMonth = formatMonthKey(now.getFullYear(), now.getMonth() + 1);
        }
        if (availableMonths.length > 0 && availableMonths.indexOf(selectedMonth) === -1) {
            selectedMonth = availableMonths[0];
        }

        function selectedValues(type) {
            return chips
                .filter(function (chip) {
                    return chip.getAttribute('data-filter-type') === type
                        && chip.classList.contains('is-active');
                })
                .map(function (chip) {
                    return chip.getAttribute('data-filter-value') || '';
                })
                .filter(Boolean);
        }

        function matchesAny(haystack, needles) {
            if (needles.length === 0) {
                return true;
            }
            return needles.some(function (needle) {
                return haystack.indexOf(needle) !== -1;
            });
        }

        function updateMonthUi() {
            if (monthRoot) {
                monthRoot.setAttribute('data-month', selectedMonth);
            }
            if (monthLabel) {
                monthLabel.textContent = monthLabelText(selectedMonth);
            }
            if (prevBtn) {
                if (availableMonths.length > 0) {
                    prevBtn.disabled = selectedMonth <= availableMonths[0];
                } else {
                    prevBtn.disabled = false;
                }
            }
            if (nextBtn) {
                if (availableMonths.length > 0) {
                    nextBtn.disabled = selectedMonth >= availableMonths[availableMonths.length - 1];
                } else {
                    nextBtn.disabled = false;
                }
            }
        }

        function applyFilters() {
            var subs = selectedValues('sub');
            var platforms = selectedValues('platform');
            var activeCount = subs.length + platforms.length;
            var visible = 0;

            cards.forEach(function (card) {
                var cardSubs = (card.getAttribute('data-subs') || '').split(/\s+/).filter(Boolean);
                var cardPlatforms = (card.getAttribute('data-platforms') || '').split(/\s+/).filter(Boolean);
                var cardMonth = card.getAttribute('data-month') || '';
                var cardGp = card.getAttribute('data-gamepass') === '1';
                var serviceOk = selectedService === 'all'
                    || (selectedService === 'yes' && cardGp)
                    || (selectedService === 'no' && !cardGp);
                var ok = cardMonth === selectedMonth
                    && serviceOk
                    && matchesAny(cardSubs, subs)
                    && matchesAny(cardPlatforms, platforms);
                card.classList.toggle('is-filtered-out', !ok);
                if (ok) {
                    visible += 1;
                }
            });

            if (todayGroup) {
                var todayMonth = todayGroup.getAttribute('data-month') || '';
                var todayMonthOk = todayMonth === selectedMonth;
                var todayVisible = 0;
                todayGroup.querySelectorAll('.cal-card').forEach(function (card) {
                    if (!card.classList.contains('is-filtered-out')) {
                        todayVisible += 1;
                    }
                });
                todayGroup.classList.toggle('is-filtered-out', !todayMonthOk || todayVisible === 0);
            }

            if (clearBtn) {
                clearBtn.hidden = activeCount === 0;
            }
            if (empty) {
                empty.hidden = visible !== 0;
            }
            grid.hidden = visible === 0;
            updateMonthUi();
        }

        serviceChips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                selectedService = chip.getAttribute('data-service-filter') || 'all';
                serviceChips.forEach(function (other) {
                    var active = other === chip;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                applyFilters();
            });
        });

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var active = chip.classList.toggle('is-active');
                chip.setAttribute('aria-pressed', active ? 'true' : 'false');
                applyFilters();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                chips.forEach(function (chip) {
                    chip.classList.remove('is-active');
                    chip.setAttribute('aria-pressed', 'false');
                });
                applyFilters();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (availableMonths.length > 0) {
                    var index = availableMonths.indexOf(selectedMonth);
                    if (index > 0) {
                        selectedMonth = availableMonths[index - 1];
                    } else if (index === -1) {
                        selectedMonth = availableMonths[0];
                    }
                } else {
                    selectedMonth = shiftMonth(selectedMonth, -1);
                }
                applyFilters();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (availableMonths.length > 0) {
                    var index = availableMonths.indexOf(selectedMonth);
                    if (index >= 0 && index < availableMonths.length - 1) {
                        selectedMonth = availableMonths[index + 1];
                    } else if (index === -1) {
                        selectedMonth = availableMonths[availableMonths.length - 1];
                    }
                } else {
                    selectedMonth = shiftMonth(selectedMonth, 1);
                }
                applyFilters();
            });
        }

        applyFilters();
    });

    document.querySelectorAll('[data-cal-shell]').forEach(function (shell) {
        var switchRoot = shell.querySelector('[data-cal-view-switch]');
        var buttons = switchRoot
            ? Array.prototype.slice.call(switchRoot.querySelectorAll('[data-cal-view]'))
            : [];
        var monthPanel = shell.querySelector('[data-cal-month-panel]');
        var monthBoard = shell.querySelector('[data-cal-month-board]');
        var monthEmpty = shell.querySelector('[data-cal-month-empty]');
        var monthNav = shell.querySelector('[data-cal-month-nav]');
        var monthLabel = shell.querySelector('[data-cal-month-label]');
        var prevMonthBtn = shell.querySelector('[data-cal-month-prev]');
        var nextMonthBtn = shell.querySelector('[data-cal-month-next]');
        var itemsNode = shell.querySelector('[data-cal-month-items]');
        var pop = shell.querySelector('[data-cal-month-pop]');
        var storageKey = 'px_cal_view';
        var desktopMq = window.matchMedia('(min-width: 901px)');
        var items = [];
        var popHideTimer = null;
        try {
            items = JSON.parse((itemsNode && itemsNode.textContent) || '[]');
            if (!Array.isArray(items)) {
                items = [];
            }
        } catch (err) {
            items = [];
        }

        var monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        var weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        var typeLabels = { game: 'Jogo', event: 'Evento', history: 'Histórico' };
        var uiLabels = {
            store: 'Loja do Xbox',
            trailer: 'Trailer',
            learn_more: 'Saber mais',
            today_prefix: 'HOJE',
            gp: 'Game Pass'
        };
        try {
            var names = JSON.parse(shell.getAttribute('data-month-names') || '[]');
            if (Array.isArray(names) && names.length === 12) {
                monthNames = names;
            }
        } catch (err) {}
        try {
            var days = JSON.parse(shell.getAttribute('data-weekdays') || '[]');
            if (Array.isArray(days) && days.length === 7) {
                weekdays = days;
            }
        } catch (err) {}
        try {
            var labels = JSON.parse(shell.getAttribute('data-type-labels') || '{}');
            if (labels && typeof labels === 'object') {
                typeLabels = labels;
            }
        } catch (err) {}
        try {
            var ui = JSON.parse(shell.getAttribute('data-ui-labels') || '{}');
            if (ui && typeof ui === 'object') {
                uiLabels = Object.assign(uiLabels, ui);
            }
        } catch (err) {}

        var todayIso = shell.getAttribute('data-today') || '';
        var selectedMonth = monthNav ? (monthNav.getAttribute('data-month') || '') : '';
        if (!selectedMonth) {
            var now = new Date();
            selectedMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        }

        function parseMonthKey(key) {
            var parts = String(key || '').split('-');
            if (parts.length < 2) {
                return null;
            }
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            if (!year || !month || month < 1 || month > 12) {
                return null;
            }
            return { year: year, month: month };
        }

        function formatMonthKey(year, month) {
            return year + '-' + String(month).padStart(2, '0');
        }

        function shiftMonth(key, delta) {
            var parsed = parseMonthKey(key);
            if (!parsed) {
                return key;
            }
            var date = new Date(parsed.year, parsed.month - 1 + delta, 1);
            return formatMonthKey(date.getFullYear(), date.getMonth() + 1);
        }

        function monthLabelText(key) {
            var parsed = parseMonthKey(key);
            if (!parsed) {
                return key;
            }
            return monthNames[parsed.month - 1] + ' - ' + parsed.year;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function itemsForDay(year, month, day) {
            var iso = formatMonthKey(year, month) + '-' + String(day).padStart(2, '0');
            return items.filter(function (item) {
                if (item.type === 'history') {
                    return Number(item.month) === month && Number(item.day) === day;
                }
                return String(item.date || '') === iso;
            });
        }

        function hideMonthPop() {
            if (!pop) {
                return;
            }
            pop.hidden = true;
            pop.classList.remove('is-visible');
            pop.innerHTML = '';
        }

        function scheduleHideMonthPop() {
            if (popHideTimer) {
                window.clearTimeout(popHideTimer);
            }
            popHideTimer = window.setTimeout(hideMonthPop, 160);
        }

        function cancelHideMonthPop() {
            if (popHideTimer) {
                window.clearTimeout(popHideTimer);
                popHideTimer = null;
            }
        }

        function buildMonthPopHtml(item) {
            var type = item.type || 'game';
            var title = String(item.title || '');
            var href = String(item.href || '#');
            var thumb = String(item.thumb || '');
            var body = String(item.body || '');
            var when = String(item.when || '');
            var store = String(item.store || '');
            var trailer = String(item.trailer || '');
            var external = String(item.external || '');
            var today = !!item.today;
            var html = '<article class="cal-month-pop__card cal-month-pop__card--' + escapeHtml(type) + '">';
            if (thumb) {
                html += '<div class="cal-month-pop__media"><img src="' + escapeHtml(thumb) + '" alt="" loading="lazy">';
                if (item.gp) {
                    html += '<span class="cal-month-pop__gp">' + escapeHtml(uiLabels.gp || 'Game Pass') + '</span>';
                }
                html += '</div>';
            }
            html += '<div class="cal-month-pop__body">';
            html += '<div class="cal-month-pop__type">' + escapeHtml(typeLabels[type] || type) + '</div>';
            if (today) {
                html += '<h4 class="cal-month-pop__title"><span class="cal-today-pill cal-today-title">'
                    + escapeHtml((uiLabels.today_prefix || 'HOJE') + ': ' + title)
                    + '</span></h4>';
            } else {
                html += '<h4 class="cal-month-pop__title">' + escapeHtml(title) + '</h4>';
            }
            if (when || store || trailer) {
                html += '<div class="cal-month-pop__meta">';
                if (when) {
                    html += '<span class="cal-month-pop__when">📅 ' + escapeHtml(when) + '</span>';
                }
                if (store) {
                    html += '<a class="cal-month-pop__store" href="' + escapeHtml(store)
                        + '" target="_blank" rel="noopener noreferrer">🛍️ '
                        + escapeHtml(uiLabels.store || 'Loja') + '</a>';
                }
                if (trailer) {
                    html += '<a class="cal-month-pop__trailer" href="' + escapeHtml(trailer)
                        + '" target="_blank" rel="noopener noreferrer" aria-label="'
                        + escapeHtml(uiLabels.trailer || 'Trailer') + '">▶</a>';
                }
                html += '</div>';
            }
            if (Array.isArray(item.subs) && item.subs.length) {
                html += '<div class="cal-badges">';
                item.subs.forEach(function (sub) {
                    html += '<span class="cal-badge">' + escapeHtml(sub) + '</span>';
                });
                html += '</div>';
            }
            if (Array.isArray(item.platforms) && item.platforms.length) {
                html += '<div class="cal-platforms">';
                item.platforms.forEach(function (platform) {
                    var icon = '';
                    if (platform.icon === 'xbox') {
                        icon = '<span class="icon-xbox" aria-hidden="true"></span> ';
                    } else if (platform.icon) {
                        icon = escapeHtml(platform.icon) + ' ';
                    }
                    html += '<span>' + icon + escapeHtml(platform.label || '') + '</span>';
                });
                html += '</div>';
            }
            if (body) {
                html += '<p class="cal-month-pop__excerpt">' + escapeHtml(body) + '</p>';
            }
            if (item.rewards && item.rewards.label) {
                html += '<span class="cal-rewards cal-rewards--pop">'
                    + '<span class="cal-rewards__medal" title="' + escapeHtml(item.rewards.tip || '')
                    + '" aria-label="' + escapeHtml(item.rewards.tip || '') + '">🏅</span>'
                    + '<span class="cal-rewards__points">' + escapeHtml(item.rewards.label) + '</span>'
                    + '</span>';
            }
            if (type === 'event' && external) {
                html += '<a class="cal-month-pop__more" href="' + escapeHtml(external)
                    + '" target="_blank" rel="noopener noreferrer">'
                    + escapeHtml(uiLabels.learn_more || 'Saber mais') + '</a>';
            } else if (href && href.charAt(0) === '#') {
                html += '<a class="cal-month-pop__more" href="' + escapeHtml(href) + '">'
                    + escapeHtml(uiLabels.learn_more || 'Saber mais') + '</a>';
            }
            html += '</div></article>';
            return html;
        }

        function positionMonthPop(anchor) {
            if (!pop || !anchor) {
                return;
            }
            var rect = anchor.getBoundingClientRect();
            var popRect = pop.getBoundingClientRect();
            var width = popRect.width || 320;
            var height = popRect.height || 280;
            var left = rect.left + (rect.width / 2) - (width / 2);
            var top = rect.bottom + 10;
            if (left < 12) {
                left = 12;
            }
            if (left + width > window.innerWidth - 12) {
                left = window.innerWidth - width - 12;
            }
            if (top + height > window.innerHeight - 12) {
                top = rect.top - height - 10;
            }
            if (top < 12) {
                top = 12;
            }
            pop.style.left = Math.round(left) + 'px';
            pop.style.top = Math.round(top) + 'px';
        }

        function showMonthPop(anchor, item) {
            if (!pop || !item) {
                return;
            }
            cancelHideMonthPop();
            pop.innerHTML = buildMonthPopHtml(item);
            pop.hidden = false;
            pop.classList.add('is-visible');
            positionMonthPop(anchor);
            // Reposition after images/layout settle.
            window.requestAnimationFrame(function () {
                positionMonthPop(anchor);
            });
        }

        function bindMonthPop() {
            if (!monthBoard || !pop) {
                return;
            }
            monthBoard.querySelectorAll('[data-cal-month-item]').forEach(function (chip) {
                chip.addEventListener('mouseenter', function () {
                    var idx = parseInt(chip.getAttribute('data-cal-month-item') || '-1', 10);
                    if (idx < 0 || !items[idx]) {
                        return;
                    }
                    showMonthPop(chip, items[idx]);
                });
                chip.addEventListener('mouseleave', scheduleHideMonthPop);
                chip.addEventListener('focus', function () {
                    var idx = parseInt(chip.getAttribute('data-cal-month-item') || '-1', 10);
                    if (idx < 0 || !items[idx]) {
                        return;
                    }
                    showMonthPop(chip, items[idx]);
                });
                chip.addEventListener('blur', scheduleHideMonthPop);
            });
        }

        if (pop) {
            pop.addEventListener('mouseenter', cancelHideMonthPop);
            pop.addEventListener('mouseleave', scheduleHideMonthPop);
        }

        function parseHistoryItem(raw) {
            try {
                var parsed = JSON.parse(raw || '{}');
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (err) {
                return null;
            }
        }

        var historyPopAnchor = null;

        function setHistoryMoreExpanded(btn, expanded) {
            if (!btn) {
                return;
            }
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        var hideMonthPopBase = hideMonthPop;
        hideMonthPop = function () {
            if (historyPopAnchor) {
                setHistoryMoreExpanded(historyPopAnchor, false);
                historyPopAnchor = null;
            }
            hideMonthPopBase();
        };

        function hideHistoryPop() {
            hideMonthPop();
        }

        function showHistoryPop(btn, item) {
            if (!pop || !item) {
                return;
            }
            cancelHideMonthPop();
            historyPopAnchor = btn;
            historyPopAnchor._openedAt = Date.now();
            setHistoryMoreExpanded(btn, true);
            showMonthPop(btn, item);
        }

        var historyIgnoreDismissUntil = 0;

        function bindHistoryMore() {
            if (!pop) {
                return;
            }
            shell.querySelectorAll('[data-history-more]').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    var item = parseHistoryItem(btn.getAttribute('data-history-item'));
                    if (!item) {
                        return;
                    }
                    if (historyPopAnchor === btn && pop && !pop.hidden) {
                        hideHistoryPop();
                        return;
                    }
                    showHistoryPop(btn, item);
                    historyIgnoreDismissUntil = Date.now() + 400;
                });
                btn.addEventListener('mouseenter', function () {
                    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                        return;
                    }
                    var item = parseHistoryItem(btn.getAttribute('data-history-item'));
                    if (item) {
                        showHistoryPop(btn, item);
                    }
                });
                btn.addEventListener('mouseleave', function () {
                    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                        return;
                    }
                    scheduleHideMonthPop();
                });
            });

            document.addEventListener('pointerdown', function (event) {
                if (!pop || pop.hidden) {
                    return;
                }
                if (Date.now() < historyIgnoreDismissUntil) {
                    return;
                }
                if (pop.contains(event.target)) {
                    return;
                }
                if (event.target.closest && event.target.closest('[data-history-more]')) {
                    return;
                }
                if (event.target.closest && event.target.closest('[data-cal-month-item]')) {
                    return;
                }
                hideHistoryPop();
            });
        }

        bindHistoryMore();

        window.addEventListener('scroll', function () {
            if (!pop || pop.hidden) {
                return;
            }
            if (historyPopAnchor && Date.now() - (historyPopAnchor._openedAt || 0) < 500) {
                return;
            }
            hideHistoryPop();
        }, true);

        function renderMonthBoard() {
            if (!monthBoard) {
                return;
            }
            hideMonthPop();
            var parsed = parseMonthKey(selectedMonth);
            if (!parsed) {
                return;
            }
            if (monthNav) {
                monthNav.setAttribute('data-month', selectedMonth);
            }
            if (monthLabel) {
                monthLabel.textContent = monthLabelText(selectedMonth);
            }

            var first = new Date(parsed.year, parsed.month - 1, 1);
            var startPad = first.getDay();
            var daysInMonth = new Date(parsed.year, parsed.month, 0).getDate();
            var html = '';
            weekdays.forEach(function (label) {
                html += '<div class="cal-month-board__weekday">' + escapeHtml(label) + '</div>';
            });

            var totalCells = Math.ceil((startPad + daysInMonth) / 7) * 7;
            var hasItems = false;
            for (var i = 0; i < totalCells; i += 1) {
                var dayNum = i - startPad + 1;
                var inMonth = dayNum >= 1 && dayNum <= daysInMonth;
                var cellYear = parsed.year;
                var cellMonth = parsed.month;
                var cellDay = dayNum;
                if (!inMonth) {
                    if (dayNum < 1) {
                        var prev = new Date(parsed.year, parsed.month - 1, 0);
                        cellYear = prev.getFullYear();
                        cellMonth = prev.getMonth() + 1;
                        cellDay = prev.getDate() + dayNum;
                    } else {
                        var next = new Date(parsed.year, parsed.month, dayNum - daysInMonth);
                        cellYear = next.getFullYear();
                        cellMonth = next.getMonth() + 1;
                        cellDay = next.getDate();
                    }
                }
                var iso = formatMonthKey(cellYear, cellMonth) + '-' + String(cellDay).padStart(2, '0');
                var dayItems = inMonth ? itemsForDay(cellYear, cellMonth, cellDay) : [];
                if (dayItems.length > 0) {
                    hasItems = true;
                }
                var classes = 'cal-month-day';
                if (!inMonth) {
                    classes += ' cal-month-day--muted';
                }
                if (iso === todayIso) {
                    classes += ' cal-month-day--today';
                }
                html += '<div class="' + classes + '">';
                html += '<div class="cal-month-day__num">' + cellDay + '</div>';
                html += '<div class="cal-month-day__items">';
                dayItems.slice(0, 4).forEach(function (item) {
                    var type = item.type || 'game';
                    var title = String(item.title || '');
                    var href = String(item.href || '#');
                    var itemIndex = items.indexOf(item);
                    var chipClass = 'cal-month-chip cal-month-chip--' + type;
                    if (item.today) {
                        chipClass += ' cal-month-chip--today';
                    }
                    html += '<a class="' + chipClass + '" href="' + escapeHtml(href)
                        + '" data-cal-month-item="' + itemIndex + '">'
                        + escapeHtml(title)
                        + '</a>';
                });
                if (dayItems.length > 4) {
                    html += '<span class="cal-month-chip cal-month-chip--' + (dayItems[4].type || 'game') + '">+'
                        + (dayItems.length - 4)
                        + '</span>';
                }
                html += '</div></div>';
            }
            monthBoard.innerHTML = html;
            if (monthEmpty) {
                monthEmpty.hidden = hasItems;
            }
            bindMonthPop();
        }

        function setView(view) {
            var allowed = ['cards', 'list', 'month'];
            if (allowed.indexOf(view) === -1) {
                view = 'cards';
            }
            if (view === 'month' && !desktopMq.matches) {
                view = 'cards';
            }
            shell.setAttribute('data-cal-view-mode', view);
            buttons.forEach(function (btn) {
                var active = btn.getAttribute('data-cal-view') === view;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (monthPanel) {
                monthPanel.hidden = view !== 'month';
            }
            try {
                localStorage.setItem(storageKey, view);
            } catch (err) {}
            if (view === 'month') {
                renderMonthBoard();
            }
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setView(btn.getAttribute('data-cal-view') || 'cards');
            });
        });

        if (prevMonthBtn) {
            prevMonthBtn.addEventListener('click', function () {
                selectedMonth = shiftMonth(selectedMonth, -1);
                renderMonthBoard();
            });
        }
        if (nextMonthBtn) {
            nextMonthBtn.addEventListener('click', function () {
                selectedMonth = shiftMonth(selectedMonth, 1);
                renderMonthBoard();
            });
        }

        var initial = 'cards';
        try {
            initial = localStorage.getItem(storageKey) || 'cards';
        } catch (err) {}
        setView(initial);

        if (typeof desktopMq.addEventListener === 'function') {
            desktopMq.addEventListener('change', function () {
                if (!desktopMq.matches && shell.getAttribute('data-cal-view-mode') === 'month') {
                    setView('cards');
                }
            });
        }
    });

    document.querySelectorAll('[data-cal-more]').forEach(function (wrap) {
        var buttons = Array.prototype.slice.call(
            wrap.querySelectorAll('.cal-card__more, .cal-card__info')
        );
        if (!buttons.length) {
            return;
        }

        function setExpanded(expanded) {
            buttons.forEach(function (btn) {
                btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var open = wrap.classList.contains('is-open');
                document.querySelectorAll('[data-cal-more].is-open').forEach(function (other) {
                    other.classList.remove('is-open');
                    other.querySelectorAll('.cal-card__more, .cal-card__info').forEach(function (otherBtn) {
                        otherBtn.setAttribute('aria-expanded', 'false');
                    });
                });
                if (!open) {
                    wrap.classList.add('is-open');
                    setExpanded(true);
                } else {
                    setExpanded(false);
                }
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-cal-more]')) {
            return;
        }
        document.querySelectorAll('[data-cal-more].is-open').forEach(function (wrap) {
            wrap.classList.remove('is-open');
            wrap.querySelectorAll('.cal-card__more, .cal-card__info').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        document.querySelectorAll('[data-cal-more].is-open').forEach(function (wrap) {
            wrap.classList.remove('is-open');
            wrap.querySelectorAll('.cal-card__more, .cal-card__info').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
        });
    });

    document.querySelectorAll('[data-lang-switcher]').forEach(function (wrap) {
        var toggle = wrap.querySelector('.lang-switcher__toggle');
        var menu = wrap.querySelector('.lang-switcher__menu');
        if (!toggle || !menu) {
            return;
        }

        function close() {
            wrap.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            menu.hidden = true;
        }

        function open() {
            wrap.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (wrap.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    function showShareToast(message) {
        var toast = document.querySelector('[data-share-toast]');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'share-toast';
            toast.setAttribute('data-share-toast', '1');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(showShareToast._timer);
        showShareToast._timer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2400);
    }

    document.querySelectorAll('[data-share-menu]').forEach(function (wrap) {
        var toggle = wrap.querySelector('.share-menu__toggle');
        var panel = wrap.querySelector('.share-menu__panel');
        if (!toggle || !panel) {
            return;
        }

        var payload = { networks: [], copied: 'Copied' };
        try {
            payload = JSON.parse(wrap.getAttribute('data-share') || '{}') || payload;
        } catch (err) {
            // keep defaults
        }
        var byId = {};
        (payload.networks || []).forEach(function (item) {
            if (item && item.id) {
                byId[item.id] = item;
            }
        });

        function close() {
            wrap.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            panel.hidden = true;
        }

        function open() {
            wrap.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            panel.hidden = false;
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (wrap.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        panel.querySelectorAll('[data-share-network]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var id = btn.getAttribute('data-share-network') || '';
                var item = byId[id];
                if (!item) {
                    close();
                    return;
                }

                if (item.mode === 'clipboard' || !item.href) {
                    var text = String(item.text || '');
                    var copiedLabel = String(payload.copied || 'Copied');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            showShareToast(copiedLabel);
                        }).catch(function () {
                            showShareToast(copiedLabel);
                        });
                    } else {
                        var area = document.createElement('textarea');
                        area.value = text;
                        area.setAttribute('readonly', '');
                        area.style.position = 'fixed';
                        area.style.left = '-9999px';
                        document.body.appendChild(area);
                        area.select();
                        try {
                            document.execCommand('copy');
                        } catch (copyErr) {
                            // ignore
                        }
                        document.body.removeChild(area);
                        showShareToast(copiedLabel);
                    }
                    close();
                    return;
                }

                window.open(String(item.href), '_blank', 'noopener,noreferrer');
                close();
            });
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    document.querySelectorAll('[data-site-search]').forEach(function (form) {
        var toggle = form.querySelector('.site-search__toggle');
        var input = form.querySelector('.site-search__input');
        if (!toggle || !input) {
            return;
        }

        function close() {
            form.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function open() {
            form.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        window.setTimeout(function () {
                input.focus();
            }, 10);
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            if (form.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        document.querySelectorAll('.card:not(.news-carousel__slide)').forEach(function (card) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(16px)';
            card.style.transition = 'opacity .5s ease, transform .5s ease';
            io.observe(card);
        });
    }

    (function initWebPush() {
        var cfg = window.__PX_PUSH__ || {};
        var labels = cfg.labels || {};
        var btn = document.querySelector('[data-push-toggle]');
        if (!btn) {
            return;
        }
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !cfg.vapidPublicKey) {
            btn.hidden = true;
            return;
        }

        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var raw = window.atob(base64);
            var output = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i += 1) {
                output[i] = raw.charCodeAt(i);
            }
            return output;
        }

        function setState(state) {
            btn.disabled = state === 'busy';
            btn.classList.toggle('is-on', state === 'on');
            if (state === 'on') {
                btn.textContent = labels.enabled || 'Alerts on';
            } else if (state === 'denied') {
                btn.textContent = labels.denied || 'Blocked';
                btn.disabled = true;
            } else {
                btn.textContent = labels.enable || 'Enable alerts';
            }
        }

        function currentSubscription(reg) {
            return reg.pushManager.getSubscription();
        }

        navigator.serviceWorker.ready.then(function (reg) {
            if (Notification.permission === 'denied') {
                setState('denied');
                return;
            }
            return currentSubscription(reg).then(function (sub) {
                setState(sub ? 'on' : 'off');
            });
        }).catch(function () {
            btn.hidden = true;
        });

        btn.addEventListener('click', function () {
            if (btn.disabled) {
                return;
            }
            setState('busy');
            navigator.serviceWorker.ready.then(function (reg) {
                return currentSubscription(reg).then(function (existing) {
                    if (existing) {
                        return fetch('/api/push/subscribe', {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ endpoint: existing.endpoint }),
                        }).then(function () {
                            return existing.unsubscribe();
                        }).then(function () {
                            setState('off');
                        });
                    }

                    return Notification.requestPermission().then(function (permission) {
                        if (permission !== 'granted') {
                            setState(permission === 'denied' ? 'denied' : 'off');
                            return null;
                        }
                        return reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(String(cfg.vapidPublicKey)),
                        }).then(function (sub) {
                            var raw = sub.toJSON();
                            return fetch('/api/push/subscribe', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(raw),
                            }).then(function (res) {
                                if (!res.ok) {
                                    throw new Error('subscribe failed');
                                }
                                setState('on');
                            });
                        });
                    });
                });
            }).catch(function () {
                setState('off');
                window.alert(labels.error || 'Could not enable alerts.');
            });
        });
    })();

    /* ——— TV Xbox sidebar player ——— */
    (function initTvXbox() {
        var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-tvxbox]'));
        if (widgets.length === 0) {
            return;
        }

        function parseVideos(el) {
            try {
                var raw = el.getAttribute('data-videos') || '[]';
                var list = JSON.parse(raw);
                return Array.isArray(list) ? list.filter(function (v) {
                    return v && typeof v.youtubeId === 'string' && v.youtubeId.length > 0;
                }) : [];
            } catch (e) {
                return [];
            }
        }

        function shuffleIndices(length) {
            var order = [];
            var i;
            for (i = 0; i < length; i += 1) {
                order.push(i);
            }
            for (i = order.length - 1; i > 0; i -= 1) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = order[i];
                order[i] = order[j];
                order[j] = tmp;
            }
            return order;
        }

        function loadYoutubeApi(cb) {
            if (window.YT && window.YT.Player) {
                cb();
                return;
            }
            var previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof previous === 'function') {
                    previous();
                }
                cb();
            };
            if (!document.querySelector('script[data-yt-api]')) {
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                tag.async = true;
                tag.setAttribute('data-yt-api', '1');
                document.head.appendChild(tag);
            }
        }

        function bindWidget(root) {
            var videos = parseVideos(root);
            if (videos.length === 0) {
                return;
            }

            var mount = root.querySelector('[data-tvxbox-mount]');
            var tagEl = root.querySelector('[data-tvxbox-tag]');
            var commentEl = root.querySelector('[data-tvxbox-comment]');
            var commentTextEl = root.querySelector('[data-tvxbox-comment-text]');
            var gpSlotEl = root.querySelector('[data-tvxbox-gp]');
            var rewardsEl = root.querySelector('[data-tvxbox-rewards]');
            var rewardsMedalEl = root.querySelector('[data-tvxbox-rewards-medal]');
            var rewardsPointsEl = root.querySelector('[data-tvxbox-rewards-points]');
            var nextBtn = root.querySelector('[data-tvxbox-next]');
            var detailsBtn = root.querySelector('[data-tvxbox-details]');
            var modalEl = root.querySelector('[data-tvxbox-modal]');
            var modalMount = root.querySelector('[data-tvxbox-modal-mount]');
            var modalCloseBtn = root.querySelector('[data-tvxbox-modal-close]');
            var modalTitleEl = root.querySelector('[data-tvxbox-modal-title]');
            var modalBodyEl = root.querySelector('[data-tvxbox-modal-body]');
            var modalCoverEl = root.querySelector('[data-tvxbox-modal-cover]');
            var modalPaEl = root.querySelector('[data-tvxbox-modal-pa]');
            var modalPtBrEl = root.querySelector('[data-tvxbox-modal-ptbr]');
            var modalReleaseEl = root.querySelector('[data-tvxbox-modal-release]');
            var modalReleaseGpEl = root.querySelector('[data-tvxbox-modal-release-gp]');
            var modalReleaseTextEl = root.querySelector('[data-tvxbox-modal-release-text]');
            var modalRewardsEl = root.querySelector('[data-tvxbox-modal-rewards]');
            var modalRewardsMedalEl = root.querySelector('[data-tvxbox-modal-rewards-medal]');
            var modalRewardsPointsEl = root.querySelector('[data-tvxbox-modal-rewards-points]');
            var modalStoreEl = root.querySelector('[data-tvxbox-modal-store]');
            var modalPlatformsLabelEl = root.querySelector('[data-tvxbox-modal-platforms-label]');
            var modalPlatformsEl = root.querySelector('[data-tvxbox-modal-platforms]');
            var modalSubsEl = root.querySelector('[data-tvxbox-modal-subs]');
            if (!mount) {
                return;
            }

            /* Shuffle-bag: play every video once before any repeat. */
            var queue = shuffleIndices(videos.length);
            var queuePos = 0;
            var index = queue[0];
            var player = null;
            var modalPlayer = null;
            var switching = false;

            function hasGame(video) {
                return !!(video && video.game && video.game.id);
            }

            function refillQueue(avoidIndex) {
                queue = shuffleIndices(videos.length);
                if (
                    videos.length > 1 &&
                    typeof avoidIndex === 'number' &&
                    queue[0] === avoidIndex
                ) {
                    var swapWith = 1 + Math.floor(Math.random() * (queue.length - 1));
                    var tmp = queue[0];
                    queue[0] = queue[swapWith];
                    queue[swapWith] = tmp;
                }
                queuePos = 0;
            }

            function takeNextIndex() {
                if (videos.length <= 1) {
                    return 0;
                }
                queuePos += 1;
                if (queuePos >= queue.length) {
                    refillQueue(index);
                }
                return queue[queuePos];
            }

            function applyMeta(video) {
                var tag = (video && video.tag) ? String(video.tag) : '';
                var comment = (video && video.comment) ? String(video.comment) : '';
                var isToday = !!(video && video.isToday);
                var onGamePass = !!(video && video.onGamePass);
                if (tagEl) {
                    if (tag) {
                        tagEl.textContent = tag;
                        tagEl.hidden = false;
                        tagEl.classList.remove('is-empty');
                    } else {
                        tagEl.textContent = '';
                        tagEl.hidden = true;
                        tagEl.classList.add('is-empty');
                    }
                }
                if (commentEl) {
                    if (commentTextEl) {
                        commentTextEl.textContent = comment;
                    } else {
                        commentEl.textContent = comment;
                    }
                    if (comment) {
                        commentEl.hidden = false;
                    } else {
                        commentEl.hidden = true;
                    }
                    commentEl.classList.toggle('tvxbox__comment--today', isToday);
                    commentEl.classList.toggle('tvxbox__comment--gp', onGamePass);
                }
                if (gpSlotEl) {
                    gpSlotEl.hidden = !onGamePass;
                }
                if (rewardsEl) {
                    var rewards = video && video.rewards ? video.rewards : null;
                    var label = rewards && rewards.label ? String(rewards.label) : '';
                    var tip = rewards && rewards.tip ? String(rewards.tip) : '';
                    var showRewards = !onGamePass && label !== '';
                    if (rewardsPointsEl) {
                        rewardsPointsEl.textContent = label;
                    }
                    if (rewardsMedalEl) {
                        rewardsMedalEl.setAttribute('title', tip);
                        rewardsMedalEl.setAttribute('aria-label', tip);
                    }
                    rewardsEl.hidden = !showRewards;
                }
                /* Detalhes só quando há jogo no calendário; omitido nos demais. */
                if (detailsBtn) {
                    detailsBtn.hidden = !hasGame(video);
                }
            }

            function fillModal(video) {
                var game = video && video.game ? video.game : null;
                if (!game || !modalEl) {
                    return;
                }
                if (modalTitleEl) {
                    modalTitleEl.textContent = game.title ? String(game.title) : '';
                }
                if (modalBodyEl) {
                    modalBodyEl.textContent = game.body ? String(game.body) : '';
                    modalBodyEl.hidden = !game.body;
                }
                if (modalCoverEl) {
                    if (game.coverUrl) {
                        modalCoverEl.src = String(game.coverUrl);
                        modalCoverEl.alt = game.title ? String(game.title) : '';
                        modalCoverEl.hidden = false;
                    } else {
                        modalCoverEl.removeAttribute('src');
                        modalCoverEl.hidden = true;
                    }
                }
                if (modalPaEl) {
                    modalPaEl.hidden = !game.playAnywhere;
                }
                if (modalPtBrEl) {
                    modalPtBrEl.hidden = !game.supportsPtBr;
                }
                if (modalReleaseEl) {
                    var releaseDate = game.releaseDate ? String(game.releaseDate) : '';
                    if (modalReleaseTextEl) {
                        modalReleaseTextEl.textContent = releaseDate;
                    }
                    if (releaseDate) {
                        modalReleaseEl.hidden = false;
                        modalReleaseEl.classList.toggle('is-gp', !!game.onGamePass);
                        if (modalReleaseGpEl) {
                            modalReleaseGpEl.hidden = !game.onGamePass;
                        }
                    } else {
                        modalReleaseEl.hidden = true;
                        modalReleaseEl.classList.remove('is-gp');
                        if (modalReleaseGpEl) {
                            modalReleaseGpEl.hidden = true;
                        }
                    }
                }
                if (modalRewardsEl) {
                    var mRewards = game.rewards || null;
                    var mLabel = mRewards && mRewards.label ? String(mRewards.label) : '';
                    var mTip = mRewards && mRewards.tip ? String(mRewards.tip) : '';
                    var showMRewards = !game.onGamePass && mLabel !== '';
                    if (modalRewardsPointsEl) {
                        var pointsTpl = modalRewardsEl.getAttribute('data-rewards-points-tpl') || '{n} Pontos Rewards';
                        modalRewardsPointsEl.textContent = mLabel
                            ? pointsTpl.replace(/\{n\}/g, mLabel)
                            : '';
                    }
                    if (modalRewardsMedalEl) {
                        modalRewardsMedalEl.setAttribute('title', mTip);
                        modalRewardsMedalEl.setAttribute('aria-label', mTip);
                    }
                    modalRewardsEl.hidden = !showMRewards;
                }
                if (modalStoreEl) {
                    if (game.storeUrl) {
                        modalStoreEl.href = String(game.storeUrl);
                        modalStoreEl.hidden = false;
                    } else {
                        modalStoreEl.removeAttribute('href');
                        modalStoreEl.hidden = true;
                    }
                }
                if (modalPlatformsEl) {
                    modalPlatformsEl.innerHTML = '';
                    var platforms = Array.isArray(game.platforms) ? game.platforms : [];
                    platforms.forEach(function (platform) {
                        var span = document.createElement('span');
                        if (platform.icon === 'xbox') {
                            var xbox = document.createElement('span');
                            xbox.className = 'icon-xbox';
                            xbox.setAttribute('aria-hidden', 'true');
                            span.appendChild(xbox);
                            span.appendChild(document.createTextNode(' ' + (platform.label || '')));
                        } else {
                            span.textContent = ((platform.icon ? platform.icon + ' ' : '') + (platform.label || '')).trim();
                        }
                        modalPlatformsEl.appendChild(span);
                    });
                    if (modalPlatformsLabelEl) {
                        modalPlatformsLabelEl.hidden = platforms.length === 0;
                    }
                }
                if (modalSubsEl) {
                    modalSubsEl.innerHTML = '';
                    var subs = Array.isArray(game.subscriptions) ? game.subscriptions : [];
                    subs.forEach(function (sub) {
                        var badge = document.createElement('span');
                        badge.className = 'cal-badge';
                        badge.textContent = String(sub);
                        modalSubsEl.appendChild(badge);
                    });
                }
            }

            function destroyModalPlayer() {
                if (modalPlayer && typeof modalPlayer.destroy === 'function') {
                    try {
                        modalPlayer.destroy();
                    } catch (err) {
                        /* ignore */
                    }
                }
                modalPlayer = null;
                if (modalMount) {
                    modalMount.innerHTML = '';
                }
            }

            function closeModal() {
                destroyModalPlayer();
                if (modalEl && typeof modalEl.close === 'function' && modalEl.open) {
                    modalEl.close();
                }
                if (player && typeof player.playVideo === 'function') {
                    try {
                        player.playVideo();
                    } catch (err) {
                        /* ignore */
                    }
                }
            }

            function openModal() {
                var video = videos[index];
                if (!hasGame(video) || !modalEl || !modalMount) {
                    return;
                }
                fillModal(video);
                if (player && typeof player.pauseVideo === 'function') {
                    try {
                        player.pauseVideo();
                    } catch (err) {
                        /* ignore */
                    }
                }
                if (typeof modalEl.showModal === 'function') {
                    modalEl.showModal();
                }
                loadYoutubeApi(function () {
                    if (!window.YT || !window.YT.Player || !modalEl.open) {
                        return;
                    }
                    destroyModalPlayer();
                    modalPlayer = new window.YT.Player(modalMount, {
                        width: '100%',
                        height: '100%',
                        videoId: video.youtubeId,
                        playerVars: {
                            autoplay: 1,
                            mute: 0,
                            controls: 1,
                            rel: 0,
                            modestbranding: 1,
                            playsinline: 1,
                            fs: 1,
                            origin: window.location.origin,
                        },
                    });
                });
            }

            function playAt(nextIndex) {
                if (nextIndex < 0 || nextIndex >= videos.length) {
                    return;
                }
                index = nextIndex;
                var video = videos[index];
                applyMeta(video);
                switching = true;
                if (player && typeof player.loadVideoById === 'function') {
                    try {
                        player.mute();
                        player.loadVideoById({
                            videoId: video.youtubeId,
                            startSeconds: 0,
                        });
                        player.playVideo();
                    } catch (err) {
                        /* ignore */
                    }
                }
                window.setTimeout(function () {
                    switching = false;
                }, 700);
            }

            function goNext() {
                playAt(takeNextIndex());
            }

            applyMeta(videos[index]);

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    goNext();
                });
            }

            if (detailsBtn) {
                detailsBtn.addEventListener('click', function () {
                    openModal();
                });
            }

            if (modalCloseBtn) {
                modalCloseBtn.addEventListener('click', function () {
                    closeModal();
                });
            }

            if (modalEl) {
                modalEl.addEventListener('close', function () {
                    destroyModalPlayer();
                });
                modalEl.addEventListener('click', function (event) {
                    if (event.target === modalEl) {
                        closeModal();
                    }
                });
            }

            loadYoutubeApi(function () {
                if (!window.YT || !window.YT.Player) {
                    return;
                }
                player = new window.YT.Player(mount, {
                    width: '100%',
                    height: '100%',
                    videoId: videos[index].youtubeId,
                    playerVars: {
                        autoplay: 1,
                        mute: 1,
                        controls: 1,
                        rel: 0,
                        modestbranding: 1,
                        playsinline: 1,
                        fs: 1,
                        origin: window.location.origin,
                    },
                    events: {
                        onReady: function (event) {
                            try {
                                event.target.mute();
                                event.target.playVideo();
                            } catch (err) {
                                /* ignore */
                            }
                        },
                        onStateChange: function (event) {
                            if (switching) {
                                return;
                            }
                            if (event.data === window.YT.PlayerState.ENDED) {
                                goNext();
                            }
                        },
                        onError: function () {
                            if (videos.length > 1) {
                                goNext();
                            }
                        },
                    },
                });
            });
        }

        widgets.forEach(bindWidget);
    })();
})();
