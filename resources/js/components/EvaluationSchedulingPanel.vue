<template>
  <section class="card sched-card">
    <!-- Header: title + live status + action -->
    <header class="sched-head">
      <div class="icon-box bg-warning-soft rounded-3">
        <i class="fas fa-calendar-check text-warning"></i>
      </div>
      <div class="sched-head-main">
        <div class="sched-title-row">
          <h5 class="mb-0 fw-bold">Evaluation Scheduling</h5>
          <span class="status-pill" :class="globalStatus === 'open' ? 'is-live' : 'is-off'">
            <span class="pill-dot"></span>
            {{ globalStatus === "open" ? "Live" : "Offline" }}
          </span>
          <span class="mini-pill count">{{ openCount }}/{{ schedules.length }} open</span>
          <span v-if="legacyMode" class="mini-pill legacy"><i class="fas fa-bolt"></i> No schedule</span>
        </div>
        <p class="text-muted small mb-0 sched-sub">
          Institution-wide default plus per-department overrides — changes apply immediately.
        </p>
      </div>
      <button class="btn btn-primary-glass rounded-pill px-4 text-nowrap" :disabled="!canAdd" @click="openAddModal">
        <i class="fas fa-plus-circle me-2"></i>
        Add Schedule
      </button>
    </header>

    <div class="sched-body">
      <!-- Legacy fallback (inline, slim) -->
      <div v-if="legacyMode" class="sched-legacy">
        <i class="fas fa-triangle-exclamation"></i>
        <span>
          <strong>No schedules yet — evaluation is closed for every department.</strong>
          Create the All departments default to open a window.
        </span>
        <button class="btn btn-sm btn-primary-glass rounded-pill px-3 text-nowrap" @click="openAddModal">
          <i class="fas fa-plus me-1"></i> Create default
        </button>
      </div>

      <div v-if="successMsg" class="alert alert-success border-0 bg-success bg-opacity-10 text-success small rounded-4">
        <i class="fas fa-check-circle me-2"></i>{{ successMsg }}
      </div>
      <div v-if="errorMsg" class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small rounded-4">
        <i class="fas fa-exclamation-circle me-2"></i>{{ errorMsg }}
      </div>

      <div v-if="loading">
        <SkeletonLoader variant="table" :rows="4" :cols="5" />
      </div>

      <div v-else-if="!schedules.length" class="empty-state">
        <div class="empty-orb">
          <i class="fas fa-calendar-plus"></i>
        </div>
        <h6 class="fw-800 mb-1">No evaluation windows yet</h6>
        <p class="text-muted small mb-3">
          Start with the institution-wide default, then override individual departments as needed.
        </p>
        <button class="btn btn-primary-premium rounded-pill px-4" @click="openAddModal">
          <i class="fas fa-plus me-2"></i>
          Create default schedule
        </button>
      </div>

      <div v-else class="table-responsive">
        <table class="schedule-table">
          <thead>
            <tr>
              <th>Scope</th>
              <th>Window</th>
              <th>Mode</th>
              <th>Effective now</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in schedules" :key="row.id" :class="{ 'global-row': row.is_global }">
              <td>
                <span v-if="row.is_global" class="scope-chip global">
                  <span class="scope-ico"><i class="fas fa-globe"></i></span>
                  All departments
                  <span class="default-tag">Default</span>
                </span>
                <span v-else class="scope-chip">
                  <span class="scope-ico"><i class="fas fa-building"></i></span>
                  {{ row.department }}
                </span>
              </td>
              <td class="window-cell">
                <i class="far fa-clock"></i>
                <span>{{ formatWindow(row) }}</span>
              </td>
              <td>
                <span class="mode-chip" :class="'mode-' + row.status">
                  <i :class="modeIcon(row.status)"></i>
                  {{ modeLabel(row.status) }}
                </span>
              </td>
              <td>
                <span class="eff-chip" :class="row.effective_status === 'open' ? 'is-open' : 'is-closed'">
                  <span class="eff-dot"></span>
                  {{ row.effective_status === "open" ? "Open" : "Closed" }}
                </span>
              </td>
              <td class="text-end text-nowrap">
                <button class="btn-icon-soft" title="Edit schedule" @click="openEditModal(row)">
                  <i class="fas fa-pen"></i>
                </button>
                <button class="btn-icon-soft danger ms-1" title="Delete schedule" @click="removeSchedule(row)">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="tip-box mt-4">
        <span class="tip-ico"><i class="fas fa-lightbulb"></i></span>
        <div class="small">
          <span class="d-block fw-bold mb-1">How gating works</span>
          <span class="text-muted">
            Windows are checked live on every request — students can only see and submit evaluations for departments
            whose window is open right now. Mode <strong>Auto</strong> follows the dates; <strong>Open</strong> /
            <strong>Closed</strong> force the window regardless of the dates. Students are emailed automatically when a
            window opens or closes.
          </span>
        </div>
      </div>
    </div>

    <!-- Add / Edit side drawer (always rendered so Bootstrap can attach) -->
    <div ref="drawerEl" class="offcanvas offcanvas-end sched-drawer" tabindex="-1" aria-hidden="true">
      <div class="offcanvas-header">
        <div>
          <h5 class="offcanvas-title fw-800">
            <i class="fas fa-calendar-plus me-2 text-primary"></i>
            {{ form.id ? "Edit Schedule" : "Add Schedule" }}
          </h5>
          <p class="text-muted small mb-0 mt-1">
            {{ form.id ? "Adjust the window, mode, or dates for this scope." : "Choose which scope this window applies to." }}
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>

      <div class="offcanvas-body">
        <div v-if="formError" class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small rounded-4">
          {{ formError }}
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold small text-uppercase ls-1">Scope</label>
          <CustomSelect
            v-model="form.department"
            :options="scopeOptions"
            placeholder="Select scope"
            :disabled="!!form.id"
          />
          <div class="form-text">A department without its own schedule follows the default (All departments).</div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold small text-uppercase ls-1">Starts at</label>
            <input v-model="form.starts_at" type="datetime-local" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold small text-uppercase ls-1">Ends at</label>
            <input v-model="form.ends_at" type="datetime-local" class="form-control" />
          </div>
          <div class="col-12">
            <span class="text-muted small">Leave a date empty for an open-ended boundary.</span>
          </div>
        </div>

        <label class="form-label fw-bold small text-uppercase ls-1">Mode</label>
        <div class="mode-segment" role="radiogroup" aria-label="Window mode">
          <button
            v-for="option in modeOptions"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="form.status === option.value"
            class="mode-btn"
            :class="['mode-' + option.value, { active: form.status === option.value }]"
            @click="form.status = option.value"
          >
            <span class="mode-ico"><i :class="option.icon"></i></span>
            <span class="mode-label">{{ option.label }}</span>
          </button>
        </div>
        <div class="mode-hint" :class="'hint-' + form.status">
          <i class="fas fa-circle-info"></i>
          <span>{{ modeHint }}</span>
        </div>
      </div>

      <div class="sched-drawer-footer">
        <button type="button" class="btn btn-light-premium flex-fill" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="button" class="btn btn-primary-premium flex-fill" :disabled="saving" @click="saveSchedule">
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status"></span>
          {{ saving ? "Saving..." : form.id ? "Save Changes" : "Create Schedule" }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import CustomSelect from "./CustomSelect.vue";
import SkeletonLoader from "./SkeletonLoader.vue";
import { useBootstrapDrawer } from "../composables/useBootstrapDrawer.js";
import { confirmAction } from "../composables/useConfirm.js";
import api from "../services/api.js";
import { format, parseISO } from "date-fns";

const loading = ref(true);
const saving = ref(false);
const schedules = ref([]);
const departments = ref([]);
const globalStatus = ref("closed");
const legacyMode = ref(false);
const successMsg = ref("");
const errorMsg = ref("");

const showDrawer = ref(false);
const { drawerEl } = useBootstrapDrawer(showDrawer);

const form = ref({ id: null, department: "", starts_at: "", ends_at: "", status: "scheduled" });
const formError = ref("");

const modeOptions = [
  { value: "scheduled", label: "Auto", icon: "fas fa-clock" },
  { value: "open", label: "Open", icon: "fas fa-lock-open" },
  { value: "closed", label: "Closed", icon: "fas fa-lock" },
];

const modeHints = {
  scheduled: "Opens and closes automatically at the start and end dates below.",
  open: "Forced open right now — the dates are ignored until you switch back to Auto.",
  closed: "Forced closed right now — the dates are ignored until you switch back to Auto.",
};

const modeHint = computed(() => modeHints[form.value.status] || modeHints.scheduled);

const globalRow = computed(() => schedules.value.find((row) => row.is_global) || null);
const departmentRows = computed(() => schedules.value.filter((row) => !row.is_global));
const openCount = computed(() => schedules.value.filter((row) => row.effective_status === "open").length);

const availableDepartments = computed(() => {
  const scheduled = new Set(departmentRows.value.map((row) => row.department));
  return departments.value.filter((department) => !scheduled.has(department));
});

const canAdd = computed(() => !globalRow.value || availableDepartments.value.length > 0);

const scopeOptions = computed(() => {
  const options = [];
  if (!globalRow.value || form.value.id === globalRow.value?.id) {
    options.push({ label: "All departments (default)", value: "" });
  }
  const current = form.value.department;
  const pool = availableDepartments.value.includes(current) || !current
    ? availableDepartments.value
    : [...availableDepartments.value, current];
  for (const department of pool) {
    options.push({ label: department, value: department });
  }
  return options;
});

onMounted(() => fetchSchedules());

async function fetchSchedules(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await api.get("/evaluation-schedules");
    schedules.value = res.data.schedules || [];
    departments.value = res.data.departments || [];
    globalStatus.value = res.data.global_status || "closed";
    legacyMode.value = !!res.data.legacy_mode;
  } catch (e) {
    errorMsg.value = extractError(e, "Failed to load evaluation schedules.");
  } finally {
    if (!silent) loading.value = false;
  }
}

