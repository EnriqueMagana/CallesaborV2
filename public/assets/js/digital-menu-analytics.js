(() => {
    const charts = new Map();
    let frame = null;

    const destroy = () => {
        charts.forEach((chart) => { try { chart.destroy(); } catch (_) {} });
        charts.clear();
    };

    const empty = (element, message) => {
        element.innerHTML = `<div class="menu-analytics-empty" role="status"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i><strong>${message}</strong><span>Las interacciones aparecerán aquí automáticamente.</span></div>`;
    };

    const base = (type, height) => ({
        chart: { type, height, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif', animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#e8ece8', strokeDashArray: 4 },
        legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '11px' },
        tooltip: { shared: true },
        noData: { text: 'Sin datos todavía' },
    });

    const initialize = () => {
        frame = null;
        destroy();
        const root = document.querySelector('[data-menu-analytics-dashboard]');
        const source = root?.querySelector('[data-menu-analytics-data]');
        if (!root || !source || typeof ApexCharts === 'undefined') return;
        let data;
        try { data = JSON.parse(source.textContent); } catch (_) { return; }

        const trend = root.querySelector('[data-menu-analytics-chart="trend"]');
        if (trend) {
            if (!data.trend.views.some((value) => Number(value) > 0)) empty(trend, 'Aún no hay visualizaciones');
            else {
                const options = base('area', 310);
                options.series = [
                    { name: 'Vistas', data: data.trend.views },
                    { name: 'Visitantes', data: data.trend.visitors },
                    { name: 'Clics', data: data.trend.clicks },
                ];
                options.colors = ['#166534', '#d97706', '#2563eb'];
                options.stroke = { curve: 'smooth', width: [3, 2, 2] };
                options.fill = { type: 'gradient', gradient: { opacityFrom: .22, opacityTo: .02, stops: [0, 95, 100] } };
                options.xaxis = { categories: data.trend.labels, tickAmount: Math.min(8, data.trend.labels.length), labels: { rotate: 0, style: { colors: '#687369', fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } };
                options.yaxis = { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value), style: { colors: '#687369' } } };
                const chart = new ApexCharts(trend, options); charts.set(trend, chart); chart.render();
            }
        }

        const products = root.querySelector('[data-menu-analytics-chart="products"]');
        if (products) {
            if (!data.products.clicks.some((value) => Number(value) > 0)) empty(products, 'Aún no hay productos vistos');
            else {
                const options = base('bar', 310);
                options.series = [{ name: 'Clics', data: data.products.clicks }];
                options.colors = ['#166534'];
                options.legend = { show: false };
                options.plotOptions = { bar: { horizontal: true, borderRadius: 6, barHeight: '55%', distributed: false } };
                options.xaxis = { categories: data.products.labels, labels: { formatter: (value) => Math.round(value), style: { colors: '#687369', fontSize: '10px' } } };
                options.yaxis = { labels: { maxWidth: 135, style: { colors: '#334238', fontSize: '11px' } } };
                options.tooltip = { y: { formatter: (value) => `${Math.round(value)} clics` } };
                const chart = new ApexCharts(products, options); charts.set(products, chart); chart.render();
            }
        }
    };

    const schedule = () => {
        if (frame) cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => { frame = requestAnimationFrame(initialize); });
    };

    const hook = () => {
        if (!window.Livewire || window.__menuAnalyticsHook) return;
        window.__menuAnalyticsHook = true;
        Livewire.hook('morph.updated', ({ el }) => {
            if (el?.matches?.('[data-menu-analytics-dashboard]') || el?.closest?.('[data-menu-analytics-dashboard]') || el?.querySelector?.('[data-menu-analytics-dashboard]')) schedule();
        });
    };

    document.addEventListener('DOMContentLoaded', schedule);
    document.addEventListener('livewire:navigating', destroy);
    document.addEventListener('livewire:navigated', schedule);
    document.addEventListener('digital-menu-analytics-updated', schedule);
    document.addEventListener('livewire:init', hook);
    hook();
    schedule();
})();
