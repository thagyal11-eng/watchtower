<script setup>
import Icon from './Icon.vue';
defineProps({
  icon: { type: String, default: 'pulse' },
  title: { type: String, default: 'Nothing here yet' },
  hint: { type: String, default: '' },
});
</script>

<template>
  <div class="flex flex-col items-center justify-center py-14 px-6 text-center animate-fade-in">
    <div
      class="flex h-12 w-12 items-center justify-center rounded-full mb-3"
      style="background: var(--wt-surface-2); border: 1px solid var(--wt-border); color: var(--wt-text-faint)"
    >
      <Icon :name="icon" :size="22" />
    </div>
    <p class="text-sm font-medium" style="color: var(--wt-text)">{{ title }}</p>
    <p v-if="hint" class="mt-1 text-xs" style="color: var(--wt-text-muted)">{{ hint }}</p>
  </div>
</template>
