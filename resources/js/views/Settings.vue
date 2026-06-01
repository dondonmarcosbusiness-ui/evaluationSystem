<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>System Settings</template></Navbar>

      <div class="content-area animate__animated animate__fadeIn">
        <div class="settings-container">
          <!-- Page Header -->
          <div class="mb-5 text-center text-md-start">
            <h2 class="fw-800 mb-2">Global Settings</h2>
            <p class="text-muted">
              Manage the active academic period and controlling the evaluation cycle for the entire institution.
            </p>
          </div>

          <div class="row g-4">
            <!-- Left Column: Academic Period -->
            <div class="col-lg-7">
              <div class="card h-100 shadow-sm border-0 bg-white rounded-4 overflow-visible">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                  <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-soft rounded-3">
                      <i class="fas fa-calendar-alt text-primary"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Academic Period</h5>
                  </div>
                </div>
                <div class="card-body px-4 pb-4">
                  <p class="text-muted small mb-4">
                    Set the current active semester and academic year that will be used for all evaluations and reports.
                  </p>

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label fw-bold small text-uppercase ls-1">Active Semester</label>
                      <CustomSelect
                        v-model="settings.active_semester"
                        :options="['1st Semester', '2nd Semester', 'Summer']"
                        placeholder="Select Semester"
                      />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold small text-uppercase ls-1">Academic Year</label>
                      <CustomSelect
                        v-model="settings.active_academic_year"
                        :options="academicYears"
                        placeholder="Select Academic Year"
                      />
                    </div>
                  </div>

                  <div class="mt-4 p-3 bg-light rounded-4 border border-light shadow-none">
                    <div class="d-flex gap-3">
                      <i class="fas fa-info-circle text-primary mt-1"></i>
                      <div class="small">
                        <span class="d-block fw-bold mb-1">Configuration Tip</span>
                        <span class="text-muted">
                          Changes to the academic period will immediately reflect on the student dashboard and
                          evaluation forms.
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column: Evaluation Control -->
            <div class="col-lg-5">
              <div class="card h-100 shadow-sm border-0 bg-white rounded-4 overflow-visible">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                  <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-warning-soft rounded-3">
                      <i class="fas fa-power-off text-warning"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Evaluation Control</h5>
                  </div>
                </div>
                <div class="card-body px-4 pb-4">
                  <p class="text-muted small mb-4">
                    Toggle the visibility and accessibility of evaluation forms for all active students.
                  </p>

                  <div
                    class="status-banner p-4 rounded-4 border transition-all"
                    :class="settings.evaluation_status === 'open' ? 'status-open' : 'status-closed'"
                  >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                      <div class="d-flex align-items-center gap-2">
                        <div v-if="settings.evaluation_status === 'open'" class="pulse-indicator"></div>
                        <span
                          class="fw-800 text-uppercase ls-1"
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

                  <div v-if="settings.evaluation_status === 'open'" class="mt-4 animate__animated animate__fadeInUp">
                    <div
                      class="alert alert-success border-0 bg-success bg-opacity-10 text-success small d-flex align-items-start gap-2 rounded-4"
                    >
                      <i class="fas fa-paper-plane mt-1"></i>
                      <span>
                        Email notifications will be dispatched to all registered students once you save these changes.
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Bar -->
          <div
            class="mt-5 d-flex flex-column flex-md-row align-items-center justify-content-between p-4 bg-white rounded-4 shadow-sm border-0"
          >
            <div class="mb-3 mb-md-0">
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
              {{ saving ? "UPDATING SYSTEM..." : "SAVE SETTINGS" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import api from "../services/api.js";

const settings = ref({
  active_semester: "",
  active_academic_year: "",
  evaluation_status: "closed",
  course_curricula: [],
});

const currentYear = new Date().getFullYear();
const academicYears = Array.from({ length: 5 }, (_, i) => `${currentYear - i}-${currentYear - i + 1}`);

const loading = ref(true);
const saving = ref(false);
const successMsg = ref("");
const errorMsg = ref("");

onMounted(async () => {
  try {
    const res = await api.get("/settings");
    // Ensure course_curricula is an array
    if (res.data.course_curricula && typeof res.data.course_curricula === "string") {
      try {
        res.data.course_curricula = JSON.parse(res.data.course_curricula);
      } catch (e) {}
    }
    if (!Array.isArray(res.data.course_curricula)) {
      res.data.course_curricula = [];
    }
    Object.assign(settings.value, res.data);
  } catch (e) {
    console.error(e);
    errorMsg.value = "Failed to load settings.";
  } finally {
    loading.value = false;
  }
});

async function saveSettings() {
  saving.value = true;
  successMsg.value = "";
  errorMsg.value = "";
  try {
    await api.post("/settings", { settings: settings.value });
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

.icon-box {
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.bg-primary-soft {
  background-color: rgba(10, 39, 138, 0.08);
}
.bg-warning-soft {
  background-color: rgba(255, 193, 7, 0.1);
}

.status-banner {
  background: var(--bg-light);
  border-color: var(--border-color) !important;
}

.status-open {
  background: rgba(14, 159, 110, 0.05);
  border-color: rgba(14, 159, 110, 0.2) !important;
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

.premium-btn {
  letter-spacing: 1px;
  box-shadow: 0 4px 15px rgba(10, 39, 138, 0.2);
  transition: all 0.3s ease;
}

.premium-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(10, 39, 138, 0.3);
}

.transition-all {
  transition: all 0.3s ease;
}

/* Dark Mode Adjustments */
[data-theme="dark"] .bg-white {
  background-color: var(--bg-card) !important;
}

[data-theme="dark"] .status-banner {
  background: rgba(255, 255, 255, 0.02);
}

[data-theme="dark"] .status-open {
  background: rgba(16, 185, 129, 0.1);
}
</style>
