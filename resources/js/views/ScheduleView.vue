<script setup>
import { ref, reactive } from 'vue';
import { api } from '../lib/api.js';
import { onPoll } from '../lib/polling.js';
import { toast } from '../lib/ui.js';
import { num, ms, relativeTime, dateTime } from '../lib/format.js';
import StatusBadge from '../components/StatusBadge.vue';
import EmptyState from '../components/EmptyState.vue';
import Spinner from '../components/Spinner.vue';
import Icon from '../components/Icon.vue';

const loading = ref(true);
const error = ref(null);
const tasks = ref([]);
const summary = ref({ total: 0, missed: 0, failing: 0 });

const expanded = reactive({}); // key -> { history, loading, runResult }
const running = reactive({}); // key -> bool

async function load(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await api.schedule();
    tasks.value = res.tasks || [];
    summary.value = res.summary || summary.value;
    error.value = null;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function toggle(task) {
  const key = task.key;
  if (expanded[key]) {
    delete expanded[key];
    return;
  }
  expanded[key] = { loading: true, history: [], runResult: null };
  try {
    const res = await api.scheduleHistory(key);
    expanded[key].history = res.runs || [];
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    if (expanded[key]) expanded[key].loading = false;
  }
}

async function runNow(task) {
  const key = task.key;
  running[key] = true;
  if (!expanded[key]) await toggle(task);
  try {
    const res = await api.scheduleRun(key);
    if (expanded[key]) expanded[key].runResult = res.result || null;
    toast(res.message || 'Task dispatched', 'success');
    // Refresh history after a run.
    try {
      const h = await api.scheduleHistory(key);
      if (expanded[key]) expanded[key].history = h.runs || [];
    } catch { /* ignore */ }
    load(true);
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    running[key] = false;
  }
}

load();
onPoll(() => load(true));
</script>

<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
      <h2 class="text-base font-semibold" style="color: var(--wt-text)">Scheduled tasks</h2>
      <div class="flex items-center gap-2 text-xs" style="color: var(--wt-text-muted)">
        <span class="wt-badge wt-st-neutral">{{ num(summary.total) }} total</span>
        <span v-if="summary.missed" class="wt-badge wt-st-warn">{{ num(summary.missed) }} missed</span>
        <span v-if="summary.failing" class="wt-badge wt-st-fail">{{ num(summary.failing) }} failing</span>
      </div>
    </div>

    <div class="wt-card overflow-hidden">
      <div v-if="loading && !tasks.length" class="flex items-center justify-center gap-2 py-14 text-sm" style="color: var(--wt-text-muted)">
        <Spinner /> Loading schedule…
      </div>
      <EmptyState
        v-else-if="error"
        icon="error" title="Couldn't load the schedule" :hint="error"
      />
      <EmptyState
        v-else-if="!tasks.length"
        icon="schedule" title="No scheduled tasks"
        hint="Tasks registered in your console kernel will appear here."
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="wt-thead">
            <th class="w-6"></th>
            <th>Command</th>
            <th>Cadence</th>
            <th>Next run</th>
            <th>Last run</th>
            <th>Status</th>
            <th class="text-right">Duration</th>
            <th class="text-right pr-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="task in tasks" :key="task.key">
            <tr
              class="wt-row" :class="{ 'wt-row-missed': task.missed }"
              @click="toggle(task)"
            >
              <td class="pl-3">
                <Icon name="chevron" :size="14" class="wt-chev" :class="{ 'wt-chev-open': expanded[task.key] }" />
              </td>
              <td class="font-mono text-xs">
                <div class="flex items-center gap-2">
                  <span class="font-medium truncate max-w-[280px]" style="color: var(--wt-text)" :title="task.command">{{ task.command }}</span>
                  <span v-if="task.missed" class="wt-badge wt-st-fail">MISSED</span>
                  <span v-if="task.without_overlapping" class="wt-badge wt-st-neutral" title="Runs without overlapping">no-overlap</span>
                </div>
              </td>
              <td>
                <div>{{ task.human || '—' }}</div>
                <div class="text-xs font-mono" style="color: var(--wt-text-faint)">{{ task.expression }} · {{ task.timezone }}</div>
              </td>
              <td :title="task.next_run_at">{{ relativeTime(task.next_run_at) }}</td>
              <td :title="task.last_run_at">{{ task.last_run_at ? relativeTime(task.last_run_at) : '—' }}</td>
              <td><StatusBadge :status="task.last_status" /></td>
              <td class="text-right tabular-nums">{{ ms(task.last_duration_ms) }}</td>
              <td class="text-right pr-4" @click.stop>
                <button class="wt-btn wt-btn-primary !px-2.5 !py-1" :disabled="running[task.key]" @click="runNow(task)">
                  <Spinner v-if="running[task.key]" :size="13" />
                  <Icon v-else name="play" :size="13" />
                  Run now
                </button>
              </td>
            </tr>
            <tr v-if="expanded[task.key]" :key="task.key + '-exp'" class="wt-exp-row">
              <td colspan="8" class="p-0">
                <div class="px-5 py-4 animate-fade-in" style="background: var(--wt-surface-2); border-top: 1px solid var(--wt-border)">
                  <div v-if="expanded[task.key].runResult" class="mb-4">
                    <div class="flex items-center gap-2 mb-1.5">
                      <span class="text-xs font-semibold uppercase tracking-wide" style="color: var(--wt-text-muted)">Run result</span>
                      <StatusBadge :status="expanded[task.key].runResult.status" />
                      <span class="text-xs" style="color: var(--wt-text-faint)">exit {{ expanded[task.key].runResult.exit_code }} · {{ ms(expanded[task.key].runResult.duration_ms) }}</span>
                    </div>
                    <pre class="wt-output">{{ expanded[task.key].runResult.output || '(no output)' }}</pre>
                  </div>

                  <div class="text-xs font-semibold uppercase tracking-wide mb-2" style="color: var(--wt-text-muted)">Recent history</div>
                  <div v-if="expanded[task.key].loading" class="flex items-center gap-2 py-3 text-sm" style="color: var(--wt-text-muted)">
                    <Spinner :size="14" /> Loading history…
                  </div>
                  <EmptyState
                    v-else-if="!expanded[task.key].history.length"
                    icon="schedule" title="No runs recorded yet"
                    hint="History will populate the next time this task runs."
                  />
                  <div v-else class="space-y-1.5">
                    <details v-for="run in expanded[task.key].history" :key="run.id" class="wt-hist">
                      <summary class="flex items-center gap-3 text-xs cursor-pointer">
                        <StatusBadge :status="run.status" />
                        <span :title="run.started_at">{{ dateTime(run.started_at) }}</span>
                        <span style="color: var(--wt-text-faint)">{{ ms(run.duration_ms) }} · exit {{ run.exit_code }}</span>
                      </summary>
                      <pre class="wt-output mt-2">{{ run.output || '(no output)' }}</pre>
                    </details>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.wt-thead th { padding: 0.6rem 0.5rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--wt-text-faint); border-bottom: 1px solid var(--wt-border); }
