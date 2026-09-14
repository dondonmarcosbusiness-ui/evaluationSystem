<template>
  <div ref="modalEl" class="modal fade confirm-dialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content confirm-content">
        <div class="modal-body confirm-body">
          <h5 class="confirm-title">{{ state.title }}</h5>
          <p v-if="state.message" class="confirm-message"><template v-for="(seg, i) in messageSegments" :key="i"><span v-if="seg.danger" class="confirm-danger-word">{{ seg.text }}</span><template v-else>{{ seg.text }}</template></template></p>
        </div>
        <div class="modal-footer confirm-footer">
          <button type="button" class="confirm-btn confirm-yes" @click="choose(true)">
            {{ state.confirmText }}
          </button>
          <button type="button" class="confirm-btn confirm-no" @click="choose(false)">
            {{ state.cancelText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import { Modal } from "bootstrap";
import { registerConfirmOpener } from "../composables/useConfirm.js";

const modalEl = ref(null);
let modal = null;
let settled = true;
let resolver = null;

const state = reactive({
  title: "Are you sure?",
  message: "",
  confirmText: "Yes",
  cancelText: "No thanks",
});

// Highlights the word "Delete" in red wherever it appears in the message.
// Split into plain-text segments (no v-html) so caller messages containing
// user data (e.g. names) can never inject markup.
const messageSegments = computed(() =>
  state.message.split(/(\bdelete\b)/gi).map((text) => ({
    text,
    danger: /^\bdelete\b$/i.test(text),
  })),
);

function choose(value) {
  if (settled) return;
  settled = true;
  const done = resolver;
  resolver = null;
  try {
    modal?.hide();
  } catch {
    /* noop */
  }
  done?.({ isConfirmed: value });
}

function onHidden() {
  // Backdrop click / ESC dismisses without a choice → counts as cancel.
  if (!settled) {
    settled = true;
    const done = resolver;
    resolver = null;
    done?.({ isConfirmed: false });
  }
}

onMounted(() => {
  modal = new Modal(modalEl.value);
  modalEl.value.addEventListener("hidden.bs.modal", onHidden);
  registerConfirmOpener((opts) => {
    state.title = opts.title ?? "Are you sure?";
    state.message = opts.message ?? "";
    state.confirmText = opts.confirmText ?? "Yes";
    state.cancelText = opts.cancelText ?? "No thanks";
    settled = false;
    return new Promise((resolve) => {
      resolver = resolve;
      modal?.show();
    });
  });
});

onBeforeUnmount(() => {
  modalEl.value?.removeEventListener("hidden.bs.modal", onHidden);
  modal?.dispose();
  modal = null;
  registerConfirmOpener(null);
});
</script>

<style scoped>
.confirm-content {
  border-radius: 1rem !important;
  overflow: hidden;
}
.confirm-body {
  text-align: center;
  padding: 1.5rem !important;
}
.confirm-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-dark);
  margin: 0 0 0.5rem;
}
.confirm-message {
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--text-muted);
  margin: 0;
  overflow-wrap: break-word;
}
.confirm-danger-word {
  color: #dc2626;
  font-weight: 700;
}
[data-theme="dark"] .confirm-danger-word {
  color: #ff7b72;
}
.confirm-dialog .modal-footer.confirm-footer {
  display: flex;
  align-items: stretch;
  padding: 0 !important;
  border-top: 1px solid var(--border-light);
}

.confirm-dialog .modal-footer.confirm-footer > * {
  margin: 0 !important;
}
.confirm-btn {
  flex: 1 1 0%;
  min-width: 0;
  border: 0;
  background: transparent;
  padding: 0.85rem 0.5rem;
  font-size: 0.95rem;
  color: var(--primary);
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.confirm-btn:hover {
  background: var(--bg-light);
}
.confirm-yes {
  font-weight: 800;
  border-right: 1px solid var(--border-light);
}
.confirm-no {
  font-weight: 500;
}

/* Dark mode: brand navy text becomes light blue for visibility */
[data-theme="dark"] .confirm-btn {
  color: #79c0ff !important;
}
[data-theme="dark"] .confirm-btn:hover {
  background: var(--bg-light) !important;
  color: #ffffff !important;
}
</style>
