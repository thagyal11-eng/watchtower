<script setup>
// Maps a status string to a consistent, color-coded badge.
const props = defineProps({
  status: { type: String, default: null },
  label: { type: String, default: null },
});

const MAP = {
  success: { cls: 'wt-st-ok', text: 'Success' },
  ok: { cls: 'wt-st-ok', text: 'OK' },
  passing: { cls: 'wt-st-ok', text: 'Passing' },
  healthy: { cls: 'wt-st-ok', text: 'Healthy' },
  completed: { cls: 'wt-st-ok', text: 'Completed' },
  resolved: { cls: 'wt-st-ok', text: 'Resolved' },
  running: { cls: 'wt-st-run', text: 'Running' },
  processing: { cls: 'wt-st-run', text: 'Processing' },
  pending: { cls: 'wt-st-warn', text: 'Pending' },
  missed: { cls: 'wt-st-warn', text: 'Missed' },
  unresolved: { cls: 'wt-st-warn', text: 'Unresolved' },
  failed: { cls: 'wt-st-fail', text: 'Failed' },
  failing: { cls: 'wt-st-fail', text: 'Failing' },
  error: { cls: 'wt-st-fail', text: 'Error' },
};

function meta() {
  const key = (props.status || '').toLowerCase();
  return MAP[key] || { cls: 'wt-st-neutral', text: props.label || props.status || 'Unknown' };
}
</script>

<template>
  <span class="wt-badge" :class="meta().cls">
    <span class="wt-dot"></span>{{ label || meta().text }}
  </span>
</template>

<style scoped>
.wt-dot {
  width: 6px; height: 6px; border-radius: 9999px; background: currentColor;
  box-shadow: 0 0 7px -0.5px currentColor;
}
.wt-st-ok   { color: var(--wt-ok);   background: color-mix(in srgb, var(--wt-ok) 12%, transparent);   border-color: color-mix(in srgb, var(--wt-ok) 30%, transparent); }
.wt-st-run  { color: var(--wt-run);  background: color-mix(in srgb, var(--wt-run) 12%, transparent);  border-color: color-mix(in srgb, var(--wt-run) 30%, transparent); }
.wt-st-warn { color: var(--wt-warn); background: color-mix(in srgb, var(--wt-warn) 13%, transparent); border-color: color-mix(in srgb, var(--wt-warn) 32%, transparent); }
.wt-st-fail { color: var(--wt-fail); background: color-mix(in srgb, var(--wt-fail) 13%, transparent); border-color: color-mix(in srgb, var(--wt-fail) 32%, transparent); }
.wt-st-neutral { color: var(--wt-text-muted); background: var(--wt-surface-2); border-color: var(--wt-border); }
</style>
