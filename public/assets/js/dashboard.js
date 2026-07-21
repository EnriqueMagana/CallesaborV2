(() => {
  const charts = new Map();

  const palette = {
    primary: '#6956e8',
    success: '#16875c',
    info: '#2875bd',
    warning: '#f3a83b',
    muted: '#a9a4b5',
    grid: '#ece9f2'
  };

  function destroyCharts() {
    charts.forEach(chart => chart.destroy());
    charts.clear();
  }

  function readData(root) {
    const source = root.querySelector('[data-dashboard-data]');
    if (!source) return null;
    try { return JSON.parse(source.textContent); } catch (_) { return null; }
  }

  function renderTrend(element, data) {
    const options = {
      chart: { type: 'area', height: 270, toolbar: { show: false }, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches } },
      series: [{ name: data.name, data: data.values }],
      colors: [palette.primary],
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 3 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .28, opacityTo: .03, stops: [0, 92, 100] } },
      grid: { borderColor: palette.grid, strokeDashArray: 4, padding: { left: 8, right: 12 } },
      xaxis: { categories: data.labels, labels: { style: { colors: palette.muted, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { formatter: value => data.money ? `$${Number(value).toLocaleString('es-MX')}` : Math.round(value), style: { colors: palette.muted, fontSize: '11px' } } },
      tooltip: { y: { formatter: value => data.money ? `$${Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2 })}` : `${Math.round(value)} pedidos` } },
      noData: { text: 'Sin actividad en este periodo' }
    };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function renderStatus(element, data) {
    const total = data.values.reduce((sum, value) => sum + Number(value), 0);
    const options = {
      chart: { type: 'donut', height: 270, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches } },
      series: data.values,
      labels: data.labels,
      colors: [palette.warning, palette.info, palette.success, palette.primary],
      stroke: { width: 4, colors: ['#fff'] },
      legend: { position: 'bottom', fontSize: '11px', markers: { width: 8, height: 8, radius: 8 } },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Pedidos', formatter: () => total } } } } },
      dataLabels: { enabled: false },
      tooltip: { y: { formatter: value => `${value} pedidos` } },
      noData: { text: 'Sin pedidos en este periodo' }
    };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function initializeDashboard() {
    destroyCharts();
    const root = document.querySelector('[data-dashboard-root]');
    if (!root || typeof ApexCharts === 'undefined') return;
    const data = readData(root);
    if (!data) return;
    const trend = root.querySelector('[data-dashboard-chart="dashboard-trend"]');
    const status = root.querySelector('[data-dashboard-chart="dashboard-status"]');
    if (trend) renderTrend(trend, data.trend);
    if (status) renderStatus(status, data.status);
  }

  function registerLivewireHook() {
    if (!window.Livewire || window.__dashboardLivewireHook) return;
    window.__dashboardLivewireHook = true;
    Livewire.hook('morph.updated', ({ el }) => {
      if (el.matches?.('[data-dashboard-root]') || el.querySelector?.('[data-dashboard-root]')) {
        queueMicrotask(initializeDashboard);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initializeDashboard);
  document.addEventListener('livewire:navigated', initializeDashboard);
  document.addEventListener('livewire:updated', initializeDashboard);
  document.addEventListener('dashboard-refreshed', initializeDashboard);
  document.addEventListener('livewire:init', registerLivewireHook);
  registerLivewireHook();
})();