.wt-row td { padding: 0.7rem 0.5rem; border-bottom: 1px solid var(--wt-border); vertical-align: top; color: var(--wt-text-muted); }
.wt-row { cursor: pointer; transition: background-color .1s ease; }
.wt-row:hover { background: var(--wt-surface-2); }
.wt-row-missed { background: rgba(220,38,38,.05); }
.wt-row-missed:hover { background: rgba(220,38,38,.09); }
.wt-row-missed td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
.wt-exp-row td { border-bottom: 1px solid var(--wt-border); }
.wt-chev { transition: transform .15s ease; color: var(--wt-text-faint); }
.wt-chev-open { transform: rotate(90deg); }
.wt-output { font-family: ui-monospace, monospace; font-size: 0.7rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; background: var(--wt-bg); border: 1px solid var(--wt-border); border-radius: 0.5rem; padding: 0.6rem 0.75rem; max-height: 240px; overflow: auto; color: var(--wt-text); }
.wt-hist summary { list-style: none; padding: 0.35rem 0.5rem; border-radius: 0.4rem; }
.wt-hist summary::-webkit-details-marker { display: none; }
.wt-hist summary:hover { background: var(--wt-surface); }
.wt-st-neutral { color: var(--wt-text-muted); background: var(--wt-surface-2); border-color: var(--wt-border); }
.wt-st-warn { color: #d97706; background: rgba(217,119,6,.12); border-color: rgba(217,119,6,.3); }
.wt-st-fail { color: #dc2626; background: rgba(220,38,38,.12); border-color: rgba(220,38,38,.3); }
</style>
