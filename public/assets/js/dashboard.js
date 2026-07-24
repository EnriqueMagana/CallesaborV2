(() => {
  const charts = new Map();
  let frameId = null;
  let timerId = null;

  const palette = {
    primary: '#6956e8',
    success: '#16875c',
    info: '#2875bd',
    warning: '#f3a83b',
    muted: '#746d80',
    grid: '#ece9f2'
  };

  function destroyCharts() {
    charts.forEach(chart => {
      try { chart.destroy(); } catch (_) {}
    });
    charts.clear();
  }

  function cancelPendingRender() {
    if (frameId !== null) cancelAnimationFrame(frameId);
    if (timerId !== null) clearTimeout(timerId);
    frameId = null;
    timerId = null;
  }

  function readData(root) {
    const source = root.querySelector('[data-dashboard-data]');
    if (!source) return null;

    try {
      return JSON.parse(source.textContent);
    } catch (_) {
      return null;
    }
  }

  function renderEmpty(element, message) {
    element.innerHTML = `
      <div class="dashboard-chart-empty" role="status">
        <i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i>
        <strong>${message}</strong>
        <span>Los datos aparecerán con la actividad del turno.</span>
      </div>
    `;
  }

  function renderTrend(element, data) {
    if (!data?.values?.some(value => Number(value) > 0)) {
      renderEmpty(element, 'Sin actividad en este periodo');
      return;
    }

    const options = {
      chart: {
        type: 'area',
        height: 270,
        toolbar: { show: false },
        animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches }
      },
      series: [{ name: data.name, data: data.values }],
      colors: [palette.primary],
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 3 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .28, opacityTo: .03, stops: [0, 92, 100] } },
      grid: { borderColor: palette.grid, strokeDashArray: 4, padding: { left: 8, right: 12 } },
      xaxis: {
        categories: data.labels,
        labels: { style: { colors: palette.muted, fontSize: '11px' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: {
        labels: {
          formatter: value => data.money ? `$${Number(value).toLocaleString('es-MX')}` : Math.round(value),
          style: { colors: palette.muted, fontSize: '11px' }
        }
      },
      tooltip: {
        y: {
          formatter: value => data.money
            ? `$${Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`
            : `${Math.round(value)} pedidos`
        }
      }
    };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function renderStatus(element, data) {
    const total = data?.values?.reduce((sum, value) => sum + Number(value), 0) ?? 0;
    if (total === 0) {
      renderEmpty(element, 'Sin pedidos en este periodo');
      return;
    }

    const options = {
      chart: {
        type: 'donut',
        height: 270,
        animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches }
      },
      series: data.values,
      labels: data.labels,
      colors: [palette.warning, palette.info, palette.success, palette.primary],
      stroke: { width: 4, colors: ['#fff'] },
      legend: { position: 'bottom', fontSize: '11px', markers: { width: 8, height: 8, radius: 8 } },
      plotOptions: {
        pie: {
          donut: {
            size: '68%',
            labels: { show: true, total: { show: true, label: 'Pedidos', formatter: () => total } }
          }
        }
      },
      dataLabels: { enabled: false },
      tooltip: { y: { formatter: value => `${value} pedidos` } }
    };
    const chart = new ApexCharts(element, options);
    charts.set(element, chart);
    chart.render();
  }

  function initializeDashboard(attempt = 0) {
    frameId = null;
    const root = document.querySelector('[data-dashboard-root][data-dashboard-state="active"]');
    if (!root || typeof ApexCharts === 'undefined') {
      destroyCharts();
      return;
    }

    const data = readData(root);
    if (!data) return;

    const trend = root.querySelector('[data-dashboard-chart="dashboard-trend"]');
    const status = root.querySelector('[data-dashboard-chart="dashboard-status"]');
    const chartWidth = trend?.getBoundingClientRect().width ?? status?.getBoundingClientRect().width ?? 0;

    if (chartWidth === 0 && attempt < 4) {
      timerId = window.setTimeout(() => scheduleInitialize(attempt + 1), 60);
      return;
    }

    destroyCharts();
    if (trend) renderTrend(trend, data.trend);
    if (status) renderStatus(status, data.status);
  }

  function scheduleInitialize(attempt = 0) {
    cancelPendingRender();
    frameId = requestAnimationFrame(() => {
      frameId = requestAnimationFrame(() => initializeDashboard(attempt));
    });
  }

  function registerLivewireHook() {
    if (!window.Livewire || window.__dashboardLivewireHook) return;
    window.__dashboardLivewireHook = true;

    Livewire.hook('morph.updated', ({ el }) => {
      const touchesDashboard = el?.matches?.('[data-dashboard-root]')
        || el?.closest?.('[data-dashboard-root]')
        || el?.querySelector?.('[data-dashboard-root]');

      if (touchesDashboard) scheduleInitialize();
    });
  }

  document.addEventListener('DOMContentLoaded', () => scheduleInitialize());
  document.addEventListener('livewire:navigating', () => {
    cancelPendingRender();
    destroyCharts();
  });
  document.addEventListener('livewire:navigated', () => scheduleInitialize());
  document.addEventListener('dashboard-refreshed', () => scheduleInitialize());
  document.addEventListener('livewire:init', registerLivewireHook);

  registerLivewireHook();
  scheduleInitialize();
})();
