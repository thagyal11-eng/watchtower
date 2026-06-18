import { ref } from 'vue';

// Minimal hash router. Routes look like #/schedule, #/queues, #/errors.
// We avoid a router dependency to keep the bundle lean.

const ROUTES = ['schedule', 'queues', 'errors'];
const DEFAULT = 'schedule';

export const route = ref(parse());

function parse() {
  const hash = (window.location.hash || '').replace(/^#\/?/, '').split('?')[0];
  const seg = hash.split('/')[0];
  return ROUTES.includes(seg) ? seg : DEFAULT;
}

window.addEventListener('hashchange', () => {
  route.value = parse();
});

export function navigate(name) {
  if (!ROUTES.includes(name)) return;
  if (parse() === name) {
    route.value = name;
    return;
  }
  window.location.hash = `#/${name}`;
}

// Ensure a canonical hash on first load (so deep-linking & refresh are stable).
if (!window.location.hash) {
  window.location.hash = `#/${DEFAULT}`;
}

export { ROUTES };
