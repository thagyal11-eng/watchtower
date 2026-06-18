import { ref } from 'vue';

// Theme: 'system' | 'light' | 'dark'. Persisted in localStorage. Applies the
// `dark` class on the #watchtower-app root (Tailwind darkMode: 'class').

const KEY = 'watchtower.theme';
export const theme = ref(load());

function load() {
  try {
    return localStorage.getItem(KEY) || 'system';
  } catch {
    return 'system';
  }
}

const media = window.matchMedia('(prefers-color-scheme: dark)');

function resolveDark() {
  if (theme.value === 'dark') return true;
  if (theme.value === 'light') return false;
  return media.matches;
}

export function applyTheme() {
  const root = document.getElementById('watchtower-app');
  if (!root) return;
  root.classList.toggle('dark', resolveDark());
}

media.addEventListener('change', () => {
  if (theme.value === 'system') applyTheme();
});

export function cycleTheme() {
  const order = ['system', 'light', 'dark'];
  const next = order[(order.indexOf(theme.value) + 1) % order.length];
  theme.value = next;
  try {
    localStorage.setItem(KEY, next);
  } catch {
    /* ignore */
  }
  applyTheme();
}

export function isDark() {
  return resolveDark();
}
