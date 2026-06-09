import { createChart } from "./chart.js";

const CONNECTION_KEY = "netpulse:connection";
const POLL_INTERVAL_MS = 30_000;

const CURSOR_INTERVAL_MS = 5_000;
const TICK_INTERVAL_MS = 1_000;
const NULL_PLACEHOLDER = "—";

const RUN_TEST_TIMEOUT_MS = 70_000;

const RUN_TEST_DONE_MS = 2_500;

const RUN_STATUS_INTERVAL_MS = 2_000;

function trimmedOneDecimal(value) {
  const rounded = Math.round(value * 10) / 10;
  return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
}

function formatBits(bitsPerSecond) {
  if (bitsPerSecond === null || bitsPerSecond === undefined) {
    return NULL_PLACEHOLDER;
  }
  const mbps = bitsPerSecond / 1_000_000;
  if (mbps >= 1000) {
    return (mbps / 1000).toFixed(1) + " Gbps";
  }
  return trimmedOneDecimal(mbps) + " Mbps";
}

function formatSeconds(seconds) {
  if (seconds === null || seconds === undefined) {
    return NULL_PLACEHOLDER;
  }
  return trimmedOneDecimal(seconds * 1000) + " ms";
}

function formatRatio(ratio) {
  if (ratio === null || ratio === undefined) {
    return NULL_PLACEHOLDER;
  }
  return trimmedOneDecimal(ratio * 100) + " %";
}

function formatUptime(uptime) {
  const clamped = Math.min(100, Math.max(0, uptime));
  return clamped === 100 ? "100" : clamped.toFixed(1);
}

const STATUS_LABELS = { healthy: "Healthy", degraded: "Degraded", down: "Down" };

function statusLabel(status) {
  return STATUS_LABELS[status] || status || "";
}

function readBootstrap() {
  const node = document.getElementById("dashboard-bootstrap");
  if (!node) {
    return null;
  }
  try {
    return JSON.parse(node.textContent || "{}");
  } catch (error) {
    console.error("netpulse: bad dashboard bootstrap JSON", error);
    return null;
  }
}

