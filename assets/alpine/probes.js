function csrf(id) {
  const meta = document.querySelector('meta[name="csrf-' + id + '"]');
  return meta ? meta.getAttribute("content") : null;
}

const LIVENESS_INTERVAL_MS = 15_000;

export const probes = () => ({
  
  modalOpen: false,
  
  form: { name: "", labels: "" },

  
  
  
  revealedToken: "",
  revealedName: "",
  copied: false,

  
  
  
  
  error: "",
  loading: false,

  
  
  
  livenessTimer: null,

  
  get hasToken() {
    return this.revealedToken !== "";
  },

  

  openCreate() {
    this.form = { name: "", labels: "" };
    this.error = "";
    this.modalOpen = true;
  },

  closeCreate() {
    this.modalOpen = false;
  },

  
  
  
  
  
  async createProbe() {
    if (this.loading) {
      return;
    }
    const name = this.form.name.trim();
    if (name === "") {
      this.error = "A probe name is required.";
      return;
    }
    const token = csrf("probe-create");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.loading = true;
    this.error = "";
    try {
      const response = await fetch("/settings/probes", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify({ name, labels: this.form.labels.trim() }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      this.closeCreate();
      this.reveal(name, payload.token);
    } catch (error) {
      this.error = error.message || "Could not create the probe.";
    } finally {
      this.loading = false;
    }
  },

  
  
  async rotate(id, name) {
    if (this.loading || !id) {
      return;
    }
    const token = csrf("probe-rotate");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.loading = true;
    this.error = "";
    try {
      const response = await fetch(
        "/settings/probes/" + encodeURIComponent(id) + "/rotate-token",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": token,
          },
        },
      );
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      this.reveal(name, payload.token);
    } catch (error) {
      this.error = error.message || "Could not rotate the token.";
    } finally {
      this.loading = false;
    }
  },

  
  
  
  async remove(id) {
    if (this.loading || !id) {
      return;
    }
    const token = csrf("probe-delete");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.loading = true;
    this.error = "";
    try {
      const response = await fetch("/settings/probes/" + encodeURIComponent(id), {
        method: "DELETE",
        headers: {
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
      });
      if (response.status === 204) {
        window.location.reload();
        return;
      }
      const payload = await response.json().catch(() => ({}));
      
      throw new Error(payload.error || "HTTP " + response.status);
    } catch (error) {
      this.error = error.message || "Could not delete the probe.";
    } finally {
      this.loading = false;
    }
  },

  
  
  
  
  async setEnabled(id, enabled, el) {
    if (!id) {
      return;
    }
    const token = csrf("probe-enabled");
    if (!token) {
      this.error = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.error = "";
    try {
      const response = await fetch(
        "/settings/probes/" + encodeURIComponent(id) + "/enabled",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": token,
          },
          body: JSON.stringify({ enabled }),
        },
      );
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      paintToggle(el, enabled);
    } catch (error) {
      this.error = "Could not update the probe.";
      
      window.location.reload();
    }
  },

  
  
  
  
  bindActions() {
    const root = this.$root || document;
    root.addEventListener("click", (event) => {
      const el = event.target.closest("[data-probe-action]");
      if (!el || !root.contains(el)) {
        return;
      }
      const action = el.dataset.probeAction;
      const id = el.dataset.probeId;
      const name = el.dataset.probeName || "";
      if (action === "toggle") {
        const next = el.getAttribute("aria-checked") !== "true";
        this.setEnabled(id, next, el);
      } else if (action === "rotate") {
        this.rotate(id, name);
      } else if (action === "delete") {
        this.remove(id);
      }
    });
  },

  init() {
    this.bindActions();
    
    this.refreshLiveness();
    this.livenessTimer = setInterval(() => {
      if (document.hidden) {
        return;
      }
      this.refreshLiveness();
    }, LIVENESS_INTERVAL_MS);
  },

  destroy() {
    if (this.livenessTimer !== null) {
      clearInterval(this.livenessTimer);
      this.livenessTimer = null;
    }
  },

  
  
  async refreshLiveness() {
    try {
      const response = await fetch("/dashboard/probes-liveness", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      const probes = Array.isArray(payload.probes) ? payload.probes : [];
      for (const probe of probes) {
        paintLivenessDot(probe);
      }
    } catch (error) {
      console.error("netpulse: probes-liveness poll failed", error);
    }
  },

  
  reveal(name, token) {
    this.revealedName = name;
    this.revealedToken = token || "";
    this.copied = false;
  },

  
  
  dismissToken() {
    this.revealedToken = "";
    this.revealedName = "";
    window.location.reload();
  },

  
  
  copyToken() {
    if (navigator.clipboard && this.revealedToken) {
      navigator.clipboard.writeText(this.revealedToken);
      this.copied = true;
      setTimeout(() => {
        this.copied = false;
      }, 1500);
    }
  },

  
  get copyLabel() {
    return this.copied ? "Copied" : "Copy";
  },
});

function paintLivenessDot(probe) {
  if (!probe || !probe.probeId) {
    return;
  }
  const dot = document.querySelector(
    '[data-probe-liveness-dot][data-probe-id="' + cssEscapeAttr(probe.probeId) + '"]',
  );
  if (!dot) {
    return;
  }
  const online = probe.isOnline === true;
  dot.classList.toggle("bg-good", online);
  dot.classList.toggle("bg-bad", !online);
  dot.classList.remove("bg-faint");

  let title;
  if (probe.lastPollAtUnix === null || probe.lastPollAtUnix === undefined) {
    title = "Never polled";
  } else if (online) {
    title = "LIVE — polled recently";
  } else {
    const mins = probe.minutesSincePoll;
    title =
      "Offline — last seen " +
      (mins === null || mins === undefined ? "a while" : mins + " min") +
      " ago";
  }
  dot.setAttribute("title", title);
}

function cssEscapeAttr(value) {
  if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
    return CSS.escape(value);
  }
  return String(value).replace(/["\\]/g, "\\$&");
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
