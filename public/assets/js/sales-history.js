(() => {
  const charts = new Map();
  let frameId = null;

  const palette = {
    primary: '#6956e8',
    secondary: '#2875bd',
    success: '#087f5b',
    muted: '#746d80',
    grid: '#ece9f2'
  };

  function destroyCharts() {
    charts.forEach(chart => {
      try { chart.destroy(); } catch (_) {}
    });
    charts.clear();
  }

  function readData(root) {
    const source = root.querySelector('[data-sales-audit-data]');
    if (!source) return null;
    try { return JSON.parse(source.textContent); } catch (_) { return null; }
  }

  function emptyChart(element, title) {
    element.innerHTML = '<div class="sales-audit-chart-empty" role="status"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i><strong>' + title + '</strong><span>Prueba con un periodo o filtro diferente.</span></div>';
  }

  function baseChart(type, height = 260) {
    return {
      chart: {
        type,
        height,
        toolbar: { show: false },
        fontFamily: 'Public Sans, sans-serif',
        animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches }
      },
      dataLabels: { enabled: false },
      grid: { borderColor: palette.grid, strokeDashArray: 4 },
      legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '11px' },
      tooltip: { shared: true },
      noData: { text: 'Sin datos' }
    };
  }

  function renderTrend(element, data, canViewFinancials) {
    if (!data?.orders?.some(value => Number(value) > 0)) {
      emptyChart(element, 'Sin ventas contabilizadas');
      return;
    }

    const options = baseChart('area', 270);
    options.series = canViewFinancials
      ? [{ name: 'Ventas', type: 'area', data: data.sales }, { name: 'Órdenes', type: 'line', data: data.orders }]
      : [{ name: 'Órdenes', data: data.orders }];
    options.colors = canViewFinancials ? [palette.primary, palette.secondary] : [palette.primary];
    options.stroke = { curve: 'smooth', width: canViewFinancials ? [3, 2] : 3 };
    options.fill = { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .03, stops: [0, 95, 100] } };
    options.xaxis = { categories: data.labels, labels: { style: { colors: palette.muted, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } };
    options.yaxis = canViewFinancials
      ? [
          { labels: { formatter: value => '$' + Number(value).toLocaleString('es-MX'), style: { colors: palette.muted } } },
          { opposite: true, labels: { formatter: value => Math.round(value), style: { colors: palette.muted } } }
        ]
      : { labels: { formatter: value => Math.round(value), style: { colors: palette.muted } } };
    options.tooltip = { shared: true, y: { formatter: (value, context) => canViewFinancials && context.seriesIndex === 0 ? '$' + Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2 }) : Math.round(value) + ' órdenes' } };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function renderBar(element, data, key, title, color, money = false) {
    const values = data?.[key] || [];
    if (!values.some(value => Number(value) > 0)) {
      emptyChart(element, title);
      return;
    }

    const options = baseChart('bar', 260);
    options.series = [{ name: title, data: values }];
    options.colors = [color];
    options.legend = { show: false };
    options.plotOptions = { bar: { horizontal: true, borderRadius: 5, barHeight: '58%' } };
    options.xaxis = { categories: data.labels, labels: { formatter: value => money ? '$' + Number(value).toLocaleString('es-MX') : Math.round(value), style: { colors: palette.muted, fontSize: '10px' } } };
    options.yaxis = { labels: { maxWidth: 125, style: { colors: palette.muted, fontSize: '10px' } } };
    options.tooltip = { y: { formatter: value => money ? '$' + Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2 }) : Math.round(value) + (key === 'units' ? ' unidades' : ' órdenes') } };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function initialize() {
    frameId = null;
    destroyCharts();
    const root = document.querySelector('[data-sales-audit-root][data-audit-state="ready"]');
    if (!root || typeof ApexCharts === 'undefined') return;
    const data = readData(root);
    if (!data) return;

    const trend = root.querySelector('[data-sales-audit-chart="trend"]');
    const products = root.querySelector('[data-sales-audit-chart="products"]');
    const customers = root.querySelector('[data-sales-audit-chart="customers"]');
    if (trend) renderTrend(trend, data.trend, data.canViewFinancials);
    if (products) renderBar(products, data.products, 'units', 'Unidades vendidas', palette.primary);
    if (customers) {
      const customerMetric = data.canViewFinancials ? 'sales' : 'orders';
      renderBar(customers, data.customers, customerMetric, data.canViewFinancials ? 'Ventas por cliente' : 'Órdenes por cliente', palette.success, data.canViewFinancials);
    }
  }

  function scheduleInitialize() {
    if (frameId !== null) cancelAnimationFrame(frameId);
    frameId = requestAnimationFrame(() => {
      frameId = requestAnimationFrame(initialize);
    });
  }

  function registerLivewireHook() {
    if (!window.Livewire || window.__salesAuditLivewireHook) return;
    window.__salesAuditLivewireHook = true;
    Livewire.hook('morph.updated', ({ el }) => {
      if (el?.matches?.('[data-sales-audit-root]') || el?.closest?.('[data-sales-audit-root]') || el?.querySelector?.('[data-sales-audit-root]')) scheduleInitialize();
    });
  }

  document.addEventListener('DOMContentLoaded', scheduleInitialize);
  document.addEventListener('livewire:navigating', destroyCharts);
  document.addEventListener('livewire:navigated', scheduleInitialize);
  document.addEventListener('sales-history-updated', scheduleInitialize);
  document.addEventListener('livewire:init', registerLivewireHook);
  registerLivewireHook();
  scheduleInitialize();
})();