function openAddModal() {
  formError.value = "";
  form.value = {
    id: null,
    department: globalRow.value ? availableDepartments.value[0] || "" : "",
    starts_at: "",
    ends_at: "",
    status: "scheduled",
  };
  showDrawer.value = true;
}

function openEditModal(row) {
  formError.value = "";
  form.value = {
    id: row.id,
    department: row.department || "",
    starts_at: row.starts_at || "",
    ends_at: row.ends_at || "",
    status: row.status,
  };
  showDrawer.value = true;
}

function validateForm() {
  if (form.value.starts_at && form.value.ends_at && new Date(form.value.ends_at) <= new Date(form.value.starts_at)) {
    return "The end date must be after the start date.";
  }
  if (form.value.status === "scheduled" && !form.value.starts_at && !form.value.ends_at) {
    return "A scheduled window needs a start and/or end date. Choose Open or Closed for a manual window.";
  }
  return "";
}

async function saveSchedule() {
  formError.value = validateForm();
  if (formError.value) return;

  saving.value = true;
  try {
    const payload = {
      department: form.value.department || null,
      starts_at: form.value.starts_at || null,
      ends_at: form.value.ends_at || null,
      status: form.value.status,
    };
    const res = form.value.id
      ? await api.put(`/evaluation-schedules/${form.value.id}`, payload)
      : await api.post("/evaluation-schedules", payload);

    globalStatus.value = res.data.global_status || globalStatus.value;
    showDrawer.value = false;
    flashSuccess(res.data.message || "Schedule saved.");
    await fetchSchedules(true);
  } catch (e) {
    formError.value = extractError(e, "Failed to save the schedule.");
  } finally {
    saving.value = false;
  }
}