export const dashboard = () => ({
  activeConnection: null,
  range: "7d",
  metric: "speed",
  chart: null,
  
  
  tileSparks: null,
  pollTimer: null,

  
  
  
  
  
  
  cursorTimer: null,
  tickTimer: null,
  lastCursorKey: null,
  lastUpdatedAtMs: null,
  updatedAgo: NULL_PLACEHOLDER,

  
  
  
  
  
  
  runState: "idle",
  runWaiting: false,
  runConnection: null,
  runBaselineCompletedAt: null,
  runTimeoutTimer: null,
  runDoneTimer: null,
  runStatusTimer: null,

  
  
  
  
  runModalOpen: false,
  runScope: "connection",
  runServerMode: "auto",
  runServerId: "",
  
  
  
  ooklaServers: [],
  ooklaServersLoaded: false,
  
  
  latestCompletedAt: {},

  init() {
    const bootstrap = readBootstrap();

    
    
    this.range = bootstrap?.range || "7d";
    this.metric = bootstrap?.metric || "speed";
    this.activeConnection =
      localStorage.getItem(CONNECTION_KEY) || bootstrap?.connectionId || null;

    
    
    
    
    
    
    const host = document.getElementById("main-chart");
    if (host) {
      this.chart = createChart(host, {
        connection: this.activeConnection,
        range: this.range,
        metric: this.metric,
        data: bootstrap || { buckets: [] },
      });

      
      
      if (
        bootstrap &&
        this.activeConnection &&
        this.activeConnection !== bootstrap.connectionId
      ) {
        this.chart.setConnection(this.activeConnection);
      }
    }

    this.initTileSparks(bootstrap);
    
    
    
    
    this.lastUpdatedAtMs = Date.now();
    this.refreshUpdatedAgo();
    this.startPolling();
    this.startCursorPolling();

    
    
    
    
    
    
    this.$watch("range", (value) => this.setRange(value));
    this.$watch("metric", (value) => this.setMetric(value));
    this.bindCardClicks();
    this.applyActiveRing();
  },

  destroy() {
    this.stopPolling();
    this.clearRunTimers();
    this.stopRunStatusPolling();
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
    this.destroyTileSparks();
  },

  

  
  
  
  selectConnection(id) {
    if (!id || id === this.activeConnection) {
      return;
    }
    this.activeConnection = id;
    localStorage.setItem(CONNECTION_KEY, id);
    this.applyActiveRing();
    if (this.chart) {
      this.chart.setConnection(id);
    }
    
    this.refreshTileSparks();
  },

  
  
  
  bindCardClicks() {
    const root = this.$root || document;
    root.addEventListener("click", (event) => {
      const card = event.target.closest("[data-card-connection]");
      if (card && root.contains(card)) {
        this.selectConnection(card.dataset.connectionId);
      }
    });
  },

  
  
  applyActiveRing() {
    const cards = document.querySelectorAll("[data-card-connection]");
    cards.forEach((card) => {
      const active = card.dataset.connectionId === this.activeConnection;
      card.classList.toggle("border-accent", active);
      card.classList.toggle("ring-2", active);
      card.classList.toggle("ring-accent-dim", active);
      card.classList.toggle("border-line", !active);
      card.classList.toggle("hover:border-line-2", !active);
    });
  },

  
  
  
  get isRunning() {
    return this.runState === "queued" || this.runState === "running";
  },
  get runBtnClass() {
    return this.isRunning ? "cursor-not-allowed opacity-60" : "";
  },
  get runLabel() {
    if (this.runState === "queued") {
      return "Queued…";
    }
    if (this.runState === "running") {
      return "Running ~30s…";
    }
    if (this.runState === "done") {
      return "Done!";
    }
    return "Run test";
  },
  
  get isSpeed() {
    return this.metric === "speed";
  },
  
  
  
  get specificDisabled() {
    return this.runScope === "all";
  },

  
  
  setRange(value) {
    this.range = value;
    if (this.chart) {
      this.chart.setRange(value);
    }
    
    this.refreshTileSparks();
  },

  setMetric(value) {
    
    
    
    this.metric = value;
    if (this.chart) {
      this.chart.setMetric(value);
    }
  },

  
  
  
  
  openRunModal() {
    this.runScope = "connection";
    this.runServerMode = "auto";
    this.runServerId = "";
    this.runModalOpen = true;
    this.loadOoklaServers();
  },

  closeRunModal() {
    this.runModalOpen = false;
  },

  
  
  async loadOoklaServers() {
    if (this.ooklaServersLoaded) {
      return;
    }
    try {
      const response = await fetch("/dashboard/ookla-servers", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        return;
      }
      const data = await response.json();
      this.ooklaServers = (data.servers || []).map((s) => ({
        id: String(s.id),
        label:
          (s.name || "server") +
          (s.location ? " · " + s.location : "") +
          " (#" + s.id + ")",
      }));
      this.ooklaServersLoaded = true;
    } catch (e) {
      
    }
  },

  
  
  
  
  
  
  
  
  
  
  
  
  
  async submitRun() {
    if (this.runState === "queued") {
      return;
    }

    const isAll = this.runScope === "all";
    if (!isAll && !this.activeConnection) {
      
      return;
    }

    const token = this.runTestToken();
    if (!token) {
      console.error("netpulse: missing run-test CSRF token");
      return;
    }

    const connectionId = isAll ? null : this.activeConnection;
    const serverId =
      this.runServerMode === "specific" && !isAll
        ? this.runServerId.trim() || null
        : null;

    
    
    
    this.runState = "queued";
    this.runWaiting = false;
    this.runConnection = this.activeConnection;
    this.runBaselineCompletedAt =
      this.latestCompletedAt[this.activeConnection] ?? null;
    this.clearRunTimers();
    this.closeRunModal();

    try {
      const response = await fetch("/dashboard/run", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify({
          scope: this.runScope,
          connectionId,
          serverId,
        }),
      });

      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
    } catch (error) {
      
      console.error("netpulse: run request failed", error);
      this.resetRun();
      return;
    }

    
    
    
    
    this.startRunStatusPolling();
    this.runTimeoutTimer = setTimeout(() => {
      this.runTimeoutTimer = null;
      if (this.runState === "queued" || this.runState === "running") {
        this.runWaiting = true;
        this.resetRunState();
      }
    }, RUN_TEST_TIMEOUT_MS);
  },

  
  
  
  
  
  
  
  startRunStatusPolling() {
    this.stopRunStatusPolling();
    if (!this.runConnection) {
      return;
    }
    this.runStatusTimer = setInterval(() => {
      if (document.hidden) {
        return;
      }
      this.pollRunStatus();
    }, RUN_STATUS_INTERVAL_MS);
  },

  stopRunStatusPolling() {
    if (this.runStatusTimer !== null) {
      clearInterval(this.runStatusTimer);
      this.runStatusTimer = null;
    }
  },

  async pollRunStatus() {
    if (
      !this.runConnection ||
      (this.runState !== "queued" && this.runState !== "running")
    ) {
      this.stopRunStatusPolling();
      return;
    }

    try {
      const url =
        "/dashboard/run-status?connectionId=" +
        encodeURIComponent(this.runConnection);
      const response = await fetch(url, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      const state = payload.state;

      if (state === "done") {
        this.markRunDone();
      } else if (state === "running" || state === "queued") {
        
        this.runState = state;
      }
      
      
    } catch (error) {
      
      console.error("netpulse: run-status poll failed", error);
    }
  },

  
  
  detectRunCompletion(connection) {
    if (
      (this.runState !== "queued" && this.runState !== "running") ||
      !connection ||
      connection.connectionId !== this.runConnection
    ) {
      return;
    }

    const completedAt = connection.completedAtUnix;
    if (completedAt === null || completedAt === undefined) {
      return;
    }

    const isNewer =
      this.runBaselineCompletedAt === null ||
      completedAt > this.runBaselineCompletedAt;

    if (isNewer) {
      this.markRunDone();
    }
  },

  
  markRunDone() {
    this.clearRunTimers();
    this.stopRunStatusPolling();
    this.runState = "done";
    this.runWaiting = false;
    this.runDoneTimer = setTimeout(() => {
      this.runDoneTimer = null;
      this.resetRunState();
    }, RUN_TEST_DONE_MS);
  },

  
  
  resetRunState() {
    this.stopRunStatusPolling();
    this.runState = "idle";
    this.runConnection = null;
    this.runBaselineCompletedAt = null;
  },

  
  resetRun() {
    this.clearRunTimers();
    this.stopRunStatusPolling();
    this.runWaiting = false;
    this.resetRunState();
  },

  clearRunTimers() {
    if (this.runTimeoutTimer !== null) {
      clearTimeout(this.runTimeoutTimer);
      this.runTimeoutTimer = null;
    }
    if (this.runDoneTimer !== null) {
      clearTimeout(this.runDoneTimer);
      this.runDoneTimer = null;
    }
  },

  
  
  runTestToken() {
    const meta = document.querySelector('meta[name="csrf-run-test"]');
    return meta ? meta.getAttribute("content") : null;
  },

  
  
  
  
  
  
  
  
  
  

  initTileSparks(bootstrap) {
    const hosts = {
      dl: document.querySelector("[data-tile-spark='download']"),
      up: document.querySelector("[data-tile-spark='upload']"),
      ping: document.querySelector("[data-tile-spark='ping']"),
      loss: document.querySelector("[data-tile-spark='loss']"),
    };

    
    
    
    const seedSpeed =
      bootstrap &&
      bootstrap.metric === "speed" &&
      bootstrap.connectionId === this.activeConnection
        ? bootstrap
        : { buckets: [] };

    this.tileSparks = {
      dl: hosts.dl
        ? createChart(hosts.dl, {
            connection: this.activeConnection,
            range: this.range,
            metric: "speed",
            spark: true,
            only: "dl",
            data: seedSpeed,
          })
        : null,
      up: hosts.up
        ? createChart(hosts.up, {
            connection: this.activeConnection,
            range: this.range,
            metric: "speed",
            spark: true,
            only: "up",
            data: seedSpeed,
          })
        : null,
      ping: hosts.ping
        ? createChart(hosts.ping, {
            connection: this.activeConnection,
            range: this.range,
            metric: "ping",
            spark: true,
          })
        : null,
      loss: hosts.loss
        ? createChart(hosts.loss, {
            connection: this.activeConnection,
            range: this.range,
            metric: "loss",
            spark: true,
          })
        : null,
    };

    
    
    if (this.tileSparks.ping) {
      this.tileSparks.ping.refresh();
    }
    if (this.tileSparks.loss) {
      this.tileSparks.loss.refresh();
    }
    if (seedSpeed.buckets.length === 0) {
      this.fetchTileSpeed();
    }
  },

  
  
  
  refreshTileSparks() {
    if (!this.tileSparks) {
      return;
    }

    
    
    for (const key of ["ping", "loss"]) {
      const spark = this.tileSparks[key];
      if (spark) {
        spark.setConnection(this.activeConnection);
        spark.setRange(this.range);
      }
    }

    
    for (const key of ["dl", "up"]) {
      const spark = this.tileSparks[key];
      if (spark) {
        spark.configure({ connection: this.activeConnection, range: this.range });
      }
    }

    const reusable =
      this.chart &&
      this.chart.getMetric() === "speed" &&
      this.chart.getConnection() === this.activeConnection &&
      this.chart.getRange() === this.range;

    if (reusable) {
      const speed = this.chart.getData();
      if (this.tileSparks.dl) {
        this.tileSparks.dl.setData(speed);
      }
      if (this.tileSparks.up) {
        this.tileSparks.up.setData(speed);
      }
    } else {
      this.fetchTileSpeed();
    }
  },

  
  
  async fetchTileSpeed() {
    if (!this.activeConnection) {
      return;
    }
    try {
      const url =
        "/dashboard/series?connection=" +
        encodeURIComponent(this.activeConnection) +
        "&range=" +
        encodeURIComponent(this.range) +
        "&metric=speed";
      const response = await fetch(url, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      if (this.tileSparks?.dl) {
        this.tileSparks.dl.setData(payload);
      }
      if (this.tileSparks?.up) {
        this.tileSparks.up.setData(payload);
      }
    } catch (error) {
      console.error("netpulse: tile speed series fetch failed", error);
    }
  },

  destroyTileSparks() {
    if (!this.tileSparks) {
      return;
    }
    for (const spark of Object.values(this.tileSparks)) {
      if (spark) {
        spark.destroy();
      }
    }
    this.tileSparks = null;
  },

  

  startPolling() {
    this.stopPolling();
    this.pollTimer = setInterval(() => {
      
      if (document.hidden) {
        return;
      }
      this.pollSnapshot();
    }, POLL_INTERVAL_MS);
    
    
    this.tickTimer = setInterval(() => this.refreshUpdatedAgo(), TICK_INTERVAL_MS);
  },

  
  
  
  startCursorPolling() {
    this.cursorTimer = setInterval(() => {
      if (document.hidden) {
        return;
      }
      this.pollCursor();
    }, CURSOR_INTERVAL_MS);
  },

  stopPolling() {
    if (this.pollTimer !== null) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
    if (this.cursorTimer !== null) {
      clearInterval(this.cursorTimer);
      this.cursorTimer = null;
    }
    if (this.tickTimer !== null) {
      clearInterval(this.tickTimer);
      this.tickTimer = null;
    }
  },

  async pollSnapshot() {
    try {
      const response = await fetch("/dashboard/snapshot", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.applySnapshot(payload);
      
      this.lastUpdatedAtMs = Date.now();
      this.refreshUpdatedAgo();
    } catch (error) {
      
      console.error("netpulse: snapshot poll failed", error);
    }
  },

  
  
  
  
  
  async pollCursor() {
    try {
      const response = await fetch("/dashboard/cursor", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      const key =
        payload.latestCompletedAtUnix + ":" + payload.totalMeasurementCount;

      if (this.lastCursorKey !== null && key !== this.lastCursorKey) {
        await this.pollSnapshot();
      }
      this.lastCursorKey = key;
    } catch (error) {
      console.error("netpulse: cursor poll failed", error);
    }
  },

  
  
  refreshUpdatedAgo() {
    if (this.lastUpdatedAtMs === null) {
      this.updatedAgo = NULL_PLACEHOLDER;
      return;
    }
    const seconds = Math.max(0, Math.round((Date.now() - this.lastUpdatedAtMs) / 1000));
    if (seconds < 5) {
      this.updatedAgo = "just now";
    } else if (seconds < 60) {
      this.updatedAgo = seconds + "s ago";
    } else {
      this.updatedAgo = Math.floor(seconds / 60) + "m ago";
    }
  },

  
  
  
  applySnapshot(payload) {
    const connections = Array.isArray(payload?.connections)
      ? payload.connections
      : [];

    for (const connection of connections) {
      
      
      if (connection.connectionId) {
        this.latestCompletedAt[connection.connectionId] =
          connection.completedAtUnix ?? null;
      }
      this.detectRunCompletion(connection);

      this.updateCard(connection);
      if (connection.connectionId === this.activeConnection) {
        this.updateTiles(connection);
        this.updateHealth(connection);
      }
    }
  },

  
  updateCard(connection) {
    const card = document.querySelector(
      `[data-card-connection="${cssEscape(connection.connectionId)}"]`,
    );
    if (!card) {
      return;
    }

    const value = card.querySelector("[data-card-download]");
    if (value) {
      value.textContent = formatBits(connection.downloadBits);
    }

    const badge = card.querySelector("[data-card-status]");
    if (badge) {
      badge.textContent = statusLabel(connection.status);
    }
  },

  
  updateTiles(connection) {
    setText("[data-tile-download]", formatBits(connection.downloadBits));
    setText("[data-tile-upload]", formatBits(connection.uploadBits));
    setText("[data-tile-ping]", formatSeconds(connection.pingSeconds));
    setText("[data-tile-loss]", formatRatio(connection.packetLossRatio));
  },

  
  
  
  updateHealth(connection) {
    const uptime = connection.uptimePct;
    if (uptime === null || uptime === undefined) {
      return;
    }
    setText("[data-health-uptime]", formatUptime(uptime));
  },
});

function setText(selector, text) {
  const node = document.querySelector(selector);
  if (node) {
    node.textContent = text;
  }
}

function cssEscape(value) {
  if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
    return CSS.escape(value);
  }
  return String(value).replace(/["\\]/g, "\\$&");
}
