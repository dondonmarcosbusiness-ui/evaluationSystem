<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Office Management</template></Navbar>

      <div class="content-area">
        <div class="card">
          <div class="card-header border-0 py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
              <div class="d-flex align-items-center py-1 gap-3">
                <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                  <i class="fas fa-building me-2 text-primary opacity-75"></i>
                  Office Directory
                </h5>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-700" style="font-size: 0.75rem">
                  {{ pagination.total || 0 }} Records
                </span>
              </div>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="premium-filter-group" style="width: 200px">
                  <span class="input-group-text"><i class="fas fa-search"></i></span>
                  <input v-model="filters.query" type="text" class="form-control" placeholder="Search offices..." @input="handleSearch" />
                </div>
                <button class="btn-premium-reset" @click="clearFilters" title="Reset Filters">
                  <i class="fas fa-redo-alt small"></i>
                </button>
                <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.1"></div>
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="openAddModal">
                  <i class="fas fa-plus"></i>
                  <span class="d-none d-xl-inline">Add Office</span>
                </button>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <Transition name="fade" mode="out-in">
              <div v-if="loading" key="loading">
                <SkeletonLoader variant="table" :rows="8" :cols="8" />
              </div>
              <div v-else key="table" class="table-scroll" @scroll="onTableScroll">
                <table class="table table-hover mb-0">
                  <thead :class="{ 'glass-header': tableScrolled }">
                  <tr>
                    <th style="width: 40px">
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" v-model="selectAll" />
                      </div>
                    </th>
                    <th>#</th>
                    <th>Office Name</th>
                    <th>Office Head</th>
                    <th>Location</th>
                    <th>Feedback</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(o, i) in officeList" :key="o.id">
                    <td>
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" :value="o.id" v-model="selectedIds" />
                      </div>
                    </td>
                    <td class="text-muted small">{{ (pagination.current_page - 1) * (pagination.per_page || 10) + i + 1 }}</td>
                    <td class="fw-semibold">{{ o.name }}</td>
                    <td class="text-muted">{{ o.office_head || "—" }}</td>
                    <td class="text-muted small">{{ o.location || "—" }}</td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ o.feedbacks_count || 0 }}</span></td>
                    <td>
                      <span class="badge-status" :class="o.is_active ? 'active' : 'inactive'">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle"></i>
                        {{ o.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-2 justify-content-start flex-nowrap align-items-center">
                        <button class="btn-icon-action info" @click="viewQr(o)" title="View QR Code"><i class="fas fa-qrcode"></i></button>
                        <button class="btn-icon-action primary" @click="openEditModal(o)" title="Edit Office"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon-action" :class="o.is_active ? 'warning' : 'success'" @click="toggleActive(o)" :title="o.is_active ? 'Deactivate' : 'Activate'">
                          <i :class="o.is_active ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                        </button>
                        <button class="btn-icon-action danger" @click="deleteOffice(o.id)" title="Delete Office"><i class="fas fa-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!officeList.length">
                    <td colspan="8" class="text-center text-muted py-4">No offices found.</td>
                  </tr>
                </tbody>
              </table>
              </div>
            </Transition>
            <Pagination :pagination="pagination" @change-page="fetchOffices" />
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <Transition name="fade"><div v-if="showModal" class="modal-backdrop-custom" @click="closeModal"></div></Transition>
      <Transition name="slide-up">
        <div v-if="showModal" class="custom-modal">
          <div class="card modal-card">
            <div class="modal-header-custom">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-building"></i></div>
                <div>
                  <h5 class="fw-800 mb-0">{{ editMode ? "Update Office" : "Register New Office" }}</h5>
                  <p class="text-muted small mb-0">{{ editMode ? "Modify office details" : "Add a new campus office" }}</p>
                </div>
              </div>
              <button class="btn-close-custom" @click="closeModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body-custom">
              <div v-if="formError" class="alert alert-danger-custom mb-4"><i class="fas fa-exclamation-circle me-2"></i>{{ formError }}</div>
              <div class="form-section-modern mb-4">
                <div class="section-label mb-3"><i class="fas fa-info-circle me-2 text-primary opacity-50"></i>Office Details</div>
                <div class="row g-4">
                  <div class="col-12">
                    <label class="label-custom">Office Name *</label>
                    <input v-model="form.name" class="input-custom" placeholder="e.g. Guidance Office" />
                  </div>
                  <div class="col-12">
                    <label class="label-custom">Description</label>
                    <textarea v-model="form.description" class="input-custom" rows="3" placeholder="Brief description of the office..."></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">Office Head</label>
                    <input v-model="form.office_head" class="input-custom" placeholder="e.g. Dr. Juan Dela Cruz" />
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">Location</label>
                    <input v-model="form.location" class="input-custom" placeholder="e.g. Ground Floor, Admin Building" />
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer-custom bg-light bg-opacity-25">
              <button class="btn btn-light-custom px-4" @click="closeModal">Discard</button>
              <button class="btn btn-primary-custom px-4" @click="saveOffice" :disabled="saving">
                <i class="fas fa-save me-2"></i>{{ saving ? "Processing..." : editMode ? "Save Changes" : "Create Office" }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- QR Code Modal -->
      <Transition name="fade"><div v-if="showQrModal" class="modal-backdrop-custom" @click="showQrModal = false"></div></Transition>
      <Transition name="slide-up">
        <div v-if="showQrModal" class="custom-modal">
          <div class="card modal-card" style="max-width: 400px">
            <div class="modal-header-custom">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-info bg-opacity-10 text-info"><i class="fas fa-qrcode"></i></div>
                <div>
                  <h5 class="fw-800 mb-0">QR Code</h5>
                  <p class="text-muted small mb-0">{{ selectedOffice?.name }}</p>
                </div>
              </div>
              <button class="btn-close-custom" @click="showQrModal = false"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body-custom text-center">
              <div v-if="qrLoading" class="py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
              <div v-else-if="qrDataUrl" class="qr-display">
                <img :src="qrDataUrl" alt="QR Code" class="img-fluid mb-3" style="max-width: 220px; border: 1px solid var(--border-color); border-radius: 12px; padding: 8px" />
                <p class="text-muted small mb-3">Scan to provide feedback for this office</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                  <a :href="qrDataUrl" :download="'qr-' + (selectedOffice?.name || 'office') + '.png'" class="btn btn-primary btn-sm d-flex align-items-center gap-2">
                    <i class="fas fa-download"></i> Download
                  </a>
                  <button class="btn btn-success btn-sm d-flex align-items-center gap-2" @click="printSingleQr">
                    <i class="fas fa-print"></i> Print
                  </button>
                  <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" @click="regenerateQr">
                    <i class="fas fa-sync-alt"></i> Regenerate
                  </button>
                </div>
              </div>
              <div v-else class="text-muted py-4">No QR code available</div>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Bulk Actions Toast -->
      <Transition name="toast-slide">
        <div v-if="selectedIds.length > 0" class="bulk-toast-bar">
          <div class="bulk-toast-inner">
            <div class="bulk-toast-count">
              <span class="bulk-toast-number">{{ selectedIds.length }}</span> offices selected
            </div>
            <div class="bulk-toast-actions">
              <button class="btn-toast btn-toast-print" @click="printSelectedQr"><i class="fas fa-print"></i> Print QR</button>
              <button class="btn-toast btn-toast-activate" @click="bulkChangeStatus(true)"><i class="fas fa-check-circle"></i> Activate</button>
              <button class="btn-toast btn-toast-deactivate" @click="bulkChangeStatus(false)"><i class="fas fa-ban"></i> Deactivate</button>
              <button class="btn-toast btn-toast-delete" @click="bulkDelete"><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
            <button class="bulk-toast-close" @click="selectedIds = []"><i class="fas fa-times"></i></button>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";

const officeList = ref([]);
const pagination = ref({});
const loading = ref(true);
const showModal = ref(false);
const showQrModal = ref(false);
const editMode = ref(false);
const editId = ref(null);
const saving = ref(false);
const formError = ref("");
const selectedIds = ref([]);
const selectedOffice = ref(null);
const qrDataUrl = ref("");
const qrLoading = ref(false);
const tableScrolled = ref(false);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

const filters = ref({ query: "" });
let searchTimeout = null;

const blankForm = () => ({ name: "", description: "", office_head: "", location: "" });
const form = ref(blankForm());

const selectAll = computed({
  get() { return officeList.value.length > 0 && selectedIds.value.length === officeList.value.length; },
  set(val) { selectedIds.value = val ? officeList.value.map((o) => o.id) : []; },
});

function handleSearch() { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => fetchOffices(1), 500); }
function clearFilters() { filters.value = { query: "" }; fetchOffices(1); }

onMounted(() => fetchOffices());

async function fetchOffices(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page, query: filters.value.query });
    const res = await api.get(`/offices?${params.toString()}`);
    officeList.value = res.data.data;
    pagination.value = res.data;
    selectedIds.value = [];
  } catch (e) { console.error(e); } finally { loading.value = false; }
}

