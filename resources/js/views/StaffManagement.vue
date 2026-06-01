<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Staff Management</template></Navbar>
 
      <div class="content-area">
        <div class="card">
          <div class="card-header border-0 py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
              <div class="d-flex align-items-center py-1 gap-3">
                <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                  <i class="fas fa-user-tie me-2 text-primary opacity-75"></i>
                  Staff Directory
                </h5>
                <span
                  class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-700"
                  style="font-size: 0.75rem"
                >
                  {{ pagination.total || 0 }} Records
                </span>
              </div>
 
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <!-- Filters -->
                <div class="premium-filter-group" style="width: 200px">
                  <span class="input-group-text">
                    <i class="fas fa-search"></i>
                  </span>
                  <input
                    v-model="filters.query"
                    type="text"
                    class="form-control"
                    placeholder="Search staff..."
                    @input="handleSearch"
                  />
                </div>
 
 
                <button class="btn-premium-reset" @click="clearFilters" title="Reset Filters">
                  <i class="fas fa-redo-alt small"></i>
                </button>
 
                <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.1"></div>
 
                <!-- Bulk Actions Button -->
                <button
                  v-if="selectedIds.length > 0"
                  class="btn btn-light btn-sm px-3 shadow-sm animate__animated animate__pulse animate__infinite"
                  style="animation-duration: 2s"
                  @click="showBulkModal = true"
                >
                  <i class="fas fa-tasks me-2"></i>
                  Manage Selected ({{ selectedIds.length }})
                </button>
 
                <!-- Action Buttons -->
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="openAddModal">
                  <i class="fas fa-plus"></i>
                  <span class="d-none d-xl-inline">Add Staff</span>
                </button>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <Transition name="fade" mode="out-in">
              <div v-if="loading" key="loading" class="text-center py-5 text-muted">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
              </div>
              <table v-else key="table" class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style="width: 40px">
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" v-model="selectAll" />
                      </div>
                    </th>
                    <th>#</th>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(s, i) in staffList" :key="s.id">
                    <td>
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" :value="s.id" v-model="selectedIds" />
                      </div>
                    </td>
                    <td class="text-muted small">
                      {{
                        pagination?.current_page
                          ? (pagination.current_page - 1) * (pagination.per_page || 10) + i + 1
                          : i + 1
                      }}
                    </td>
                    <td class="small fw-bold text-primary">
                      {{ s.user?.id_number || "N/A" }}
                    </td>
                    <td class="fw-semibold">
                      {{ s.user?.name }}
                      <div
                        v-if="s.user?.is_google_linked"
                        class="badge bg-success bg-opacity-10 text-success small ms-1"
                        style="font-size: 0.6rem"
                      >
                        <i class="fab fa-google"></i>
                      </div>
                    </td>
                    <td class="text-muted small">{{ s.user?.email || "N/A" }}</td>
                    <td>{{ s.designation || "N/A" }}</td>
                    <td>
                      <span class="badge-status" :class="s.user?.is_active ? 'active' : 'inactive'">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle"></i>
                        {{ s.user?.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-2 justify-content-start flex-nowrap align-items-center">
                        <button class="btn-icon-action primary" @click="openEditModal(s)" title="Edit Staff">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button
                          class="btn-icon-action"
                          :class="s.user?.is_active ? 'warning' : 'success'"
                          @click="toggleActive(s)"
                          :title="s.user?.is_active ? 'Deactivate' : 'Activate'"
                        >
                          <i :class="s.user?.is_active ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                        </button>
                        <button class="btn-icon-action danger" @click="deleteStaff(s.id)" title="Delete Staff">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!staffList.length">
                    <td colspan="9" class="text-center text-muted py-4">No staff records yet.</td>
                  </tr>
                </tbody>
              </table>
            </Transition>
 
            <!-- Pagination -->
            <Pagination :pagination="pagination" @change-page="fetchStaff" />
          </div>
        </div>
      </div>
 
      <!-- Add/Edit Modal (Modernized) -->
      <Transition name="fade">
        <div v-if="showModal" class="modal-backdrop-custom" @click="closeModal"></div>
      </Transition>
 
      <Transition name="slide-up">
        <div v-if="showModal" class="custom-modal staff">
          <div class="card modal-card">
            <div class="modal-header-custom teal">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-info bg-opacity-10 text-info">
                  <i class="fas fa-user-tie"></i>
                </div>
                <div>
                  <h5 class="fw-800 mb-0">{{ editMode ? "Update Staff Profile" : "Register Staff Member" }}</h5>
                  <p class="text-muted small mb-0">
                    {{
                      editMode
                        ? "Modify professional assignment and credentials"
                        : "Add new administrative or support staff to the directory"
                    }}
                  </p>
                </div>
              </div>
              <button class="btn-close-custom" @click="closeModal">
                <i class="fas fa-times"></i>
              </button>
            </div>
 
            <div class="modal-body-custom">
              <div v-if="formError" class="alert alert-danger-custom mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ formError }}
              </div>
 
              <!-- Form Sections -->
              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-id-card me-2 text-info opacity-50"></i>
                  Personal Information
                </div>
                <div class="row g-4">
                  <div class="col-md-6">
                    <label class="label-custom">ID Number *</label>
                    <input v-model="form.id_number" class="input-custom" placeholder="e.g. STF-0001" />
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">Last Name *</label>
                    <input v-model="form.lastname" class="input-custom" placeholder="e.g. Dela Cruz" />
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">First Name *</label>
                    <input v-model="form.firstname" class="input-custom" placeholder="e.g. Juan" />
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">Middle Name</label>
                    <input v-model="form.middlename" class="input-custom" placeholder="Optional" />
                  </div>
                </div>
              </div>
 
              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-at me-2 text-info opacity-50"></i>
                  Contact & Security
                </div>
                <div class="row g-4">
                  <div class="col-md-6">
                    <label class="label-custom">Official Email *</label>
                    <div class="input-group-custom">
                      <span class="input-icon"><i class="fas fa-envelope"></i></span>
                      <input
                        v-model="form.email"
                        type="email"
                        class="input-custom with-icon"
                        placeholder="Leave blank for default"
                      />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="label-custom">
                      {{ editMode ? "New Password" : "Password *" }}
                    </label>
                    <div class="input-group-custom">
                      <span class="input-icon"><i class="fas fa-lock"></i></span>
                      <input
                        v-model="form.password"
                        type="password"
                        class="input-custom with-icon"
                        :placeholder="editMode ? 'Leave blank to keep' : 'Min 6 characters'"
                      />
                    </div>
                  </div>
                </div>
              </div>
 
              <div class="form-section-modern">
                <div class="section-label mb-3">
                  <i class="fas fa-briefcase me-2 text-info opacity-50"></i>
                  Employment Details
                </div>
                <div class="row g-4">
                  <div class="col-12">
                    <label class="label-custom">Designation *</label>
                    <input v-model="form.designation" class="input-custom" placeholder="e.g. Registrar Officer" />
                  </div>
                </div>
              </div>
            </div>
 
            <div class="modal-footer-custom bg-light bg-opacity-25">
              <button class="btn btn-light-custom px-4" @click="closeModal">Discard</button>
              <button class="btn btn-primary-custom px-4" @click="saveStaff" :disabled="saving">
                <i class="fas fa-save me-2"></i>
                {{ saving ? "Processing..." : editMode ? "Save Changes" : "Register Staff" }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
 
      <!-- Bulk Actions Modal -->
      <div
        v-if="showBulkModal"
        class="modal d-flex"
        style="
          background: rgba(0, 0, 0, 0.6);
          backdrop-filter: blur(4px);
          position: fixed;
          inset: 0;
          z-index: 2050;
          align-items: center;
          justify-content: center;
        "
      >
        <div
          class="card border-0 shadow-lg animate__animated animate__zoomIn"
          style="width: 420px; max-width: 95vw; overflow: hidden"
        >
          <div class="card-header bg-light text-white d-flex justify-content-between align-items-center py-3 px-4">
            <h6 class="mb-0 fw-bold text-dark">
              <i class="fas fa-users-cog me-2"></i>
              Bulk Management
            </h6>
            <button class="btn-close btn-close-dark" @click="showBulkModal = false"></button>
          </div>
          <div class="card-body p-4 text-center">
            <div class="mb-4">
              <div class="display-6 fw-bold text-primary mb-1">{{ selectedIds.length }}</div>
              <div class="text-muted small text-uppercase tracking-wider fw-semibold">Staff Selected</div>
            </div>
 
            <div class="d-grid gap-3">
              <button
                class="btn btn-outline-success py-3 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2"
                @click="bulkChangeStatus(true)"
              >
                <i class="fas fa-check-circle fa-lg"></i>
                Activate All Selected
              </button>
              <button
                class="btn btn-outline-warning py-3 rounded-3 fw-bold shadow-sm text-dark d-flex align-items-center justify-content-center gap-2"
                @click="bulkChangeStatus(false)"
              >
                <i class="fas fa-ban fa-lg"></i>
                Deactivate All Selected
              </button>
              <div class="py-2"><hr class="my-0 opacity-10" /></div>
              <button
                class="btn btn-danger py-3 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2"
                @click="bulkDelete"
              >
                <i class="fas fa-trash-alt fa-lg"></i>
                Delete Permanently
              </button>
            </div>
          </div>
          <div class="card-footer bg-light border-0 py-3 text-center">
            <button
              class="btn btn-link text-muted text-decoration-none small fw-semibold"
              @click="showBulkModal = false"
            >
              Cancel and Close
            </button>
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
import CustomSelect from "../components/CustomSelect.vue";
import Pagination from "../components/Pagination.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
 
const staffList = ref([]);
const pagination = ref({});
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const editId = ref(null);
const saving = ref(false);
const formError = ref("");
const selectedIds = ref([]);
const showBulkModal = ref(false);
 
// Filter State
const filters = ref({
  query: "",
});
 
let searchTimeout = null;
 
const blankForm = () => ({
  id_number: "",
  firstname: "",
  lastname: "",
  middlename: "",
  email: "",
  password: "",
  designation: "",
});
const form = ref(blankForm());
 

 
const selectAll = computed({
  get() {
    return staffList.value.length > 0 && selectedIds.value.length === staffList.value.length;
  },
  set(val) {
    if (val) {
      selectedIds.value = staffList.value.map((s) => s.id);
    } else {
      selectedIds.value = [];
    }
  },
});
 
function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchStaff(1);
  }, 500);
}
 
