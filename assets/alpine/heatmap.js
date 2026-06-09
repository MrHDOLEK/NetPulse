const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const HOURS_PER_DAY = 24;

function readBootstrap() {
  const node = document.getElementById("heatmap-bootstrap");
  if (!node) {
    return null;
  }
  try {
    return JSON.parse(node.textContent || "{}");
  } catch (error) {
    console.error("netpulse: bad heatmap bootstrap JSON", error);
    return null;
  }
}

export const heatmap = () => ({
  
  metric: "download",
  window: "30d",
  connectionId: "",

  
  cells: [],
  scale: {},
  legend: [],
  unit: "",

  
  connections: [],

  
  
  active: {},

  loading: false,

  init() {
    const bootstrap = readBootstrap();

    if (bootstrap) {
      this.metric = stringOr(bootstrap.metric, this.metric);
      this.window = stringOr(bootstrap.window, this.window);
      this.connectionId = stringOr(bootstrap.connectionId, this.connectionId);
      this.unit = stringOr(bootstrap.unit, this.unit);
      this.cells = Array.isArray(bootstrap.cells) ? bootstrap.cells : [];
      this.scale = bootstrap.scale || {};
      this.legend = Array.isArray(bootstrap.legend) ? bootstrap.legend : [];
      this.connections = Array.isArray(bootstrap.connections) ? bootstrap.connections : [];
    }

    
    
    this.$watch("metric", () => this.refetch());
    this.$watch("window", () => this.refetch());
    this.$watch("connectionId", () => this.refetch());

    this.bindDelegatedHover();
  },

  
  
  
  bindDelegatedHover() {
    const root = this.$root || document;
    const onPoint = (event) => {
      const target = event.target;
      if (!target || typeof target.closest !== "function") {
        return;
      }
      const td = target.closest("[data-dow]");
      if (td && root.contains(td)) {
        this.setActive(td.dataset);
      }
    };
    root.addEventListener("mouseover", onPoint);
    root.addEventListener("focusin", onPoint);
  },

  
  
  setActive(dataset) {
    const dow = Number(dataset.dow);
    const hour = Number(dataset.hour);
    const index = dow * HOURS_PER_DAY + hour;
    const cell = this.cells[index];
    this.active = cell || {};
  },

  
  buildQuery() {
    const params = new URLSearchParams();
    params.set("metric", this.metric);
    params.set("window", this.window);
    params.set("connection", this.connectionId);
    return params.toString();
  },

  
  
  async refetch() {
    if (!this.connectionId) {
      return;
    }
    this.loading = true;
    const query = this.buildQuery();
    try {
      const response = await fetch("/dashboard/heatmap?" + query, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.cells = Array.isArray(payload.cells) ? payload.cells : [];
      this.scale = payload.scale || {};
      this.legend = Array.isArray(payload.legend) ? payload.legend : [];
      this.unit = stringOr(payload.unit, this.unit);
      
      
      this.active = {};
      this.syncUrl(query);
    } catch (error) {
      console.error("netpulse: heatmap fetch failed", error);
    } finally {
      this.loading = false;
    }
  },

  
  
  syncUrl(query) {
    try {
      const url = query ? "/heatmap?" + query : "/heatmap";
      window.history.replaceState(null, "", url);
    } catch (error) {
      console.error("netpulse: heatmap URL sync failed", error);
    }
  },

  
  
  get rows() {
    const rows = [];
    for (let dow = 0; dow < WEEKDAYS.length; dow++) {
      const start = dow * HOURS_PER_DAY;
      rows.push({
        dow: dow,
        label: WEEKDAYS[dow],
        cells: this.cells.slice(start, start + HOURS_PER_DAY),
      });
    }
    return rows;
  },

  
  get hasActive() {
    return Boolean(this.active && this.active.aria);
  },
});

function stringOr(value, fallback) {
  return value === null || value === undefined ? fallback : String(value);
}
