<template>
  <Teleport to="body">
    <transition name="rbac-fade">
      <div v-if="show" class="rbac-root" @keydown.esc.prevent="requestClose">
        <div class="rbac-backdrop" @click="requestClose"></div>

        <aside
          ref="panelEl"
          class="rbac-panel"
          role="dialog"
          aria-modal="true"
          aria-label="Manage User Permissions"
          tabindex="-1"
        >
          <!-- Header -->
          <div class="rbac-header">
            <div class="d-flex align-items-start gap-3">
              <div class="rbac-avatar">
                <i class="fas fa-user-shield"></i>
              </div>
              <div class="rbac-user">
                <h5 class="mb-0 fw-800 text-primary">Manage User Permissions</h5>
                <p class="text-muted small mb-0 text-truncate">{{ user?.name }} ({{ user?.email }})</p>
              </div>
            </div>
            <button type="button" class="rbac-close" aria-label="Close" @click="requestClose">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="rbac-body">
            <div v-if="loading" class="py-3">
              <SkeletonLoader variant="list" :rows="6" />
            </div>

            <div v-else>
              <div class="alert alert-info border-0 shadow-sm rounded-3 mb-3">
                <div class="d-flex gap-3 align-items-center">
                  <i class="fas fa-info-circle fa-lg"></i>
                  <div>
                    <h6 class="fw-bold mb-1">Direct Permission Assignment</h6>
                    <p class="small mb-0 opacity-75">
                      Permissions are grouped by section. Check a section to grant everything inside it, or expand it to
                      pick individual permissions.
                    </p>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="perm-pill perm-pill-success">
                  <i class="perm-pill-dot fas fa-circle" aria-hidden="true"></i>
                  {{ selectedPermissions.length }} direct
                </span>
                <span class="perm-pill perm-pill-neutral">
                  <i class="perm-pill-dot fas fa-circle" aria-hidden="true"></i>
                  {{ inheritedPermissions.length }} inherited via role
                </span>
                <span v-if="dirty" class="perm-pill perm-pill-warning">
                  <i class="perm-pill-dot fas fa-circle" aria-hidden="true"></i>
                  Unsaved changes
                </span>
              </div>

              <div v-if="!permissionGroups.length" class="text-muted small py-3 text-center">
                No permissions available.
              </div>

              <!-- ── Section tree ── -->
              <div
                v-for="group in permissionGroups"
                :key="group.key"
                class="perm-section rounded-3 mb-2 overflow-hidden"
              >
                <!-- Section header (collapse + tri-state parent) -->
                <div
                  class="perm-section-header d-flex align-items-center gap-2 px-3 py-2"
                  @click="toggleGroup(group.key)"
                >
                  <i
                    class="fas fa-chevron-right perm-chevron transition-all"
                    :style="{ transform: expanded[group.key] ? 'rotate(90deg)' : 'rotate(0deg)' }"
                  ></i>
                  <input
                    type="checkbox"
                    class="form-check-input flex-shrink-0 m-0"
                    :checked="parentChecked(group)"
                    :disabled="saving || !group.scope.length"
                    :ref="(el) => setParentState(el, group)"
                    @click.stop
                    @change="toggleParent(group)"
                  />
                  <span class="fw-semibold small text-uppercase perm-section-title">{{ group.label }}</span>
                  <span v-if="group.scope.length" class="badge bg-secondary bg-opacity-10 text-secondary ms-auto">
                    {{ selectedCount(group) }} / {{ group.scope.length }}
                  </span>
                  <span v-else class="badge bg-secondary ms-auto" style="font-size: 0.65rem">not for students</span>
                </div>

                <!-- Children -->
                <div v-show="expanded[group.key]" class="perm-tree">
                  <div
                    v-for="perm in group.permissions"
                    :key="perm.id"
                    class="perm-tree-row d-flex align-items-center gap-2 px-3 py-2 transition-all"
                    :class="[
                      inheritedPermissions.includes(perm.name)
                        ? 'perm-row-inherited'
                        : selectedPermissions.includes(perm.name)
                          ? 'perm-row-selected'
                          : permBlockedForUser(perm.name)
                            ? 'perm-row-blocked'
                            : '',
                    ]"
                    :style="rowStyle(perm)"
                    @click="onRowClick(perm)"
                  >
                    <input
                      class="form-check-input flex-shrink-0 m-0"
                      type="checkbox"
                      :id="'up-' + perm.id"
                      :value="perm.name"
                      :checked="isSelected(perm.name)"
                      :disabled="rowDisabled(perm)"
                      @change="onRowChange(perm)"
                      @click.stop
                    />
                    <label
                      class="form-check-label small fw-semibold mb-0 flex-grow-1"
                      :for="'up-' + perm.id"
                      :style="{ cursor: rowDisabled(perm) ? 'not-allowed' : 'pointer' }"
                      @click.stop
                    >
                      {{ formatPerm(perm.name) }}
                      <code class="ms-1 text-muted fw-normal" style="font-size: 0.7rem">{{ perm.name }}</code>
                    </label>
                    <span
                      v-if="inheritedPermissions.includes(perm.name)"
                      class="badge bg-success flex-shrink-0"
                      style="font-size: 0.65rem"
                    >
                      Inherited via Role
                    </span>
                    <span
                      v-else-if="permBlockedForUser(perm.name)"
                      class="badge bg-secondary flex-shrink-0"
                      style="font-size: 0.65rem"
                    >
                      Not for students
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="rbac-footer">
            <button type="button" class="btn btn-light px-4 rounded-pill" :disabled="saving" @click="requestClose">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary px-4 rounded-pill d-flex align-items-center gap-2"
              @click="save"
              :disabled="saving || loading || !dirty"
            >
              <span v-if="saving">
                <i class="fas fa-spinner fa-spin"></i>
                Saving...
              </span>
              <span v-else>
                <i class="fas fa-check"></i>
                Save Changes
              </span>
            </button>
          </div>
        </aside>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from "vue";
