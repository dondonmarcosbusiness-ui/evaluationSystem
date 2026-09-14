<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Faculty Management</template></Navbar>

      <div class="content-area">
        <div class="card">
          <div class="card-header border-0 py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
              <div class="d-flex align-items-center py-1 gap-3">
                <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                  <i class="fas fa-chalkboard-teacher me-2 text-primary opacity-75"></i>
                  Faculty
                </h5>
                <span
                  class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-700"
                  style="font-size: 0.75rem"
                >
                  {{ faculty.length }} Records
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
                    placeholder="Search faculty..."
                    @input="handleSearch"
                  />
                </div>

                <div class="premium-filter-group" style="width: 180px">
                  <CustomSelect
                    v-model="filters.department"
                    :options="filterDeptOptions"
                    placeholder="All Departments"
                    @change="fetchFaculty(1)"
                  />
                </div>

                <button class="btn-premium-reset" @click="clearFilters" title="Reset Filters">
                  <i class="fas fa-redo-alt small"></i>
                </button>

                <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.1"></div>



                <!-- Action Buttons -->
                <button class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" @click="openUploadModal">
                  <i class="fas fa-file-csv"></i>
                  <span class="d-none d-xl-inline">Upload CSV</span>
                </button>
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-2" @click="openAddModal">
                  <i class="fas fa-plus"></i>
                  <span class="d-none d-xl-inline">Add Faculty</span>
                </button>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <Transition name="fade" mode="out-in">
              <div v-if="loading" key="loading">
                <SkeletonLoader variant="table" :rows="8" :cols="10" />
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
                    <th>Department</th>
                    <th>Course</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(f, i) in faculty" :key="f.id">
                    <td>
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" :value="f.id" v-model="selectedIds" />
                      </div>
                    </td>
                    <td class="text-muted small">
                      {{
                        pagination?.current_page
                          ? (pagination.current_page - 1) * (pagination.per_page || 10) + i + 1
                          : i + 1
                      }}
                    </td>
                    <td class="small fw-bold text-indigo">
                      {{ f.user?.id_number || "N/A" }}
                    </td>
                    <td class="fw-semibold">
                      {{ f.user?.name }}
                      <div
                        v-if="f.user?.is_google_linked"
                        class="badge bg-success bg-opacity-10 text-success small ms-1"
                        style="font-size: 0.6rem"
                      >
                        <i class="fab fa-google"></i>
                      </div>
                    </td>
                    <td class="text-muted small">
                      {{ f.user?.email }}
                    </td>
                    <td>{{ f.department }}</td>
                    <td>
                      <span
                        class="badge"
                        :class="
                          f.course && f.course.includes('All Course')
                            ? 'badge-all-course'
                            : 'bg-primary bg-opacity-10 text-primary'
                        "
                      >
                        {{ f.course || "N/A" }}
                      </span>
                    </td>
                    <td>{{ f.position }}</td>
                    <td>
                      <span class="badge-status" :class="f.user?.is_active ? 'active' : 'inactive'">
                        <i class="fas fa-circle me-1"></i>
                        {{ f.user?.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td class="text-center">
                      <div
                        class="dropdown action-dropdown"
                        :class="{ show: openMenuId === f.id, 'drop-up': i >= faculty.length - 2 }"
                      >
                        <button
                          class="action-trigger"
                          @click.stop="toggleMenu(f.id)"
                          title="Actions"
                          aria-label="Row actions"
                        >
                          <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div v-show="openMenuId === f.id" class="action-menu">
                          <button class="action-menu-item" @click="openEditModal(f)">
                            <span>Edit</span>
                          </button>
                          <button class="action-menu-item" @click="toggleActive(f)">
                            <span>{{ f.user?.is_active ? "Deactivate" : "Activate" }}</span>
                          </button>
                          <button class="action-menu-item" @click="openRBACModal(f.user)">
                            <span>Manage Access</span>
                          </button>
                          <button class="action-menu-item danger" @click="deleteFaculty(f.id)">
                            <span>Delete</span>
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!faculty.length">
                    <td colspan="10" class="text-center text-muted py-4">No faculty records yet.</td>
                  </tr>
                </tbody>
              </table>
              </div>
            </Transition>

            <!-- Pagination -->
            <Pagination
              :pagination="pagination"
              :per-page="perPage"
              @change-page="fetchFaculty"
              @update:per-page="changePerPage"
            />
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div ref="facultyModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-indigo bg-opacity-10 text-indigo">
                  <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                  <h5 class="modal-title fw-800 mb-0">
                    {{ editMode ? "Update Faculty Profile" : "Register Faculty Member" }}
                  </h5>
                  <p class="text-muted small mb-0">
                    {{
                      editMode
                        ? "Modify professional assignment and credentials"
                        : "Add new instructor to the academic roster"
                    }}
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
                  <i class="fas fa-user-tie me-2 text-indigo opacity-50"></i>
                  Legal Name
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
                </div>
              </div>

              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-at me-2 text-indigo opacity-50"></i>
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

              <div class="form-section-modern mb-4">
                <div class="section-label mb-3">
                  <i class="fas fa-briefcase me-2 text-indigo opacity-50"></i>
                  Teaching Assignment
                </div>
                <div class="row g-4">
                  <div class="col-md-12">
                    <label class="label-custom">Departmental Affiliation *</label>
                    <CustomSelect
                      v-model="form.department"
                      :options="modalDeptOptions"
                      placeholder="-- Select department --"
                      @change="onDepartmentChange"
                    />
                  </div>

                  <div class="col-md-12" v-if="form.department === 'General Education'">
                    <label class="label-custom d-flex justify-content-between align-items-center">
                      <span>Courses Taught *</span>
                      <span
                        class="badge bg-indigo bg-opacity-10 text-indigo py-1 px-2 rounded-pill fw-bold"
                        style="font-size: 0.65rem"
                      >
                        {{ form.coursesArray.length }} Selected
                      </span>
                    </label>
                    <div class="course-selection-box p-3 mt-1 border rounded-4 bg-light bg-opacity-25">
                      <div class="row g-2">
                        <div class="col-sm-6" v-for="cName in allAvailableCourses" :key="cName">
                          <div class="course-check-item" :class="{ active: form.coursesArray.includes(cName) }">
                            <input
                              class="form-check-input hidden-check"
                              type="checkbox"
                              :value="cName"
                              v-model="form.coursesArray"
                              :id="'chk_' + cName.replace(/[^a-zA-Z0-9]/g, '')"
                              @change="handleCourseSelection(cName)"
                            />
                            <label
                              class="course-check-label w-100 px-3 py-2 rounded-3"
                              :for="'chk_' + cName.replace(/[^a-zA-Z0-9]/g, '')"
                            >
                              <i
                                class="fas"
                                :class="
                                  form.coursesArray.includes(cName)
                                    ? 'fa-check-circle me-2'
                                    : 'fa-circle me-2 opacity-25'
                                "
                              ></i>
                              {{ cName }}
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6" v-else>
                    <label class="label-custom">Specialization Course *</label>
                    <CustomSelect
                      v-model="form.course"
                      :options="modalCourseOptions"
                      placeholder="-- Select course --"
                      :disabled="!form.department"
                    />
                  </div>

                  <div class="col-md-6">
                    <label class="label-custom">Academic Position *</label>
                    <CustomSelect
                      v-model="form.position"
                      :options="modalPositionOptions"
                      placeholder="-- Select position --"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Discard</button>
              <button type="button" class="btn btn-primary" @click="saveFaculty" :disabled="saving">
                <i class="fas fa-save me-2"></i>
                {{ saving ? "Processing..." : editMode ? "Save Changes" : "Register Faculty" }}
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
                <strong>id number, last name, first name, middle name, position, department, course</strong>
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
              faculty selected
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

      <!-- RBAC Modal -->
      <UserRBACModal
        :show="showRBACModal"
        :user="selectedUser"
        @close="showRBACModal = false"
        @updated="fetchFaculty"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import UserRBACModal from "../components/UserRBACModal.vue";
import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";

const faculty = ref([]);
const pagination = ref({});
const perPage = ref(10);
const availableCourses = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editMode = ref(false);
const editId = ref(null);
const saving = ref(false);
const formError = ref("");
const selectedIds = ref([]);
const tableScrolled = ref(false);
const openMenuId = ref(null);

function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
  openMenuId.value = null;
}

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

// Filter State
const filters = ref({
  query: "",
  department: "",
});

let searchTimeout = null;

// Upload State
const showUploadModal = ref(false);
const { modalEl: facultyModalEl } = useBootstrapModal(showModal);
const { modalEl: uploadModalEl } = useBootstrapModal(showUploadModal);
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref("");
const uploadSuccess = ref("");

// RBAC State
const showRBACModal = ref(false);
const selectedUser = ref(null);

const blankForm = () => ({
  id_number: "",
  firstname: "",
  lastname: "",
  middlename: "",
  email: "",
  password: "",
  department: "",
  course: "",
  coursesArray: [],
  position: "",
});
const form = ref(blankForm());

const availableDepartments = computed(() => {
  const depts = availableCourses.value.map((c) => c.department).filter((d) => !!d);
  const uniqueDepts = [...new Set(depts)];
  if (!uniqueDepts.includes("General Education")) uniqueDepts.push("General Education");
  return uniqueDepts.sort();
});

const filterDeptOptions = computed(() => [
  { label: "All Departments", value: "" },
  ...availableDepartments.value.map((d) => ({ label: d, value: d })),
]);

const modalDeptOptions = computed(() => [
  { label: "-- Select department --", value: "" },
  ...availableDepartments.value.map((d) => ({ label: d, value: d })),
]);

const modalCourseOptions = computed(() => [
  { label: "-- Select course --", value: "" },
  { label: "All Course", value: "All Course" },
  ...filteredCourses.value.map((c) => ({ label: c.name, value: c.name })),
]);

const modalPositionOptions = [
  { label: "-- Select position --", value: "" },
  { label: "Instructor I", value: "Instructor I" },
  { label: "Instructor II", value: "Instructor II" },
  { label: "Instructor III", value: "Instructor III" },
  { label: "Assistant Professor I", value: "Assistant Professor I" },
  { label: "Assistant Professor II", value: "Assistant Professor II" },
  { label: "Associate Professor I", value: "Associate Professor I" },
  { label: "Professor I", value: "Professor I" },
  { label: "Part-time Instructor", value: "Part-time Instructor" },
];

const filteredCourses = computed(() => {
  if (!form.value.department) return [];
  return availableCourses.value.filter((c) => c.department === form.value.department && c.name !== "All Course");
});

const allAvailableCourses = computed(() => {
  const names = availableCourses.value.map((c) => c.name).filter((n) => n !== "All Course" && n);
  const unique = [...new Set(names)].sort();
  return ["All Course", ...unique];
});

const selectAll = computed({
  get() {
    return faculty.value.length > 0 && selectedIds.value.length === faculty.value.length;
  },
  set(val) {
    if (val) {
      selectedIds.value = faculty.value.map((f) => f.id);
    } else {
      selectedIds.value = [];
    }
  },
});

function onDepartmentChange() {
  form.value.course = "";
  form.value.coursesArray = [];
}

function handleCourseSelection(selectedName) {
  if (selectedName === "All Course") {
    const isChecked = form.value.coursesArray.includes("All Course");
    if (isChecked) {
      // Select all
      form.value.coursesArray = [...allAvailableCourses.value];
    } else {
      // Deselect all
      form.value.coursesArray = [];
    }
  } else {
    // If a specific course is unchecked, ensure "All Course" is also unchecked
    const isChecked = form.value.coursesArray.includes(selectedName);
    const allCourseIdx = form.value.coursesArray.indexOf("All Course");

    if (!isChecked && allCourseIdx !== -1) {
      form.value.coursesArray.splice(allCourseIdx, 1);
    }

    // If all specific courses are manually checked, auto-check "All Course"
    if (form.value.coursesArray.length === allAvailableCourses.value.length - 1 && allCourseIdx === -1) {
      form.value.coursesArray.push("All Course");
    }
  }
}

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchFaculty(1);
  }, 500);
}