function openAddModal() { editMode.value = false; form.value = blankForm(); formError.value = ""; showModal.value = true; }
function openEditModal(o) {
  editMode.value = true; editId.value = o.id;
  form.value = { name: o.name || "", description: o.description || "", office_head: o.office_head || "", location: o.location || "" };
  formError.value = ""; showModal.value = true;
}
function closeModal() { showModal.value = false; }

async function saveOffice() {
  saving.value = true; formError.value = "";
  try {
    if (editMode.value) { await api.put(`/offices/${editId.value}`, form.value); }
    else { await api.post("/offices", form.value); }
    closeModal(); await fetchOffices();
    Swal.fire({ icon: "success", title: "Success", text: editMode.value ? "Office updated." : "Office created.", timer: 1500, showConfirmButton: false });
  } catch (e) {
    formError.value = e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(" ") : e.response?.data?.message || "Failed to save.";
  } finally { saving.value = false; }
}

async function toggleActive(o) {
  const action = o.is_active ? "deactivate" : "activate";
  const result = await Swal.fire({ title: "Are you sure?", text: `${action.charAt(0).toUpperCase() + action.slice(1)} "${o.name}"?`, icon: "question", showCancelButton: true, confirmButtonColor: "#191970", confirmButtonText: `Yes, ${action}!` });
  if (!result.isConfirmed) return;
  try { await api.patch(`/offices/${o.id}/toggle-active`); await fetchOffices(); Swal.fire({ icon: "success", title: "Updated", timer: 1500, showConfirmButton: false }); } catch (e) { Swal.fire("Error", "Failed.", "error"); }
}

