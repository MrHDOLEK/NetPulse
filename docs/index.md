---
layout: home
title: Self-hosted internet speed tracking
titleTemplate: NetPulse

hero:
  name: NetPulse
  text: Self-hosted internet speed tracking
  tagline: Metrics-first speed, ping and packet-loss monitoring for every connection — scheduled, adaptive, and Prometheus-ready.
  image:
    src: /logo.svg
    alt: NetPulse
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: How it works
      link: /guide/how-it-works
    - theme: alt
      text: GitHub
      link: https://github.com/MrHDOLEK/NetPulse

features:
  - icon: 📈
    title: Metrics-first
    details: Every measurement lands on a Prometheus /metrics endpoint and a bundled Grafana dashboard — own your data and graph it however you like.
  - icon: 🛰️
    title: Scheduled & adaptive
    details: Even or cron schedules per connection, round-robin server selection, and adaptive retesting that probes a degraded link sooner.
  - icon: 🔒
    title: Self-hosted & private
    details: Single-tenant, runs entirely on your Docker host. One admin, optional OIDC SSO and TOTP two-factor. No third-party telemetry.
  - icon: 📊
    title: Dashboard, history & heatmap
    details: Live speed / ping / loss charts over 24h–90d, a filterable measurement history with CSV export, and a weekday × hour heatmap.
  - icon: 🔔
    title: Alerts & digests
    details: Edge-debounced alert and recovery notifications over email, chat or webhook, plus a daily or weekly digest of every connection.
  - icon: 🧩
    title: Clean architecture
    details: PHP 8.4 / Symfony 7.3, Hexagonal + DDD enforced by deptrac, PHPStan level 10. A no-Node frontend via AssetMapper, Tailwind, Alpine and uPlot.
---

<div style="max-width: 960px; margin: 3rem auto 0; text-align: center;">

![NetPulse — self-hosted, metrics-first internet speed tracking](/banner.svg)

</div>
