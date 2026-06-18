<script setup>
import { ref, reactive, computed } from 'vue';
import { api } from '../lib/api.js';
import { onPoll } from '../lib/polling.js';
import { toast, confirmAction } from '../lib/ui.js';
import { num, ms, relativeTime, truncate } from '../lib/format.js';
import StatCard from '../components/StatCard.vue';
import ThroughputChart from '../components/ThroughputChart.vue';
import StatusBadge from '../components/StatusBadge.vue';
import EmptyState from '../components/EmptyState.vue';
import Spinner from '../components/Spinner.vue';
import Pagination from '../components/Pagination.vue';
import Icon from '../components/Icon.vue';

const WINDOWS = [
  { v: 'hour', label: 'Last hour' },
  { v: 'day', label: 'Last 24h' },
  { v: 'week', label: 'Last 7d' },
];
const win = ref('day');

const metrics = ref(null);
const metricsLoading = ref(true);
const metricsError = ref(null);

// Failed jobs
const failed = ref([]);
const failedMeta = ref({ page: 1, per_page: 25, total: 0, last_page: 1 });
const filters = ref({ exception_classes: [], queues: [] });
const failedLoading = ref(true);
const failedError = ref(null);
const fq = reactive({ page: 1, queue: '', exception_class: '', search: '' });

const expandedJob = reactive({});
const busy = reactive({}); // id -> 'retry'|'delete'
const bulkBusy = ref(false);

// Retry-by-window state
const showWindow = ref(false);
const windowForm = reactive({ from: '', to: '' });

const pendingEstimate = computed(() => metrics.value?.pending_estimate || { supported: false });

async function loadMetrics(silent = false) {
  if (!silent) metricsLoading.value = true;
  try {
    metrics.value = await api.queueMetrics(win.value);
    metricsError.value = null;
  } catch (e) {
    metricsError.value = e.message;
  } finally {
    metricsLoading.value = false;
  }
}

async function loadFailed(silent = false) {
  if (!silent) failedLoading.value = true;
  try {
    const res = await api.queueFailed({
      page: fq.page,
      queue: fq.queue,
      exception_class: fq.exception_class,
      search: fq.search,
    });
    failed.value = res.data || [];
    failedMeta.value = res.meta || failedMeta.value;
    if (res.filters) filters.value = res.filters;
    failedError.value = null;
  } catch (e) {
    failedError.value = e.message;
  } finally {
    failedLoading.value = false;
  }
}

function setWindow(v) {
  win.value = v;
  loadMetrics();
}

function applyFilters() {
  fq.page = 1;
  loadFailed();
}

function changePage(p) {
  fq.page = p;
  loadFailed();
}

function toggleJob(job) {
  expandedJob[job.id] = !expandedJob[job.id];
}

