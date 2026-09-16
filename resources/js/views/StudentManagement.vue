<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Student Management</template></Navbar>

      <div class="content-area">
        <div class="card">
          <div class="card-header border-0 py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
              <div class="d-flex align-items-center py-1 gap-3">
                <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                  <i class="fas fa-user-graduate me-2 text-primary opacity-75"></i>
                  {{ props.defaultType ? (props.defaultType === 'regular' ? 'Regular Students' : 'Irregular Students') : 'Students' }}
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
                <div class="premium-filter-group" style="width: 180px">
                  <span class="input-group-text">
                    <i class="fas fa-search"></i>
                  </span>
                  <input
                    v-model="filters.query"
                    type="text"
                    class="form-control"
                    placeholder="Search student..."
                    @input="handleSearch"
                  />
                </div>

                <div class="premium-filter-group" style="width: 180px">
                  <CustomSelect
                    v-model="filters.course"
                    :options="courseOptions"
                    placeholder="All Courses"
                    @change="handleFilterChange"
                  />
                </div>

                <div v-if="props.defaultType !== 'irregular'" class="premium-filter-group" style="width: 140px">
                  <CustomSelect
                    v-model="filters.section_id"
                    :options="filterSectionOptions"
                    placeholder="All Sec"
                    :disabled="!filterSectionOptions.length"
                    @change="fetchStudents(1)"
                  />
                </div>

                <!-- Only show type filter if not locked by route prop -->
                <div v-if="!props.defaultType" class="premium-filter-group" style="width: 150px">
                  <CustomSelect
                    v-model="filters.student_type"
                    :options="[
                      { label: 'All Types', value: '' },
                      { label: 'Regular', value: 'regular' },
                      { label: 'Irregular', value: 'irregular' }
                    ]"
                    placeholder="All Types"
                    @change="fetchStudents(1)"
                  />
                </div>

                <button class="btn-premium-reset" @click="clearFilters" title="Reset Filters">
                  <i class="fas fa-undo-alt small"></i>
                </button>

                <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.1"></div>



                <!-- Action Buttons -->
                <button class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" @click="openUploadModal">
                  <i class="fas fa-file-csv"></i>
                  <span class="d-none d-xl-inline">Upload CSV</span>
                </button>
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="openAddModal">
                  <i class="fas fa-plus"></i>
                  <span class="d-none d-xl-inline">Add Student</span>
                </button>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <Transition name="fade" mode="out-in">
              <div v-if="loading" key="loading">
                <SkeletonLoader variant="table" :rows="8" :cols="12" />
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
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Type</th>
                    <th v-if="props.defaultType !== 'irregular'">Section</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(student, i) in students" :key="student?.id || i">
                    <td>
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" :value="student.id" v-model="selectedIds" />
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
                      {{ student.id_number || "N/A" }}
                    </td>
                    <td class="fw-semibold">
                      {{ student.name }}
                      <div
                        v-if="student.is_google_linked"
                        class="badge bg-success bg-opacity-10 text-success small ms-1"
                        style="font-size: 0.6rem"
                      >
                        <i class="fab fa-google"></i>
                      </div>
                    </td>
                    <td class="text-muted small">
                      {{ student.email }}
                    </td>
                    <td>
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ student.student?.course || "N/A" }}
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary">
                        {{ student.student?.year_level ? `${student.student.year_level} Year` : "—" }}
                      </span>
                    </td>
                    <td>
                      <span class="badge" :class="student.student?.student_type === 'irregular' ? 'bg-warning text-dark' : 'bg-info text-white'">
                        {{ student.student?.student_type || 'regular' }}
                      </span>
                    </td>
                    <td v-if="props.defaultType !== 'irregular'">
                      <span class="small fw-bold text-muted">
                        {{ student.student?.section_relationship?.name || student.student?.section || "N/A" }}
                      </span>
                    </td>
                    <td>
                      <span class="badge-status" :class="student.is_active ? 'active' : 'inactive'">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle"></i>
                        {{ student.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-2 justify-content-start flex-nowrap align-items-center">
                        <button 
                          v-if="student.student?.student_type === 'irregular'"
                          class="btn-icon-action success" 
                          @click="openEnrollmentModal(student)" 
                          title="Manage Enrollments"
                        >
                          <i class="fas fa-book"></i>
                        </button>
                        <button class="btn-icon-action primary" @click="openEditModal(student)" title="Edit Student">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button
                          class="btn-icon-action"
                          :class="student.is_active ? 'warning' : 'success'"
                          @click="toggleActive(student)"
                          :title="student.is_active ? 'Deactivate' : 'Activate'"
                        >
                          <i :class="student.is_active ? 'fas fa-ban' : 'fas fa-check-circle'"></i>
                        </button>

                        <button
                          class="btn-icon-action danger"
                          @click="deleteStudent(student.id)"
                          title="Delete Student"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!students.length">
                    <td colspan="10" class="text-center text-muted py-4">No student records yet.</td>
                  </tr>
                </tbody>
              </table>
              </div>
            </Transition>

            <!-- Pagination -->
            <Pagination
              :pagination="pagination"
              :per-page="perPage"
              @change-page="fetchStudents"
              @update:per-page="changePerPage"
            />
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div ref="studentModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-primary bg-opacity-10 text-primary">
                  <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                  <h5 class="modal-title fw-800 mb-0">
                    {{ editMode ? "Update Student Profile" : "Register New Student" }}
                  </h5>
                  <p class="text-muted small mb-0">
                    {{ editMode ? "Modify existing enrollment records" : "Enter student background information" }}
                  </p>
                </div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
              <div v-if="formError" class="alert alert-danger-custom mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ formError }}
              </div>

              <!-- Form Sections -->
              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-id-card me-2 text-primary opacity-50"></i>
                  Personal Information
                </div>
                <div class="row g-4">
                  <div class="col-md-6">
                    <label class="label-custom">ID Number *</label>
                    <input v-model="form.id_number" class="input-custom" placeholder="e.g. 2021-0001" />
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
                  <div class="col-md-12">
                    <label class="label-custom">Email Address *</label>
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
                </div>
              </div>

              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-graduation-cap me-2 text-primary opacity-50"></i>
                  Academic Track
                </div>
                <div class="row g-4">
                  <div :class="props.defaultType ? 'col-md-6' : 'col-md-4'">
                    <label class="label-custom">Target Course *</label>
                    <CustomSelect
                      v-model="form.course"
                      :options="availableCourses.map((c) => ({ label: c.name, value: c.name }))"
                      placeholder="-- Select course --"
                      @change="onCourseChange"
                    />
                  </div>
                  <div class="col-md-4" v-if="!props.defaultType">
                    <label class="label-custom">Student Type *</label>
                    <CustomSelect
                      v-model="form.student_type"
                      :options="[
                        { label: 'Regular', value: 'regular' },
                        { label: 'Irregular', value: 'irregular' }
                      ]"
                      placeholder="-- Select type --"
                    />
                  </div>
                  <div :class="props.defaultType ? 'col-md-6' : 'col-md-4'">
                    <label class="label-custom">Assigned Section *</label>
                    <CustomSelect
                      v-model="form.section_id"
                      :options="availableSectionsData.map((s) => ({ label: s.year_level ? `${s.name} (${s.year_level})` : s.name, value: s.id }))"
                      placeholder="-- Select section --"
                      :disabled="!availableSectionsData.length"
                      @change="onSectionChange"
                    />
                  </div>
                  <div :class="props.defaultType ? 'col-md-6' : 'col-md-4'">
                    <label class="label-custom">Year Level *</label>
                    <CustomSelect
                      v-model="form.year_level"
                      :options="yearLevelOptions"
                      placeholder="-- Select year --"
                    />
                  </div>
                </div>
              </div>

              <div class="form-section-modern">
                <div class="section-label mb-3">
                  <i class="fas fa-shield-alt me-2 text-primary opacity-50"></i>
                  Security Details
                </div>
                <div class="row g-4">
                  <div class="col-12">
                    <label class="label-custom">
                      {{ editMode ? "Reset Password" : "Initial Password *" }}
                      <span v-if="editMode" class="text-muted fw-normal ms-1">(leave blank to keep current)</span>
                    </label>
                    <div class="input-group-custom">
                      <span class="input-icon"><i class="fas fa-lock"></i></span>
                      <input
                        v-model="form.password"
                        type="password"
                        class="input-custom with-icon"
                        :placeholder="editMode ? 'Enter new password' : 'Min 6 characters'"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" @click="saveStudent" :disabled="saving">
                <i class="fas fa-save me-2"></i>
                {{ saving ? "Finalizing..." : editMode ? "Update Record" : "Save Changes" }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload CSV Modal -->
      <div ref="uploadModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="fas fa-file-upload me-2 text-success"></i>
                Upload CSV
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div v-if="uploadError" class="alert alert-danger small py-2">
                {{ uploadError }}
              </div>
              <div v-if="uploadSuccess" class="alert alert-success small py-2">
                {{ uploadSuccess }}
              </div>
              <p class="small text-muted mb-2">
                Ensure your CSV has exactly these headers:
                <strong>id number, last name, first name, middle name, course, section</strong>
                (optional extra column: <strong>year level</strong> — otherwise inherited from the section).
                . Default password will be the
                <strong>ID Number</strong>
                .
              </p>
              <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <button class="btn btn-outline-success btn-sm" @click="downloadTemplate" type="button">
                  <i class="fas fa-download me-2"></i>
                  Download CSV Template
                </button>
                <small class="text-muted">No need to type headers manually — includes an example row.</small>
              </div>
              <input type="file" ref="fileInput" class="form-control" accept=".csv" />
            </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-success" @click="uploadCsv" :disabled="uploading">
              <i class="fas fa-upload me-2"></i>
              {{ uploading ? "Uploading…" : "Upload" }}
            </button>
          </div>
        </div>
      </div>
      </div>
      <!-- Bulk Actions Toast -->
      <Transition name="toast-slide">
        <div v-if="selectedIds.length > 0" class="bulk-toast-bar">
          <div class="bulk-toast-inner">
            <div class="bulk-toast-count">
              <span class="bulk-toast-number">{{ selectedIds.length }}</span>
              students selected
            </div>
            <div class="bulk-toast-actions">
              <button class="btn-toast btn-toast-activate" @click="bulkChangeStatus(true)">
                <i class="fas fa-check-circle"></i> Activate
              </button>
              <button class="btn-toast btn-toast-deactivate" @click="bulkChangeStatus(false)">
                <i class="fas fa-ban"></i> Deactivate
              </button>
              <button class="btn-toast btn-toast-delete" @click="bulkDelete">
                <i class="fas fa-trash-alt"></i> Delete
              </button>
            </div>
            <button class="bulk-toast-close" @click="selectedIds = []">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </Transition>


      <EnrollmentModal
        v-if="showEnrollmentModal"
        :student="selectedStudentForEnrollment"
        @close="showEnrollmentModal = false"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";

import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";
import EnrollmentModal from "../components/EnrollmentModal.vue";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";

const props = defineProps({
  defaultType: {
    type: String,
    default: "",
  },
});

const students = ref([]);
const pagination = ref({});
const perPage = ref(10);

function changePerPage(n) {
  perPage.value = n;
  fetchStudents(1);
}
const availableCourses = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const editId = ref(null);
const saving = ref(false);
const formError = ref("");
const selectedIds = ref([]);
const tableScrolled = ref(false);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

const showEnrollmentModal = ref(false);
const selectedStudentForEnrollment = ref(null);

// Filter State
const filters = ref({
  query: "",
  course: "",
  section_id: "",
  student_type: props.defaultType || "",
});

watch(
  () => props.defaultType,
  (newType) => {
    filters.value.student_type = newType || "";
    fetchStudents(1);
  }
);

let searchTimeout = null;

// Upload State
const showUploadModal = ref(false);
const { modalEl: studentModalEl } = useBootstrapModal(showModal);
const { modalEl: uploadModalEl } = useBootstrapModal(showUploadModal);
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref("");
const uploadSuccess = ref("");



const yearLevelOptions = [
  { label: "1st Year", value: "1st" },
  { label: "2nd Year", value: "2nd" },
  { label: "3rd Year", value: "3rd" },
  { label: "4th Year", value: "4th" },
  { label: "Irregular", value: "Irregular" },
];

const blankForm = () => ({
  id_number: "",
  firstname: "",
  lastname: "",
  middlename: "",
  email: "",
  password: "",
  course: "",
  section: "",
  section_id: "",
  student_type: "regular",
  year_level: "",
});
const form = ref(blankForm());

const availableSectionsData = computed(() => {
  if (!form.value.course) return [];
  const course = availableCourses.value.find((c) => c.name === form.value.course);
  return course?.academic_sections || [];
});

const filterSections = computed(() => {
  if (!filters.value.course) return [];
  const course = availableCourses.value.find((c) => c.name === filters.value.course);
  return course?.academic_sections || [];
});

const courseOptions = computed(() => [
  { label: "All Courses", value: "" },
  ...availableCourses.value.map((c) => ({ label: c.name, value: c.name })),
]);

const filterSectionOptions = computed(() => [
  { label: "All Sec", value: "" },
  ...filterSections.value.map((s) => ({ label: s.name, value: s.id })),
]);

const selectAll = computed({
  get() {
    return students.value.length > 0 && selectedIds.value.length === students.value.length;
  },
  set(val) {
    if (val) {
      selectedIds.value = students.value.map((s) => s.id);
    } else {
      selectedIds.value = [];
    }
  },
});

function handleFilterChange() {
  filters.value.section_id = "";
  fetchStudents(1);
}

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchStudents(1);
  }, 500);
}