async function deleteOffice(id) {
  const result = await Swal.fire({ title: "Delete office?", text: "This cannot be undone.", icon: "warning", showCancelButton: true, confirmButtonColor: "#ef4444", confirmButtonText: "Yes, delete!" });
  if (!result.isConfirmed) return;
  try { await api.delete(`/offices/${id}`); await fetchOffices(); Swal.fire("Deleted!", "", "success"); } catch (e) { Swal.fire("Error", "Failed.", "error"); }
}

async function bulkDelete() {
  if (!selectedIds.value.length) return;
  const result = await Swal.fire({ title: `Delete ${selectedIds.value.length} offices?`, icon: "warning", showCancelButton: true, confirmButtonColor: "#ef4444" });
  if (!result.isConfirmed) return;
  try { await api.post("/offices/bulk-delete", { ids: selectedIds.value }); selectedIds.value = []; await fetchOffices(); Swal.fire("Deleted!", "", "success"); } catch (e) { Swal.fire("Error", "", "error"); }
}

async function bulkChangeStatus(status) {
  if (!selectedIds.value.length) return;
  try { await api.post("/offices/bulk-status", { ids: selectedIds.value, status }); selectedIds.value = []; await fetchOffices(); Swal.fire("Success", "", "success"); } catch (e) { Swal.fire("Error", "", "error"); }
}

async function viewQr(o) {
  selectedOffice.value = o; showQrModal.value = true; qrLoading.value = true; qrDataUrl.value = "";
  try {
    const res = await api.get(`/qr-codes?office_id=${o.id}`);
    const qr = res.data.find((q) => q.office_id === o.id) || res.data[0];
    if (qr) {
      const url = `${window.location.origin}${getAppBasePath()}/qr/${qr.qr_token}`;
      qrDataUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}`;
    }
  } catch (e) { console.error(e); } finally { qrLoading.value = false; }
}

async function regenerateQr() {
  if (!selectedOffice.value) return;
  try {
    const res = await api.post(`/qr-codes/generate`, { office_id: selectedOffice.value.id });
    const qr = res.data.data;
    const url = `${window.location.origin}${getAppBasePath()}/qr/${qr.qr_token}`;
    qrDataUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}`;
    Swal.fire({ icon: "success", title: "QR Regenerated", timer: 1500, showConfirmButton: false });
  } catch (e) { Swal.fire("Error", "Failed.", "error"); }
}

