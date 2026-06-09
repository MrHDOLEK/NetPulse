function csrf(id) {
  const meta = document.querySelector('meta[name="csrf-' + id + '"]');
  return meta ? meta.getAttribute("content") : null;
}

export const settingsGeneral = () => ({
  form: { siteName: "", timezone: "" },

  
  message: "",
  ok: false,
  loading: false,

  init() {
    const raw = this.$root.dataset.settings;
    if (raw) {
      try {
        const data = JSON.parse(raw);
        this.form.siteName = data.siteName || "";
        this.form.timezone = data.timezone || "";
      } catch (error) {
        console.error("netpulse: settings-general seed parse failed", error);
      }
    }
  },

  get saveLabel() {
    return this.loading ? "Saving…" : "Save changes";
  },

  
  get alertClass() {
    return this.ok
      ? "border-[color-mix(in_oklab,var(--good)_45%,transparent)] bg-[color-mix(in_oklab,var(--good)_12%,transparent)] text-good"
      : "border-[color-mix(in_oklab,var(--bad)_45%,transparent)] bg-[color-mix(in_oklab,var(--bad)_12%,transparent)] text-bad";
  },

  async save() {
    if (this.loading) {
      return;
    }
    const token = csrf("settings-general");
    if (!token) {
      this.ok = false;
      this.message = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.loading = true;
    this.message = "";
    try {
      const response = await fetch("/settings/general", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify({
          siteName: this.form.siteName.trim(),
          timezone: this.form.timezone.trim(),
        }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      this.ok = true;
      this.message = "Settings saved.";
    } catch (error) {
      this.ok = false;
      this.message = error.message || "Could not save settings.";
    } finally {
      this.loading = false;
    }
  },
});