function clearFilters() {
  filters.value = {
    query: "",
  };
  fetchStaff(1);
}
 
onMounted(() => {
  fetchStaff();
});
 
async function fetchStaff(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page,
      query: filters.value.query,
    });
    const res = await api.get(`/staff?${params.toString()}`);
    staffList.value = res.data.data;
    pagination.value = res.data;
    selectedIds.value = [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}
 
function openAddModal() {
  editMode.value = false;
  form.value = blankForm();
  formError.value = "";
  showModal.value = true;
}
 
function openEditModal(s) {
  editMode.value = true;
  editId.value = s.id;
  form.value = {
    id_number: s.user?.id_number || "",
    firstname: s.user?.firstname || "",
    lastname: s.user?.lastname || "",
    middlename: s.user?.middlename || "",
    email: s.user?.email || "",
    designation: s.designation || "",
    password: "",
  };
  formError.value = "";
  showModal.value = true;
}
 
function closeModal() {
  showModal.value = false;
}
 
async function saveStaff() {
  saving.value = true;
  formError.value = "";
  try {
    if (editMode.value) {
      await api.put(`/staff/${editId.value}`, form.value);
    } else {
      await api.post("/staff", form.value);
    }
    closeModal();
    await fetchStaff();
    Swal.fire({
      icon: "success",
      title: "Success",
      text: editMode.value ? "Staff account updated." : "Staff account created.",
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      formError.value = Object.values(errors).flat().join(" ");
    } else {
      formError.value = e.response?.data?.message || "Failed to save.";
    }
  } finally {
    saving.value = false;
  }
}
 
async function toggleActive(s) {
  const action = s.user?.is_active ? "deactivate" : "activate";
  
  const result = await Swal.fire({
    title: "Are you sure?",
    text: `Do you want to ${action} ${s.user?.name}?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#3b82f6",
    confirmButtonText: `Yes, ${action}!`,
    background: document.documentElement.getAttribute("data-theme") === "dark" ? "#1e293b" : "#fff",
    color: document.documentElement.getAttribute("data-theme") === "dark" ? "#f1f5f9" : "#1e293b",
  });
 
  if (!result.isConfirmed) return;
 
  try {
    await api.patch(`/staff/${s.id}/toggle-active`);
    await fetchStaff();
    Swal.fire({
      icon: "success",
      title: "Updated",
      text: `Staff status ${action}d successfully.`,
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (e) {
    Swal.fire("Error", "Failed to update status.", "error");
  }
}
 
async function deleteStaff(id) {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Delete this staff record and all related data? This cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });
 
  if (!result.isConfirmed) return;
 
  try {
    await api.delete(`/staff/${id}`);
    await fetchStaff();
    Swal.fire("Deleted!", "Staff record has been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete.", "error");
  }
}
 
async function bulkDelete() {
  if (!selectedIds.value.length) return;
  
  const result = await Swal.fire({
    title: "Bulk Delete?",
    text: `Delete ${selectedIds.value.length} selected staff members? This cannot be undone.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete all!",
  });
 
  if (!result.isConfirmed) return;
 
  try {
    await api.post("/staff/bulk-delete", { ids: selectedIds.value });
    showBulkModal.value = false;
    await fetchStaff();
    Swal.fire("Deleted!", "Selected staff records deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete selected items.", "error");
  }
}
 
async function bulkChangeStatus(status) {
  if (!selectedIds.value.length) return;
  const statusText = status ? "activate" : "deactivate";
  
  const result = await Swal.fire({
    title: `Bulk ${statusText}?`,
    text: `Do you want to ${statusText} all ${selectedIds.value.length} selected staff members?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#3b82f6",
    confirmButtonText: "Yes, proceed",
  });
 
  if (!result.isConfirmed) return;
 
  try {
    await api.post("/staff/bulk-status", { ids: selectedIds.value, status });
    showBulkModal.value = false;
    await fetchStaff();
    Swal.fire("Success", "Selected staff statuses updated.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to update selected items.", "error");
  }
}
</script>
 
<style scoped>
.premium-filter-group {
  display: flex;
  align-items: center;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 12px;
  padding: 0 0.75rem;
  transition: all 0.2s;
}

.premium-filter-group:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(0, 82, 255, 0.1);
}

.premium-filter-group .input-group-text {
  background: transparent;
  border: none;
  padding: 0;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.premium-filter-group .form-control {
  background: transparent;
  border: none;
  padding: 0.6rem 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.premium-filter-group .form-control:focus {
  box-shadow: none;
}

.btn-premium-reset {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 12px;
  color: var(--text-muted);
  transition: all 0.2s;
}

/* Deep override for CustomSelect when used in filters */
.premium-filter-group :deep(.custom-select-trigger) {
  border: none !important;
  background: transparent !important;
  padding: 0.6rem 0.5rem !important;
  font-size: 0.85rem !important;
  box-shadow: none !important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translateY(30px);
}

/* --- Premium Modal --- */
.modal-backdrop-custom {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(8px);
  z-index: 2000;
}

.custom-modal {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 2001;
  pointer-events: none;
  overflow-y: auto;
  padding: 3rem 1rem;
}

.custom-modal .card {
  margin: auto;
  pointer-events: all;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 2rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  width: 600px;
  max-width: 95vw;
  overflow: visible;
}

.modal-header-custom {
  padding: 1.75rem 2rem;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header-custom.teal {
  border-top-left-radius: 2rem;
  border-top-right-radius: 2rem;
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.05) 0%, rgba(14, 116, 144, 0.08) 100%);
}

.profile-avatar-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.modal-body-custom {
  padding: 2rem;
  overflow: visible;
}

.modal-footer-custom {
  padding: 1.25rem 2rem;
  background: rgba(0, 0, 0, 0.02);
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.form-section-modern .section-label {
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

/* --- Premium Inputs --- */
.label-custom {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  margin-bottom: 0.65rem;
  margin-left: 0.25rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.input-custom {
  width: 100%;
  padding: 0.75rem 1.15rem;
  border-radius: 12px;
  border: 2px solid var(--border-light);
  background: var(--bg-card);
  color: var(--text-dark);
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.input-custom:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(10, 39, 138, 0.1);
}

.input-group-custom {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 1rem;
  color: var(--text-muted);
  font-size: 0.9rem;
  opacity: 0.6;
}

.input-custom.with-icon {
  padding-left: 2.75rem;
}

.btn-primary-custom {
  background: #3b82f6 !important;
  color: white !important;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 700;
  transition: all 0.2s ease;
}

.btn-primary-custom:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 15px -5px rgba(59, 130, 246, 0.5);
  background: #2563eb !important;
}

.btn-light-custom {
  background: var(--border-light);
  color: var(--text-main);
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 700;
}

.btn-close-custom {
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 1.25rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-close-custom:hover {
  color: var(--danger);
  transform: rotate(90deg);
}

.alert-danger-custom {
  padding: 1rem;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 12px;
  color: #ef4444;
  font-size: 0.85rem;
  font-weight: 600;
}

[data-theme="dark"] .modal-backdrop-custom {
  background: rgba(0, 0, 0, 0.7);
}

[data-theme="dark"] .modal-header-custom,
[data-theme="dark"] .modal-footer-custom {
  border-color: rgba(255, 255, 255, 0.05);
}
</style>
