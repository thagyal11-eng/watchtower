<script setup>
import { ui } from '../lib/ui.js';
</script>

<template>
  <transition name="wt-fade">
    <div
      v-if="ui.confirm"
      class="fixed inset-0 z-[70] flex items-center justify-center p-4"
      style="background: rgba(2, 6, 23, 0.55); backdrop-filter: blur(2px)"
      @click.self="ui.confirm.resolve(false)"
    >
      <div class="wt-card w-full max-w-sm p-5 animate-fade-in" role="dialog" aria-modal="true">
        <h3 class="text-sm font-semibold" style="color: var(--wt-text)">{{ ui.confirm.title }}</h3>
        <p class="mt-1.5 text-sm" style="color: var(--wt-text-muted)">{{ ui.confirm.message }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button class="wt-btn" @click="ui.confirm.resolve(false)">Cancel</button>
          <button
            class="wt-btn"
            :class="ui.confirm.danger ? 'wt-btn-danger' : 'wt-btn-primary'"
            @click="ui.confirm.resolve(true)"
          >
            {{ ui.confirm.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.wt-fade-enter-active, .wt-fade-leave-active { transition: opacity 0.15s ease; }
.wt-fade-enter-from, .wt-fade-leave-to { opacity: 0; }
</style>
