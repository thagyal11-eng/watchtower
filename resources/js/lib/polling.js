import { ref, onMounted, onUnmounted } from 'vue';

// A shared, app-wide polling clock. Views subscribe a callback that fires on
// each tick. A single global pause/resume state is exposed so the toggle in
// the header controls everything.

const paused = ref(false);
const lastTick = ref(Date.now());
const subscribers = new Set();
let timer = null;

function interval() {
  const v = Number(window.Watchtower?.pollingInterval);
  return Number.isFinite(v) && v >= 1000 ? v : 5000;
}

function tick() {
  lastTick.value = Date.now();
  subscribers.forEach((fn) => {
    try {
      fn();
    } catch (e) {
      /* never let one subscriber break the clock */
    }
  });
}

function ensureTimer() {
  if (timer) clearInterval(timer);
  timer = setInterval(() => {
    if (!paused.value && !document.hidden) tick();
  }, interval());
}

ensureTimer();

document.addEventListener('visibilitychange', () => {
  // Catch up immediately when the tab regains focus.
  if (!document.hidden && !paused.value) tick();
});

export function usePolling() {
  return {
    paused,
    lastTick,
    intervalMs: interval,
    togglePause() {
      paused.value = !paused.value;
      if (!paused.value) tick();
    },
  };
}

// Register a per-view poll callback that auto-unsubscribes on unmount.
export function onPoll(fn) {
  onMounted(() => subscribers.add(fn));
  onUnmounted(() => subscribers.delete(fn));
}
