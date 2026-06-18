<script setup>
import { ui, dismissToast } from '../lib/ui.js';
import Icon from './Icon.vue';

const ICONS = { success: 'check', error: 'error', info: 'pulse' };
</script>

<template>
  <div class="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 w-[min(360px,calc(100vw-2rem))]">
    <div
      v-for="t in ui.toasts"
      :key="t.id"
      class="wt-card flex items-start gap-2.5 px-3.5 py-3 text-sm animate-fade-in"
      :class="`wt-toast-${t.type}`"
    >
      <span class="mt-0.5 shrink-0"><Icon :name="ICONS[t.type] || 'pulse'" :size="16" /></span>
      <span class="flex-1" style="color: var(--wt-text)">{{ t.message }}</span>
      <button class="shrink-0 opacity-60 hover:opacity-100" @click="dismissToast(t.id)">
        <Icon name="close" :size="14" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.wt-toast-success { border-left: 3px solid #16a34a; }
.wt-toast-error { border-left: 3px solid #dc2626; }
.wt-toast-info { border-left: 3px solid var(--wt-accent); }
.wt-toast-success span:first-child { color: #16a34a; }
.wt-toast-error span:first-child { color: #dc2626; }
.wt-toast-info span:first-child { color: var(--wt-accent); }
</style>
