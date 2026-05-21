/**
 * Seelenfunke Analytics Dashboards
 * Encapsulates Chart.js instances in closures for Alpine.js integration to prevent memory leaks and reactivity pollution.
 */

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
            if (!el) {
                return {
                    volume: { labels: [], data: [] },
                    source: { labels: [], data: [] },
                    ticketStatus: { labels: [], data: [] },
                    chatStatus: { labels: [], data: [] },
                    chatRating: { labels: [], data: [] },
                    ticketRating: { labels: [], data: [] }
                };
            }
            return {
                volume: JSON.parse(el.getAttribute('data-volume') || '{"labels":[],"data":[]}'),
                source: JSON.parse(el.getAttribute('data-source') || '{"labels":[],"data":[]}'),
                ticketStatus: JSON.parse(el.getAttribute('data-ticketstatus') || '{"labels":[],"data":[]}'),
                chatStatus: JSON.parse(el.getAttribute('data-chatstatus') || '{"labels":[],"data":[]}'),
                chatRating: JSON.parse(el.getAttribute('data-chatrating') || '{"labels":[],"data":[]}'),
                ticketRating: JSON.parse(el.getAttribute('data-ticketrating') || '{"labels":[],"data":[]}')
            };
        },

        initCharts() {
            const data = this.getPayload();
            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };
            const el = document.getElementById('analytics-data-bridge');
            const tc = el ? el.getAttribute('data-theme-color') || '#c5a059' : '#c5a059';
            
            // Generate monochromatic gradient palette
            const monoPalette = [tc, tc+'e6', tc+'cc', tc+'b3', tc+'99', tc+'80', tc+'66', tc+'4d', tc+'33', tc+'1a'];

            // Destroy existing charts to prevent canvas reuse errors
            if (volumeChartObj) volumeChartObj.destroy();
            if (sourceChartObj) sourceChartObj.destroy();
            if (ticketStatusChartObj) ticketStatusChartObj.destroy();
            if (chatStatusChartObj) chatStatusChartObj.destroy();
            if (chatRatingChartObj) chatRatingChartObj.destroy();
            if (ticketRatingChartObj) ticketRatingChartObj.destroy();

            // 1. Volume Growth
            const ctxVol = document.getElementById('volumeChart');
            if (ctxVol) {
                volumeChartObj = new Chart(ctxVol.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.volume.labels,
                        datasets: [{
                            label: 'Support Volumen',
                            data: data.volume.data,
                            borderColor: tc,
                            backgroundColor: tc + '1a',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: tc, pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Source Distribution
            const ctxSrc = document.getElementById('sourceChart');
            if (ctxSrc) {
                sourceChartObj = new Chart(ctxSrc.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.source.labels,
                        datasets: [{
                            data: data.source.data,
                            backgroundColor: monoPalette,
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 3. Ticket Status
            const ctxTick = document.getElementById('ticketStatusChart');
            if (ctxTick) {
                ticketStatusChartObj = new Chart(ctxTick.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.ticketStatus.labels,
                        datasets: [{
                            data: data.ticketStatus.data,
                            backgroundColor: monoPalette,
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 4. Chat Status
            const ctxChat = document.getElementById('chatStatusChart');
            if (ctxChat) {
                chatStatusChartObj = new Chart(ctxChat.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.chatStatus.labels,
                        datasets: [{
                            data: data.chatStatus.data,
                            backgroundColor: monoPalette,
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 5. Chat Rating
            const ctxRating = document.getElementById('chatRatingChart');
            if (ctxRating) {
                chatRatingChartObj = new Chart(ctxRating.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.chatRating.labels,
                        datasets: [{
                            data: data.chatRating.data,
                            backgroundColor: monoPalette,
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 6. Ticket Rating
            const ctxTicketRating = document.getElementById('ticketRatingChart');
            if (ctxTicketRating) {
                ticketRatingChartObj = new Chart(ctxTicketRating.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.ticketRating.labels,
                        datasets: [{
                            data: data.ticketRating.data,
                            backgroundColor: monoPalette,
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }
        },

        updateCharts() {
            const data = this.getPayload();
            
            const updateMap = [
                { obj: volumeChartObj, src: data.volume },
                { obj: sourceChartObj, src: data.source },
                { obj: ticketStatusChartObj, src: data.ticketStatus },
                { obj: chatStatusChartObj, src: data.chatStatus },
                { obj: chatRatingChartObj, src: data.chatRating },
                { obj: ticketRatingChartObj, src: data.ticketRating }
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
            if (!el) {
                return {
                    newsletter: { labels: [], data: [] },
                    voucher: { labels: [], data: [] },
                    blog: { labels: [], data: [] },
                    voucherType: { labels: [], data: [] },
                    landingPage: { labels: [], data: [] }
                };
            }
            return {
                newsletter: JSON.parse(el.getAttribute('data-newsletter') || '{"labels":[],"data":[]}'),
                voucher: JSON.parse(el.getAttribute('data-voucher') || '{"labels":[],"data":[]}'),
                blog: JSON.parse(el.getAttribute('data-blog') || '{"labels":[],"data":[]}'),
                voucherType: JSON.parse(el.getAttribute('data-vouchertype') || '{"labels":[],"data":[]}'),
                landingPage: JSON.parse(el.getAttribute('data-landingpage') || '{"labels":[],"data":[]}')
            };
        },

        initCharts() {
            const data = this.getPayload();
            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };

            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

            if (newsletterChartObj) newsletterChartObj.destroy();
            if (voucherChartObj) voucherChartObj.destroy();
            if (blogChartObj) blogChartObj.destroy();
            if (voucherTypeChartObj) voucherTypeChartObj.destroy();
            if (landingPageChartObj) landingPageChartObj.destroy();

            // 1. Newsletter Growth
            const ctxNl = document.getElementById('newsletterChart');
            if (ctxNl) {
                newsletterChartObj = new Chart(ctxNl.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: toArr(data.newsletter.labels),
                        datasets: [{
                            label: 'Neue Abonnenten',
                            data: toArr(data.newsletter.data),
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(16, 185, 129, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Voucher Gen
            const ctxVc = document.getElementById('voucherChart');
            if (ctxVc) {
                voucherChartObj = new Chart(ctxVc.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: toArr(data.voucher.labels),
                        datasets: [{
                            label: 'Erstellte Gutscheine',
                            data: toArr(data.voucher.data),
                            backgroundColor: 'rgba(244, 63, 94, 0.8)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 3. Blog Categories
            const ctxBl = document.getElementById('blogChart');
            if (ctxBl) {
                blogChartObj = new Chart(ctxBl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: toArr(data.blog.labels),
                        datasets: [{
                            data: toArr(data.blog.data),
                            backgroundColor: ['#6366f1', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'],
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 4. Voucher Types
            const ctxVt = document.getElementById('voucherTypeChart');
            if (ctxVt) {
                voucherTypeChartObj = new Chart(ctxVt.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: toArr(data.voucherType.labels),
                        datasets: [{
                            data: toArr(data.voucherType.data),
                            backgroundColor: ['#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#10b981', '#6366f1'],
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 5. Landing Page Visits
            const ctxLp = document.getElementById('landingPageChart');
            if (ctxLp) {
                landingPageChartObj = new Chart(ctxLp.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: toArr(data.landingPage.labels),
                        datasets: [{
                            label: 'Seitenaufrufe',
                            data: toArr(data.landingPage.data),
                            borderColor: 'rgba(168, 85, 247, 1)',
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(168, 85, 247, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        },

        updateCharts() {
            const data = this.getPayload();
            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

            const updateMap = [
                { obj: newsletterChartObj, src: data.newsletter },
                { obj: voucherChartObj, src: data.voucher },
                { obj: blogChartObj, src: data.blog },
                { obj: voucherTypeChartObj, src: data.voucherType },
                { obj: landingPageChartObj, src: data.landingPage }
            ];

            updateMap.forEach(m => {
                if (m.obj && m.src) {
                    m.obj.data.labels = toArr(m.src.labels);
                    m.obj.data.datasets[0].data = toArr(m.src.data);
                    m.obj.update();
                }
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
            if (!el) {
                return {
                    b2b: { labels: [], data: [] },
                    processing: { labels: [], data: [] },
                    peak: { labels: [], data: [] },
                    retention: { labels: [], data: [] },
                    cancellation: { labels: [], data: [] },
                    bestseller: { labels: [], data: [] },
                    weekday: { labels: [], data: [] },
                    quotes: { labels: [], data: [] },
                    revocations: { labels: [], data: [] }
                };
            }
            return {
                b2b: JSON.parse(el.getAttribute('data-b2b') || '{"labels":[],"data":[]}'),
                processing: JSON.parse(el.getAttribute('data-processing') || '{"labels":[],"data":[]}'),
                peak: JSON.parse(el.getAttribute('data-peak') || '{"labels":[],"data":[]}'),
                retention: JSON.parse(el.getAttribute('data-retention') || '{"labels":[],"data":[]}'),
                cancellation: JSON.parse(el.getAttribute('data-cancellation') || '{"labels":[],"data":[]}'),
                bestseller: JSON.parse(el.getAttribute('data-bestseller') || '{"labels":[],"data":[]}'),
                weekday: JSON.parse(el.getAttribute('data-weekday') || '{"labels":[],"data":[]}'),
                quotes: JSON.parse(el.getAttribute('data-quotes') || '{"labels":[],"data":[]}'),
                revocations: JSON.parse(el.getAttribute('data-revocations') || '{"labels":[],"data":[]}')
            };
        },

        initCharts() {
            const el = document.getElementById('analytics-data-bridge');
            const themeColor = el ? el.getAttribute('data-theme-color') || '#c5a059' : '#c5a059';

            const colors = {
                primary: themeColor,
                primaryLight: themeColor + '33',
                blue: 'rgba(59, 130, 246, 1)',
                blueLight: 'rgba(59, 130, 246, 0.2)',
                purple: 'rgba(168, 85, 247, 1)',
                emerald: 'rgba(16, 185, 129, 1)',
                rose: 'rgba(225, 29, 72, 1)',
                roseLight: 'rgba(225, 29, 72, 0.2)',
                amber: 'rgba(245, 158, 11, 1)',
                cyan: 'rgba(6, 182, 212, 1)',
                cyanLight: 'rgba(6, 182, 212, 0.2)',
                gray: 'rgba(156, 163, 175, 1)'
            };
            
            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', tickColor: 'transparent' };
            const gridOptionsX = { display: false };
            const data = this.getPayload();

            if (b2bChartObj) b2bChartObj.destroy();
            if (processingChartObj) processingChartObj.destroy();
            if (peakChartObj) peakChartObj.destroy();
            if (retentionChartObj) retentionChartObj.destroy();
            if (cancellationChartObj) cancellationChartObj.destroy();
            if (bestsellerChartObj) bestsellerChartObj.destroy();
            if (weekdayChartObj) weekdayChartObj.destroy();
            if (quotesChartObj) quotesChartObj.destroy();
            if (revocationsChartObj) revocationsChartObj.destroy();

            // 1. Retention Rate (Doughnut)
            const ctxRet = document.getElementById('retentionChart');
            if (ctxRet) {
                retentionChartObj = new Chart(ctxRet.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.retention.labels,
                        datasets: [{
                            data: data.retention.data,
                            backgroundColor: [colors.emerald, 'rgba(255, 255, 255, 0.1)'],
                            borderColor: ['rgba(0,0,0,0)', 'rgba(0,0,0,0)'],
                            borderWidth: 0, hoverOffset: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { color: colors.gray } } } }
                });
            }

            // 2. B2B vs B2C (Doughnut)
            const ctxB2b = document.getElementById('b2bChart');
            if (ctxB2b) {
                b2bChartObj = new Chart(ctxB2b.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.b2b.labels,
                        datasets: [{
                            data: data.b2b.data,
                            backgroundColor: ['rgba(255, 255, 255, 0.1)', colors.blue],
                            borderColor: ['rgba(0,0,0,0)', 'rgba(0,0,0,0)'],
                            borderWidth: 0, hoverOffset: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { color: colors.gray } } } }
                });
            }

            // 3. Cancellation Drain (Line)
            const ctxCan = document.getElementById('cancellationChart');
            if (ctxCan) {
                cancellationChartObj = new Chart(ctxCan.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.cancellation.labels,
                        datasets: [{
                            label: 'Verlorener Wert in €',
                            data: data.cancellation.data,
                            borderColor: colors.rose,
                            backgroundColor: colors.roseLight,
                            borderWidth: 2, tension: 0.1, fill: true,
                            pointBackgroundColor: colors.rose, pointBorderColor: '#fff'
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
            const ctxBest = document.getElementById('bestsellerChart');
            if (ctxBest) {
                bestsellerChartObj = new Chart(ctxBest.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.bestseller.labels,
                        datasets: [{
                            label: 'Verkaufte Einheiten',
                            data: data.bestseller.data,
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
            const ctxProc = document.getElementById('processingChart');
            if (ctxProc) {
                processingChartObj = new Chart(ctxProc.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.processing.labels,
                        datasets: [{
                            label: 'Dauer (Std)',
                            data: data.processing.data,
                            borderColor: colors.cyan,
                            backgroundColor: colors.cyanLight,
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: colors.cyan, pointBorderColor: '#fff',
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
            const ctxPeak = document.getElementById('peakChart');
            if (ctxPeak) {
                peakChartObj = new Chart(ctxPeak.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.peak.labels,
                        datasets: [{
                            label: 'Bestellungen pro Uhrzeit',
                            data: data.peak.data,
                            backgroundColor: colors.purple,
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
            const ctxWeek = document.getElementById('weekdayChart');
            if (ctxWeek) {
                weekdayChartObj = new Chart(ctxWeek.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.weekday.labels,
                        datasets: [{
                            label: 'Bestellungen pro Wochentag',
                            data: data.weekday.data,
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
            const ctxQuotes = document.getElementById('quotesChart');
            if (ctxQuotes) {
                quotesChartObj = new Chart(ctxQuotes.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.quotes.labels,
                        datasets: [{
                            label: 'Angebote',
                            data: data.quotes.data,
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.2)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(99, 102, 241, 1)', pointBorderColor: '#fff',
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
            const ctxRevs = document.getElementById('revocationsChart');
            if (ctxRevs) {
                revocationsChartObj = new Chart(ctxRevs.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.revocations.labels,
                        datasets: [{
                            label: 'Widerrufe',
                            data: data.revocations.data,
                            borderColor: 'rgba(239, 68, 68, 1)',
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(239, 68, 68, 1)', pointBorderColor: '#fff',
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
            const data = this.getPayload();

            const mapping = [
                { obj: retentionChartObj, source: data.retention },
                { obj: b2bChartObj, source: data.b2b },
                { obj: cancellationChartObj, source: data.cancellation },
                { obj: bestsellerChartObj, source: data.bestseller },
                { obj: processingChartObj, source: data.processing },
                { obj: peakChartObj, source: data.peak },
                { obj: weekdayChartObj, source: data.weekday },
                { obj: quotesChartObj, source: data.quotes },
                { obj: revocationsChartObj, source: data.revocations }
            ];

            mapping.forEach(item => {
                if (item.obj) {
                    item.obj.data.labels = item.source.labels;
                    item.obj.data.datasets[0].data = item.source.data;
                    item.obj.update();
                }
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
            if (!el) {
                return {
                    loss: { labels: [], data: [] },
                    topLoss: { labels: [], data: [] },
                    review: { labels: [], data: [] },
                    supplier: { labels: [], data: [] }
                };
            }
            return {
                loss: JSON.parse(el.getAttribute('data-loss') || '{"labels":[],"data":[]}'),
                topLoss: JSON.parse(el.getAttribute('data-toploss') || '{"labels":[],"data":[]}'),
                review: JSON.parse(el.getAttribute('data-review') || '{"labels":[],"data":[]}'),
                supplier: JSON.parse(el.getAttribute('data-supplier') || '{"labels":[],"data":[]}')
            };
        },

        initCharts() {
            const data = this.getPayload();
            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };

            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

            if (lossChartObj) lossChartObj.destroy();
            if (topLossChartObj) topLossChartObj.destroy();
            if (reviewChartObj) reviewChartObj.destroy();
            if (supplierChartObj) supplierChartObj.destroy();

            // 1. Loss Chart
            const ctxLs = document.getElementById('lossChart');
            if (ctxLs) {
                lossChartObj = new Chart(ctxLs.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: toArr(data.loss.labels),
                        datasets: [{
                            label: 'Beschädigte Artikel (Menge)',
                            data: toArr(data.loss.data),
                            backgroundColor: 'rgba(244, 63, 94, 0.8)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Top Loss Chart
            const ctxTop = document.getElementById('topLossChart');
            if (ctxTop) {
                topLossChartObj = new Chart(ctxTop.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: toArr(data.topLoss.labels),
                        datasets: [{
                            label: 'Finanzieller Schaden (€)',
                            data: toArr(data.topLoss.data),
                            backgroundColor: 'rgba(239, 68, 68, 0.9)',
                            borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, y: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 3. Review Chart
            const ctxRev = document.getElementById('reviewChart');
            if (ctxRev) {
                reviewChartObj = new Chart(ctxRev.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: toArr(data.review.labels),
                        datasets: [{
                            data: toArr(data.review.data),
                            backgroundColor: ['#10b981', '#34d399', '#fbbf24', '#f87171', '#ef4444'],
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }

            // 4. Supplier Chart
            const ctxSup = document.getElementById('supplierChart');
            if (ctxSup) {
                supplierChartObj = new Chart(ctxSup.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: toArr(data.supplier.labels),
                        datasets: [{
                            data: toArr(data.supplier.data),
                            backgroundColor: ['#6366f1', '#06b6d4', '#ec4899', '#f59e0b', '#8b5cf6', '#14b8a6', '#64748b'],
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
                        cutout: '60%'
                    }
                });
            }
        },

        updateCharts() {
            const data = this.getPayload();
            const toArr = (val) => Array.isArray(val) ? val : Object.values(val || {});

            const updateMap = [
                { obj: lossChartObj, src: data.loss },
                { obj: topLossChartObj, src: data.topLoss },
                { obj: reviewChartObj, src: data.review },
                { obj: supplierChartObj, src: data.supplier }
            ];

            updateMap.forEach(m => {
                if (m.obj && m.src) {
                    m.obj.data.labels = toArr(m.src.labels);
                    m.obj.data.datasets[0].data = toArr(m.src.data);
                    m.obj.update();
                }
            });
        }
    });
})();

// 5. Accounting Dashboard
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
            const gridOptions = { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false };
            const gridOptionsX = { display: false, drawBorder: false };

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
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2, tension: 0.4, fill: true,
                            pointBackgroundColor: 'rgba(16, 185, 129, 1)', pointBorderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
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
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
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
                        scales: { y: { beginAtZero: true, grid: gridOptions, ticks: { color: '#9ca3af', precision: 0 } }, x: { grid: gridOptionsX, ticks: { color: '#9ca3af' } } },
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
                            borderWidth: 2, borderColor: '#1f2937'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right', labels: { color: '#9ca3af', padding: 15, font: { size: 10 } } } },
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
