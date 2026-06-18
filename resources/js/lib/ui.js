import { reactive } from 'vue';

let toastId = 0;

export const ui = reactive({
  toasts: [],
  confirm: null, // { title, message, danger, resolve }
});

export function toast(message, type = 'info', timeout = 4000) {
  const id = ++toastId;
  ui.toasts.push({ id, message, type });
  if (timeout) {
    setTimeout(() => dismissToast(id), timeout);
  }
  return id;
}

export function dismissToast(id) {
  const i = ui.toasts.findIndex((t) => t.id === id);
  if (i !== -1) ui.toasts.splice(i, 1);
}

// Returns a promise that resolves true/false.
export function confirmAction({ title, message, confirmLabel = 'Confirm', danger = false }) {
  return new Promise((resolve) => {
    ui.confirm = {
      title,
      message,
      confirmLabel,
      danger,
      resolve: (val) => {
        ui.confirm = null;
        resolve(val);
      },
    };
  });
}
