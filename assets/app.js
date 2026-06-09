import Alpine from "@alpinejs/csp";
import { theme } from "./alpine/theme.js";
import { sidebar } from "./alpine/sidebar.js";
import { chart } from "./alpine/chart.js";
import { dashboard } from "./alpine/dashboard.js";
import { history } from "./alpine/history.js";
import { heatmap } from "./alpine/heatmap.js";
import { servers } from "./alpine/servers.js";
import { connections } from "./alpine/connections.js";
import { probes } from "./alpine/probes.js";
import { settingsGeneral } from "./alpine/settings-general.js";
import { settingsSso } from "./alpine/settings-sso.js";
import { settingsNotifications } from "./alpine/settings-notifications.js";

window.Alpine = Alpine;

Alpine.data("theme", theme);
Alpine.data("sidebar", sidebar);
Alpine.data("chart", chart);
Alpine.data("dashboard", dashboard);
Alpine.data("history", history);
Alpine.data("heatmap", heatmap);
Alpine.data("servers", servers);
Alpine.data("connections", connections);
Alpine.data("probes", probes);
Alpine.data("settingsGeneral", settingsGeneral);
Alpine.data("settingsSso", settingsSso);
Alpine.data("settingsNotifications", settingsNotifications);

Alpine.start();
