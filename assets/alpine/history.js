const SORT_COLUMNS = {
  completed_at: ["completed_at_desc", "completed_at_asc"],
  download: ["download_desc", "download_asc"],
  upload: ["upload_desc", "upload_asc"],
  ping: ["ping_desc", "ping_asc"],
  jitter: ["jitter_desc", "jitter_asc"],
  loss: ["loss_desc", "loss_asc"],
};

const DEFAULT_SORT = "completed_at_desc";
const DEFAULT_LIMIT = 25;

function readBootstrap() {
  const node = document.getElementById("history-bootstrap");
  if (!node) {
    return null;
  }
  try {
    return JSON.parse(node.textContent || "{}");
  } catch (error) {
    console.error("netpulse: bad history bootstrap JSON", error);
    return null;
  }
}

export const history = () => ({
  
  
  
  
  filters: {
    since: "",
    until: "",
    connection: "",
    server: "",
    status: "",
    healthy: "",
    scheduled: "",
  },

  
  
  
  defaultSince: "",
  defaultUntil: "",

  limit: DEFAULT_LIMIT,
  offset: 0,
  sort: DEFAULT_SORT,

  items: [],
  total: 0,
  loading: false,

  
  
  
  detail: {},
  detailOpen: false,

  
  
  
  
  shareUrl: "",

  init() {
    const bootstrap = readBootstrap();

    if (bootstrap) {
      const filters = bootstrap.filters || {};
      this.filters.since = stringOr(filters.since, "");
      this.filters.until = stringOr(filters.until, "");
      this.filters.connection = stringOr(filters.connection, "");
      this.filters.server = stringOr(filters.server, "");
      this.filters.status = stringOr(filters.status, "");
      this.filters.healthy = stringOr(filters.healthy, "");
      this.filters.scheduled = stringOr(filters.scheduled, "");

      this.defaultSince = this.filters.since;
      this.defaultUntil = this.filters.until;

      if (bootstrap.limit !== undefined && bootstrap.limit !== null) {
        this.limit = Number(bootstrap.limit) || DEFAULT_LIMIT;
      }
      if (bootstrap.offset !== undefined && bootstrap.offset !== null) {
        this.offset = Number(bootstrap.offset) || 0;
      }
      this.sort = stringOr(bootstrap.sort, DEFAULT_SORT);
      this.total = Number(bootstrap.total) || 0;
    }

    
    
    if (bootstrap && Array.isArray(bootstrap.items)) {
      this.items = bootstrap.items;
    } else {
      this.refetch();
    }

    
    
    
    this.$watch("filters.since", () => this.onFilterChange());
    this.$watch("filters.until", () => this.onFilterChange());
    this.$watch("filters.connection", () => this.onFilterChange());
    this.$watch("filters.server", () => this.onFilterChange());
    this.$watch("filters.status", () => this.onFilterChange());
    this.$watch("filters.healthy", () => this.onFilterChange());
    this.$watch("filters.scheduled", () => this.onFilterChange());
    this.$watch("limit", () => this.onFilterChange());
    this.$watch("sort", () => this.onFilterChange());
    
    this.$watch("offset", () => this.refetch());

    this.bindDelegatedClicks();
  },

  
  
  
  onFilterChange() {
    if (this.offset === 0) {
      this.refetch();
    } else {
      this.offset = 0;
    }
  },

  
  
  
  
  bindDelegatedClicks() {
    const root = this.$root || document;
    root.addEventListener("click", (event) => {
      const sortable = event.target.closest("[data-sort]");
      if (sortable && root.contains(sortable)) {
        this.setSort(sortable.dataset.sort);
        return;
      }
      const row = event.target.closest("[data-measurement-id]");
      if (row && root.contains(row)) {
        this.openDetail(row.dataset.measurementId);
      }
    });
  },

  
  
  
  
  
  buildQuery(paging) {
    const params = new URLSearchParams();
    const filters = this.filters;
    if (filters.since) {
      params.set("since", filters.since);
    }
    if (filters.until) {
      params.set("until", filters.until);
    }
    if (filters.connection) {
      params.set("connection", filters.connection);
    }
    if (filters.server) {
      params.set("server", filters.server);
    }
    if (filters.status) {
      params.set("status", filters.status);
    }
    if (filters.healthy !== "") {
      params.set("healthy", filters.healthy);
    }
    if (filters.scheduled !== "") {
      params.set("scheduled", filters.scheduled);
    }
    if (paging) {
      params.set("limit", String(this.limit));
      params.set("offset", String(this.offset));
      params.set("sort", this.sort);
    }
    return params.toString();
  },

  
  
  
  async refetch() {
    this.loading = true;
    const query = this.buildQuery(true);
    try {
      const response = await fetch("/dashboard/history?" + query, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.items = Array.isArray(payload.items) ? payload.items : [];
      this.total = Number(payload.total) || 0;
      this.syncUrl(query);
    } catch (error) {
      console.error("netpulse: history list fetch failed", error);
    } finally {
      this.loading = false;
    }
  },

  
  
  syncUrl(query) {
    try {
      const url = query ? "/history?" + query : "/history";
      window.history.replaceState(null, "", url);
    } catch (error) {
      console.error("netpulse: history URL sync failed", error);
    }
  },

  
  
  
  async openDetail(id) {
    if (!id) {
      return;
    }
    try {
      const response = await fetch(
        "/dashboard/history/" + encodeURIComponent(id),
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const detail = await response.json();
      detail.rawJson = JSON.stringify(detail.rawPayload, null, 2);
      this.detail = detail;
      
      this.shareUrl = "";
      this.detailOpen = true;
    } catch (error) {
      console.error("netpulse: history detail fetch failed", error);
    }
  },

  
  
  
  
  
  
  async shareCurrent() {
    const id = this.detail.id;
    if (!id) {
      return;
    }
    const token = this.shareToken();
    if (!token) {
      console.error("netpulse: missing run-test CSRF token");
      return;
    }
    try {
      const response = await fetch(
        "/dashboard/history/" + encodeURIComponent(id) + "/share",
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-CSRF-Token": token,
          },
        },
      );
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      const payload = await response.json();
      this.shareUrl = location.origin + payload.shareUrl;
    } catch (error) {
      console.error("netpulse: history share request failed", error);
    }
  },

  
  
  
  copyShare() {
    if (navigator.clipboard && this.shareUrl) {
      navigator.clipboard.writeText(this.shareUrl);
    }
  },

  
  
  
  shareToken() {
    const meta = document.querySelector('meta[name="csrf-run-test"]');
    return meta ? meta.getAttribute("content") : null;
  },

  
  
  closeDetail() {
    this.detailOpen = false;
    
    
    this.shareUrl = "";
    setTimeout(() => {
      if (!this.detailOpen) {
        this.detail = {};
      }
    }, 200);
  },

  
  
  clearFilters() {
    this.filters.since = this.defaultSince;
    this.filters.until = this.defaultUntil;
    this.filters.connection = "";
    this.filters.server = "";
    this.filters.status = "";
    this.filters.healthy = "";
    this.filters.scheduled = "";
  },

  
  prevPage() {
    if (!this.onFirstPage) {
      this.offset = Math.max(0, this.offset - this.pageSize);
    }
  },

  nextPage() {
    if (!this.onLastPage) {
      this.offset = this.offset + this.pageSize;
    }
  },

  
  
  
  setSort(column) {
    const directions = SORT_COLUMNS[column];
    if (!directions) {
      return;
    }
    const [desc, asc] = directions;
    this.sort = this.sort === desc ? asc : desc;
  },

  
  
  get pageSize() {
    return Number(this.limit) || DEFAULT_LIMIT;
  },

  get onFirstPage() {
    return this.offset === 0;
  },

  get onLastPage() {
    return this.offset + this.pageSize >= this.total;
  },

  get hasResults() {
    return this.items.length > 0;
  },

  
  get rangeLabel() {
    if (this.total === 0) {
      return "No results";
    }
    const first = this.offset + 1;
    const last = Math.min(this.offset + this.pageSize, this.total);
    return first + "–" + last + " of " + this.total;
  },

  
  
  get csvHref() {
    const query = this.buildQuery(false);
    return query
      ? "/dashboard/history/export.csv?" + query
      : "/dashboard/history/export.csv";
  },
});

function stringOr(value, fallback) {
  return value === null || value === undefined ? fallback : String(value);
}
