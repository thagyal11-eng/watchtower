<script setup>
import { ref, reactive } from 'vue';
import { api } from '../lib/api.js';
import { onPoll } from '../lib/polling.js';
import { toast, confirmAction } from '../lib/ui.js';
import { num, relativeTime, truncate } from '../lib/format.js';
import StatusBadge from '../components/StatusBadge.vue';
import ContextBadge from '../components/ContextBadge.vue';
import EmptyState from '../components/EmptyState.vue';
import Spinner from '../components/Spinner.vue';
import Pagination from '../components/Pagination.vue';
import Icon from '../components/Icon.vue';

const list = ref([]);
const meta = ref({ page: 1, per_page: 25, total: 0, last_page: 1 });
const summary = ref({ unresolved: 0, resolved: 0 });
const loading = ref(true);
const error = ref(null);

const q = reactive({ sort: 'recent', status: 'unresolved', context: '', search: '', page: 1 });

const detail = reactive({}); // id -> { loading, data }
const busy = reactive({}); // id -> bool

const SORTS = [
  { v: 'recent', label: 'Most recent' },
  { v: 'frequency', label: 'Most frequent' },
];
const STATUSES = [
  { v: 'unresolved', label: 'Unresolved' },
  { v: 'resolved', label: 'Resolved' },
  { v: 'all', label: 'All' },
];

async function load(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await api.exceptions({
      sort: q.sort,
      status: q.status,
      context: q.context,
      search: q.search,
      page: q.page,
    });
    list.value = res.data || [];
    meta.value = res.meta || meta.value;
    summary.value = res.summary || summary.value;
    error.value = null;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  q.page = 1;
  load();
}

function changePage(p) {
  q.page = p;
  load();
}

async function toggle(item) {
  const id = item.id;
  if (detail[id]) {
    delete detail[id];
    return;
  }
  detail[id] = { loading: true, data: null };
  try {
    const res = await api.exception(id);
    detail[id].data = res.data;
  } catch (e) {
    toast(e.message, 'error');
    delete detail[id];
    return;
  } finally {
    if (detail[id]) detail[id].loading = false;
  }
}

