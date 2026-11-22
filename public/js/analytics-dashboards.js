/**
 * Seelenfunke Analytics Dashboards
 * Encapsulates Chart.js instances in closures for Alpine.js integration to prevent memory leaks and reactivity pollution.
 */

const sfAnalyticsHelpers = {
    toArr(val) {
        return Array.isArray(val) ? val : Object.values(val || {});
    },

    parseJsonAttr(el, attr, fallback = { labels: [], data: [] }) {
        if (!el) return fallback;
        const raw = el.getAttribute(attr);
        if (!raw) return fallback;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    },

    destroyCanvas(id, instanceRef) {
        if (instanceRef && typeof instanceRef.destroy === 'function') {
            try { instanceRef.destroy(); } catch (e) {}
        }
        const canvas = document.getElementById(id);
        if (canvas && window.Chart && typeof window.Chart.getChart === 'function') {
            const existing = window.Chart.getChart(canvas);
            if (existing) {
                try { existing.destroy(); } catch (e) {}
            }
        }
        return canvas;
    },

    normalizeDoughnut(labels, values, palette, emptyRingColor) {
        const cleanLabels = this.toArr(labels);
        const cleanData = this.toArr(values).map(v => Number(v) || 0);
        const total = cleanData.reduce((acc, v) => acc + v, 0);

        if (cleanLabels.length === 0 || total <= 0) {
            return {
                isEmpty: true,
                labels: ['Keine Einträge'],
                data: [1],
                backgroundColor: [emptyRingColor]
            };
        }

        return {
            isEmpty: false,
            labels: cleanLabels,
            data: cleanData,
            backgroundColor: palette
        };
    },

    doughnutTooltipCallbacks(chartRefGetter) {
        return {
            label(context) {
                const chart = typeof chartRefGetter === 'function' ? chartRefGetter() : context.chart;
                if (chart && chart.$sfIsEmpty) {
                    return ' Keine Einträge im gewählten Zeitraum';
                }
                const label = context.label ? context.label + ': ' : '';
                return ' ' + label + context.formattedValue;
            }
        };
    }
};

// 1. Support Dashboard
window.supportDashboard = (() => {
    let volumeChartObj = null;
    let sourceChartObj = null;
    let ticketStatusChartObj = null;
    let chatStatusChartObj = null;
    let chatRatingChartObj = null;
    let ticketRatingChartObj = null;

    return () => ({
        getPayload() {
            const el = document.getElementById('analytics-data-bridge');
            return {
                volume: sfAnalyticsHelpers.parseJsonAttr(el, 'data-volume'),
                source: sfAnalyticsHelpers.parseJsonAttr(el, 'data-source'),
                ticketStatus: sfAnalyticsHelpers.parseJsonAttr(el, 'data-ticketstatus'),
                chatStatus: sfAnalyticsHelpers.parseJsonAttr(el, 'data-chatstatus'),
                chatRating: sfAnalyticsHelpers.parseJsonAttr(el, 'data-chatrating'),
                ticketRating: sfAnalyticsHelpers.parseJsonAttr(el, 'data-ticketrating')
            };
        },

        initCharts() {
            if (!window.Chart) return;
            const data = this.getPayload();
            const isDark = document.documentElement.classList.contains('dark');
            const gridOptions = { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };
            const donutBorder = isDark ? '#1f2937' : '#ffffff';
            const emptyRing = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';
            const el = document.getElementById('analytics-data-bridge');
            const tc = el ? (el.getAttribute('data-theme-color') || '#c5a059') : '#c5a059';

            const monoPalette = [tc, '#3b82f6', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#14b8a6', '#64748b'];

            const ctxVol = sfAnalyticsHelpers.destroyCanvas('volumeChart', volumeChartObj);
            volumeChartObj = null;
            if (ctxVol) {
                volumeChartObj = new Chart(ctxVol.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.volume.labels),
                        datasets: [{
                            label: 'Support-Volumen',
                            data: sfAnalyticsHelpers.toArr(data.volume.data),
                            borderColor: tc,
                            backgroundColor: tc + '1a',
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: tc, pointBorderColor: isDark ? '#111827' : '#ffffff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            const makeDoughnut = (canvasId, prevObj, srcPayload, paletteOverride = null) => {
                const canvas = sfAnalyticsHelpers.destroyCanvas(canvasId, prevObj);
                if (!canvas) return null;
                const norm = sfAnalyticsHelpers.normalizeDoughnut(srcPayload.labels, srcPayload.data, paletteOverride || monoPalette, emptyRing);
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: norm.labels,
                        datasets: [{
                            data: norm.data,
                            backgroundColor: norm.backgroundColor,
                            borderWidth: norm.isEmpty ? 0 : 2,
                            borderColor: donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { color: textColor, padding: 14, font: { size: 11 } } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => chart) }
                        },
                        cutout: '62%'
                    }
                });
                chart.$sfIsEmpty = norm.isEmpty;
                return chart;
            };

            const ratingPalette = ['#10b981', '#34d399', '#f59e0b', '#f97316', '#ef4444'];
            sourceChartObj = makeDoughnut('sourceChart', sourceChartObj, data.source);
            ticketStatusChartObj = makeDoughnut('ticketStatusChart', ticketStatusChartObj, data.ticketStatus);
            chatStatusChartObj = makeDoughnut('chatStatusChart', chatStatusChartObj, data.chatStatus);
            chatRatingChartObj = makeDoughnut('chatRatingChart', chatRatingChartObj, data.chatRating, ratingPalette);
            ticketRatingChartObj = makeDoughnut('ticketRatingChart', ticketRatingChartObj, data.ticketRating, ratingPalette);
        },

        updateCharts() {
            requestAnimationFrame(() => {
                this.initCharts();
            });
        }
    });
})();

