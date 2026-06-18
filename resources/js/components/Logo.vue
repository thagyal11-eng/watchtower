<script setup>
defineProps({ size: { type: Number, default: 26 } });
</script>

<template>
  <!-- Watchtower mark. Fixed brand colours (navy outline, amber/cream bands,
       red roof) so it reads identically in light and dark on its tile. -->
  <svg :width="size" :height="size" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <!-- fills -->
    <path d="M32 7.5 L52 25 H12 Z" fill="#d0584c" />
    <rect x="19.5" y="25" width="25" height="8" fill="#f3a25c" />
    <rect x="19.5" y="33" width="25" height="8" fill="#fbe1c0" />
    <rect x="12" y="41" width="40" height="6.5" fill="#fbe1c0" />
    <rect x="12" y="47.5" width="40" height="6.5" fill="#fbe1c0" />
    <rect x="21" y="54" width="22" height="5" fill="#f3a25c" />
    <rect x="21" y="59" width="22" height="5.5" fill="#fbe1c0" />

    <!-- navy outlines -->
    <g stroke="#0c2f6b" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round" fill="none">
      <path d="M32 7.5 L52 25 H12 Z" />
      <path d="M19.5 25 V41 H44.5 V25" />
      <rect x="12" y="41" width="40" height="13" />
      <path d="M24 41 V47.5 M32 41 V47.5 M40 41 V47.5" />
      <path d="M21 54 V64 H43 V54" />
    </g>
  </svg>
</template>