import SkeletonLoader from "./SkeletonLoader.vue";
import api from "../services/api";
import { confirmAction } from "../composables/useConfirm.js";
import { notifySuccess, notifyError } from "../composables/useSnackbar.js";

const props = defineProps({
  show: Boolean,
  user: Object,
});

const emit = defineEmits(["close", "updated"]);

const panelEl = ref(null);

const loading = ref(false);
const saving = ref(false);
const allPermissions = ref([]);
const modules = ref({});
const selectedPermissions = ref([]);
const inheritedPermissions = ref([]);
const studentAllowlist = ref([]);
const expanded = ref({});
const baseline = ref("");

let scrollLocked = false;

// Class-based lock (see app.css body.drawer-open): Bootstrap modals set/clear
// body overflow inline, which would otherwise wipe an inline lock while a
// confirm dialog is shown over the drawer.
function lockScroll() {
  if (scrollLocked) return;
  scrollLocked = true;
  document.body.classList.add("drawer-open");
}

function unlockScroll() {
  if (!scrollLocked) return;
  scrollLocked = false;
  document.body.classList.remove("drawer-open");
}

watch(
  () => props.show,
  async (newVal) => {
    if (newVal) {
      expanded.value = {};
      lockScroll();
      await nextTick();
      panelEl.value?.focus();
      fetchUserData();
    } else {
      unlockScroll();
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  unlockScroll();
});

async function fetchUserData() {
  loading.value = true;
  try {
    const [permsRes, userDetailsRes] = await Promise.all([
      api.get("/permissions"),
      api.get(`/users/${props.user?.id || 0}/rbac-details`),
    ]);

    allPermissions.value = permsRes.data.permissions || [];
    modules.value = permsRes.data.modules || {};
    studentAllowlist.value = permsRes.data.student_allowlist || [];
    selectedPermissions.value = userDetailsRes.data.direct_permissions;
    inheritedPermissions.value = userDetailsRes.data.permissions.filter(
      (p) => !userDetailsRes.data.direct_permissions.includes(p),
    );
    baseline.value = snapshot();
  } catch (error) {
    notifyError("We couldn't load this user's access details.", { title: "Loading failed" });
  } finally {
    loading.value = false;
  }
}

// ── Dirty tracking ────────────────────────────────────────────────────────

function snapshot() {
  return [...selectedPermissions.value].sort().join("|");
}

const dirty = computed(() => snapshot() !== baseline.value);

async function requestClose() {
  if (saving.value) return;

  if (dirty.value) {
    const result = await confirmAction({
      title: "Discard changes?",
      message: "You have unsaved permission changes. Close without saving?",
      confirmText: "Discard",
      cancelText: "Keep editing",
    });
    if (!result.isConfirmed) return;
  }

  emit("close");
}

// ── Section tree ──────────────────────────────────────────────────────────

const permissionGroups = computed(() => {
  const byModule = {};
  for (const perm of allPermissions.value) {
    const key = perm.module || "custom";
    if (!byModule[key]) byModule[key] = [];
    byModule[key].push(perm);
  }

  const groups = [];
  for (const key of Object.keys(modules.value)) {
    if (byModule[key]) {
      groups.push({ key, label: modules.value[key] || key, permissions: byModule[key] });
      delete byModule[key];
    }
  }
  for (const key of Object.keys(byModule)) {
    groups.push({ key, label: key === "custom" ? "Custom" : key, permissions: byModule[key] });
  }

  // Scope = permissions the parent checkbox may actually toggle
  // (blocked student permissions are excluded; they stay individually readable).
  return groups
    .map((g) => ({ ...g, scope: g.permissions.filter((p) => !permBlockedForUser(p.name)) }))
    .filter((g) => g.permissions.length > 0);
});

function toggleGroup(key) {
  expanded.value = { ...expanded.value, [key]: !expanded.value[key] };
}

function isSelected(permName) {
  return selectedPermissions.value.includes(permName) || inheritedPermissions.value.includes(permName);
}

function selectedCount(group) {
  return group.scope.filter((p) => isSelected(p.name)).length;
}

function parentChecked(group) {
  return group.scope.length > 0 && group.scope.every((p) => isSelected(p.name));
}

function parentIndeterminate(group) {
  if (!group.scope.length) return false;
  const count = selectedCount(group);
  return count > 0 && count < group.scope.length;
}

// Vue doesn't reliably patch the `indeterminate` DOM property, so set it via
// the ref callback — function refs re-run on every re-render.
function setParentState(el, group) {
  if (!el) return;
  el.indeterminate = parentIndeterminate(group);
}

function toggleParent(group) {
  const scopeNames = new Set(group.scope.map((p) => p.name));

  if (parentChecked(group)) {
    // Uncheck everything the parent controls (inherited stays via role).
    selectedPermissions.value = selectedPermissions.value.filter((n) => !scopeNames.has(n));
    return;
  }

  // Indeterminate or empty → select every remaining child in the section.
  for (const perm of group.scope) {
    if (inheritedPermissions.value.includes(perm.name)) continue;
    if (!selectedPermissions.value.includes(perm.name)) {
      selectedPermissions.value.push(perm.name);
    }
  }
}

// ── Leaf rows ─────────────────────────────────────────────────────────────

// Students may only receive evaluation permissions — server rejects the rest
// (config/authorization.php student_permission_allowlist). Existing selections
// stay editable so an admin can revoke a non-allowlisted grant.
function permBlockedForUser(permName) {
  return props.user?.role === "student" && !studentAllowlist.value.includes(permName);
}

function rowDisabled(perm) {
  return (
    inheritedPermissions.value.includes(perm.name) ||
    (permBlockedForUser(perm.name) && !selectedPermissions.value.includes(perm.name))
  );
}

function rowStyle(perm) {
  if (rowDisabled(perm) && !isSelected(perm.name)) return { opacity: 0.65 };
  return {};
}

function onRowClick(perm) {
  if (rowDisabled(perm)) return;
  togglePermission(perm.name);
}

function onRowChange(perm) {
  if (rowDisabled(perm)) return;
  togglePermission(perm.name);
}

function togglePermission(permName) {
  const index = selectedPermissions.value.indexOf(permName);
  if (index > -1) {
    selectedPermissions.value.splice(index, 1);
  } else {
    if (permBlockedForUser(permName)) return;
    selectedPermissions.value.push(permName);
  }
}

async function save() {
  saving.value = true;
  try {
    await api.post(`/users/${props.user?.id || 0}/permissions`, {
      permissions: selectedPermissions.value,
    });

    baseline.value = snapshot();
    notifySuccess("Your permission changes are now live.", { title: "Access updated" });
    emit("updated");
    emit("close");
  } catch (error) {
    notifyError("Your changes were not saved. Please try again.", { title: "Update failed" });
  } finally {
    saving.value = false;
  }
}

function formatPerm(name) {
  return name.replace(/[._]/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}
</script>

<style scoped>
.rbac-root {
  position: fixed;
  inset: 0;
  z-index: 1060;
}

.rbac-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
}

.rbac-panel {
  position: absolute;
  top: 0;
  right: 0;
  height: 100%;
  width: min(660px, 100vw);
  display: flex;
  flex-direction: column;
  background: var(--bg-card, #fff);
  color: var(--text-dark, #1f2328);
  box-shadow: -12px 0 40px rgba(0, 0, 0, 0.18);
  outline: none;
}

.rbac-user {
  min-width: 0;
}

.rbac-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.15rem 1.35rem;
  border-bottom: 1px solid var(--border-color, #d0d7de);
  background: var(--bg-card, #fff);
  flex-shrink: 0;
}

.rbac-avatar {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.12);
  color: var(--bs-primary, #0d6efd);
}

.rbac-close {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: var(--text-muted, #59636e);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s ease;
}
.rbac-close:hover {
  background: var(--bg-light, #eef0f3);
  color: var(--text-dark, #1f2328);
}

.rbac-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 1.25rem 1.35rem;
}

.rbac-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.35rem;
  border-top: 1px solid var(--border-color, #d0d7de);
  background: var(--bg-card, #fff);
  flex-shrink: 0;
}

/* ── Slide transitions ── */
.rbac-fade-enter-active,
.rbac-fade-leave-active {
  transition: opacity 0.25s ease;
}
.rbac-fade-enter-active .rbac-panel,
.rbac-fade-leave-active .rbac-panel {
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
.rbac-fade-enter-from,
.rbac-fade-leave-to {
  opacity: 0;
}
.rbac-fade-enter-from .rbac-panel,
.rbac-fade-leave-to .rbac-panel {
  transform: translateX(100%);
}

.transition-all {
  transition: all 0.2s ease-in-out;
}

.perm-section {
  border: 1px solid var(--border-color, #d0d7de);
}

/* Summary chips — soft tinted pills matching the app badge language */
.perm-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.3rem 0.7rem;
  border-radius: 50px;
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1.2;
  white-space: nowrap;
}
.perm-pill-dot {
  font-size: 0.4rem;
}
.perm-pill-success {
  background: var(--badge-success-bg, #dafbe1);
  color: var(--badge-success-text, #1a7f37);
}
.perm-pill-neutral {
  background: var(--bg-light, #eef0f3);
  color: var(--text-muted, #59636e);
}
.perm-pill-warning {
  background: var(--badge-warning-bg, #fff8c5);
  color: var(--badge-warning-text, #9a6700);
}
.perm-pill-warning .perm-pill-dot {
  animation: perm-pill-pulse 1.4s ease-in-out infinite;
}

@keyframes perm-pill-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}

.perm-section-header {
  background: var(--bg-card, #fff);
  cursor: pointer;
  user-select: none;
}
.perm-section-header:hover {
  background: var(--bg-light, #eef0f3);
}
.perm-chevron {
  font-size: 0.65rem;
  color: var(--text-muted, #59636e);
}
.perm-section-title {
  letter-spacing: 0.02em;
}

/* Tree: vertical spine + horizontal connectors per row */
.perm-tree {
  position: relative;
  padding-left: 2rem;
  border-top: 1px solid var(--border-color, #d0d7de);
}
.perm-tree::before {
  content: "";
  position: absolute;
  left: 1.05rem;
  top: 0;
  bottom: 0.9rem;
  width: 1px;
  background: var(--border-color, #d0d7de);
}
.perm-tree-row {
  position: relative;
  cursor: pointer;
}
.perm-tree-row::before {
  content: "";
  position: absolute;
  left: -0.95rem;
  top: 50%;
  width: 0.75rem;
  height: 1px;
  background: var(--border-color, #d0d7de);
}
.perm-tree-row:last-child::after {
  content: "";
  position: absolute;
  left: -1.05rem;
  top: 0;
  bottom: 0;
  width: 1px;
  background: var(--bg-card, #fff);
}
.perm-tree-row:hover {
  background: var(--bg-light, #eef0f3);
}
.perm-row-selected {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.07);
}
.perm-row-selected:hover {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.12);
}
.perm-row-inherited {
  background: rgba(var(--bs-success-rgb, 25, 135, 84), 0.08);
  cursor: not-allowed;
}
.perm-row-blocked {
  background: var(--bg-light, #eef0f3);
  cursor: not-allowed;
}
</style>
