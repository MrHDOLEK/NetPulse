const BITS_PER_MBIT = 1_000_000;

function csrf(id) {
  const meta = document.querySelector('meta[name="csrf-' + id + '"]');
  return meta ? meta.getAttribute("content") : null;
}

function bitsToMbps(bits) {
  if (bits === null || bits === undefined) {
    return 0;
  }
  return Math.round(bits / BITS_PER_MBIT);
}

function labelsToCsv(labels) {
  if (!labels || typeof labels !== "object") {
    return "";
  }
  return Object.keys(labels)
    .map((key) => key + "=" + labels[key])
    .join(",");
}

function blankForm(defaults) {
  const thresholds = (defaults && defaults.thresholds) || {};
  const adaptive = (defaults && defaults.adaptivePolicy) || {};
  return {
    id: null,
    probeId: "",
    name: "",
    isp: "",
    color: (defaults && defaults.color) || "primary",
    downloadMbps: 0,
    uploadMbps: 0,
    scheduleMode: (defaults && defaults.scheduleMode) || "even",
    testsPerDay: defaults && defaults.testsPerDay != null ? defaults.testsPerDay : 24,
    jitter: defaults && defaults.jitterSeconds != null ? defaults.jitterSeconds : 120,
    cron: "",
    serverPool: "",
    labels: "",
    minDownloadRatio: nullToEmpty(thresholds.minDownloadRatio),
    minUploadRatio: nullToEmpty(thresholds.minUploadRatio),
    maxPingMs: nullToEmpty(thresholds.maxPingMs),
    maxJitterMs: nullToEmpty(thresholds.maxJitterMs),
    maxPacketLossRatio: nullToEmpty(thresholds.maxPacketLossRatio),
    adaptiveIntervalSeconds: nullToEmpty(adaptive.adaptiveIntervalSeconds),
    recoveryHealthyCount: nullToEmpty(adaptive.recoveryHealthyCount),
    maxConsecutiveFailures: nullToEmpty(adaptive.maxConsecutiveFailures),
  };
}

function nullToEmpty(value) {
  return value === null || value === undefined ? "" : value;
}

