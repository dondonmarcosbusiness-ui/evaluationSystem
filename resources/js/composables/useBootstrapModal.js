import { ref, watch, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Modal } from "bootstrap";
import { modalSyncState } from "./modalSync.js";

/**
 * Binds a boolean ref to a Bootstrap 5 Modal instance.
 *
 * Usage:
 *   const showModal = ref(false);
 *   const { modalEl } = useBootstrapModal(showModal);
 *   // template: <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">...
 *
 * Setting showModal.value = true shows the Bootstrap modal (with backdrop,
 * ESC handling, focus trap). Closing via [data-bs-dismiss], backdrop click,
 * or ESC syncs showModal back to false via the hidden.bs.modal event.
 *
 * Options (besides Bootstrap Modal options):
 *   onHidden() — callback fired after the hide transition fully completes
 *   (backdrop removed). Useful for chaining modals: open the next dialog
 *   here so two Bootstrap modals are never open at the same time.
 *
 * IMPORTANT: the modal root element must always be rendered (no v-if on it)
 * so the Modal instance can attach on mount.
 */
export function useBootstrapModal(showRef, options = {}) {
  const { onHidden: onHiddenCallback, ...modalOptions } = options;
  const modalEl = ref(null);
  let instance = null;
  let handleHidden = null;

  function getInstance() {
    if (!instance && modalEl.value) {
      instance = new Modal(modalEl.value, {
        backdrop: true,
        keyboard: true,
        // Focus trap disabled: CustomSelect teleports its dropdown (with a
        // search input) to <body>, outside the modal element. Keeping the
        // trap would yank focus away and break searchable dropdowns.
        focus: false,
        ...modalOptions,
      });
      handleHidden = () => {
        // Skipped while the global confirm dialog has parked this modal;
        // it will be re-shown with Vue state untouched.
        if (!modalSyncState.suspended && showRef.value) showRef.value = false;
        onHiddenCallback?.();
      };
      modalEl.value.addEventListener("hidden.bs.modal", handleHidden);
    }
    return instance;
  }

  function sync(visible) {
    const m = getInstance();
    if (!m) return;
    // Avoid redundant calls that can fight Bootstrap's transition state.
    const isShown = modalEl.value?.classList.contains("show");
    if (visible && !isShown) m.show();
    else if (!visible && isShown) m.hide();
    else if (visible) m.show();
    else m.hide();
  }

  onMounted(() => {
    getInstance();
    if (showRef.value) nextTick(() => sync(true));
  });

  watch(showRef, (visible) => {
    nextTick(() => sync(visible));
  });

  onBeforeUnmount(() => {
    if (modalEl.value && handleHidden) {
      modalEl.value.removeEventListener("hidden.bs.modal", handleHidden);
    }
    try {
      instance?.hide();
    } catch {
      /* noop */
    }
    instance?.dispose();
    instance = null;
  });

  return { modalEl };
}