async function removeSchedule(row) {
  const scope = row.is_global ? "the All departments default" : row.department;
  const result = await confirmAction({
    title: "Delete schedule?",
    message: row.is_global
      ? "Deleting the default closes every department that relies on it (unless it has its own schedule). Continue?"
      : `${row.department} will follow the default schedule instead. Continue?`,
    confirmText: "Delete",
  });
  if (!result.isConfirmed) return;

  try {
    const res = await api.delete(`/evaluation-schedules/${row.id}`);
    globalStatus.value = res.data.global_status || globalStatus.value;
    flashSuccess(res.data.message || `Schedule for ${scope} deleted.`);
    await fetchSchedules(true);
  } catch (e) {
    errorMsg.value = extractError(e, "Failed to delete the schedule.");
  }
}

function formatWindow(row) {
  const start = row.starts_at ? format(parseISO(row.starts_at), "MMM d, yyyy · h:mm a") : null;
  const end = row.ends_at ? format(parseISO(row.ends_at), "MMM d, yyyy · h:mm a") : null;
  if (start && end) return `${start} → ${end}`;
  if (start) return `From ${start}`;
  if (end) return `Until ${end}`;
  return "No date limits";
}

function modeLabel(status) {
  return modeOptions.find((option) => option.value === status)?.label || status;
}