// 2. Marketing Dashboard
window.marketingDashboard = (() => {
    let newsletterChartObj = null;
    let voucherChartObj = null;
    let blogChartObj = null;
    let voucherTypeChartObj = null;
    let landingPageChartObj = null;

    return () => ({
        getPayload() {
            const el = document.getElementById('analytics-data-bridge');
            return {
                newsletter: sfAnalyticsHelpers.parseJsonAttr(el, 'data-newsletter'),
                voucher: sfAnalyticsHelpers.parseJsonAttr(el, 'data-voucher'),
                blog: sfAnalyticsHelpers.parseJsonAttr(el, 'data-blog'),
                voucherType: sfAnalyticsHelpers.parseJsonAttr(el, 'data-vouchertype'),
                landingPage: sfAnalyticsHelpers.parseJsonAttr(el, 'data-landingpage')
            };
        },

        initCharts() {
            if (!window.Chart) return;
            const data = this.getPayload();
            const isDark = document.documentElement.classList.contains('dark');
            const gridOptions = { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };
            const donutBorder = isDark ? '#1f2937' : '#ffffff';
            const emptyRing = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            const ctxNl = sfAnalyticsHelpers.destroyCanvas('newsletterChart', newsletterChartObj);
            newsletterChartObj = null;
            if (ctxNl) {
                newsletterChartObj = new Chart(ctxNl.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.newsletter.labels),
                        datasets: [{
                            label: 'Neue Abonnenten',
                            data: sfAnalyticsHelpers.toArr(data.newsletter.data),
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: isDark ? 'rgba(16, 185, 129, 0.12)' : 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: 'rgba(16, 185, 129, 1)', pointBorderColor: isDark ? '#111827' : '#ffffff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            const ctxVc = sfAnalyticsHelpers.destroyCanvas('voucherChart', voucherChartObj);
            voucherChartObj = null;
            if (ctxVc) {
                voucherChartObj = new Chart(ctxVc.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.voucher.labels),
                        datasets: [{
                            label: 'Erstellte Gutscheine',
                            data: sfAnalyticsHelpers.toArr(data.voucher.data),
                            backgroundColor: isDark ? 'rgba(244, 63, 94, 0.8)' : 'rgba(244, 63, 94, 0.85)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            const ctxBl = sfAnalyticsHelpers.destroyCanvas('blogChart', blogChartObj);
            blogChartObj = null;
            if (ctxBl) {
                const normBlog = sfAnalyticsHelpers.normalizeDoughnut(
                    data.blog.labels,
                    data.blog.data,
                    ['#6366f1', '#10b981', '#ef4444', '#f59e0b', '#06b6d4', '#ec4899', '#14b8a6', '#c5a059'],
                    emptyRing
                );
                blogChartObj = new Chart(ctxBl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normBlog.labels,
                        datasets: [{
                            data: normBlog.data,
                            backgroundColor: normBlog.backgroundColor,
                            borderWidth: normBlog.isEmpty ? 0 : 2,
                            borderColor: donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor, padding: 14, font: { size: 11 } } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => blogChartObj) }
                        },
                        cutout: '62%'
                    }
                });
                blogChartObj.$sfIsEmpty = normBlog.isEmpty;
            }

            const ctxVt = sfAnalyticsHelpers.destroyCanvas('voucherTypeChart', voucherTypeChartObj);
            voucherTypeChartObj = null;
            if (ctxVt) {
                const normVt = sfAnalyticsHelpers.normalizeDoughnut(
                    data.voucherType.labels,
                    data.voucherType.data,
                    ['#c5a059', '#10b981', '#ec4899', '#06b6d4', '#f59e0b', '#6366f1'],
                    emptyRing
                );
                voucherTypeChartObj = new Chart(ctxVt.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normVt.labels,
                        datasets: [{
                            data: normVt.data,
                            backgroundColor: normVt.backgroundColor,
                            borderWidth: normVt.isEmpty ? 0 : 2,
                            borderColor: donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor, padding: 14, font: { size: 11 } } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => voucherTypeChartObj) }
                        },
                        cutout: '62%'
                    }
                });
                voucherTypeChartObj.$sfIsEmpty = normVt.isEmpty;
            }

            const ctxLp = sfAnalyticsHelpers.destroyCanvas('landingPageChart', landingPageChartObj);
            landingPageChartObj = null;
            if (ctxLp) {
                landingPageChartObj = new Chart(ctxLp.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.landingPage.labels),
                        datasets: [{
                            label: 'Seitenaufrufe',
                            data: sfAnalyticsHelpers.toArr(data.landingPage.data),
                            borderColor: '#c5a059',
                            backgroundColor: isDark ? 'rgba(197, 160, 89, 0.14)' : 'rgba(197, 160, 89, 0.08)',
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: '#c5a059', pointBorderColor: isDark ? '#111827' : '#ffffff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        },

        updateCharts() {
            requestAnimationFrame(() => {
                this.initCharts();
            });
        }
    });
})();

// 3. Order Analytics Dashboard
window.orderAnalyticsDashboard = (() => {
    let b2bChartObj = null;
    let processingChartObj = null;
    let peakChartObj = null;
    let retentionChartObj = null;
    let cancellationChartObj = null;
    let bestsellerChartObj = null;
    let weekdayChartObj = null;
    let quotesChartObj = null;
    let revocationsChartObj = null;

    return () => ({
        getPayload() {
            const el = document.getElementById('analytics-data-bridge');
            return {
                b2b: sfAnalyticsHelpers.parseJsonAttr(el, 'data-b2b'),
                processing: sfAnalyticsHelpers.parseJsonAttr(el, 'data-processing'),
                peak: sfAnalyticsHelpers.parseJsonAttr(el, 'data-peak'),
                retention: sfAnalyticsHelpers.parseJsonAttr(el, 'data-retention'),
                cancellation: sfAnalyticsHelpers.parseJsonAttr(el, 'data-cancellation'),
                bestseller: sfAnalyticsHelpers.parseJsonAttr(el, 'data-bestseller'),
                weekday: sfAnalyticsHelpers.parseJsonAttr(el, 'data-weekday'),
                quotes: sfAnalyticsHelpers.parseJsonAttr(el, 'data-quotes'),
                revocations: sfAnalyticsHelpers.parseJsonAttr(el, 'data-revocations')
            };
        },

        initCharts() {
            if (!window.Chart) return;
            const el = document.getElementById('analytics-data-bridge');
            const themeColor = el ? (el.getAttribute('data-theme-color') || '#c5a059') : '#c5a059';
            const isDark = document.documentElement.classList.contains('dark');

            const colors = {
                primary: themeColor,
                primaryLight: themeColor + '26',
                blue: 'rgba(59, 130, 246, 1)',
                emerald: 'rgba(16, 185, 129, 1)',
                rose: 'rgba(225, 29, 72, 1)',
                roseLight: 'rgba(225, 29, 72, 0.16)',
                amber: 'rgba(245, 158, 11, 1)',
                cyan: 'rgba(6, 182, 212, 1)',
                cyanLight: 'rgba(6, 182, 212, 0.16)',
                slate: isDark ? '#64748b' : '#94a3b8',
                gray: isDark ? 'rgba(156, 163, 175, 1)' : 'rgba(75, 85, 99, 1)',
                emptyRing: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)',
                donutBorder: isDark ? '#111827' : '#ffffff'
            };

            const gridOptions = { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)', tickColor: 'transparent' };
            const gridOptionsX = { display: false };
            const data = this.getPayload();

            // 1. Retention Rate (Doughnut)
            const ctxRet = sfAnalyticsHelpers.destroyCanvas('retentionChart', retentionChartObj);
            retentionChartObj = null;
            if (ctxRet) {
                const normRet = sfAnalyticsHelpers.normalizeDoughnut(data.retention.labels, data.retention.data, [colors.emerald, colors.primary], colors.emptyRing);
                retentionChartObj = new Chart(ctxRet.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normRet.labels,
                        datasets: [{
                            data: normRet.data,
                            backgroundColor: normRet.backgroundColor,
                            borderColor: colors.donutBorder,
                            borderWidth: normRet.isEmpty ? 0 : 2,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '70%',
                        plugins: {
                            legend: { position: 'bottom', labels: { color: colors.gray } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => retentionChartObj) }
                        }
                    }
                });
                retentionChartObj.$sfIsEmpty = normRet.isEmpty;
            }

            // 2. B2B vs B2C (Doughnut)
            const ctxB2b = sfAnalyticsHelpers.destroyCanvas('b2bChart', b2bChartObj);
            b2bChartObj = null;
            if (ctxB2b) {
                const normB2b = sfAnalyticsHelpers.normalizeDoughnut(data.b2b.labels, data.b2b.data, [colors.primary, colors.blue], colors.emptyRing);
                b2bChartObj = new Chart(ctxB2b.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normB2b.labels,
                        datasets: [{
                            data: normB2b.data,
                            backgroundColor: normB2b.backgroundColor,
                            borderColor: colors.donutBorder,
                            borderWidth: normB2b.isEmpty ? 0 : 2,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '70%',
                        plugins: {
                            legend: { position: 'bottom', labels: { color: colors.gray } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => b2bChartObj) }
                        }
                    }
                });
                b2bChartObj.$sfIsEmpty = normB2b.isEmpty;
            }

            // 3. Cancellation Drain (Line)
            const ctxCan = sfAnalyticsHelpers.destroyCanvas('cancellationChart', cancellationChartObj);
            cancellationChartObj = null;
            if (ctxCan) {
                cancellationChartObj = new Chart(ctxCan.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.cancellation.labels),
                        datasets: [{
                            label: 'Stornierter Wert (€)',
                            data: sfAnalyticsHelpers.toArr(data.cancellation.data),
                            borderColor: colors.rose,
                            backgroundColor: colors.roseLight,
                            borderWidth: 2, tension: 0.25, fill: true,
                            pointBackgroundColor: colors.rose, pointBorderColor: colors.donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 4. Bestsellers (Bar Horizontal)
            const ctxBest = sfAnalyticsHelpers.destroyCanvas('bestsellerChart', bestsellerChartObj);
            bestsellerChartObj = null;
            if (ctxBest) {
                bestsellerChartObj = new Chart(ctxBest.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.bestseller.labels),
                        datasets: [{
                            label: 'Verkaufte Einheiten',
                            data: sfAnalyticsHelpers.toArr(data.bestseller.data),
                            backgroundColor: colors.amber,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, y: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 5. Processing Time (Line)
            const ctxProc = sfAnalyticsHelpers.destroyCanvas('processingChart', processingChartObj);
            processingChartObj = null;
            if (ctxProc) {
                processingChartObj = new Chart(ctxProc.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.processing.labels),
                        datasets: [{
                            label: 'Dauer (Std)',
                            data: sfAnalyticsHelpers.toArr(data.processing.data),
                            borderColor: colors.cyan,
                            backgroundColor: colors.cyanLight,
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: colors.cyan, pointBorderColor: colors.donutBorder,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 6. Peak Times (Bar)
            const ctxPeak = sfAnalyticsHelpers.destroyCanvas('peakChart', peakChartObj);
            peakChartObj = null;
            if (ctxPeak) {
                peakChartObj = new Chart(ctxPeak.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.peak.labels),
                        datasets: [{
                            label: 'Bestellungen pro Uhrzeit',
                            data: sfAnalyticsHelpers.toArr(data.peak.data),
                            backgroundColor: colors.blue,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 7. Weekday Volume (Bar)
            const ctxWeek = sfAnalyticsHelpers.destroyCanvas('weekdayChart', weekdayChartObj);
            weekdayChartObj = null;
            if (ctxWeek) {
                weekdayChartObj = new Chart(ctxWeek.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.weekday.labels),
                        datasets: [{
                            label: 'Bestellungen pro Wochentag',
                            data: sfAnalyticsHelpers.toArr(data.weekday.data),
                            backgroundColor: colors.primary,
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 8. Quotes (Line)
            const ctxQuotes = sfAnalyticsHelpers.destroyCanvas('quotesChart', quotesChartObj);
            quotesChartObj = null;
            if (ctxQuotes) {
                quotesChartObj = new Chart(ctxQuotes.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.quotes.labels),
                        datasets: [{
                            label: 'Angebotsanfragen',
                            data: sfAnalyticsHelpers.toArr(data.quotes.data),
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.16)',
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: 'rgba(99, 102, 241, 1)', pointBorderColor: colors.donutBorder,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 9. Revocations (Line)
            const ctxRevs = sfAnalyticsHelpers.destroyCanvas('revocationsChart', revocationsChartObj);
            revocationsChartObj = null;
            if (ctxRevs) {
                revocationsChartObj = new Chart(ctxRevs.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.revocations.labels),
                        datasets: [{
                            label: 'Widerrufe',
                            data: sfAnalyticsHelpers.toArr(data.revocations.data),
                            borderColor: 'rgba(239, 68, 68, 1)',
                            backgroundColor: 'rgba(239, 68, 68, 0.16)',
                            borderWidth: 2, tension: 0.35, fill: true,
                            pointBackgroundColor: 'rgba(239, 68, 68, 1)', pointBorderColor: colors.donutBorder,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: colors.gray, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: colors.gray } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        },

        updateCharts() {
            requestAnimationFrame(() => {
                this.initCharts();
            });
        }
    });
})();

// 4. Product Dashboard
window.productDashboard = (() => {
    let lossChartObj = null;
    let topLossChartObj = null;
    let reviewChartObj = null;
    let supplierChartObj = null;

    return () => ({
        getPayload() {
            const el = document.getElementById('analytics-data-bridge');
            return {
                loss: sfAnalyticsHelpers.parseJsonAttr(el, 'data-loss'),
                topLoss: sfAnalyticsHelpers.parseJsonAttr(el, 'data-toploss'),
                review: sfAnalyticsHelpers.parseJsonAttr(el, 'data-review'),
                supplier: sfAnalyticsHelpers.parseJsonAttr(el, 'data-supplier')
            };
        },

        initCharts() {
            if (!window.Chart) return;
            const data = this.getPayload();
            const isDark = document.documentElement.classList.contains('dark');
            const gridOptions = { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };
            const donutBorder = isDark ? '#1f2937' : '#ffffff';
            const emptyRing = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            // 1. Loss Chart
            const ctxLs = sfAnalyticsHelpers.destroyCanvas('lossChart', lossChartObj);
            lossChartObj = null;
            if (ctxLs) {
                lossChartObj = new Chart(ctxLs.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.loss.labels),
                        datasets: [{
                            label: 'Beschädigte Artikel (Menge)',
                            data: sfAnalyticsHelpers.toArr(data.loss.data),
                            backgroundColor: 'rgba(244, 63, 94, 0.8)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Top Loss Chart
            const ctxTop = sfAnalyticsHelpers.destroyCanvas('topLossChart', topLossChartObj);
            topLossChartObj = null;
            if (ctxTop) {
                topLossChartObj = new Chart(ctxTop.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: sfAnalyticsHelpers.toArr(data.topLoss.labels),
                        datasets: [{
                            label: 'Finanzieller Schaden (€)',
                            data: sfAnalyticsHelpers.toArr(data.topLoss.data),
                            backgroundColor: 'rgba(239, 68, 68, 0.9)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, grid: gridOptions, ticks: { color: textColor, precision: 0 } }, y: { grid: gridOptionsX, ticks: { color: textColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 3. Review Chart
            const ctxRev = sfAnalyticsHelpers.destroyCanvas('reviewChart', reviewChartObj);
            reviewChartObj = null;
            if (ctxRev) {
                const normRev = sfAnalyticsHelpers.normalizeDoughnut(
                    data.review.labels,
                    data.review.data,
                    ['#10b981', '#34d399', '#fbbf24', '#f87171', '#ef4444'],
                    emptyRing
                );
                reviewChartObj = new Chart(ctxRev.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normRev.labels,
                        datasets: [{
                            data: normRev.data,
                            backgroundColor: normRev.backgroundColor,
                            borderWidth: normRev.isEmpty ? 0 : 2,
                            borderColor: donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor, padding: 14, font: { size: 11 } } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => reviewChartObj) }
                        },
                        cutout: '62%'
                    }
                });
                reviewChartObj.$sfIsEmpty = normRev.isEmpty;
            }

            // 4. Supplier Chart
            const ctxSup = sfAnalyticsHelpers.destroyCanvas('supplierChart', supplierChartObj);
            supplierChartObj = null;
            if (ctxSup) {
                const normSup = sfAnalyticsHelpers.normalizeDoughnut(
                    data.supplier.labels,
                    data.supplier.data,
                    ['#c5a059', '#6366f1', '#06b6d4', '#ec4899', '#10b981', '#f59e0b', '#64748b'],
                    emptyRing
                );
                supplierChartObj = new Chart(ctxSup.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: normSup.labels,
                        datasets: [{
                            data: normSup.data,
                            backgroundColor: normSup.backgroundColor,
                            borderWidth: normSup.isEmpty ? 0 : 2,
                            borderColor: donutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor, padding: 14, font: { size: 11 } } },
                            tooltip: { callbacks: sfAnalyticsHelpers.doughnutTooltipCallbacks(() => supplierChartObj) }
                        },
                        cutout: '62%'
                    }
                });
                supplierChartObj.$sfIsEmpty = normSup.isEmpty;
            }
        },

        updateCharts() {
            requestAnimationFrame(() => {
                this.initCharts();
            });
        }
    });
})();

// 5. Accounting Dashboard (Read-only preserved)
window.accountingDashboard = (() => {
    let invoiceChartObj = null;
    let specialChartObj = null;
    let costChartObj = null;
    let groupChartObj = null;

    return () => ({
        getPayload() {
            const el = document.getElementById('analytics-data-bridge');
            if (!el) {
                return {
                    invoices: { labels: [], data: [] },
                    special: { labels: [], data: [] },
                    costs: { labels: [], data: [] },
                    groups: { labels: [], data: [] }
                };
            }
            return {
                invoices: JSON.parse(el.getAttribute('data-invoices') || '{"labels":[],"data":[]}'),
                special: JSON.parse(el.getAttribute('data-special') || '{"labels":[],"data":[]}'),
                costs: JSON.parse(el.getAttribute('data-costs') || '{"labels":[],"data":[]}'),
                groups: JSON.parse(el.getAttribute('data-groups') || '{"labels":[],"data":[]}')
            };
        },

        initCharts() {
            const data = this.getPayload();
            const isDark = document.documentElement.classList.contains('dark');
            const gridOptions = { color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };
            const tickColor = isDark ? '#9ca3af' : '#6b7280';
            const doughnutBorder = isDark ? '#1f2937' : '#ffffff';

            if (invoiceChartObj) invoiceChartObj.destroy();
            if (specialChartObj) specialChartObj.destroy();
            if (costChartObj) costChartObj.destroy();
            if (groupChartObj) groupChartObj.destroy();

            // 1. Invoices
            const ctxInv = document.getElementById('invoiceChart');
            if (ctxInv) {
                invoiceChartObj = new Chart(ctxInv.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.invoices.labels,
                        datasets: [{
                            label: 'Erlöse (€)',
                            data: data.invoices.data,
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: isDark ? 'rgba(16, 185, 129, 0.1)' : 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(16, 185, 129, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: tickColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: tickColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Special Issues
            const ctxSpec = document.getElementById('specialChart');
            if (ctxSpec) {
                specialChartObj = new Chart(ctxSpec.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.special.labels,
                        datasets: [{
                            label: 'Sonderausgaben (€)',
                            data: data.special.data,
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: tickColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: tickColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 3. Cost Items
            const ctxCost = document.getElementById('costChart');
            if (ctxCost) {
                costChartObj = new Chart(ctxCost.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.costs.labels,
                        datasets: [{
                            label: 'Fixkosten Volumen (€)',
                            data: data.costs.data,
                            backgroundColor: 'rgba(99, 102, 241, 0.8)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: tickColor, precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: tickColor } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 4. Groups
            const ctxGrp = document.getElementById('groupChart');
            if (ctxGrp) {
                groupChartObj = new Chart(ctxGrp.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.groups.labels,
                        datasets: [{
                            data: data.groups.data,
                            backgroundColor: ['#6366f1', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4'],
                            borderWidth: 2, borderColor: doughnutBorder
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: tickColor, padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }
        },

        updateCharts() {
            const data = this.getPayload();

            const updateMap = [
                { obj: invoiceChartObj, src: data.invoices },
                { obj: specialChartObj, src: data.special },
                { obj: costChartObj, src: data.costs },
                { obj: groupChartObj, src: data.groups }
            ];

            updateMap.forEach(m => {
                if (m.obj && m.src) {
                    m.obj.data.labels = m.src.labels;
                    m.obj.data.datasets[0].data = m.src.data;
                    m.obj.update();
                }
            });
        }
    });
})();

// Register all dashboards with Alpine.js
const registerAnalyticsDashboards = () => {
    if (window.Alpine) {
        window.Alpine.data('supportDashboard', window.supportDashboard);
        window.Alpine.data('marketingDashboard', window.marketingDashboard);
        window.Alpine.data('orderAnalyticsDashboard', window.orderAnalyticsDashboard);
        window.Alpine.data('productDashboard', window.productDashboard);
        window.Alpine.data('accountingDashboard', window.accountingDashboard);
    }
};

if (window.Alpine) {
    registerAnalyticsDashboards();
} else {
    document.addEventListener('alpine:init', registerAnalyticsDashboards);
}
