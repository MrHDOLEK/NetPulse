import { withMermaid } from 'vitepress-plugin-mermaid'

const ogUrl = 'https://mrhdolek.github.io/NetPulse/'
const ogImage = `${ogUrl}banner.svg`
const description =
  'NetPulse is a self-hosted, metrics-first internet speed tracker built on Symfony. ' +
  'Schedule Ookla speed tests across multiple connections, track download, upload, ping ' +
  'and packet-loss trends, get alerts, and export to Prometheus and Grafana.'

// https://vitepress.dev/reference/site-config
export default withMermaid({
  title: 'NetPulse',
  description,
  lang: 'en-US',
  // Project page served from https://mrhdolek.github.io/NetPulse/
  base: '/NetPulse/',
  cleanUrls: true,
  lastUpdated: true,
  // localhost:8080 etc. are the user's own running instance, not pages on this site.
  ignoreDeadLinks: [/^https?:\/\/localhost/],
  // docs/superpowers holds internal planning docs, not part of the public site.
  srcExclude: ['superpowers/**', 'README.md'],
  sitemap: { hostname: ogUrl },
  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/NetPulse/favicon.svg' }],
    ['meta', { name: 'theme-color', content: '#46c8d6' }],
    [
      'meta',
      {
        name: 'keywords',
        content:
          'self-hosted internet speed test, speedtest tracker, Ookla speedtest, Prometheus, ' +
          'Grafana, Symfony, PHP, network monitoring, ISP monitoring, bandwidth monitor, homelab',
      },
    ],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'NetPulse — self-hosted internet speed tracking' }],
    ['meta', { property: 'og:description', content: description }],
    ['meta', { property: 'og:image', content: ogImage }],
    ['meta', { property: 'og:url', content: ogUrl }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:title', content: 'NetPulse' }],
    ['meta', { name: 'twitter:description', content: description }],
    ['meta', { name: 'twitter:image', content: ogImage }],
  ],
  themeConfig: {
    logo: '/logo.svg',
    nav: [
      { text: 'Guide', link: '/guide/introduction', activeMatch: '/guide/' },
      { text: 'Contributing', link: '/contributing' },
      {
        text: 'More',
        items: [
          { text: 'About the author', link: '/more/about' },
          { text: 'Blog ↗', link: 'https://aleksander-kowalski.pl/en/' },
        ],
      },
    ],
    sidebar: {
      '/': [
        {
          text: 'Guide',
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Getting started', link: '/guide/getting-started' },
            { text: 'How it works', link: '/guide/how-it-works' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Notifications', link: '/guide/notifications' },
            { text: 'Architecture', link: '/guide/architecture' },
          ],
        },
        {
          text: 'Project',
          items: [{ text: 'Contributing', link: '/contributing' }],
        },
      ],
    },
    socialLinks: [{ icon: 'github', link: 'https://github.com/MrHDOLEK/NetPulse' }],
    search: { provider: 'local' },
    editLink: {
      pattern: 'https://github.com/MrHDOLEK/NetPulse/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message:
        'NetPulse is built and maintained by <a href="https://aleksander-kowalski.pl/en/" target="_blank" rel="noopener">mrhdolek</a>. Released under the MIT License.',
      copyright: 'Copyright © 2026 NetPulse contributors',
    },
  },
  mermaid: {},
})
