const STORAGE_KEY = "neust_eval_device_id";

export function getDeviceId() {
  let id = localStorage.getItem(STORAGE_KEY);
  if (!id) {
    id = crypto.randomUUID ? crypto.randomUUID() : "d-" + Date.now().toString(36) + Math.random().toString(36).slice(2);
    localStorage.setItem(STORAGE_KEY, id);
  }
  return id;
}