async function retry(job) {
  const ok = await confirmAction({
    title: 'Retry failed job?',
    message: `Re-dispatch "${job.name}" onto the ${job.queue} queue.`,
    confirmLabel: 'Retry',
  });
  if (!ok) return;
  busy[job.id] = 'retry';
  try {
    await api.queueRetry(job.id);
    toast('Job queued for retry', 'success');
    loadFailed(true);
    loadMetrics(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    delete busy[job.id];
  }
}

async function remove(job) {
  const ok = await confirmAction({
    title: 'Delete failed job?',
    message: `This permanently removes the failed record for "${job.name}".`,
    confirmLabel: 'Delete',
    danger: true,
  });
  if (!ok) return;
  busy[job.id] = 'delete';
  try {
    await api.queueDelete(job.id);
    toast('Failed job deleted', 'success');
    loadFailed(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    delete busy[job.id];
  }
}

async function bulkRetry(body, label) {
  const ok = await confirmAction({
    title: 'Bulk retry',
    message: label,
    confirmLabel: 'Retry jobs',
  });
  if (!ok) return;
  bulkBusy.value = true;
  try {
    const res = await api.queueRetryBulk(body);
    toast(res.message || `${num(res.count)} jobs queued for retry`, 'success');
    showWindow.value = false;
    loadFailed(true);
    loadMetrics(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    bulkBusy.value = false;
  }
}

function retryAll() {
  bulkRetry({ mode: 'all' }, `Retry all ${num(failedMeta.value.total)} failed jobs currently matching your filters?`);
}

function retryByException(cls) {
  if (!cls) return;
  bulkRetry({ mode: 'exception', exception_class: cls }, `Retry every failed job that threw ${cls}?`);
}

function retryWindow() {
  if (!windowForm.from && !windowForm.to) {
    toast('Pick at least one date bound', 'error');
    return;
  }
  bulkRetry(
    { mode: 'window', from: windowForm.from || undefined, to: windowForm.to || undefined },
    `Retry failed jobs in the selected time window?`
  );
}

loadMetrics();
loadFailed();
onPoll(() => {
  loadMetrics(true);
  loadFailed(true);
});
</script>

<template>
  <section class="space-y-5">
    <!-- Window selector -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-base font-semibold" style="color: var(--wt-text)">Queues &amp; jobs</h2>
      <div class="wt-seg">
        <button
          v-for="w in WINDOWS" :key="w.v"
          class="wt-seg-btn" :class="{ 'wt-seg-active': win === w.v }"
          @click="setWindow(w.v)"
        >{{ w.label }}</button>
      </div>
    </div>

    <!-- Metrics tiles -->
    <div v-if="metricsError" class="wt-card">
      <EmptyState icon="error" title="Couldn't load queue metrics" :hint="metricsError" />
    </div>
    <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
      <StatCard label="Pending" :value="metrics ? num(metrics.totals.pending) : '—'" tone="warn" icon="queue" />
      <StatCard label="Processing" :value="metrics ? num(metrics.totals.processing) : '—'" tone="accent" icon="pulse" />
      <StatCard label="Processed" :value="metrics ? num(metrics.totals.processed) : '—'" tone="ok" icon="check" />
      <StatCard label="Failed" :value="metrics ? num(metrics.totals.failed) : '—'" :tone="metrics && metrics.totals.failed ? 'fail' : 'neutral'" icon="error" />
      <StatCard label="Avg duration" :value="metrics ? ms(metrics.duration.avg_ms) : '—'" tone="neutral" />
      <StatCard label="P95 duration" :value="metrics ? ms(metrics.duration.p95_ms) : '—'" tone="neutral" />
    </div>

    <!-- Pending estimate banner -->
    <div
      v-if="metrics"
      class="wt-card flex items-center justify-between px-4 py-2.5 text-sm"
    >
      <span class="flex items-center gap-2" style="color: var(--wt-text-muted)">
        <Icon name="queue" :size="15" /> Estimated backlog
      </span>
      <span v-if="pendingEstimate.supported" class="tabular-nums font-semibold" style="color: var(--wt-text)">
        {{ num(pendingEstimate.count) }} jobs
      </span>
      <span v-else style="color: var(--wt-text-faint)">
        — <span class="text-xs">(not available for {{ pendingEstimate.driver || 'this' }} driver)</span>
      </span>
    </div>

    <!-- Throughput chart -->
    <ThroughputChart :series="metrics?.throughput || []" />

    <!-- Per-queue breakdown -->
    <div class="wt-card overflow-hidden">
      <div class="px-4 py-3" style="border-bottom: 1px solid var(--wt-border)">
        <h3 class="text-sm font-semibold" style="color: var(--wt-text)">Per-queue breakdown</h3>
      </div>
      <EmptyState
        v-if="!metrics || !metrics.per_queue || !metrics.per_queue.length"
        icon="queue" title="No queue activity" hint="Per-queue counts appear once jobs flow through."
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="wt-thead">
            <th>Queue</th>
            <th class="text-right">Pending</th>
            <th class="text-right">Processing</th>
            <th class="text-right">Processed</th>
            <th class="text-right pr-4">Failed</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in metrics.per_queue" :key="q.queue" class="wt-row">
            <td class="font-mono text-xs" style="color: var(--wt-text)">{{ q.queue }}</td>
            <td class="text-right tabular-nums">{{ num(q.pending) }}</td>
            <td class="text-right tabular-nums">{{ num(q.processing) }}</td>
            <td class="text-right tabular-nums">{{ num(q.processed) }}</td>
            <td class="text-right tabular-nums pr-4" :style="q.failed ? 'color:#dc2626;font-weight:600' : ''">{{ num(q.failed) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Failed jobs -->
    <div class="wt-card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" style="border-bottom: 1px solid var(--wt-border)">
        <h3 class="text-sm font-semibold" style="color: var(--wt-text)">Failed jobs</h3>
        <div class="flex flex-wrap items-center gap-2">
          <button class="wt-btn" :disabled="bulkBusy || !failed.length" @click="retryAll">
            <Spinner v-if="bulkBusy" :size="13" /><Icon v-else name="retry" :size="13" /> Retry all
          </button>
          <select class="wt-input" :disabled="bulkBusy" @change="retryByException($event.target.value); $event.target.value = ''">
            <option value="">Retry by exception…</option>
            <option v-for="c in filters.exception_classes" :key="c" :value="c">{{ truncate(c, 50) }}</option>
          </select>
          <button class="wt-btn" :class="{ 'wt-btn-primary': showWindow }" @click="showWindow = !showWindow">
            <Icon name="schedule" :size="13" /> Retry by window
          </button>
        </div>
      </div>

      <!-- window retry panel -->
      <div v-if="showWindow" class="flex flex-wrap items-end gap-3 px-4 py-3 animate-fade-in" style="background: var(--wt-surface-2); border-bottom: 1px solid var(--wt-border)">
        <label class="flex flex-col gap-1 text-xs" style="color: var(--wt-text-muted)">
          From
          <input v-model="windowForm.from" type="datetime-local" class="wt-input" />
        </label>
        <label class="flex flex-col gap-1 text-xs" style="color: var(--wt-text-muted)">
          To
          <input v-model="windowForm.to" type="datetime-local" class="wt-input" />
        </label>
        <button class="wt-btn wt-btn-primary" :disabled="bulkBusy" @click="retryWindow">
          <Spinner v-if="bulkBusy" :size="13" /><Icon v-else name="retry" :size="13" /> Retry window
        </button>
      </div>

      <!-- filters -->
      <div class="flex flex-wrap items-center gap-2 px-4 py-2.5" style="border-bottom: 1px solid var(--wt-border)">
        <div class="relative flex-1 min-w-[180px]">
          <span class="absolute left-2.5 top-1/2 -translate-y-1/2" style="color: var(--wt-text-faint)"><Icon name="search" :size="14" /></span>
          <input
            v-model="fq.search" type="text" placeholder="Search jobs…"
            class="wt-input w-full !pl-8" @keyup.enter="applyFilters"
          />
        </div>
        <select v-model="fq.queue" class="wt-input" @change="applyFilters">
          <option value="">All queues</option>
          <option v-for="q in filters.queues" :key="q" :value="q">{{ q }}</option>
        </select>
        <select v-model="fq.exception_class" class="wt-input" @change="applyFilters">
          <option value="">All exceptions</option>
          <option v-for="c in filters.exception_classes" :key="c" :value="c">{{ truncate(c, 40) }}</option>
        </select>
        <button class="wt-btn" @click="applyFilters"><Icon name="filter" :size="13" /> Apply</button>
      </div>

      <div v-if="failedLoading && !failed.length" class="flex items-center justify-center gap-2 py-14 text-sm" style="color: var(--wt-text-muted)">
        <Spinner /> Loading failed jobs…
      </div>
      <EmptyState
        v-else-if="failedError"
        icon="error" title="Couldn't load failed jobs" :hint="failedError"
      />
      <EmptyState
        v-else-if="!failed.length"
        icon="check" title="No failed jobs"
        hint="Nothing has failed for the current filters — keep it that way."
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="wt-thead">
            <th class="w-6"></th>
            <th>Job</th>
            <th>Queue</th>
            <th>Exception</th>
            <th>Failed</th>
            <th class="text-right pr-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="job in failed" :key="job.id">
            <tr class="wt-row" @click="toggleJob(job)">
              <td class="pl-3"><Icon name="chevron" :size="14" class="wt-chev" :class="{ 'wt-chev-open': expandedJob[job.id] }" /></td>
              <td class="font-mono text-xs">
                <div class="font-medium truncate max-w-[240px]" style="color: var(--wt-text)" :title="job.name">{{ job.name }}</div>
                <div style="color: var(--wt-text-faint)">{{ job.connection }} · {{ job.uuid ? job.uuid.slice(0, 8) : job.id }}</div>
              </td>
              <td><span class="wt-badge wt-st-neutral">{{ job.queue }}</span></td>
              <td>
                <div class="font-mono text-xs" style="color:#dc2626" :title="job.exception_class">{{ truncate(job.exception_class, 36) }}</div>
                <div class="text-xs truncate max-w-[260px]" style="color: var(--wt-text-muted)" :title="job.exception_summary">{{ truncate(job.exception_summary, 60) }}</div>
              </td>
              <td :title="job.failed_at">{{ relativeTime(job.failed_at) }}</td>
              <td class="text-right pr-4" @click.stop>
                <div class="inline-flex gap-1.5">
                  <button class="wt-btn !px-2 !py-1" :disabled="busy[job.id]" @click="retry(job)">
                    <Spinner v-if="busy[job.id] === 'retry'" :size="13" /><Icon v-else name="retry" :size="13" />
                  </button>
                  <button class="wt-btn wt-btn-danger !px-2 !py-1" :disabled="busy[job.id]" @click="remove(job)">
                    <Spinner v-if="busy[job.id] === 'delete'" :size="13" /><Icon v-else name="trash" :size="13" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="expandedJob[job.id]" :key="job.id + '-exp'">
              <td colspan="6" class="p-0">
                <div class="px-5 py-4 animate-fade-in" style="background: var(--wt-surface-2); border-top: 1px solid var(--wt-border)">
                  <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--wt-text-muted)">Exception</div>
                  <pre class="wt-output">{{ job.exception || job.exception_summary || '(no detail)' }}</pre>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <Pagination :meta="failedMeta" @change="changePage" />
    </div>
  </section>
</template>

<style scoped>
.wt-seg { display: inline-flex; background: var(--wt-surface); border: 1px solid var(--wt-border); border-radius: 0.6rem; padding: 2px; }
.wt-seg-btn { padding: 0.3rem 0.7rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.45rem; color: var(--wt-text-muted); cursor: pointer; transition: all .12s ease; }
.wt-seg-btn:hover { color: var(--wt-text); }
.wt-seg-active { background: var(--wt-accent); color: var(--wt-accent-fg); font-weight: 600; }
.wt-thead th { padding: 0.6rem 0.5rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--wt-text-faint); border-bottom: 1px solid var(--wt-border); }
.wt-row td { padding: 0.7rem 0.5rem; border-bottom: 1px solid var(--wt-border); vertical-align: top; color: var(--wt-text-muted); }
.wt-row { cursor: pointer; transition: background-color .1s ease; }
.wt-row:hover { background: var(--wt-surface-2); }
.wt-chev { transition: transform .15s ease; color: var(--wt-text-faint); }
.wt-chev-open { transform: rotate(90deg); }
.wt-output { font-family: ui-monospace, monospace; font-size: 0.7rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; background: var(--wt-bg); border: 1px solid var(--wt-border); border-radius: 0.5rem; padding: 0.6rem 0.75rem; max-height: 320px; overflow: auto; color: var(--wt-text); }
.wt-st-neutral { color: var(--wt-text-muted); background: var(--wt-surface-2); border: 1px solid var(--wt-border); }
</style>