function clearFilters() {
  filters.value = {
    query: "",
    department: "",
  };
  fetchFaculty(1);
}

onMounted(() => {
  fetchFaculty();
  fetchCourses();
  document.addEventListener("click", closeMenu);
});

onUnmounted(() => {
  document.removeEventListener("click", closeMenu);
});

function changePerPage(n) {
  perPage.value = n;
  fetchFaculty(1);
}

async function fetchFaculty(page = 1) {  loading.value = true;
  closeMenu();
  try {
    const params = new URLSearchParams({
      page,
      per_page: perPage.value,
      query: filters.value.query,
      department: filters.value.department,
    });
    const res = await api.get(`/faculty?${params.toString()}`);
    faculty.value = res.data.data;
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
  formError.value = "";
  showModal.value = true;
}

function openEditModal(f) {
  closeMenu();
  editMode.value = true;
  editId.value = f.id;
  let coursesArr = [];
  if (f.department === "General Education" && f.course) {
    coursesArr = f.course.split(",").map((s) => s.trim());
  }
  form.value = {
    id_number: f.user?.id_number || "",
    firstname: f.user?.firstname || "",
    lastname: f.user?.lastname || "",
    middlename: f.user?.middlename || "",
    email: f.user?.email,
    department: f.department,
    course: f.department !== "General Education" ? f.course : "",
    coursesArray: coursesArr,
    position: f.position,
    password: "",
  };
  formError.value = "";
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function openRBACModal(user) {
  closeMenu();
  selectedUser.value = user;
  showRBACModal.value = true;
}

async function saveFaculty() {
  saving.value = true;
  formError.value = "";
  try {
    const payload = { ...form.value };
    if (payload.department === "General Education") {
      payload.course = payload.coursesArray.join(", ");
      if (!payload.course) {
        formError.value = "Please select at least one Course.";
        saving.value = false;
        return;
      }
    }

    if (editMode.value) {
      await api.put(`/faculty/${editId.value}`, payload);
    } else {
      await api.post("/faculty", payload);
    }
    closeModal();
    await fetchFaculty();
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

async function toggleActive(f) {
  closeMenu();
  const action = f.user?.is_active ? "deactivate" : "activate";
  
  const result = await confirmAction({
    title: "Are you sure?",
    message: `Do you want to ${action} ${f.user?.name}?`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.patch(`/faculty/${f.id}/toggle-active`);
    await fetchFaculty();
    Swal.fire({
      icon: "success",
      title: "Updated",
      text: `Faculty status ${action}d successfully.`,
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
    await fetchFaculty();
    Swal.fire("Unlinked!", "Google account has been unlinked.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to unlink Google account.", "error");
  }
}

async function deleteFaculty(id) {
  closeMenu();
  const result = await confirmAction({
    title: "Are you sure?",
    message: "Delete this faculty and all their data? This cannot be undone.",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/faculty/${id}`);
    await fetchFaculty();
    Swal.fire("Deleted!", "Faculty record has been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete.", "error");
  }
}

async function bulkDelete() {
  if (!selectedIds.value.length) return;
  
  const result = await confirmAction({
    title: "Bulk Delete?",
    message: `Delete ${selectedIds.value.length} selected faculty? This cannot be undone.`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.post("/faculty/bulk-delete", { ids: selectedIds.value });
    selectedIds.value = [];
    await fetchFaculty();
    Swal.fire("Deleted!", "Selected faculty records have been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to bulk delete.", "error");
  }
}

async function bulkChangeStatus(status) {
  if (!selectedIds.value.length) return;
  const action = status ? "activate" : "deactivate";
  
  const result = await confirmAction({
    title: "Bulk Update?",
    message: `Are you sure you want to bulk ${action} the selected faculty?`,
  });

  if (!result.isConfirmed) return;

  try {
    await api.post("/faculty/bulk-status", { ids: selectedIds.value, status });
    selectedIds.value = [];
    await fetchFaculty();
    Swal.fire("Updated!", `Selected faculty have been ${action}d.`, "success");
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
  const headers = ["id number", "last name", "first name", "middle name", "position", "department", "course"];
  const example = ["2021-F001", "Dela Cruz", "Maria", "Santos", "Instructor I", "IT Department", "BSIT"];
  const csv = "\uFEFF" + headers.join(",") + "\n" + example.join(",") + "\n";
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = "faculty_template.csv";
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
    const res = await api.post("/faculty/import", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    uploadSuccess.value = `Imported ${res.data.imported} faculty. Failed: ${res.data.failed}.`;
    await fetchFaculty();
    fileInput.value.value = null; // reset
  } catch (e) {
    uploadError.value = e.response?.data?.message || "Upload failed.";
  } finally {
    uploading.value = false;
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* --- Premium Modal (Faculty Theme - Indigo) --- */
:root {
  --indigo: #232380;
}

.bg-indigo {
  background-color: var(--indigo) !important;
}
.text-indigo {
  color: var(--indigo) !important;
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
  border-color: var(--indigo);
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

.course-selection-box {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid var(--border-light);
}

.course-check-item {
  transition: all 0.2s ease;
}

.course-check-label {
  cursor: pointer;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  transition: all 0.2s ease;
}

.course-check-item.active .course-check-label {
  background: rgba(25, 25, 112, 0.1);
  border-color: var(--indigo);
  color: var(--indigo);
}

.hidden-check {
  display: none;
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

.badge-all-course {
  background: linear-gradient(135deg, #232380 0%, #191970 100%);
  color: white;
  box-shadow: 0 2px 4px rgba(25, 25, 112, 0.2);
  border: none;
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