function modeIcon(status) {
  return modeOptions.find((option) => option.value === status)?.icon || "fas fa-clock";
}

function flashSuccess(message) {
  successMsg.value = message;
  errorMsg.value = "";
  setTimeout(() => (successMsg.value = ""), 4000);
}

function extractError(e, fallback) {
  const errors = e.response?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
  }
  return e.response?.data?.message || fallback;
}
</script>

<style scoped>
.sched-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

/* Header */
.sched-head {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 1.1rem 1.4rem;
  border-bottom: 1px solid var(--border-light);
}

.sched-head-main {
  flex: 1;
  min-width: 0;
}

.sched-title-row {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.sched-sub {
  margin-top: 0.2rem;
}

.icon-box {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

.bg-warning-soft {
  background-color: rgba(255, 193, 7, 0.12);
}

/* Header pills */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.22rem 0.65rem;
  border-radius: 999px;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.status-pill.is-live {
  background: rgba(14, 159, 110, 0.12);
  color: var(--success);
}

.status-pill.is-off {
  background: rgba(127, 127, 127, 0.14);
  color: var(--text-muted);
}

.pill-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

.status-pill.is-live .pill-dot {
  animation: pill-pulse 2s infinite;
}

@keyframes pill-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(14, 159, 110, 0.55);
  }
  70% {
    box-shadow: 0 0 0 6px rgba(14, 159, 110, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(14, 159, 110, 0);
  }
}

.mini-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.22rem 0.6rem;
  border-radius: 999px;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.03em;
}

.mini-pill.count {
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
}

.mini-pill.legacy {
  background: rgba(255, 193, 7, 0.16);
  color: #b7791f;
  border: 1px solid rgba(255, 193, 7, 0.45);
}

/* Body */
.sched-body {
  padding: 1.4rem;
}

/* Legacy fallback (inline) */
.sched-legacy {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 0.65rem 0.9rem;
  margin-bottom: 1rem;
  border: 1px solid rgba(255, 193, 7, 0.45);
  border-left: 3px solid var(--warning);
  border-radius: var(--radius-md);
  background: rgba(255, 193, 7, 0.1);
  font-size: 0.8rem;
  line-height: 1.5;
}

.sched-legacy > i {
  color: #b7791f;
}

.sched-legacy > span {
  flex: 1;
  min-width: 220px;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 2.25rem 1rem 2rem;
}

.empty-orb {
  width: 64px;
  height: 64px;
  margin: 0 auto 0.9rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--primary);
  background: rgba(25, 25, 112, 0.07);
  border: 1px dashed rgba(25, 25, 112, 0.35);
}

/* Table */
.schedule-table {
  width: 100%;
  border-collapse: collapse;
}

.schedule-table th {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  font-weight: 700;
  padding: 0.75rem 0.85rem;
  border-bottom: 1px solid var(--border-light);
}

.schedule-table td {
  padding: 0.9rem 0.85rem;
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}

.schedule-table tr:last-child td {
  border-bottom: 0;
}

.schedule-table tbody tr {
  transition: background 0.15s ease;
}

.schedule-table tbody tr:hover {
  background: var(--bg-light);
}

.schedule-table tr.global-row {
  background: rgba(25, 25, 112, 0.04);
  box-shadow: inset 3px 0 0 var(--primary);
}

.schedule-table tr.global-row:hover {
  background: rgba(25, 25, 112, 0.07);
}

.scope-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  font-weight: 700;
}

.scope-ico {
  width: 26px;
  height: 26px;
  flex-shrink: 0;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-muted);
}

.scope-chip.global {
  color: var(--primary);
}

.scope-chip.global .scope-ico {
  background: rgba(25, 25, 112, 0.1);
  border-color: rgba(25, 25, 112, 0.2);
  color: var(--primary);
}

.default-tag {
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: var(--primary);
  color: #fff;
}

.window-cell i {
  color: var(--text-muted);
  font-size: 0.8rem;
  margin-right: 0.45rem;
}

.window-cell span {
  font-size: 0.82rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.mode-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid var(--border-color);
  background: var(--bg-light);
  white-space: nowrap;
}

.mode-chip.mode-scheduled {
  color: var(--primary);
  border-color: rgba(25, 25, 112, 0.3);
  background: rgba(25, 25, 112, 0.07);
}

