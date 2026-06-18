<script setup>
import { computed, ref } from 'vue';
import { dateTime, num } from '../lib/format.js';
import EmptyState from './EmptyState.vue';

// Lightweight, dependency-free SVG bar chart for the throughput series.
// Stacked bars: processed (accent) + failed (red).
const props = defineProps({
  series: { type: Array, default: () => [] }, // [{at, processed, failed}]
});

const W = 720;
const H = 200;
const PAD = { t: 12, r: 12, b: 24, l: 36 };
const hover = ref(null);

const points = computed(() => props.series || []);
const innerW = computed(() => W - PAD.l - PAD.r);
const innerH = computed(() => H - PAD.t - PAD.b);

const maxVal = computed(() => {
  const m = Math.max(1, ...points.value.map((p) => (p.processed || 0) + (p.failed || 0)));
  // round up to a "nice" number
  const pow = Math.pow(10, Math.floor(Math.log10(m)));
  return Math.ceil(m / pow) * pow;
});

const bars = computed(() => {
  const n = points.value.length;
  if (!n) return [];
  const slot = innerW.value / n;
  const bw = Math.max(2, Math.min(28, slot * 0.62));
  return points.value.map((p, i) => {
    const x = PAD.l + slot * i + (slot - bw) / 2;
    const total = (p.processed || 0) + (p.failed || 0);
    const totalH = (total / maxVal.value) * innerH.value;
    const failH = ((p.failed || 0) / maxVal.value) * innerH.value;
    const procH = totalH - failH;
    return {
      i, x, bw,
      data: p,
      procY: PAD.t + innerH.value - totalH,
      procH,
      failY: PAD.t + innerH.value - failH,
      failH,
    };
  });
});

const gridLines = computed(() => {
  const lines = [];
  for (let i = 0; i <= 4; i++) {
    const v = (maxVal.value / 4) * i;
    const y = PAD.t + innerH.value - (v / maxVal.value) * innerH.value;
    lines.push({ y, label: num(Math.round(v)) });
  }
  return lines;
});

// Show a sparse subset of x-axis labels.
const xLabels = computed(() => {
  const n = bars.value.length;
  if (!n) return [];
  const step = Math.max(1, Math.ceil(n / 6));
  return bars.value.filter((_, i) => i % step === 0 || i === n - 1);
});
</script>

<template>
  <div class="wt-card p-4">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-sm font-semibold" style="color: var(--wt-text)">Throughput</h3>
      <div class="flex items-center gap-3 text-xs" style="color: var(--wt-text-muted)">
        <span class="flex items-center gap-1.5"><span class="wt-key wt-key-proc"></span>Processed</span>
        <span class="flex items-center gap-1.5"><span class="wt-key wt-key-fail"></span>Failed</span>
      </div>
    </div>

    <EmptyState
      v-if="!points.length"
      icon="pulse"
      title="No throughput data"
      hint="Once jobs run in this window, activity will chart here."
    />

    <div v-else class="relative">
      <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" style="height: auto" @mouseleave="hover = null">
        <!-- grid -->
        <g>
          <line
            v-for="(g, i) in gridLines"
            :key="'g' + i"
            :x1="PAD.l" :x2="W - PAD.r" :y1="g.y" :y2="g.y"
            stroke="var(--wt-border)" stroke-width="1"
          />
          <text
            v-for="(g, i) in gridLines"
            :key="'gl' + i"
            :x="PAD.l - 6" :y="g.y + 3"
            text-anchor="end" font-size="9" fill="var(--wt-text-faint)"
          >{{ g.label }}</text>
        </g>

        <!-- bars -->
        <g v-for="b in bars" :key="b.i">
          <rect
            :x="b.x" :y="b.procY" :width="b.bw" :height="Math.max(0, b.procH)"
            rx="2" fill="var(--wt-accent)" :opacity="hover && hover.i !== b.i ? 0.45 : 0.95"
          />
          <rect
            v-if="b.failH > 0.5"
            :x="b.x" :y="b.failY" :width="b.bw" :height="Math.max(0, b.failH)"
            rx="2" fill="#ef4444" :opacity="hover && hover.i !== b.i ? 0.45 : 0.95"
          />
          <rect
            :x="b.x - 1" :y="PAD.t" :width="b.bw + 2" :height="innerH"
            fill="transparent" @mouseenter="hover = b"
          />
        </g>

        <!-- x labels -->
        <text
          v-for="b in xLabels" :key="'x' + b.i"
          :x="b.x + b.bw / 2" :y="H - 8"
          text-anchor="middle" font-size="9" fill="var(--wt-text-faint)"
        >{{ dateTime(b.data.at) }}</text>
      </svg>

      <div
        v-if="hover"
        class="wt-tooltip"
        :style="{ left: (hover.x / W * 100) + '%' }"
      >
        <div class="font-medium">{{ dateTime(hover.data.at) }}</div>
        <div style="color: var(--wt-accent)">Processed: {{ num(hover.data.processed) }}</div>
        <div style="color: #ef4444">Failed: {{ num(hover.data.failed) }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wt-key { width: 9px; height: 9px; border-radius: 2px; }
.wt-key-proc { background: var(--wt-accent); }
.wt-key-fail { background: #ef4444; }
.wt-tooltip {
  position: absolute; top: 0; transform: translateX(-50%);
  background: var(--wt-surface); border: 1px solid var(--wt-border-strong);
  border-radius: 0.5rem; padding: 0.4rem 0.55rem; font-size: 0.6875rem;
  pointer-events: none; box-shadow: var(--wt-shadow); white-space: nowrap;
  color: var(--wt-text); z-index: 5;
}
</style>
