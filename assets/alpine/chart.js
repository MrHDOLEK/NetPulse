import uPlot from "uplot";

function token(name, fallback) {
  const value = getComputedStyle(document.documentElement)
    .getPropertyValue(name)
    .trim();
  return value || fallback;
}

function fill(color) {
  return `color-mix(in oklab, ${color} 18%, transparent)`;
}

function trimmedOneDecimal(value) {
  const rounded = Math.round(value * 10) / 10;
  return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
}

function bitsAxisLabel(bitsPerSecond) {
  if (bitsPerSecond === null || bitsPerSecond === undefined) {
    return "";
  }
  const mbps = bitsPerSecond / 1_000_000;
  if (mbps >= 1000) {
    return (mbps / 1000).toFixed(1);
  }
  return trimmedOneDecimal(mbps);
}

function secondsAxisLabel(seconds) {
  if (seconds === null || seconds === undefined) {
    return "";
  }
  return trimmedOneDecimal(seconds * 1000);
}

function ratioAxisLabel(ratio) {
  if (ratio === null || ratio === undefined) {
    return "";
  }
  return trimmedOneDecimal(ratio * 100);
}

function yAxisValues(metric) {
  let labelFor;
  if (metric === "ping") {
    labelFor = secondsAxisLabel;
  } else if (metric === "loss") {
    labelFor = ratioAxisLabel;
  } else {
    labelFor = bitsAxisLabel;
  }
  return (_self, splits) => splits.map((value) => labelFor(value));
}

function seriesSpec(metric, only) {
  let spec;

  if (metric === "ping") {
    spec = {
      keys: ["ping"],
      series: [{ label: "Ping", stroke: token("--amber", "#d6a23e") }],
    };
  } else if (metric === "loss") {
    spec = {
      keys: ["loss"],
      series: [{ label: "Loss", stroke: token("--bad", "#d2483a") }],
    };
  } else {
    
    spec = {
      keys: ["dl", "up"],
      series: [
        { label: "Download", stroke: token("--accent", "#46c8d6") },
        { label: "Upload", stroke: token("--violet", "#9b7be0") },
      ],
    };
  }

  if (only) {
    const index = spec.keys.indexOf(only);
    if (index !== -1) {
      return { keys: [only], series: [spec.series[index]] };
    }
  }

  return spec;
}

function toColumns(payload, keys) {
  const buckets = Array.isArray(payload?.buckets) ? payload.buckets : [];
  const xs = [];
  const ys = keys.map(() => []);

  for (const bucket of buckets) {
    xs.push(bucket.t);
    keys.forEach((key, column) => {
      const value = bucket[key];
      ys[column].push(value === undefined || value === null ? null : value);
    });
  }

  return [xs, ...ys];
}

function chartOptions(el, metric, only) {
  const spec = seriesSpec(metric, only);
  const axisColor = token("--text-faint", "#8a8f99");
  const gridColor = token("--border", "#3a3f4a");

  return {
    width: el.clientWidth || 600,
    height: el.clientHeight || 250,
    
    
    tzDate: (ts) => new Date(ts * 1000),
    legend: { show: false },
    cursor: { y: false },
    scales: { x: { time: true } },
    axes: [
      
      {
        stroke: axisColor,
        grid: { stroke: gridColor, width: 1 },
        ticks: { stroke: gridColor, width: 1 },
        font: "11px var(--font-sans, sans-serif)",
      },
      
      
      {
        stroke: axisColor,
        grid: { stroke: gridColor, width: 1 },
        ticks: { stroke: gridColor, width: 1 },
        font: "11px var(--font-sans, sans-serif)",
        values: yAxisValues(metric),
      },
    ],
    series: [
      {},
      ...spec.series.map((descriptor) => ({
        label: descriptor.label,
        stroke: descriptor.stroke,
        width: 2,
        fill: fill(descriptor.stroke),
        
        
        
        
        spanGaps: true,
        
        
        
        
        
        points: { show: true, size: 5 },
      })),
    ],
  };
}