.mode-chip.mode-open {
  color: var(--success);
  border-color: rgba(14, 159, 110, 0.35);
  background: rgba(14, 159, 110, 0.08);
}

.mode-chip.mode-closed {
  color: var(--text-muted);
}

.eff-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.eff-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.eff-chip.is-open {
  background: rgba(14, 159, 110, 0.1);
  color: var(--success);
}

.eff-chip.is-open .eff-dot {
  background: var(--success);
}

.eff-chip.is-closed {
  background: rgba(127, 127, 127, 0.12);
  color: var(--text-muted);
}

.eff-chip.is-closed .eff-dot {
  background: var(--text-muted);
  opacity: 0.7;
}

.btn-icon-soft {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: var(--bg-light);
  color: var(--text-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-icon-soft:hover {
  color: var(--primary);
  border-color: var(--primary);
}

.btn-icon-soft.danger:hover {
  color: var(--danger);
  border-color: var(--danger);
}

/* Tip */
.tip-box {
  display: flex;
  gap: 0.85rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-left: 3px solid var(--primary);
  border-radius: var(--radius-md);
  padding: 1rem 1.1rem;
}

.tip-ico {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(25, 25, 112, 0.1);
  color: var(--primary);
  font-size: 0.9rem;
}

/* Side drawer */
.sched-drawer {
  width: min(430px, 100vw);
  background: var(--bg-card);
  color: var(--text-main);
  border-left: 1px solid var(--border-color);
}

.sched-drawer .offcanvas-header {
  padding: 1.25rem 1.4rem 0.75rem;
  border-bottom: 0;
}

.sched-drawer .offcanvas-body {
  padding: 0.5rem 1.4rem 1.25rem;
}

.sched-drawer-footer {
  display: flex;
  gap: 0.75rem;
  padding: 1rem 1.4rem 1.4rem;
  border-top: 1px solid var(--border-light);
}

/* Mode segment (drawer form) */
.mode-segment {
  display: flex;
  gap: 0.3rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 10px;
  padding: 0.3rem;
}

.mode-btn {
  flex: 1;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  border: 1px solid transparent;
  background: transparent;
  border-radius: 8px;
  padding: 0.5rem 0.35rem;
  color: var(--text-muted);
  cursor: pointer;
  transition:
    color 0.15s ease,
    background 0.15s ease,
    box-shadow 0.15s ease,
    border-color 0.15s ease;
}

.mode-btn .mode-ico {
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  font-size: 0.62rem;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    border-color 0.15s ease;
}

.mode-btn .mode-label {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.mode-btn:hover:not(.active) {
  color: var(--text-main);
}

.mode-btn:hover:not(.active) .mode-ico {
  border-color: var(--primary);
  color: var(--primary);
}

.mode-btn.active {
  background: var(--bg-card);
  border-color: var(--border-color, var(--border-light));
  box-shadow: 0 3px 10px rgba(15, 23, 42, 0.1);
}

.mode-btn.mode-auto.active {
  color: var(--primary);
}

.mode-btn.mode-auto.active .mode-ico {
  background: rgba(25, 25, 112, 0.1);
  border-color: transparent;
  color: var(--primary);
}

.mode-btn.mode-open.active {
  color: var(--success);
}

.mode-btn.mode-open.active .mode-ico {
  background: rgba(14, 159, 110, 0.12);
  border-color: transparent;
  color: var(--success);
}

.mode-btn.mode-closed.active {
  color: var(--danger);
}

.mode-btn.mode-closed.active .mode-ico {
  background: rgba(239, 68, 68, 0.12);
  border-color: transparent;
  color: var(--danger);
}

.mode-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin-top: 0.6rem;
  padding: 0.5rem 0.7rem;
  border: 1px dashed var(--border-light);
  border-radius: 8px;
  background: var(--bg-light);
  font-size: 0.75rem;
  line-height: 1.45;
  color: var(--text-muted);
}

.mode-hint i {
  margin-top: 0.15rem;
  font-size: 0.7rem;
  color: var(--primary);
}

.mode-hint.hint-open i {
  color: var(--success);
}

.mode-hint.hint-closed i {
  color: var(--danger);
}

@media (max-width: 767px) {
  .sched-head {
    flex-wrap: wrap;
  }

  .sched-head .btn {
    width: 100%;
  }

  .sched-legacy .btn {
    width: 100%;
  }

  .window-cell span {
    white-space: normal;
  }
}
</style>
