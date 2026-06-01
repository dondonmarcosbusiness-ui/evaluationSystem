<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar>
        <template #title>Data Backup & Recovery</template>
      </Navbar>

      <div class="content-area p-4">
        <!-- Status Overview Cards -->
        <div class="row g-4 mb-4">
          <div class="col-md-4">
            <div class="stats-card h-100">
              <div class="d-flex align-items-center gap-3">
                <div class="icon-box primary">
                  <i class="fas fa-database"></i>
                </div>
                <div>
                  <div class="label">System Status</div>
                  <div class="value">Operational</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stats-card h-100">
              <div class="d-flex align-items-center gap-3">
                <div class="icon-box success" :class="{ 'warning': isBackupOld }">
                  <i class="fas fa-history"></i>
                </div>
                <div>
                  <div class="label">Last Backup</div>
                  <div class="value small">{{ lastBackup || "Never" }}</div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stats-card h-100">
              <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-box info">
                    <i class="fas fa-robot"></i>
                  </div>
                  <div>
                    <div class="label">Auto Backup</div>
                    <div class="value">{{ autoBackupEnabled ? "Enabled" : "Disabled" }}</div>
                  </div>
                </div>
                <div class="form-check form-switch custom-switch">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="autoBackupEnabled"
                    @change="toggleAutoBackup"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Actions & History -->
        <div class="card border-0 shadow-sm overflow-hidden">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
              <h5 class="fw-bold mb-0">Backup History</h5>
              <p class="text-muted small mb-0">The system automatically keeps the 5 most recent backups.</p>
            </div>
            <button
              class="btn btn-primary px-4 py-2 d-flex align-items-center gap-2 shadow-sm"
              @click="createBackup"
              :disabled="creating"
            >
              <i class="fas fa-plus-circle" :class="{ 'fa-spin': creating }"></i>
              {{ creating ? "Generating..." : "Backup Now" }}
            </button>
          </div>

          <div class="card-body p-0">
            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="text-muted mt-3">Loading backup history...</p>
            </div>

            <div v-else-if="backups.length === 0" class="text-center py-5">
              <div class="mb-3 opacity-25">
                <i class="fas fa-folder-open fa-4x text-muted"></i>
              </div>
              <h6 class="text-muted">No backups found</h6>
              <p class="small text-muted mb-0">Manual or automated backups will appear here</p>
            </div>

            <table v-else class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Date Created</th>
                  <th class="py-3 text-uppercase small fw-bold text-muted">File Name</th>
                  <th class="py-3 text-uppercase small fw-bold text-muted">File Size</th>
                  <th class="pe-4 py-3 text-end text-uppercase small fw-bold text-muted">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="backup in backups" :key="backup.filename">
                  <td class="ps-4">
                    <span class="fw-medium">{{ formatDate(backup.created_at) }}</span>
                    <div class="text-muted small">{{ formatTime(backup.created_at) }}</div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <i class="far fa-file-alt text-primary"></i>
                      <span>{{ backup.filename }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark fw-normal px-2 py-1">{{ backup.size }}</span>
                  </td>
                  <td class="pe-4 text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <button
                        class="btn btn-sm btn-outline-primary"
                        @click="downloadBackup(backup.filename)"
                        title="Download SQL"
                      >
                        <i class="fas fa-download"></i>
                      </button>
                      <button
                        class="btn btn-sm btn-outline-success"
                        @click="confirmRestore(backup.filename)"
                        title="Restore Database"
                      >
                        <i class="fas fa-undo-alt"></i>
                      </button>
                      <button
                        class="btn btn-sm btn-outline-danger"
                        @click="deleteBackup(backup.filename)"
                        title="Delete Permanently"
                      >
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="isBackupOld" class="alert alert-warning mt-4 border-0 shadow-sm d-flex align-items-center gap-3 recommendation-alert">
          <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
          <div>
            <h6 class="alert-heading fw-bold mb-1 text-dark">Backup Recommendation</h6>
            <p class="mb-0 text-dark opacity-75">It has been more than 3 days since the last backup. We recommend generating a new backup to ensure your data is safe.</p>
          </div>
        </div>
      </div>

      <!-- Restore Modal -->
      <div v-if="showRestoreModal" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-bold">Restore Database</h5>
              <button type="button" class="btn-close" @click="showRestoreModal = false"></button>
            </div>
            <div class="modal-body py-4">
              <div class="text-center mb-4">
                <div class="icon-circle bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                  <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <h6 class="fw-bold">Are you absolutely sure?</h6>
                <p class="text-muted">
                  Restoring <strong>{{ selectedFile }}</strong> will overwrite your current database. This action cannot be undone.
                </p>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold">Verify Admin Password</label>
                <input
                  v-model="verifyPassword"
                  type="password"
                  class="form-control"
                  placeholder="Enter your password to confirm"
                />
              </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 justify-content-center gap-2">
              <button class="btn btn-light px-4" @click="showRestoreModal = false">Cancel</button>
              <button class="btn btn-danger px-4" @click="restoreDatabase" :disabled="restoring">
                {{ restoring ? "Restoring..." : "Confirm Restore" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import api from "../services/api";
import { format } from "date-fns";
import Swal from "sweetalert2";

const backups = ref([]);
const loading = ref(true);
const creating = ref(false);
const autoBackupEnabled = ref(false);
const lastBackup = ref("");

const showRestoreModal = ref(false);
const selectedFile = ref("");
const verifyPassword = ref("");
const restoring = ref(false);

onMounted(fetchBackups);

async function fetchBackups() {
  loading.value = true;
  try {
    const res = await api.get("/backups");
    backups.value = res.data.backups;
    autoBackupEnabled.value = res.data.auto_backup;
    lastBackup.value = res.data.last_backup;
  } catch (err) {
    console.error("Failed to load backups:", err);
  } finally {
    loading.value = false;
  }
}

async function createBackup() {
  creating.value = true;
  try {
    await api.post("/backups");
    await fetchBackups();
    Swal.fire({
      icon: "success",
      title: "Success",
      text: "Backup generated successfully!",
      timer: 2000,
      showConfirmButton: false,
    });
  } catch (err) {
    Swal.fire("Error", "Failed to generate backup: " + (err.response?.data?.message || err.message), "error");
  } finally {
    creating.value = false;
  }
}

async function toggleAutoBackup() {
  try {
    await api.post("/backups/toggle-auto", { enabled: autoBackupEnabled.value });
    Swal.fire({
      icon: "success",
      title: "Updated",
      text: `Auto-backup ${autoBackupEnabled.value ? 'enabled' : 'disabled'} successfully.`,
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (err) {
    autoBackupEnabled.value = !autoBackupEnabled.value;
    Swal.fire("Error", "Failed to update auto-backup setting.", "error");
  }
}

async function downloadBackup(filename) {
  try {
    const response = await api.get(`/backups/download/${filename}`, {
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (err) {
    Swal.fire("Error", "Failed to download backup.", "error");
  }
}

async function deleteBackup(filename) {
  const result = await Swal.fire({
    title: "Delete Backup?",
    text: `Are you sure you want to delete ${filename}?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/backups/${filename}`);
    await fetchBackups();
    Swal.fire("Deleted!", "Backup file has been removed.", "success");
  } catch (err) {
    Swal.fire("Error", "Failed to delete backup.", "error");
  }
}

function confirmRestore(filename) {
  selectedFile.value = filename;
  verifyPassword.value = "";
  showRestoreModal.value = true;
}

async function restoreDatabase() {
  if (!verifyPassword.value) {
    Swal.fire("Required", "Password is required for verification.", "warning");
    return;
  }
  restoring.value = true;
  try {
    await api.post("/backups/restore", {
      filename: selectedFile.value,
      password: verifyPassword.value
    });
    showRestoreModal.value = false;
    await Swal.fire({
      icon: "success",
      title: "Success",
      text: "Database restored successfully! The system will now reload.",
      timer: 2000,
      showConfirmButton: false,
    });
    window.location.reload();
  } catch (err) {
    Swal.fire("Restoration Failed", err.response?.data?.message || "Check your password", "error");
  } finally {
    restoring.value = false;
  }
}

const isBackupOld = computed(() => {
  if (!lastBackup.value) return true;
  const last = new Date(lastBackup.value);
  const now = new Date();
  const diffTime = Math.abs(now - last);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays > 3;
});

function formatDate(dateString) {
  if (!dateString) return "";
  return format(new Date(dateString), "MMM dd, yyyy");
}

function formatTime(dateString) {
  if (!dateString) return "";
  return format(new Date(dateString), "HH:mm:ss");
}
</script>

<style scoped>
.stats-card {
  background: white;
  padding: 1.5rem;
  border-radius: 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

[data-theme="dark"] .stats-card {
  background: rgba(30, 41, 59, 0.5);
  border-color: rgba(255, 255, 255, 0.05);
  color: white;
}

.icon-box {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.icon-box.primary { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.icon-box.success { background: rgba(25, 135, 84, 0.1); color: #198754; }
.icon-box.warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.icon-box.info { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }

[data-theme="dark"] .value {
  color: white !important;
}

.label {
  color: #6c757d;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

[data-theme="dark"] .label {
  color: rgba(255, 255, 255, 0.5);
}

.value {
  color: #212529;
  font-size: 1.15rem;
  font-weight: 700;
}

[data-theme="dark"] .card {
  background: rgba(30, 41, 59, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.05) !important;
}

[data-theme="dark"] .card-header {
  background: rgba(255, 255, 255, 0.02) !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
  color: white;
}

[data-theme="dark"] .table thead th {
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.6) !important;
}

[data-theme="dark"] .table tbody td {
  color: rgba(255, 255, 255, 0.8);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .table-hover tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02);
}

[data-theme="dark"] .badge.bg-light {
  background-color: rgba(255, 255, 255, 0.1) !important;
  color: rgba(255, 255, 255, 0.8) !important;
}

.recommendation-alert {
  background-color: #fff3cd;
}

[data-theme="dark"] .recommendation-alert {
  background-color: rgba(255, 193, 7, 0.15);
  border: 1px solid rgba(255, 193, 7, 0.2);
}

[data-theme="dark"] .recommendation-alert .alert-heading,
[data-theme="dark"] .recommendation-alert p {
  color: #ffc107 !important;
}

[data-theme="dark"] .modal-content {
  background: #1e293b;
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .modal-header .btn-close {
  filter: invert(1) grayscale(100%) brightness(200%);
}

.custom-switch .form-check-input {
  width: 3rem;
  height: 1.5rem;
  cursor: pointer;
}

.icon-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.table th {
  letter-spacing: 0.05em;
}

.btn-icon-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-icon-action:hover {
  transform: translateY(-2px);
}
</style>
