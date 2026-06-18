<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  sub: { type: String, default: '' },
  tone: { type: String, default: 'neutral' }, // neutral | ok | warn | fail | accent
  icon: { type: String, default: null },
});
import Icon from './Icon.vue';
</script>

<template>
  <div class="wt-card relative overflow-hidden px-4 py-3.5" :class="`wt-tone-${tone}`">
    <div class="flex items-start justify-between">
      <p class="wt-eyebrow">{{ label }}</p>
      <span v-if="icon" class="wt-stat-icon"><Icon :name="icon" :size="15" /></span>
    </div>
    <p class="wt-stat-value wt-display tabular-nums">{{ value }}</p>
    <p v-if="sub" class="wt-stat-sub">{{ sub }}</p>
    <span class="wt-tone-bar"></span>
  </div>
</template>

<style scoped>
.wt-stat-value {
  margin-top: 0.5rem;
  font-size: 1.85rem;
  font-weight: 600;
  line-height: 1.05;
  color: var(--wt-text);
}
.wt-stat-sub {
  margin-top: 0.35rem;
  font-family: 'IBM Plex Mono', monospace;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: var(--wt-text-muted);
}
.wt-tone-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--wt-border-strong); }
.wt-stat-icon { color: var(--wt-text-faint); }

.wt-tone-ok .wt-tone-bar { background: var(--wt-ok); box-shadow: 0 0 14px -1px var(--wt-ok); }
.wt-tone-warn .wt-tone-bar { background: var(--wt-warn); box-shadow: 0 0 14px -1px var(--wt-warn); }
.wt-tone-fail .wt-tone-bar { background: var(--wt-fail); box-shadow: 0 0 14px -1px var(--wt-fail); }
.wt-tone-accent .wt-tone-bar { background: var(--wt-accent); box-shadow: 0 0 14px -1px var(--wt-accent); }

.wt-tone-ok .wt-stat-icon { color: var(--wt-ok); }
.wt-tone-warn .wt-stat-icon { color: var(--wt-warn); }
.wt-tone-fail .wt-stat-icon { color: var(--wt-fail); }
.wt-tone-accent .wt-stat-icon { color: var(--wt-accent); }

/* The "fail/warn" cards earn a faint wash so the eye lands on them first. */
.wt-tone-fail .wt-stat-value { color: var(--wt-fail); }
.wt-tone-warn .wt-stat-sub { color: var(--wt-warn); font-weight: 600; }
</style>
