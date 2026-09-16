<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>System Settings</template></Navbar>

      <div class="content-area animate__animated animate__fadeIn">
        <div class="settings-container">
          <!-- Page Header -->
          <div class="page-head mb-4">
            <div class="page-head-icon">
              <i class="fas fa-sliders"></i>
            </div>
            <div>
              <h2 class="fw-800 mb-1">Global Settings</h2>
              <p class="text-muted mb-0">
                Manage the active academic period, session security, and the evaluation cycle for the entire
                institution.
              </p>
            </div>
          </div>

          <div class="row g-4">
            <!-- Left Column: Academic Period -->
            <div class="col-lg-7">
              <section class="card settings-card h-100">
                <header class="settings-card-head">
                  <div class="icon-box bg-primary-soft rounded-3">
                    <i class="fas fa-calendar-alt text-primary"></i>
                  </div>
                  <div>
                    <h5 class="mb-0 fw-bold">Academic Period</h5>
                    <p class="text-muted small mb-0">Active semester and year used across evaluations and reports</p>
                  </div>
                </header>
                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-bold small text-uppercase ls-1">Active Semester</label>
                      <CustomSelect
                        v-model="settings.active_semester"
                        :options="semesterOptions"
                        placeholder="Select Semester"
                      />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold small text-uppercase ls-1">Academic Year</label>
                      <CustomSelect
                        v-model="settings.active_academic_year"
                        :options="yearOptions"
                        placeholder="Select Academic Year"
                      />
                    </div>
                  </div>

                  <!-- Manageable option lists (nothing hardcoded) -->
                  <div class="option-manager mt-4">
                    <div class="option-manager-head">
                      <span class="option-manager-title">
                        <i class="fas fa-list-ul text-primary opacity-50 me-2"></i>
                        Semesters
                      </span>
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ settings.semester_options.length }} defined
                      </span>
                    </div>
                    <div class="chip-wrap">
                      <span v-for="s in settings.semester_options" :key="s" class="option-chip">
                        {{ s }}
                        <button
                          type="button"
                          class="option-remove"
                          title="Archive semester (keeps history reviewable)"
                          @click="archiveSemester(s)"
                        >
                          <i class="fas fa-box-archive"></i>
                        </button>
                      </span>
                      <span v-if="!settings.semester_options.length" class="text-muted small">
                        No semesters defined yet.
                      </span>
                    </div>
                    <div class="add-row">
                      <input
                        v-model="newSemester"
                        class="form-control"
                        placeholder="e.g. Midyear"
                        @keydown.enter.prevent="addSemester"
                      />
                      <button type="button" class="btn btn-outline-primary text-nowrap" @click="addSemester">
                        <i class="fas fa-plus me-1"></i>
                        Add
                      </button>
                    </div>
                  </div>

                  <div class="option-manager mt-3">
                    <div class="option-manager-head">
                      <span class="option-manager-title">
                        <i class="fas fa-calendar text-primary opacity-50 me-2"></i>
                        Academic Years
                      </span>
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ settings.academic_year_options.length }} defined
                      </span>
                    </div>
                    <div class="chip-wrap">
                      <span v-for="y in settings.academic_year_options" :key="y" class="option-chip">
                        {{ y }}
                        <button
                          type="button"
                          class="option-remove"
                          title="Archive academic year (keeps history reviewable)"
                          @click="archiveYear(y)"
                        >
                          <i class="fas fa-box-archive"></i>
                        </button>
                      </span>
                      <span v-if="!settings.academic_year_options.length" class="text-muted small">
                        No academic years defined yet.
                      </span>
                    </div>
                    <div class="add-row">
                      <input
                        v-model="newYear"
                        class="form-control"
                        placeholder="e.g. 2026-2027"
                        @keydown.enter.prevent="addYear"
                      />
                      <button type="button" class="btn btn-outline-primary text-nowrap" @click="addYear">
                        <i class="fas fa-plus me-1"></i>
                        Add
                      </button>
                    </div>
                  </div>

                  <div class="tip-box mt-4">
                    <i class="fas fa-circle-info text-primary"></i>
                    <div class="small">
                      <span class="d-block fw-bold mb-1">Configuration Tip</span>
                      <span class="text-muted">
                        These lists feed every semester and academic-year dropdown in the system. Changes to the
                        academic period will immediately reflect on the student dashboard and evaluation forms.
                        Archiving removes a value from active dropdowns without deleting history — past results
                        stay reviewable on the <router-link to="/archive" class="fw-bold">archive page</router-link>.
                      </span>
                    </div>
                  </div>
                </div>
              </section>
            </div>

            <!-- Right Column: Evaluation Control + Archived Periods -->
            <div class="col-lg-5 d-flex flex-column gap-4">
              <section class="card settings-card">
                <header class="settings-card-head">
                  <div class="icon-box bg-warning-soft rounded-3">
                    <i class="fas fa-power-off text-warning"></i>
                  </div>
                  <div>
                    <h5 class="mb-0 fw-bold">Evaluation Control</h5>
                    <p class="text-muted small mb-0">Availability of forms for active students</p>
                  </div>
                </header>
                <div class="card-body">
                  <div
                    class="status-banner transition-all"
                    :class="settings.evaluation_status === 'open' ? 'status-open' : 'status-closed'"
                  >
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="d-flex align-items-center gap-2">
                        <div v-if="settings.evaluation_status === 'open'" class="pulse-indicator"></div>
                        <span
                          class="fw-800 text-uppercase ls-1 small"
                          :class="settings.evaluation_status === 'open' ? 'text-success' : 'text-muted'"
                        >
                          {{ settings.evaluation_status === "open" ? "Evaluation Live" : "Evaluation Offline" }}
                        </span>
                      </div>
                      <div class="form-check form-switch m-0">
                        <input
                          class="form-check-input premium-switch"
                          type="checkbox"
                          role="switch"
                          id="evalStatusToggle"
                          :checked="settings.evaluation_status === 'open'"
                          @change="settings.evaluation_status = $event.target.checked ? 'open' : 'closed'"
                        />
                      </div>
                    </div>

                    <p class="small mb-0 opacity-75">
                      {{
                        settings.evaluation_status === "open"
                          ? "Evaluation window is currently open. Students can now submit their ratings."
                          : "Evaluation window is closed. Students cannot access evaluation forms at this time."
                      }}
                    </p>
                  </div>

                  <div v-if="settings.evaluation_status === 'open'" class="mt-3 animate__animated animate__fadeInUp">
                    <div
                      class="alert alert-success border-0 bg-success bg-opacity-10 text-success small d-flex align-items-start gap-2 rounded-4 mb-0"
                    >
                      <i class="fas fa-paper-plane mt-1"></i>
                      <span>
                        Email notifications will be dispatched to all registered students once you save these changes.
                      </span>
                    </div>
                  </div>
                </div>
              </section>

              <section class="card settings-card">
                <header class="settings-card-head">
                  <div class="icon-box bg-primary-soft rounded-3">
                    <i class="fas fa-box-archive text-primary"></i>
                  </div>
                  <div>
                    <h5 class="mb-0 fw-bold">Archived Periods</h5>
                    <p class="text-muted small mb-0">Retired values — history stays reviewable</p>
                  </div>
                </header>
                <div class="card-body">
                  <div
                    v-if="!settings.archived_semester_options.length && !settings.archived_academic_year_options.length"
                    class="text-muted small mb-0"
                  >
                    Nothing archived yet. Archiving a semester or academic year retires it from active forms
                    without deleting history.
                  </div>
                  <div v-else>
                    <div v-if="settings.archived_semester_options.length" class="mb-3">
                      <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem">
                        Semesters
                      </div>
                      <div class="chip-wrap mb-0">
                        <span v-for="s in settings.archived_semester_options" :key="'arch-' + s" class="option-chip archived-chip">
                          {{ s }}
                          <span v-if="semesterUsage[s]" class="badge bg-warning bg-opacity-25 text-dark ms-1" style="font-size: 0.62rem">
                            {{ semesterUsage[s] }} in use
                          </span>
                          <button type="button" class="option-restore" title="Restore to active options" @click="restoreSemester(s)">
                            <i class="fas fa-rotate-left"></i>
                          </button>
                          <button type="button" class="option-remove" title="Delete forever (only when unused)" @click="forgetSemester(s)">
                            <i class="fas fa-trash"></i>
                          </button>
                        </span>
                      </div>
                    </div>
                    <div v-if="settings.archived_academic_year_options.length" class="mb-3">
                      <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem">
                        Academic years
                      </div>
                      <div class="chip-wrap mb-0">
                        <span v-for="y in settings.archived_academic_year_options" :key="'arch-' + y" class="option-chip archived-chip">
                          {{ y }}
                          <span v-if="academicYearUsage[y]" class="badge bg-warning bg-opacity-25 text-dark ms-1" style="font-size: 0.62rem">
                            {{ academicYearUsage[y] }} in use
                          </span>
                          <button type="button" class="option-restore" title="Restore to active options" @click="restoreYear(y)">
                            <i class="fas fa-rotate-left"></i>
                          </button>
                          <button type="button" class="option-remove" title="Delete forever (only when unused)" @click="forgetYear(y)">
                            <i class="fas fa-trash"></i>
                          </button>
                        </span>
                      </div>
                    </div>
                    <router-link to="/archive" class="btn btn-outline-primary btn-sm w-100">
                      <i class="fas fa-box-archive me-2"></i>
                      Open Evaluation Archive
                    </router-link>
                  </div>
                </div>
              </section>
            </div>
          </div>

          <!-- Session Timeout -->
          <div class="row g-4 mt-1">
            <div class="col-12">
              <section class="card settings-card">
                <header class="settings-card-head">
                  <div class="icon-box bg-danger-soft rounded-3">
                    <i class="fas fa-shield-halved text-danger"></i>
                  </div>
                  <div>
                    <h5 class="mb-0 fw-bold">Session Timeout</h5>
                    <p class="text-muted small mb-0">
                      After a period of inactivity, admins will be automatically signed out and need to log in
                      again. Student and faculty accounts are never affected.
                    </p>
                  </div>
                </header>
                <div class="card-body">
                  <div class="timeout-box">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                      <label class="fw-600 mb-0" for="sessionTimeoutToggle">
                        Enable session timeout for admins
                        <i
                          class="fas fa-info-circle text-muted ms-1"
                          title="When enabled, inactive admin sessions are signed out automatically after the selected inactivity time. Students and faculty are exempt."
                        ></i>
                      </label>
                      <div class="form-check form-switch m-0">
                        <input
                          id="sessionTimeoutToggle"
                          v-model="settings.session_timeout_enabled"
                          class="form-check-input premium-switch"
                          type="checkbox"
                          role="switch"
                        />
                      </div>
                    </div>

                    <div class="mt-3" :class="{ 'opacity-50': !settings.session_timeout_enabled }">
                      <label class="form-label text-muted small mb-2" for="inactivitySelect">
                        Inactivity time
                      </label>
                      <div class="inactivity-select">
                        <CustomSelect
                          v-model="settings.session_timeout_minutes"
                          :options="inactivityOptions"
                          placeholder="Select inactivity time"
                          :disabled="!settings.session_timeout_enabled"
                        />
                      </div>
                    </div>
                  </div>

                  <p class="text-muted small mb-0 mt-3">
                    <i class="fas fa-circle-info me-1"></i>
                    Applies the next time users sign in or refresh the page. A 30-second warning is shown before
                    signing out.
                  </p>
                </div>
              </section>
            </div>
          </div>

          <!-- Action Bar -->
          <div class="action-bar mt-4">
            <div class="mb-2 mb-md-0">
              <h6 v-if="successMsg" class="text-success fw-bold mb-0 animate__animated animate__fadeIn">
                <i class="fas fa-check-circle me-2"></i>
                {{ successMsg }}
              </h6>
              <h6 v-else-if="errorMsg" class="text-danger fw-bold mb-0 animate__animated animate__shakeX">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ errorMsg }}
              </h6>
              <p v-else class="text-muted small mb-0">Review your changes carefully before saving.</p>
            </div>
            <button
              class="btn btn-primary text-light premium-btn px-5 py-3 rounded-pill fw-800"
              @click="saveSettings"
              :disabled="saving"
            >
              <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status"></span>
              <i v-else class="fas fa-save me-2"></i>
              {{ saving ? "UPDATING SYSTEM..." : "SAVE CHANGES" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import api from "../services/api.js";
import { confirmAction } from "../composables/useConfirm.js";
import {
  DEFAULT_SEMESTERS,
  defaultAcademicYears,
  asStringArray,
  clearAcademicCache,
  fetchAcademicPeriods,
} from "../helpers/academic.js";

const settings = ref({
  active_semester: "",
  active_academic_year: "",
  evaluation_status: "closed",
  course_curricula: [],
  semester_options: [...DEFAULT_SEMESTERS],
  academic_year_options: defaultAcademicYears(),
  archived_semester_options: [],
  archived_academic_year_options: [],
  session_timeout_enabled: false,
  session_timeout_minutes: 60,
});

const inactivityOptions = [
  { label: "1 minute", value: 1 },
  { label: "5 minutes", value: 5 },
  { label: "15 minutes", value: 15 },
  { label: "30 minutes", value: 30 },
  { label: "1 hour", value: 60 },
  { label: "2 hours", value: 120 },
  { label: "4 hours", value: 240 },
  { label: "8 hours", value: 480 },
];

const semesterOptions = computed(() => [...settings.value.semester_options]);
const yearOptions = computed(() => [...settings.value.academic_year_options]);

const newSemester = ref("");
const newYear = ref("");

const loading = ref(true);
const saving = ref(false);
const successMsg = ref("");
const errorMsg = ref("");

function toBool(v) {
  return v === true || v === 1 || v === "1" || v === "true";
}

function normalizeSettings(data) {
  const normalized = { ...data };
  if (normalized.course_curricula && typeof normalized.course_curricula === "string") {
    try {
      normalized.course_curricula = JSON.parse(normalized.course_curricula);
    } catch (e) {}
  }
  if (!Array.isArray(normalized.course_curricula)) normalized.course_curricula = [];
  normalized.semester_options = asStringArray(normalized.semester_options, DEFAULT_SEMESTERS);
  normalized.academic_year_options = asStringArray(normalized.academic_year_options, defaultAcademicYears());
  normalized.archived_semester_options = asStringArray(normalized.archived_semester_options, []);
  normalized.archived_academic_year_options = asStringArray(normalized.archived_academic_year_options, []);
  // Archived values must never shadow the active dropdowns.
  normalized.archived_semester_options = normalized.archived_semester_options.filter(
    (s) => !normalized.semester_options.includes(s) && s !== normalized.active_semester
  );
  normalized.archived_academic_year_options = normalized.archived_academic_year_options.filter(
    (y) => !normalized.academic_year_options.includes(y) && y !== normalized.active_academic_year
  );
  normalized.session_timeout_enabled = toBool(normalized.session_timeout_enabled);
  normalized.session_timeout_minutes = Number(normalized.session_timeout_minutes) || 60;
  if (!normalized.evaluation_status) normalized.evaluation_status = "closed";
  return normalized;
}

onMounted(async () => {
  try {
    const res = await api.get("/settings");
    Object.assign(settings.value, normalizeSettings(res.data || {}));
  } catch (e) {
    console.error(e);
    errorMsg.value = "Failed to load settings.";
  } finally {
    loading.value = false;
  }
  // Usage counts power the "in use" badges on archived values. Non-blocking.
  try {
    const periods = await fetchAcademicPeriods();
    semesterUsage.value = periods.semesterUsage || {};
    academicYearUsage.value = periods.academicYearUsage || {};
  } catch {
    /* usage badges stay hidden when history is unreachable */
  }
});

const semesterUsage = ref({});
const academicYearUsage = ref({});

function addSemester() {
  const v = newSemester.value.trim();
  if (!v) return;
  if (!settings.value.semester_options.some((s) => s.toLowerCase() === v.toLowerCase())) {
    settings.value.semester_options.push(v);
  }
  newSemester.value = "";
}

function removeSemester(s) {
  archiveSemester(s);
}

function archiveSemester(s) {
  if (s === settings.value.active_semester) {
    errorMsg.value = "Switch the active semester first — the active period cannot be archived.";
    setTimeout(() => (errorMsg.value = ""), 3000);
    return;
  }
  settings.value.semester_options = settings.value.semester_options.filter((x) => x !== s);
  if (!settings.value.archived_semester_options.includes(s)) {
    settings.value.archived_semester_options.push(s);
  }
}

function restoreSemester(s) {
  settings.value.archived_semester_options = settings.value.archived_semester_options.filter((x) => x !== s);
  if (!settings.value.semester_options.includes(s)) {
    settings.value.semester_options.push(s);
  }
}

async function forgetSemester(s) {
  const uses = semesterUsage.value?.[s] || 0;
  if (uses > 0) {
    errorMsg.value = `"${s}" has ${uses} recorded evaluation(s) and cannot be permanently deleted. It stays in the archive for history review.`;
    setTimeout(() => (errorMsg.value = ""), 4000);
    return;
  }
  const result = await confirmAction({
    title: "Delete forever?",
    message: `"${s}" has no recorded evaluations. Remove it from the archive permanently?`,
  });
  if (!result.isConfirmed) return;
  settings.value.archived_semester_options = settings.value.archived_semester_options.filter((x) => x !== s);
}

function addYear() {
  const v = newYear.value.trim();
  if (!v) return;
  if (!settings.value.academic_year_options.some((y) => y.toLowerCase() === v.toLowerCase())) {
    settings.value.academic_year_options.push(v);
  }
  newYear.value = "";
}

function removeYear(y) {
  archiveYear(y);
}

function archiveYear(y) {
  if (y === settings.value.active_academic_year) {
    errorMsg.value = "Switch the active academic year first — the active period cannot be archived.";
    setTimeout(() => (errorMsg.value = ""), 3000);
    return;
  }
  settings.value.academic_year_options = settings.value.academic_year_options.filter((x) => x !== y);
  if (!settings.value.archived_academic_year_options.includes(y)) {
    settings.value.archived_academic_year_options.push(y);
  }
}

function restoreYear(y) {
  settings.value.archived_academic_year_options = settings.value.archived_academic_year_options.filter((x) => x !== y);
  if (!settings.value.academic_year_options.includes(y)) {
    settings.value.academic_year_options.push(y);
  }
}

async function forgetYear(y) {
  const uses = academicYearUsage.value?.[y] || 0;
  if (uses > 0) {
    errorMsg.value = `"${y}" has ${uses} recorded evaluation(s) and cannot be permanently deleted. It stays in the archive for history review.`;
    setTimeout(() => (errorMsg.value = ""), 4000);
    return;
  }
  const result = await confirmAction({
    title: "Delete forever?",
    message: `"${y}" has no recorded evaluations. Remove it from the archive permanently?`,
  });
  if (!result.isConfirmed) return;
  settings.value.archived_academic_year_options = settings.value.archived_academic_year_options.filter((x) => x !== y);
}

async function saveSettings() {
  saving.value = true;
  successMsg.value = "";
  errorMsg.value = "";
  try {
    // Dedupe lists and keep the active selections valid.
    settings.value.semester_options = [...new Set(settings.value.semester_options.map((s) => s.trim()).filter(Boolean))];
    settings.value.academic_year_options = [
      ...new Set(settings.value.academic_year_options.map((y) => y.trim()).filter(Boolean)),
    ];
    settings.value.archived_semester_options = [
      ...new Set((settings.value.archived_semester_options || []).map((s) => s.trim()).filter(Boolean)),
    ].filter((s) => !settings.value.semester_options.includes(s));
    settings.value.archived_academic_year_options = [
      ...new Set((settings.value.archived_academic_year_options || []).map((y) => y.trim()).filter(Boolean)),
    ].filter((y) => !settings.value.academic_year_options.includes(y));
    if (settings.value.active_semester && !settings.value.semester_options.includes(settings.value.active_semester)) {
      settings.value.semester_options.push(settings.value.active_semester);
    }
    if (
      settings.value.active_academic_year &&
      !settings.value.academic_year_options.includes(settings.value.active_academic_year)
    ) {
      settings.value.academic_year_options.push(settings.value.active_academic_year);
    }
    await api.post("/settings", { settings: settings.value });
    clearAcademicCache();
    // Live-update the session timeout manager (same tab, no refresh needed).
    window.dispatchEvent(new CustomEvent("session-timeout:reload"));
    successMsg.value = "Settings saved successfully!";
    setTimeout(() => (successMsg.value = ""), 3000);
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "Failed to save settings.";
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.settings-container {
  max-width: 1000px;
  margin: 0 auto;
}

/* Page header */
.page-head {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-head-icon {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: #fff;
  background: var(--primary);
}

/* Cards */
.settings-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

.settings-card-head {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-light);
}

.settings-card .card-body {
  padding: 1.5rem;
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

.bg-primary-soft {
  background-color: rgba(25, 25, 112, 0.08);
}
.bg-warning-soft {
  background-color: rgba(255, 193, 7, 0.12);
}
.bg-danger-soft {
  background-color: rgba(240, 82, 82, 0.1);
}

/* Evaluation status */
.status-banner {
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 1.25rem;
}

.status-open {
  background: rgba(14, 159, 110, 0.06);
  border-color: rgba(14, 159, 110, 0.25) !important;
}

.status-closed {
  background: var(--bg-light);
}

.premium-switch {
  width: 3.5rem !important;
  height: 1.75rem !important;
  cursor: pointer;
  border-color: var(--border-color);
}

.premium-switch:checked {
  background-color: var(--success);
  border-color: var(--success);
}

.pulse-indicator {
  width: 10px;
  height: 10px;
  background: var(--success);
  border-radius: 50%;
  box-shadow: 0 0 0 rgba(14, 159, 110, 0.4);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(14, 159, 110, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 10px rgba(14, 159, 110, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(14, 159, 110, 0);
  }
}

/* Editable option lists */
.option-manager {
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: var(--card-radius);
  padding: 1rem 1.1rem;
}

.option-manager-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.7rem;
}

.option-manager-title {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-main);
}

.chip-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.8rem;
}

.chip-wrap:empty {
  display: none;
}

.option-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.35rem 0.35rem 0.8rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-main);
}

.option-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: 0;
  border-radius: 50%;
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.65rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.option-remove:hover {
  background: var(--danger);
  color: #fff;
}

.archived-chip {
  background: transparent;
  border-style: dashed;
  opacity: 0.85;
}

.option-restore {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: 0;
  border-radius: 50%;
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.65rem;
  cursor: pointer;
  transition: all 0.15s ease;
}

.option-restore:hover {
  background: var(--success);
  color: #fff;
}

.add-row {
  display: flex;
  gap: 0.5rem;
}

.add-row .form-control {
  background: var(--bg-card);
  border-color: var(--border-color);
  color: var(--text-main);
}

/* Session timeout */
.timeout-box {
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: var(--card-radius);
  padding: 1.25rem;
}

.inactivity-select {
  max-width: 280px;
}

.tip-box {
  display: flex;
  gap: 0.75rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: var(--card-radius);
  padding: 1rem 1.1rem;
}

/* Sticky action bar */
.action-bar {
  position: sticky;
  bottom: 1rem;
  z-index: 900;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 1rem 1.5rem;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

@media (min-width: 768px) {
  .action-bar {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.premium-btn {
  letter-spacing: 1px;
  box-shadow: 0 4px 15px rgba(25, 25, 112, 0.2);
  transition: all 0.3s ease;
}

.premium-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(25, 25, 112, 0.3);
}

.transition-all {
  transition: all 0.3s ease;
}
</style>
