import { ref } from "vue";

// Max snackbars on screen at once — extras wait in `pending` (the queue).
const MAX_VISIBLE = 3;

let seq = 0;
const visible = ref([]);
const pending = ref([]);

function showNext() {
  while (visible.value.length < MAX_VISIBLE && pending.value.length > 0) {
    const item = pending.value.shift();
    visible.value.push(item);
    item.timer = setTimeout(() => dismissSnackbar(item.id), item.duration);
  }
}

export function dismissSnackbar(id) {
  const idx = visible.value.findIndex((s) => s.id === id);
  if (idx === -1) {
    const p = pending.value.findIndex((s) => s.id === id);
    if (p !== -1) pending.value.splice(p, 1);
    return;
  }
  const [item] = visible.value.splice(idx, 1);
  if (item.timer) clearTimeout(item.timer);
  showNext();
}

function push(type, message, { title = "", duration = 4000 } = {}) {
  const item = { id: ++seq, type, title, message, duration, timer: null };
  pending.value.push(item);
  showNext();
  return item.id;
}

export const notify = (message, opts = {}) => push(opts.type || "info", message, opts);
export const notifyInfo = (message, opts = {}) => push("info", message, opts);
export const notifySuccess = (message, opts = {}) => push("success", message, opts);
export const notifyWarning = (message, opts = {}) => push("warning", message, opts);
export const notifyError = (message, opts = {}) => push("error", message, opts);

// Shared singleton state — import in SnackbarQueue.vue and any view.
export const snackbarVisible = visible;
export const snackbarPending = pending;

export function useSnackbar() {
  return { visible, pending, push, dismiss: dismissSnackbar, notify, notifyInfo, notifySuccess, notifyWarning, notifyError };
}
