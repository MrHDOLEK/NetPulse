function csrf(id) {
  const meta = document.querySelector('meta[name="csrf-' + id + '"]');
  return meta ? meta.getAttribute("content") : null;
}

export const settingsSso = () => ({
  form: {
    enabled: false,
    name: "",
    clientId: "",
    clientSecret: "",
    authorizationUrl: "",
    tokenUrl: "",
    userInfoUrl: "",
    redirectUrl: "",
    scopes: "",
    discoveryUrl: "",
  },

  
  
  secretIsSet: false,
  canEncrypt: true,

  
  message: "",
  ok: false,
  loading: false,
  testing: false,

  init() {
    this.secretIsSet = this.$root.dataset.secretSet === "1";
    this.canEncrypt = this.$root.dataset.canEncrypt === "1";

    const raw = this.$root.dataset.settings;
    if (raw) {
      try {
        const data = JSON.parse(raw);
        this.form.enabled = data.enabled === true;
        this.form.name = data.name || "";
        this.form.clientId = data.clientId || "";
        this.form.authorizationUrl = data.authorizationUrl || "";
        this.form.tokenUrl = data.tokenUrl || "";
        this.form.userInfoUrl = data.userInfoUrl || "";
        this.form.redirectUrl = data.redirectUrl || "";
        this.form.scopes = data.scopes || "";
      } catch (error) {
        console.error("netpulse: settings-sso seed parse failed", error);
      }
    }
  },

  get saveLabel() {
    return this.loading ? "Saving…" : "Save changes";
  },

  get testLabel() {
    return this.testing ? "Testing…" : "Test connection";
  },

  
  get secretPlaceholder() {
    return this.secretIsSet ? "•••••••• (unchanged)" : "Paste the client secret";
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
    const token = csrf("settings-sso");
    if (!token) {
      this.ok = false;
      this.message = "Missing CSRF token — reload the page and try again.";
      return;
    }

    const body = {
      enabled: this.form.enabled === true,
      name: this.form.name.trim(),
      clientId: this.form.clientId.trim(),
      authorizationUrl: this.form.authorizationUrl.trim(),
      tokenUrl: this.form.tokenUrl.trim(),
      userInfoUrl: this.form.userInfoUrl.trim(),
      redirectUrl: this.form.redirectUrl.trim(),
      scopes: this.form.scopes.trim(),
    };
    
    
    const secret = this.form.clientSecret.trim();
    if (secret !== "") {
      body.clientSecret = secret;
    }

    this.loading = true;
    this.message = "";
    try {
      const response = await fetch("/settings/sso", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify(body),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      this.ok = true;
      this.message = "SSO settings saved.";
      this.secretIsSet = payload.secretIsSet === true;
      
      this.form.clientSecret = "";
    } catch (error) {
      this.ok = false;
      this.message = error.message || "Could not save SSO settings.";
    } finally {
      this.loading = false;
    }
  },

  async test() {
    if (this.testing) {
      return;
    }
    const token = csrf("settings-sso");
    if (!token) {
      this.ok = false;
      this.message = "Missing CSRF token — reload the page and try again.";
      return;
    }

    this.testing = true;
    this.message = "";
    try {
      const response = await fetch("/settings/sso/test", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": token,
        },
        body: JSON.stringify({ discoveryUrl: this.form.discoveryUrl.trim() }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || "HTTP " + response.status);
      }
      this.ok = payload.ok === true;
      this.message = payload.message || (this.ok ? "Discovery succeeded." : "Discovery failed.");
    } catch (error) {
      this.ok = false;
      this.message = error.message || "Could not run the discovery probe.";
    } finally {
      this.testing = false;
    }
  },
});
