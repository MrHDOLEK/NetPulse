function csrf(id) {
  const meta = document.querySelector('meta[name="csrf-' + id + '"]');
  return meta ? meta.getAttribute("content") : null;
}

export const settingsNotifications = () => ({
  form: {
    enabled: false,
    threshold: "3",
    emailEnabled: false,
    chatEnabled: false,
    webhookEnabled: false,
    emailTo: "",
    emailDsn: "",
    chatDsn: "",
    webhookUrl: "",
  },

  emailDsnSet: false,
  chatDsnSet: false,
  webhookUrlSet: false,
  canEncrypt: true,

  message: "",
  ok: false,
  loading: false,
  testing: false,

  init() {
    this.emailDsnSet = this.$root.dataset.emailDsnSet === "1";
    this.chatDsnSet = this.$root.dataset.chatDsnSet === "1";
    this.webhookUrlSet = this.$root.dataset.webhookUrlSet === "1";
    this.canEncrypt = this.$root.dataset.canEncrypt === "1";

    const raw = this.$root.dataset.settings;
    if (raw) {
      try {
        const data = JSON.parse(raw);
        this.form.enabled = data.enabled === true;
        this.form.threshold = String(data.threshold || "3");
        this.form.emailEnabled = data.emailEnabled === true;
        this.form.chatEnabled = data.chatEnabled === true;
        this.form.webhookEnabled = data.webhookEnabled === true;
        this.form.emailTo = data.emailTo || "";
      } catch (error) {
        console.error("netpulse: settings-notifications seed parse failed", error);
      }
    }
  },

  get saveLabel() {
    return this.loading ? "Saving…" : "Save changes";
  },

  get testLabel() {
    return this.testing ? "Sending…" : "Send test";
  },

  get emailDsnPlaceholder() {
    return this.emailDsnSet ? "•••••••• (unchanged)" : "smtp://user:pass@smtp.example:587";
  },

  get chatDsnPlaceholder() {
    return this.chatDsnSet ? "•••••••• (unchanged)" : "slack://TOKEN@default?channel=alerts";
  },

  get webhookUrlPlaceholder() {
    return this.webhookUrlSet ? "•••••••• (unchanged)" : "https://example.com/hooks/netpulse";
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
    const token = csrf("settings-notifications");
    if (!token) {
      this.ok = false;
      this.message = "Missing CSRF token — reload the page and try again.";
      return;
    }

    const body = {
      enabled: this.form.enabled === true,
      threshold: String(this.form.threshold).trim(),
      emailEnabled: this.form.emailEnabled === true,
      chatEnabled: this.form.chatEnabled === true,
      webhookEnabled: this.form.webhookEnabled === true,
      emailTo: this.form.emailTo.trim(),
    };
    
    this.addSecret(body, "emailDsn", this.form.emailDsn);
    this.addSecret(body, "chatDsn", this.form.chatDsn);
    this.addSecret(body, "webhookUrl", this.form.webhookUrl);

    this.loading = true;
    this.message = "";
    try {
      const payload = await this.post("/settings/notifications", body);
      this.ok = true;
      this.message = "Notification settings saved.";
      this.emailDsnSet = payload.emailDsnSet === true;
      this.chatDsnSet = payload.chatDsnSet === true;
      this.webhookUrlSet = payload.webhookUrlSet === true;
      this.form.emailDsn = "";
      this.form.chatDsn = "";
      this.form.webhookUrl = "";
    } catch (error) {
      this.ok = false;
      this.message = error.message || "Could not save notification settings.";
    } finally {
      this.loading = false;
    }
  },

  async test() {
    if (this.testing) {
      return;
    }
    const token = csrf("settings-notifications");
    if (!token) {
      this.ok = false;
      this.message = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.testing = true;
    this.message = "";
    try {
      const payload = await this.post("/settings/notifications/test", {});
      this.ok = payload.ok === true;
      const results = payload.results || {};
      const parts = Object.keys(results).map((channel) => channel + ": " + results[channel]);
      const detail = parts.length ? "  (" + parts.join(" · ") + ")" : "";
      this.message = (payload.message || "Test ran.") + detail;
    } catch (error) {
      this.ok = false;
      this.message = error.message || "Could not send the test notification.";
    } finally {
      this.testing = false;
    }
  },

  addSecret(body, key, value) {
    const trimmed = String(value).trim();
    if (trimmed !== "") {
      body[key] = trimmed;
    }
  },

  async post(url, body) {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-Token": csrf("settings-notifications"),
      },
      body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload.error || "HTTP " + response.status);
    }
    return payload;
  },
});