async function resolve(item) {
  const ok = await confirmAction({
    title: 'Resolve exception?',
    message: `Mark "${truncate(item.message, 60)}" as resolved.`,
    confirmLabel: 'Resolve',
  });
  if (!ok) return;
  busy[item.id] = true;
  try {
    await api.exceptionResolve(item.id);
    toast('Exception resolved', 'success');
    load(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    delete busy[item.id];
  }
}

async function reopen(item) {
  const ok = await confirmAction({
    title: 'Reopen exception?',
    message: `Move "${truncate(item.message, 60)}" back to unresolved.`,
    confirmLabel: 'Reopen',
  });
  if (!ok) return;
  busy[item.id] = true;
  try {
    await api.exceptionReopen(item.id);
    toast('Exception reopened', 'success');
    load(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    delete busy[item.id];
  }
}

load();
onPoll(() => load(true));
</script>

<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <h2 class="text-base font-semibold" style="color: var(--wt-text)">Errors</h2>
        <span class="wt-badge wt-st-warn">{{ num(summary.unresolved) }} unresolved</span>
        <span class="wt-badge wt-st-ok">{{ num(summary.resolved) }} resolved</span>
      </div>
      <div class="wt-seg">
        <button v-for="s in SORTS" :key="s.v" class="wt-seg-btn" :class="{ 'wt-seg-active': q.sort === s.v }" @click="q.sort = s.v; applyFilters()">{{ s.label }}</button>
      </div>
    </div>

    <div class="wt-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-2 px-4 py-2.5" style="border-bottom: 1px solid var(--wt-border)">
        <div class="wt-seg">
          <button v-for="s in STATUSES" :key="s.v" class="wt-seg-btn" :class="{ 'wt-seg-active': q.status === s.v }" @click="q.status = s.v; applyFilters()">{{ s.label }}</button>
        </div>
        <div class="relative flex-1 min-w-[180px]">
          <span class="absolute left-2.5 top-1/2 -translate-y-1/2" style="color: var(--wt-text-faint)"><Icon name="search" :size="14" /></span>
          <input v-model="q.search" type="text" placeholder="Search class or message…" class="wt-input w-full !pl-8" @keyup.enter="applyFilters" />
        </div>
        <select v-model="q.context" class="wt-input" @change="applyFilters">
          <option value="">All contexts</option>
          <option value="request">Request</option>
          <option value="job">Job</option>
          <option value="schedule">Schedule</option>
        </select>
        <button class="wt-btn" @click="applyFilters"><Icon name="filter" :size="13" /> Apply</button>
      </div>

      <div v-if="loading && !list.length" class="flex items-center justify-center gap-2 py-14 text-sm" style="color: var(--wt-text-muted)">
        <Spinner /> Loading exceptions…
      </div>
      <EmptyState
        v-else-if="error" icon="error" title="Couldn't load exceptions" :hint="error"
      />
      <EmptyState
        v-else-if="!list.length"
        icon="check"
        :title="q.status === 'resolved' ? 'No resolved exceptions' : 'No exceptions'"
        :hint="q.status === 'unresolved' ? 'Your app is running clean — nothing unresolved right now.' : 'Adjust the filters above to widen the search.'"
      />
      <ul v-else>
        <li v-for="item in list" :key="item.id" style="border-bottom: 1px solid var(--wt-border)">
          <div class="wt-err-row" :class="{ 'wt-err-resolved': item.resolved_at }" @click="toggle(item)">
            <Icon name="chevron" :size="14" class="wt-chev mt-1 shrink-0" :class="{ 'wt-chev-open': detail[item.id] }" />
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-semibold" style="color:#dc2626" :title="item.class">{{ truncate(item.class, 48) }}</span>
                <ContextBadge :type="item.context_type" />
                <StatusBadge v-if="item.resolved_at" status="resolved" />
              </div>
              <p class="mt-0.5 text-sm truncate" style="color: var(--wt-text)" :title="item.message">{{ item.message }}</p>
              <p class="mt-0.5 text-xs font-mono" style="color: var(--wt-text-faint)">
                {{ truncate(item.file, 70) }}<span v-if="item.line">:{{ item.line }}</span>
              </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1 text-right">
              <span class="wt-count" :title="`${num(item.count)} occurrences`">{{ num(item.count) }}×</span>
              <span class="text-xs" style="color: var(--wt-text-muted)" :title="item.last_seen_at">{{ relativeTime(item.last_seen_at) }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-1.5" @click.stop>
              <button v-if="!item.resolved_at" class="wt-btn !px-2 !py-1" :disabled="busy[item.id]" @click="resolve(item)">
                <Spinner v-if="busy[item.id]" :size="13" /><Icon v-else name="check" :size="13" /> Resolve
              </button>
              <button v-else class="wt-btn !px-2 !py-1" :disabled="busy[item.id]" @click="reopen(item)">
                <Spinner v-if="busy[item.id]" :size="13" /><Icon v-else name="reopen" :size="13" /> Reopen
              </button>
            </div>
          </div>

          <div v-if="detail[item.id]" class="px-5 pb-4 animate-fade-in">
            <div class="rounded-lg" style="background: var(--wt-surface-2); border: 1px solid var(--wt-border)">
              <div class="flex flex-wrap gap-x-6 gap-y-1 px-4 py-2.5 text-xs" style="border-bottom: 1px solid var(--wt-border); color: var(--wt-text-muted)">
                <span>First seen <strong style="color: var(--wt-text)">{{ relativeTime(item.first_seen_at) }}</strong></span>
                <span>Last seen <strong style="color: var(--wt-text)">{{ relativeTime(item.last_seen_at) }}</strong></span>
                <span>Fingerprint <code class="font-mono">{{ truncate(item.fingerprint, 16) }}</code></span>
              </div>
              <div class="p-3">
                <div v-if="detail[item.id].loading" class="flex items-center gap-2 py-4 text-sm" style="color: var(--wt-text-muted)">
                  <Spinner :size="14" /> Loading stack trace…
                </div>
                <pre v-else class="wt-output">{{ detail[item.id].data?.trace || '(no trace available)' }}</pre>
              </div>
            </div>
          </div>
        </li>
      </ul>
      <Pagination :meta="meta" @change="changePage" />
    </div>
  </section>
</template>

<style scoped>
.wt-seg { display: inline-flex; background: var(--wt-surface); border: 1px solid var(--wt-border); border-radius: 0.6rem; padding: 2px; }
.wt-seg-btn { padding: 0.3rem 0.7rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.45rem; color: var(--wt-text-muted); cursor: pointer; transition: all .12s ease; }
.wt-seg-btn:hover { color: var(--wt-text); }
.wt-seg-active { background: var(--wt-accent); color: var(--wt-accent-fg); font-weight: 600; }
.wt-err-row { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; transition: background-color .1s ease; }
.wt-err-row:hover { background: var(--wt-surface-2); }
.wt-err-resolved { opacity: 0.72; }
.wt-chev { transition: transform .15s ease; color: var(--wt-text-faint); }
.wt-chev-open { transform: rotate(90deg); }
.wt-count { font-size: 0.8rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--wt-text); }
.wt-output { font-family: ui-monospace, monospace; font-size: 0.7rem; line-height: 1.55; white-space: pre-wrap; word-break: break-word; background: var(--wt-bg); border: 1px solid var(--wt-border); border-radius: 0.5rem; padding: 0.7rem 0.85rem; max-height: 420px; overflow: auto; color: var(--wt-text); }
.wt-st-warn { color: #d97706; background: rgba(217,119,6,.12); border-color: rgba(217,119,6,.3); }
.wt-st-ok { color: #16a34a; background: rgba(22,163,74,.1); border-color: rgba(22,163,74,.25); }
</style>