function clearFilters() {
  filters.value = {
    query: "",
    course: "",
    section_id: "",
    student_type: props.defaultType || "",
  };
  fetchStudents(1);
}

function onCourseChange() {
  form.value.section_id = "";
  form.value.section = "";
}

function onSectionChange() {
  const section = availableSectionsData.value.find((s) => s.id === form.value.section_id);
  if (section) {
    form.value.section = section.name;
    if (!form.value.year_level && section.year_level) {
      form.value.year_level = section.year_level;
    }
  } else {
    form.value.section = "";
  }
}

onMounted(() => {
  fetchStudents();
  fetchCourses();
});

async function fetchStudents(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page,
      per_page: perPage.value,
      query: filters.value.query,
      course: filters.value.course,
      section_id: filters.value.section_id,
      student_type: filters.value.student_type,
    });
    const res = await api.get(`/students?${params.toString()}`);
    students.value = res.data.data;
    pagination.value = res.data;
    selectedIds.value = [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function fetchCourses() {
  try {
    const res = await api.get("/courses");
    availableCourses.value = res.data;
  } catch (e) {
    console.error("Failed to fetch courses:", e);
  }
}

function openAddModal() {
  editMode.value = false;
  form.value = blankForm();
  if (props.defaultType) {
    form.value.student_type = props.defaultType;
  }
  formError.value = "";
  showModal.value = true;
}

function openEnrollmentModal(student) {
  selectedStudentForEnrollment.value = student;
  showEnrollmentModal.value = true;
}

function openEditModal(student) {
  editMode.value = true;
  editId.value = student.id;
  form.value = {
    id_number: student.id_number || "",
    firstname: student.firstname || "",
    lastname: student.lastname || "",
    middlename: student.middlename || "",
    email: student.email,
    course: student.student?.course || "",
    section_id: student.student?.section_id || "",
    student_type: student.student?.student_type || "regular",
    year_level: student.student?.year_level || "",
    password: "",
  };
  formError.value = "";
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}



async function saveStudent() {
  saving.value = true;
  formError.value = "";
  try {
    if (editMode.value) {
      await api.put(`/students/${editId.value}`, form.value);
    } else {
      await api.post("/students", form.value);
    }
    closeModal();
    await fetchStudents();
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

async function toggleActive(student) {
  const action = student.is_active ? "deactivate" : "activate";
  
  const result = await confirmAction({
    title: "Are you sure?",
    message: `Do you want to ${action} ${student.name}?`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.patch(`/students/${student.id}/toggle-active`);
    await fetchStudents();
    Swal.fire({
      icon: "success",
      title: "Success",
      text: `Student ${action}d successfully.`,
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (e) {
    Swal.fire("Error", "Failed to update status.", "error");
  }
}

async function unlinkGoogle(userId) {
  const result = await confirmAction({
    title: "Unlink Google Account?",
    message: "They will no longer be able to login with Google until they reconnect.",
  });

  if (!result.isConfirmed) return;

  try {
    await api.post(`/auth/google/unlink/${userId}`);
    await fetchStudents();
    Swal.fire("Unlinked!", "Google account has been unlinked.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to unlink Google account.", "error");
  }
}

async function deleteStudent(id) {
  const result = await confirmAction({
    title: "Are you sure?",
    message: "Delete this student and all their data? This cannot be undone.",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/students/${id}`);
    await fetchStudents();
    Swal.fire("Deleted!", "Student record has been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete.", "error");
  }
}

async function bulkDelete() {
  if (!selectedIds.value.length) return;
  
  const result = await confirmAction({
    title: "Bulk Delete?",
    message: `Delete ${selectedIds.value.length} selected students? This cannot be undone.`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.post("/students/bulk-delete", { ids: selectedIds.value });
    selectedIds.value = [];
    await fetchStudents();
    Swal.fire("Deleted!", "Selected students have been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to bulk delete.", "error");
  }
}

async function bulkChangeStatus(status) {
  if (!selectedIds.value.length) return;
  const action = status ? "activate" : "deactivate";
  
  const result = await confirmAction({
    title: "Bulk Update?",
    message: `Are you sure you want to bulk ${action} the selected students?`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.post("/students/bulk-status", { ids: selectedIds.value, status });
    selectedIds.value = [];
    await fetchStudents();
    Swal.fire("Updated!", `Selected students have been ${action}d.`, "success");
  } catch (e) {
    Swal.fire("Error", "Failed to update status.", "error");
  }
}

function openUploadModal() {
  uploadError.value = "";
  uploadSuccess.value = "";
  showUploadModal.value = true;
  if (fileInput.value) fileInput.value.value = null;
}

function downloadTemplate() {
  const headers = ["id number", "last name", "first name", "middle name", "course", "section", "year level"];
  const example = ["2021-00001", "Dela Cruz", "Juan", "Santos", "BSIT", "BSIT 1-A", "1st Year"];
  const csv = "\uFEFF" + headers.join(",") + "\n" + example.join(",") + "\n";
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = "students_template.csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

async function uploadCsv() {
  const file = fileInput.value?.files[0];
  if (!file) {
    uploadError.value = "Please select a CSV file.";
    return;
  }

  uploading.value = true;
  uploadError.value = "";
  uploadSuccess.value = "";

  const formData = new FormData();
  formData.append("file", file);

  try {
    const res = await api.post("/students/import", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    uploadSuccess.value = `Imported ${res.data.imported} students. Failed: ${res.data.failed}.`;
    await fetchStudents();
    fileInput.value.value = null; // reset
  } catch (e) {
    uploadError.value = e.response?.data?.message || "Upload failed.";
  } finally {
    uploading.value = false;
  }
}
</script>

<style scoped>
.premium-filter-group {
  display: flex;
  align-items: center;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0 12px;
  height: 40px;
  min-height: 40px;
  transition: all 0.2s;
}

.premium-filter-group:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

.premium-filter-group .input-group-text {
  background: transparent;
  border: none;
  padding: 0 8px 0 0;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.premium-filter-group .form-control {
  background: transparent;
  border: none;
  padding: 0 12px 0 0;
  font-size: 14px;
  font-weight: 500;
  min-height: 0;
}

.premium-filter-group .form-control:focus {
  box-shadow: none;
}

.btn-premium-reset {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  color: var(--text-muted);
  transition: all 0.2s;
}

.btn-premium-reset:hover {
  background: var(--primary);
  color: white !important;
  border-color: var(--primary);
}

.btn-premium-reset:hover i {
  color: white !important;
}

/* Deep override for CustomSelect when used in filters */
.premium-filter-group :deep(.custom-select-trigger) {
  border: none !important;
  background: transparent !important;
  padding: 0 12px 0 0 !important;
  font-size: 14px !important;
  min-height: 0 !important;
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

/* --- Premium Modal (Student Theme) --- */
.profile-avatar-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
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

.input-custom,
.select-custom {
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

.select-custom {
  padding-right: 2.5rem;
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 1rem center;
  background-size: 14px 10px;
  cursor: pointer;
}

.input-custom:focus,
.select-custom:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(25, 25, 112, 0.1);
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

.alert-danger-custom {
  padding: 1rem;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 12px;
  color: #ef4444;
  font-size: 0.85rem;
  font-weight: 600;
}

/* Bulk Actions Toast */
@property --border-angle {
  syntax: "<angle>";
  initial-value: 0deg;
  inherits: false;
}

.bulk-toast-bar {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  min-width: 480px;
  max-width: 90vw;
  padding: 2px;
  overflow: hidden;
}

.bulk-toast-bar::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 16px;
  background: conic-gradient(
    from var(--border-angle),
    #ffc107,
    #ff7b00,
    #191970,
    #232380,
    #232380,
    #191970,
    #ffc107
  );
  animation: spin-border 2s linear infinite;
  z-index: 0;
  mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  mask-composite: exclude;
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  padding: 2px;
}

@keyframes spin-border {
  to {
    --border-angle: 360deg;
  }
}

.bulk-toast-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--bg-card);
  border-radius: 14px;
  padding: 12px 20px;
}

.bulk-toast-count {
  color: var(--text-dark);
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}

.bulk-toast-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 800;
}

.bulk-toast-actions {
  display: flex;
  gap: 8px;
  flex: 1;
}

.btn-toast {
  padding: 6px 14px;
  border-radius: 8px;
  border: none;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

.btn-toast-activate {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}
.btn-toast-activate:hover {
  background: #22c55e;
  color: #fff;
}

.btn-toast-deactivate {
  background: rgba(234, 179, 8, 0.15);
  color: #eab308;
}
.btn-toast-deactivate:hover {
  background: #eab308;
  color: #fff;
}

.btn-toast-delete {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
.btn-toast-delete:hover {
  background: #ef4444;
  color: #fff;
}

.bulk-toast-close {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
.bulk-toast-close:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(20px);
}

/* Full-screen table layout — fill viewport height/width */
.main-wrapper {
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-height: 100vh;
  overflow: hidden;
}
.content-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: calc(100vh - 65px);
  width: 100%;
  max-width: 100%;
}
.content-area .card {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  width: 100%;
  max-width: 100%;
}
.card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
/* Sticky Table Header with Glassmorphism */
.table-scroll {
  flex: 1;
  min-height: 0;
  max-height: none;
  height: 100%;
  overflow: auto;
  border-radius: 8px;
  width: 100%;
  max-width: 100%;
}
table { border-collapse: separate; border-spacing: 0; width: 100%; }
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
  background: #161b22 !important;
  background-color: #161b22 !important;
  color: #b1bac4 !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
  box-shadow: none !important;
}

/* Dark-mode readability for header, filters, table, and upload modal */
[data-theme="dark"] h5.text-main i.text-primary {
  color: #79c0ff !important;
  opacity: 1 !important;
}
[data-theme="dark"] .badge.bg-primary.bg-opacity-10.text-primary {
  background: rgba(31, 111, 235, 0.18) !important;
  color: #79c0ff !important;
  border: 1px solid rgba(121, 192, 255, 0.25) !important;
}
[data-theme="dark"] .premium-filter-group .input-group-text,
[data-theme="dark"] .premium-filter-group .input-group-text i {
  color: #79c0ff !important;
  opacity: 1 !important;
}
[data-theme="dark"] .premium-filter-group .form-control {
  color: #e6edf3 !important;
}
[data-theme="dark"] .premium-filter-group .form-control::placeholder {
  color: #8b949e !important;
  opacity: 1 !important;
}
[data-theme="dark"] td.small.fw-bold.text-primary {
  color: #79c0ff !important;
}
[data-theme="dark"] .btn-outline-success i,
[data-theme="dark"] .btn-primary i,
[data-theme="dark"] .btn-success i {
  color: inherit !important;
}
[data-theme="dark"] .modal-title i.text-success {
  color: #3fb950 !important;
  opacity: 1 !important;
}
[data-theme="dark"] .modal-body .small.text-muted strong {
  color: #e6edf3 !important;
}
</style>
