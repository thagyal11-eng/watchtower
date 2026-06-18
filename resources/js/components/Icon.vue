<script setup>
// Inline stroke icons (no icon-font dependency). 24x24 viewBox.
const PATHS = {
  schedule: 'M12 8v4l3 2M12 21a9 9 0 100-18 9 9 0 000 18z',
  queue: 'M4 6h16M4 12h16M4 18h10',
  error: 'M12 9v4m0 4h.01M10.3 3.86l-8.07 14A1.5 1.5 0 003.53 20h16.94a1.5 1.5 0 001.3-2.14l-8.07-14a1.5 1.5 0 00-2.6 0z',
  play: 'M6 4l14 8-14 8V4z',
  retry: 'M4 4v6h6M20 20v-6h-6M5.5 9a7.5 7.5 0 0113-3.5L20 8M18.5 15a7.5 7.5 0 01-13 3.5L4 16',
  trash: 'M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z',
  check: 'M5 13l4 4L19 7',
  reopen: 'M3 12a9 9 0 109-9 9 9 0 00-7 3.5L3 9m0-6v6h6',
  chevron: 'M9 6l6 6-6 6',
  sun: 'M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.42-1.42M6.34 6.34L4.93 4.93m12.73 0l-1.42 1.42M6.34 17.66l-1.42 1.42M12 8a4 4 0 100 8 4 4 0 000-8z',
  moon: 'M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z',
  pause: 'M9 5v14M15 5v14',
  refresh: 'M4 4v6h6M5.5 9a7.5 7.5 0 0113-3.5L20 8',
  close: 'M6 6l12 12M18 6L6 18',
  search: 'M11 19a8 8 0 100-16 8 8 0 000 16zm10 2l-4.35-4.35',
  filter: 'M3 5h18M6 12h12M10 19h4',
  pulse: 'M3 12h4l2 6 4-14 2 8h6',
};
defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: 16 } });
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="PATHS[name]" />
  </svg>
</template>
