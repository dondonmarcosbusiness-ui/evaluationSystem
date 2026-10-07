import { ref, watch, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Offcanvas } from "bootstrap";
import { modalSyncState } from "./modalSync.js";

/**
 * Binds a boolean ref to a Bootstrap 5 Offcanvas (side drawer) instance.
 *
 * Usage:
 *   const showDrawer = ref(false);
 *   const { drawerEl } = useBootstrapDrawer(showDrawer);
 *   // template: <div ref="drawerEl" class="offcanvas offcanvas-end" tabindex="-1" aria-hidden="true">...
 *
 * Setting showDrawer.value = true slides the drawer in. Closing via
 * [data-bs-dismiss], backdrop click, or ESC syncs showDrawer back to false
 * via the hidden.bs.offcanvas event.
 *
 * Options (besides Bootstrap Offcanvas options):
 *   onHidden() — callback fired after the hide transition fully completes.
 *
 * IMPORTANT: the drawer root element must always be rendered (no v-if on it)
 * so the Offcanvas instance can attach on mount.
 */
export function useBootstrapDrawer(showRef, options = {}) {
  const { onHidden: onHiddenCallback, ...drawerOptions } = options;
  const drawerEl = ref(null);
  let instance = null;
  let handleHidden = null;

  function getInstance() {
    if (!instance && drawerEl.value) {
      instance = new Offcanvas(drawerEl.value, {
        backdrop: true,
        keyboard: true,
        scroll: false,
        // Focus trap disabled for the same reason as useBootstrapModal:
        // CustomSelect teleports its dropdown to <body>, outside the drawer.
        focus: false,
        ...drawerOptions,
      });
      handleHidden = () => {
        // Skipped while the global confirm dialog has parked this drawer;
        // it will be re-shown with Vue state untouched.
        if (!modalSyncState.suspended && showRef.value) showRef.value = false;
        onHiddenCallback?.();
      };
      drawerEl.value.addEventListener("hidden.bs.offcanvas", handleHidden);
    }
    return instance;
  }

  function sync(visible) {
    const d = getInstance();
    if (!d) return;
    const isShown = drawerEl.value?.classList.contains("show");
    if (visible && !isShown) d.show();
    else if (!visible && isShown) d.hide();
    else if (visible) d.show();
    else d.hide();
  }

  onMounted(() => {
    getInstance();
    if (showRef.value) nextTick(() => sync(true));
  });

  watch(showRef, (visible) => {
    nextTick(() => sync(visible));
  });

  onBeforeUnmount(() => {
    if (drawerEl.value && handleHidden) {
      drawerEl.value.removeEventListener("hidden.bs.offcanvas", handleHidden);
    }
    try {
      instance?.hide();
    } catch {
      /* noop */
    }
    instance?.dispose();
    instance = null;
  });

  return { drawerEl };
}
