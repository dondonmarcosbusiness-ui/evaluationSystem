import { Modal } from "bootstrap";
import { modalSyncState } from "./modalSync.js";

let opener = null;

/** Called once by ConfirmDialog.vue on mount. */
export function registerConfirmOpener(fn) {
  opener = fn;
}

/**
 * App-wide confirmation dialog matching the standard design:
 * small centered dialog, centered title + message, split Yes / No thanks
 * footer. Drop-in replacement for `Swal.fire({... showCancelButton: true})`.
 *
 * Resolves `{ isConfirmed: boolean }` so existing `result.isConfirmed`
 * checks keep working unchanged. Backdrop click / ESC counts as cancel.
 *
 * Any currently open Bootstrap modals are parked while the question is
 * asked (Bootstrap can't stack modals cleanly) and re-shown afterwards.
 */
export async function confirmAction({
  title = "Are you sure?",
  message = "",
  confirmText = "Yes",
  cancelText = "No thanks",
} = {}) {
  if (!opener) throw new Error("ConfirmDialog is not mounted");

  const openModals = [...document.querySelectorAll(".modal.show")]
    .map((el) => Modal.getInstance(el))
    .filter((m) => m && document.contains(m._element));

  modalSyncState.suspended = true;
  openModals.forEach((m) => {
    try {
      m.hide();
    } catch {
      /* noop */
    }
  });

  let result;
  try {
    result = await opener({ title, message, confirmText, cancelText });
  } finally {
    openModals.forEach((m) => {
      try {
        // The component may have unmounted (e.g. logout) while asking.
        if (document.contains(m._element)) m.show();
      } catch {
        /* noop */
      }
    });
    modalSyncState.suspended = false;
  }
  return result;
}