export const connections = () => ({
  
  
  
  
  meta: {
    probes: [],
    colors: ["primary", "violet", "amber"],
    scheduleModes: ["even", "cron"],
    defaults: null,
  },

  
  modalOpen: false,
  
  mode: "create",
  form: blankForm(null),

  
  error: "",
  loading: false,

  init() {
    this.bindActions();
    this.loadMeta();
  },

  
  
  
  async loadMeta() {
    try {
      const response = await fetch("/settings/connections/meta", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.meta = {
        probes: Array.isArray(payload.probes) ? payload.probes : [],
        colors: Array.isArray(payload.colors) ? payload.colors : this.meta.colors,
        scheduleModes: Array.isArray(payload.scheduleModes)
          ? payload.scheduleModes
          : this.meta.scheduleModes,
        defaults: payload.defaults || null,
      };
    } catch (error) {
      console.error("netpulse: connections meta fetch failed", error);
    }
  },

  

  get isEven() {
    return this.form.scheduleMode === "even";
  },
  get isCron() {
    return this.form.scheduleMode === "cron";
  },
  get isEdit() {
    return this.mode === "edit";
  },
  get modalTitle() {
    return this.mode === "edit" ? "Edit connection" : "New connection";
  },
  get submitLabel() {
    return this.mode === "edit" ? "Save changes" : "Create connection";
  },

  

  
  openCreate() {
    this.mode = "create";
    this.form = blankForm(this.meta.defaults);
    
    if (this.meta.probes.length > 0) {
      this.form.probeId = this.meta.probes[0].id;
    }
    this.error = "";
    this.modalOpen = true;
  },

  
  
  
  openEdit(row) {
    if (!row) {
      return;
    }
    const thresholds = row.thresholds || {};
    const adaptive = row.adaptivePolicy || {};
    this.mode = "edit";
    this.form = {
      id: row.id,
      probeId: row.probeId,
      name: row.name || "",
      isp: row.isp || "",
      color: row.color || "primary",
      downloadMbps: bitsToMbps(row.expectedDownloadBits),
      uploadMbps: bitsToMbps(row.expectedUploadBits),
      scheduleMode: row.scheduleMode || "even",
      testsPerDay: row.testsPerDay != null ? row.testsPerDay : 24,
      jitter: row.jitterSeconds != null ? row.jitterSeconds : 120,
      cron: Array.isArray(row.cronExpressions) ? row.cronExpressions.join(",") : "",
      serverPool: Array.isArray(row.serverPool) ? row.serverPool.join(",") : "",
      labels: labelsToCsv(row.labels),
      minDownloadRatio: nullToEmpty(thresholds.minDownloadRatio),
      minUploadRatio: nullToEmpty(thresholds.minUploadRatio),
      maxPingMs: nullToEmpty(thresholds.maxPingMs),
      maxJitterMs: nullToEmpty(thresholds.maxJitterMs),
      maxPacketLossRatio: nullToEmpty(thresholds.maxPacketLossRatio),
      adaptiveIntervalSeconds: nullToEmpty(adaptive.adaptiveIntervalSeconds),
      recoveryHealthyCount: nullToEmpty(adaptive.recoveryHealthyCount),
      maxConsecutiveFailures: nullToEmpty(adaptive.maxConsecutiveFailures),
    };
    this.error = "";
    this.modalOpen = true;
  },

  closeModal() {
    this.modalOpen = false;
  },

  
  
  
  
  
  buildBody() {
    const body = {
      probeId: this.form.probeId,
      name: this.form.name.trim(),
      isp: this.form.isp.trim(),
      color: this.form.color,
      downloadMbps: toInt(this.form.downloadMbps),
      uploadMbps: toInt(this.form.uploadMbps),
      scheduleMode: this.form.scheduleMode,
      cron: this.form.cron.trim(),
      testsPerDay: toInt(this.form.testsPerDay),
      jitter: toInt(this.form.jitter),
      serverPool: this.form.serverPool.trim(),
      labels: this.form.labels.trim(),
    };

    
    assignNumeric(body, "minDownloadRatio", this.form.minDownloadRatio);
    assignNumeric(body, "minUploadRatio", this.form.minUploadRatio);
    assignNumeric(body, "adaptiveIntervalSeconds", this.form.adaptiveIntervalSeconds);
    assignNumeric(body, "recoveryHealthyCount", this.form.recoveryHealthyCount);
    assignNumeric(body, "maxConsecutiveFailures", this.form.maxConsecutiveFailures);

    
    body.maxPingMs = capValue(this.form.maxPingMs);
    body.maxJitterMs = capValue(this.form.maxJitterMs);
    body.maxPacketLossRatio = capValue(this.form.maxPacketLossRatio);

    return body;
  },

  
  
  
  async submit() {
    if (this.loading) {
      return;
    }
    if (!this.form.probeId) {
      this.error = "Select a probe for this connection.";
      return;
    }
    if (this.form.name.trim() === "") {
      this.error = "A connection name is required.";
      return;
    }

    const isEdit = this.mode === "edit";
    const tokenId = isEdit ? "connection-edit" : "connection-create";
    const token = csrf(tokenId);
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    const url = isEdit
      ? "/settings/connections/" + encodeURIComponent(this.form.id)
      : "/settings/connections";

    this.loading = true;
    this.error = "";
    try {
      const response = await fetch(url, {
        method: isEdit ? "PUT" : "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify(this.buildBody()),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      window.location.reload();
    } catch (error) {
      this.error = error.message || "Could not save the connection.";
      this.loading = false;
    }
  },

  
  
  async remove(id, probeId) {
    if (this.loading || !id) {
      return;
    }
    const token = csrf("connection-delete");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.loading = true;
    this.error = "";
    try {
      const response = await fetch(
        "/settings/connections/" + encodeURIComponent(id),
        {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": token,
          },
          body: JSON.stringify({ probeId }),
        },
      );
      if (response.status === 204) {
        window.location.reload();
        return;
      }
      const payload = await response.json().catch(() => ({}));
      throw new Error(payload.error || "HTTP " + response.status);
    } catch (error) {
      this.error = error.message || "Could not delete the connection.";
    } finally {
      this.loading = false;
    }
  },

  
  
  async setEnabled(id, probeId, enabled, el) {
    if (!id) {
      return;
    }
    const token = csrf("connection-enabled");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.error = "";
    try {
      const response = await fetch(
        "/settings/connections/" + encodeURIComponent(id) + "/enabled",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": token,
          },
          body: JSON.stringify({ enabled, probeId }),
        },
      );
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      paintToggle(el, enabled);
    } catch (error) {
      this.error = "Could not update the connection.";
      window.location.reload();
    }
  },

  
  
  
  bindActions() {
    const root = this.$root || document;
    root.addEventListener("click", (event) => {
      const el = event.target.closest("[data-conn-action]");
      if (!el || !root.contains(el)) {
        return;
      }
      const action = el.dataset.connAction;
      const row = this.rowFor(el);
      if (action === "edit") {
        this.openEdit(row);
      } else if (action === "delete") {
        this.remove(row && row.id, row && row.probeId);
      } else if (action === "toggle") {
        const next = el.getAttribute("aria-checked") !== "true";
        this.setEnabled(row && row.id, row && row.probeId, next, el);
      } else if (action === "create") {
        this.openCreate();
      }
    });
  },

  
  
  
  rowFor(el) {
    const host = el.closest("[data-connection]");
    if (!host) {
      return null;
    }
    try {
      return JSON.parse(host.dataset.connection || "null");
    } catch (error) {
      console.error("netpulse: bad connection row JSON", error);
      return null;
    }
  },
});

function assignNumeric(body, key, value) {
  if (value === "" || value === null || value === undefined) {
    return;
  }
  const num = Number(value);
  if (!Number.isNaN(num)) {
    body[key] = num;
  }
}

function capValue(value) {
  if (value === "" || value === null || value === undefined) {
    return null;
  }
  const num = Number(value);
  return Number.isNaN(num) ? null : num;
}

function toInt(value) {
  const num = parseInt(value, 10);
  return Number.isNaN(num) ? 0 : num;
}

function paintToggle(el, on) {
  if (!el) {
    return;
  }
  el.setAttribute("aria-checked", on ? "true" : "false");
  el.classList.toggle("bg-accent", on);
  el.classList.toggle("bg-surface-2", !on);
  const knob = el.querySelector(".toggle-knob");
  if (knob) {
    knob.classList.toggle("translate-x-4", on);
    knob.classList.toggle("translate-x-0", !on);
  }
}
