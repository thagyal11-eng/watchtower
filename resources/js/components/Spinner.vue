<script setup>
defineProps({ size: { type: [Number, String], default: 16 } });
</script>

<template>
  <svg
    class="animate-spin-slow"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.2" />
    <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
  </svg>
</template>
