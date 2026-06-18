<script setup>
const props = defineProps({ type: { type: String, default: null } });
const MAP = {
  request: 'wt-ctx-request',
  job: 'wt-ctx-job',
  schedule: 'wt-ctx-schedule',
  queue: 'wt-ctx-job',
  command: 'wt-ctx-schedule',
};
function cls() {
  return MAP[(props.type || '').toLowerCase()] || 'wt-ctx-neutral';
}
</script>

<template>
  <span v-if="type" class="wt-badge" :class="cls()">{{ type }}</span>
</template>

<style scoped>
.wt-ctx-request { color: #7c3aed; background: rgba(124,58,237,.1); border-color: rgba(124,58,237,.25); }
.wt-ctx-job { color: #0891b2; background: rgba(8,145,178,.1); border-color: rgba(8,145,178,.25); }
.wt-ctx-schedule { color: #db2777; background: rgba(219,39,119,.1); border-color: rgba(219,39,119,.25); }
.wt-ctx-neutral { color: var(--wt-text-muted); background: var(--wt-surface-2); border-color: var(--wt-border); }
:global(#watchtower-app.dark) .wt-ctx-request { color: #c4b5fd; }
:global(#watchtower-app.dark) .wt-ctx-job { color: #67e8f9; }
:global(#watchtower-app.dark) .wt-ctx-schedule { color: #f9a8d4; }
</style>
