const SORT_KEYS = {
  download: "download",
  upload: "upload",
  ping: "ping",
  loss: "loss",
  tests: "tests",
  health: "healthPct",
  lastSeen: "lastSeenUnix",
};

export const servers = () => ({
  
  window: "30d",

  
  rows: [],

  
  sortColumn: "download",
  sortDir: "desc",

  init() {
    const node = document.getElementById("servers-bootstrap");
    let bootstrap = {};
    if (node) {
      try {
        bootstrap = JSON.parse(node.textContent || "{}");
      } catch (error) {
        console.error("netpulse: bad servers bootstrap JSON", error);
      }
    }

    this.window = bootstrap.window || "30d";
    this.rows = Array.isArray(bootstrap.rows) ? bootstrap.rows : [];

    
    
    this.$watch("window", () => this.refetch());

    this.bindSort();
  },

  
  
  
  bindSort() {
    const root = this.$root || document;
    root.addEventListener("click", (event) => {
      const th = event.target.closest("[data-sort]");
      if (th && root.contains(th)) {
        this.setSort(th.dataset.sort);
      }
    });
  },

  
  
  setSort(column) {
    if (!SORT_KEYS[column]) {
      return;
    }
    if (this.sortColumn === column) {
      this.sortDir = this.sortDir === "desc" ? "asc" : "desc";
    } else {
      this.sortColumn = column;
      this.sortDir = "desc";
    }
  },

  
  
  async refetch() {
    try {
      const response = await fetch(
        "/dashboard/servers?window=" + encodeURIComponent(this.window),
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.rows = Array.isArray(payload.rows) ? payload.rows : [];
      try {
        window.history.replaceState(null, "", "/servers?window=" + encodeURIComponent(this.window));
      } catch (error) {
        console.error("netpulse: servers URL sync failed", error);
      }
    } catch (error) {
      console.error("netpulse: servers fetch failed", error);
    }
  },

  
  
  
  get sortedRows() {
    const key = SORT_KEYS[this.sortColumn];
    const dir = this.sortDir === "asc" ? 1 : -1;
    return [...this.rows].sort((a, b) => {
      const av = a[key];
      const bv = b[key];
      if (av === null || av === undefined) {
        return 1;
      }
      if (bv === null || bv === undefined) {
        return -1;
      }
      return av < bv ? -dir : av > bv ? dir : 0;
    });
  },

  
  get hasRows() {
    return this.rows.length > 0;
  },
});
