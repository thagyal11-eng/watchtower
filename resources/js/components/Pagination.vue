<script setup>
import { num } from '../lib/format.js';
const props = defineProps({
  meta: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['change']);

function go(p) {
  const last = props.meta.last_page || 1;
  if (p < 1 || p > last || p === props.meta.page) return;
  emit('change', p);
}
</script>

<template>
  <div
    v-if="meta && (meta.last_page > 1 || meta.total)"
    class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs"
    style="border-top: 1px solid var(--wt-border); color: var(--wt-text-muted)"
  >
    <span>
      <template v-if="meta.total">
        {{ num(((meta.page - 1) * meta.per_page) + 1) }}–{{ num(Math.min(meta.page * meta.per_page, meta.total)) }}
        of <strong style="color: var(--wt-text)">{{ num(meta.total) }}</strong>
      </template>
      <template v-else>No results</template>
    </span>
    <div class="flex items-center gap-1.5">
      <button class="wt-btn !px-2 !py-1" :disabled="meta.page <= 1" @click="go(meta.page - 1)">Prev</button>
      <span class="px-1">Page {{ meta.page || 1 }} / {{ meta.last_page || 1 }}</span>
      <button class="wt-btn !px-2 !py-1" :disabled="meta.page >= (meta.last_page || 1)" @click="go(meta.page + 1)">Next</button>
    </div>
  </div>
</template>