function sparkOptions(el, metric, only) {
  const spec = seriesSpec(metric, only);

  return {
    width: el.clientWidth || 96,
    height: el.clientHeight || 32,
    legend: { show: false },
    cursor: { show: false },
    scales: { x: { time: false } },
    axes: [{ show: false }, { show: false }],
    series: [
      {},
      ...spec.series.map((descriptor) => ({
        stroke: descriptor.stroke,
        width: 1.5,
        fill: fill(descriptor.stroke),
        
        
        spanGaps: true,
        points: { show: true, size: 3 },
      })),
    ],
  };
}

export function createChart(el, opts = {}) {
  let metric = opts.metric || "speed";
  let connection = opts.connection || null;
  let range = opts.range || "7d";
  const isSpark = Boolean(opts.spark);
  const only = opts.only || null;

  let plot = null;
  let lastPayload = opts.data || { buckets: [] };
  let destroyed = false;

  function build() {
    if (plot) {
      plot.destroy();
      plot = null;
    }

    if (destroyed) {
      return;
    }

    const options = isSpark
      ? sparkOptions(el, metric, only)
      : chartOptions(el, metric, only);
    const data = toColumns(lastPayload, seriesSpec(metric, only).keys);
    plot = new uPlot(options, data, el);
  }

  function render() {
    if (!plot || destroyed) {
      return;
    }
    plot.setData(toColumns(lastPayload, seriesSpec(metric, only).keys));
  }

  
  const resizeObserver = new ResizeObserver(() => {
    if (!plot || destroyed) {
      return;
    }
    const width = el.clientWidth;
    const height = el.clientHeight;
    if (width > 0 && height > 0) {
      plot.setSize({ width, height });
    }
  });
  resizeObserver.observe(el);

  
  
  async function refresh() {
    if (!connection || destroyed) {
      return;
    }

    const url =
      "/dashboard/series?connection=" +
      encodeURIComponent(connection) +
      "&range=" +
      encodeURIComponent(range) +
      "&metric=" +
      encodeURIComponent(metric);

    try {
      const response = await fetch(url, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      lastPayload = payload;
      render();
    } catch (error) {
      console.error("netpulse: series fetch failed", error);
    }
  }

  build();

  const controller = {
    
    setData(payload) {
      lastPayload = payload || { buckets: [] };
      render();
    },
    
    
    
    getData() {
      return lastPayload;
    },
    
    
    getMetric() {
      return metric;
    },
    getConnection() {
      return connection;
    },
    getRange() {
      return range;
    },
    
    setMetric(next) {
      if (next === metric) {
        return;
      }
      metric = next;
      build();
      return refresh();
    },
    setRange(next) {
      if (next === range) {
        return;
      }
      range = next;
      return refresh();
    },
    setConnection(next) {
      if (next === connection) {
        return;
      }
      connection = next;
      return refresh();
    },
    
    
    
    configure(next = {}) {
      if (next.connection !== undefined) {
        connection = next.connection;
      }
      if (next.range !== undefined) {
        range = next.range;
      }
    },
    refresh,
    destroy() {
      destroyed = true;
      resizeObserver.disconnect();
      if (plot) {
        plot.destroy();
        plot = null;
      }
    },
  };

  return controller;
}

export const chart = () => ({
  controller: null,
  init() {
    const el = this.$el;
    const isSpark =
      el.hasAttribute("data-spark") || el.dataset.chartVariant === "spark";

    this.controller = createChart(el, {
      connection: el.dataset.connectionId || null,
      metric: el.dataset.metric || "speed",
      range: el.dataset.range || "7d",
      spark: isSpark,
      only: el.dataset.only || null,
    });

    
    this.controller.refresh();
  },
  destroy() {
    if (this.controller) {
      this.controller.destroy();
      this.controller = null;
    }
  },
});