function getAppBasePath() {
  return window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";
}

function buildQrUrl(token) {
  return `${window.location.origin}${getAppBasePath()}/qr/${token}`;
}

function buildQrImgUrl(token) {
  return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(buildQrUrl(token))}`;
}

async function printSingleQr() {
  if (!qrDataUrl.value || !selectedOffice.value) return;
  const items = [{ name: selectedOffice.value.name, img: qrDataUrl.value }];
  openPrintWindow(items);
}

async function printSelectedQr() {
  if (!selectedIds.value.length) return;
  try {
    const res = await api.get("/qr-codes");
    const selected = res.data.filter((qr) => selectedIds.value.includes(qr.office_id));
    if (!selected.length) {
      Swal.fire({ icon: "info", title: "No QR Codes", text: "Selected offices do not have QR codes yet." });
      return;
    }
    const items = selected.map((qr) => ({
      name: qr.office?.name || "Office",
      img: buildQrImgUrl(qr.qr_token),
    }));
    openPrintWindow(items);
  } catch (e) {
    Swal.fire("Error", "Failed to load QR codes.", "error");
  }
}

function openPrintWindow(items) {
  const printWindow = window.open("", "_blank", "width=800,height=600");
  const html = `<!DOCTYPE html>
<html><head><title>Print QR Codes</title>
<style>
  @page { margin: 15mm; size: A4; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; color: #1e293b; }
  h2 { text-align: center; margin-bottom: 24px; font-size: 18px; color: #191970; }
  .qr-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
  .qr-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; page-break-inside: avoid; }
  .qr-card img { width: 180px; height: 180px; display: block; margin: 0 auto 12px; }
  .qr-card .office-name { font-size: 13px; font-weight: 700; color: #191970; margin-bottom: 4px; }
  .qr-card .scan-text { font-size: 10px; color: #6b7280; }
  @media print {
    body { padding: 0; }
    .no-print { display: none !important; }
  }
</style></head><body>
  <div class="no-print" style="text-align:center;margin-bottom:16px;">
    <button onclick="window.print()" style="padding:10px 24px;font-size:14px;font-weight:700;background:#191970;color:#fff;border:none;border-radius:8px;cursor:pointer;">Print Now</button>
    <button onclick="window.close()" style="padding:10px 24px;font-size:14px;font-weight:700;background:#e2e8f0;color:#475569;border:none;border-radius:8px;cursor:pointer;margin-left:8px;">Close</button>
  </div>
  <h2>Office QR Codes</h2>
  <div class="qr-grid">
    ${items.map((item) => `
      <div class="qr-card">
        <img src="${item.img}" alt="QR Code for ${item.name}" />
        <div class="office-name">${item.name}</div>
        <div class="scan-text">Scan to provide feedback</div>
      </div>
    `).join("")}
  </div>
</body></html>`;
  printWindow.document.write(html);
  printWindow.document.close();
}
</script>

<style scoped>
.premium-filter-group { display: flex; align-items: center; background: var(--bg-light); border: 1px solid var(--border-light); border-radius: 12px; padding: 0 0.75rem; transition: all 0.2s; }
.premium-filter-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(25, 25, 112, 0.1); }
.premium-filter-group .input-group-text { background: transparent; border: none; padding: 0; color: var(--text-muted); font-size: 0.85rem; }
.premium-filter-group .form-control { background: transparent; border: none; padding: 0.6rem 0.5rem; font-size: 0.85rem; font-weight: 600; }
.premium-filter-group .form-control:focus { box-shadow: none; }
.btn-premium-reset { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; background: var(--bg-light); border: 1px solid var(--border-light); border-radius: 12px; color: var(--text-muted); transition: all 0.2s; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(30px); }
.modal-backdrop-custom { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); z-index: 2000; }
.custom-modal { position: fixed; inset: 0; display: flex; align-items: flex-start; justify-content: center; z-index: 2001; pointer-events: none; overflow-y: auto; padding: 3rem 1rem; }
.custom-modal .card { margin: auto; pointer-events: all; background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--card-radius); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); width: 600px; max-width: 95vw; overflow: visible; }
.modal-header-custom { padding: 1.75rem 2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; }
.profile-avatar-icon { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.modal-body-custom { padding: 2rem; overflow: visible; }
.modal-footer-custom { padding: 1.25rem 2rem; background: rgba(0, 0, 0, 0.02); border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 1rem; }
.form-section-modern .section-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.label-custom { display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.65rem; margin-left: 0.25rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; }
.input-custom { width: 100%; padding: 0.75rem 1.15rem; border-radius: 0!important; border: 2px solid var(--border-light); background: var(--bg-card); color: var(--text-dark); font-size: 0.9rem; font-weight: 500; transition: all 0.2s ease; }
.input-custom:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(25, 25, 112, 0.1); }
.btn-primary-custom { background: #191970 !important; color: white !important; border: none; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; transition: all 0.2s ease; }
.btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 8px 15px -5px rgba(25, 25, 112, 0.5); }
.btn-light-custom { background: var(--border-light); color: var(--text-main); border: none; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; }
.btn-close-custom { background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; transition: all 0.2s ease; }
.btn-close-custom:hover { color: var(--danger); transform: rotate(90deg); }
.alert-danger-custom { padding: 1rem; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; color: #ef4444; font-size: 0.85rem; font-weight: 600; }
[data-theme="dark"] .modal-backdrop-custom { background: rgba(0, 0, 0, 0.7); }
[data-theme="dark"] .modal-header-custom, [data-theme="dark"] .modal-footer-custom { border-color: rgba(255, 255, 255, 0.05); }

@property --border-angle { syntax: "<angle>"; initial-value: 0deg; inherits: false; }
.bulk-toast-bar { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 9999; border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15); min-width: 480px; max-width: 90vw; padding: 2px; overflow: hidden; }
.bulk-toast-bar::before { content: ""; position: absolute; inset: 0; border-radius: 16px; background: conic-gradient(from var(--border-angle), #ffc107, #ff7b00, #191970, #232380, #232380, #191970, #ffc107); animation: spin-border 2s linear infinite; z-index: 0; mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); mask-composite: exclude; -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; padding: 2px; }
@keyframes spin-border { to { --border-angle: 360deg; } }
.bulk-toast-inner { position: relative; z-index: 1; display: flex; align-items: center; gap: 16px; background: var(--bg-card); border-radius: 14px; padding: 12px 20px; }
.bulk-toast-count { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
.bulk-toast-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: var(--primary); color: #fff; font-size: 0.75rem; font-weight: 800; }
.bulk-toast-actions { display: flex; gap: 8px; flex: 1; }
.btn-toast { padding: 6px 14px; border-radius: 8px; border: none; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.btn-toast-print { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.btn-toast-print:hover { background: #22c55e; color: #fff; }
.btn-toast-activate { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.btn-toast-activate:hover { background: #22c55e; color: #fff; }
.btn-toast-deactivate { background: rgba(234, 179, 8, 0.15); color: #eab308; }
.btn-toast-deactivate:hover { background: #eab308; color: #fff; }
.btn-toast-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.btn-toast-delete:hover { background: #ef4444; color: #fff; }
.bulk-toast-close { width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
.bulk-toast-close:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
.toast-slide-enter-active, .toast-slide-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateX(-50%) translateY(20px); }

/* Sticky Table Header with Glassmorphism */
.table-scroll { max-height: 60vh; overflow-y: auto; border-radius: 8px; }
table { border-collapse: separate; border-spacing: 0; }
thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--bg-card);
  transition: all 0.2s ease;
  box-shadow: none;
  border-right: 1px solid var(--border-light);
}
thead th:last-child { border-right: none; }
.glass-header th {
  background: rgba(255, 255, 255, 0.6);
  backdrop-filter: blur(12px) saturate(180%);
  -webkit-backdrop-filter: blur(12px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
[data-theme="dark"] .glass-header th {
  background: rgba(30, 41, 59, 0.6);
  border-bottom: 1px solid rgba(148, 163, 184, 0.1);
}
</style>
