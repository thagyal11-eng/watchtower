// Small formatting helpers shared across views.

export function num(n) {
  if (n === null || n === undefined) return '—';
  return new Intl.NumberFormat().format(n);
}

export function ms(value) {
  if (value === null || value === undefined) return '—';
  if (value < 1) return '<1ms';
  if (value < 1000) return `${Math.round(value)}ms`;
  const s = value / 1000;
  if (s < 60) return `${s.toFixed(s < 10 ? 1 : 0)}s`;
  const m = Math.floor(s / 60);
  const rem = Math.round(s % 60);
  return `${m}m ${rem}s`;
}

export function relativeTime(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d)) return String(dateStr);
  const diff = (Date.now() - d.getTime()) / 1000; // seconds
  const future = diff < 0;
  const abs = Math.abs(diff);
  const units = [
    [60, 'second', 1],
    [3600, 'minute', 60],
    [86400, 'hour', 3600],
    [604800, 'day', 86400],
    [2629800, 'week', 604800],
    [31557600, 'month', 2629800],
    [Infinity, 'year', 31557600],
  ];
  for (const [limit, label, div] of units) {
    if (abs < limit) {
      const v = Math.max(1, Math.round(abs / div));
      const plural = v === 1 ? '' : 's';
      return future ? `in ${v} ${label}${plural}` : `${v} ${label}${plural} ago`;
    }
  }
  return d.toLocaleString();
}

export function dateTime(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d)) return String(dateStr);
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function truncate(str, len = 80) {
  if (!str) return '';
  return str.length > len ? str.slice(0, len - 1) + '…' : str;
}
