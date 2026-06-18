import { createApp } from 'vue';
import App from './App.vue';
import '../css/app.css';

const mount = document.getElementById('watchtower-app');

if (mount) {
  // Defensive defaults so the SPA never crashes if config is partially absent.
  window.Watchtower = Object.assign(
    {
      path: 'watchtower',
      version: 'dev',
      pollingInterval: 5000,
      perPage: 25,
      recording: { schedule: true, queue: true, exceptions: true },
      csrfToken: '',
      basePath: '/watchtower',
      apiBase: '/watchtower/api',
    },
    window.Watchtower || {}
  );

  createApp(App).mount(mount);
}
