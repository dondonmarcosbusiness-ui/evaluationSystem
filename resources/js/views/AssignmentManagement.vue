<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Faculty Assignments</template></Navbar>

      <div class="content-area">
        <!-- Premium Filters & Action Bar -->
        <div class="stats-bar-premium mb-5 fade-in-up">
          <div
            class="d-flex align-items-center justify-content-between flex-wrap gap-4 px-4 py-3 rounded-4 shadow-sm bg-card border border-light"
          >
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div class="search-premium-box v2">
                <i class="fas fa-search"></i>
                <input v-model="filters.query" type="text" placeholder="Search professor..." @input="handleSearch" />
              </div>

              <div class="filter-dropdown-premium">
                <CustomSelect
                  v-model="filters.department"
                  :options="deptOptions"
                  placeholder="All Departments"
                  @change="fetchAssignments(1)"
                />
              </div>

              <div class="filter-dropdown-premium sm">
                <CustomSelect
                  v-model="filters.semester"
                  :options="semesterOptions"
                  placeholder="All Semesters"
                  @change="fetchAssignments(1)"
                />
              </div>

              <button class="btn-reset-premium" @click="clearFilters" title="Reset Filters">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>

            <button class="btn btn-primary-glass px-4 rounded-pill shadow-sm" @click="openAddModal">
              <i class="fas fa-plus-circle me-2"></i>
              New Assignment
            </button>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="cards" :rows="8" />
        </div>

        <!-- Faculty Assignment Grid -->
        <div v-else class="row g-4 fade-in-up">
          <TransitionGroup name="grid-stagger">
            <div v-for="group in paginatedFacultyGroups" :key="group.facultyId" class="col-md-6 col-lg-4 col-xl-3">
              <div class="faculty-card-premium" @click="handleFacultyClick(group)">
                <div class="card-glow"></div>
                <div class="faculty-card-inner p-4 h-100 d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="faculty-avatar-box">
                      <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="load-badge">{{ group.assignments.length }} LOADS</div>
                  </div>

                  <h5 class="faculty-name-v3 mb-1 fw-800">{{ group.facultyName }}</h5>
                  <span class="faculty-dept-v3 mb-4">{{ group.department }}</span>

                  <div class="mt-auto pt-3 border-top border-light">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex -space-x-2">
                        <div v-for="i in Math.min(group.assignments.length, 3)" :key="i" class="mini-load-dot"></div>
                        <div v-if="group.assignments.length > 3" class="mini-load-more">
                          +{{ group.assignments.length - 3 }}
                        </div>
                      </div>
                      <span class="view-details-link small fw-800 text-uppercase ls-1">
                        Manage Load
                        <i class="fas fa-chevron-right ms-1"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </TransitionGroup>

          <!-- Pagination -->
          <div v-if="totalFacultyPages > 1" class="col-12 mt-4 d-flex justify-content-center">
            <div
              class="pagination-premium d-flex align-items-center gap-3 bg-card p-2 rounded-pill shadow-sm border border-light"
            >
              <button class="btn btn-icon-sm" :disabled="facultyPage === 1" @click="facultyPage--">
                <i class="fas fa-chevron-left"></i>
              </button>
              <span class="small fw-800 px-2">Page {{ facultyPage }} of {{ totalFacultyPages }}</span>
              <button class="btn btn-icon-sm" :disabled="facultyPage === totalFacultyPages" @click="facultyPage++">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!paginatedFacultyGroups.length && !loading" class="col-12 text-center py-5">
            <i class="fas fa-user-slash fa-4x opacity-10 mb-3"></i>
            <h5 class="fw-800">No Assignments Found</h5>
            <p class="text-muted small">Try different filters or create a new assignment.</p>
          </div>
        </div>

        <!-- Faculty Load Drawer -->
        <div v-if="showDrawer" class="drawer-overlay" @click="closeDrawer"></div>
        <Transition name="drawer-slide">
          <div v-if="showDrawer" class="load-drawer">
            <div
              class="drawer-header p-4 d-flex align-items-center justify-content-between border-bottom sticky-top bg-card shadow-sm"
            >
              <div class="d-flex align-items-center gap-3">
                <div class="drawer-icon-v3">
                  <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                  <h5 class="fw-800 mb-0">{{ activeFacultyGroup?.facultyName }}</h5>
                  <span class="small text-muted fw-600 ls-1 text-uppercase">Academic Load Management</span>
                </div>
              </div>
              <button class="btn-close-drawer" @click="closeDrawer">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="drawer-content p-4">
              <div class="mb-4 d-flex justify-content-between align-items-center">
                <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-0">Assigned Courses</h6>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">
                  {{ activeFacultyGroup?.assignments.length }} Items
                </span>
              </div>

              <div class="load-list d-flex flex-column gap-3">
                <div v-for="a in activeFacultyGroup?.assignments" :key="a.id" class="load-item-card">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <span class="load-code-tag">{{ a.subject?.code }}</span>
                      <h6 class="fw-800 mb-1 mt-2">{{ a.subject?.name }}</h6>
                      <div class="d-flex align-items-center gap-2 text-muted small fw-600">
                        <i class="fas fa-users-rectangle"></i>
                        <span>Section {{ a.section?.name }}</span>
                      </div>
                    </div>
                    <button class="btn-unlink-minimal" @click="deleteAssignment(a.id)">
                      <i class="fas fa-unlink"></i>
                    </button>
                  </div>
                  <div class="pt-2 border-top border-light-subtle d-flex justify-content-between align-items-center">
                    <div class="small text-muted fw-600">
                      <i class="fas fa-calendar-alt me-1 opacity-50"></i>
                      {{ a.academic_year }} • {{ a.semester }}
                    </div>
                    <div class="small fw-700 text-primary">
                      {{ a.subject?.course?.name }}
                    </div>
                  </div>
                </div>

                <div v-if="!activeFacultyGroup?.assignments.length" class="text-center py-5 opacity-50">
                  <i class="fas fa-calendar-xmark fa-3x mb-3"></i>
                  <p class="small fw-bold">No assignments for this faculty.</p>
                </div>
              </div>
            </div>
          </div>
        </Transition>

        <!-- Assignment Modal -->
        <Transition name="fade">
          <div v-if="showModal" class="glass-backdrop-v2" @click="showModal = false"></div>
        </Transition>

        <Transition name="zoom-in">
          <div v-if="showModal" class="glass-modal-centered">
            <div class="glass-modal-inner card border-0 shadow-lg" style="max-width: 520px">
              <div class="p-4">
                <h5 class="fw-800 mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-plus-circle text-primary"></i>
                  New Faculty Assignment
                </h5>

                <div v-if="formError" class="alert-premium-error mb-4">
                  <i class="fas fa-exclamation-circle me-2"></i>
                  {{ formError }}
                </div>

                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label-premium">Professor</label>
                    <CustomSelect
                      v-model="form.faculty_id"
                      :options="facultyOptions"
                      placeholder="Select a Faculty Member"
                      searchable
                      @change="onFacultyChange"
                    />
                  </div>
                  <div class="col-12">
                    <label class="form-label-premium">Target Subject</label>
                    <CustomSelect
                      v-model="form.subject_id"
                      :options="subjectOptions"
                      placeholder="Select Subject"
                      :disabled="!form.faculty_id"
                    />
                  </div>
                  <div class="col-12">
                    <label class="form-label-premium">Academic Section</label>
                    <CustomSelect
                      v-model="form.section_id"
                      :options="sectionOptions"
                      placeholder="Select Section"
                      :disabled="!form.faculty_id"
                    />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Academic Year</label>
                    <input v-model="form.academic_year" class="form-control-premium" placeholder="2024-2025" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Semester</label>
                    <CustomSelect v-model="form.semester" :options="['1st Semester', '2nd Semester', 'Summer']" />
                  </div>
                </div>
              </div>
              <div class="p-4 pt-0 d-flex gap-2">
                <button class="btn btn-light-premium w-100" @click="showModal = false">Cancel</button>
                <button class="btn btn-primary-premium w-100" @click="saveAssignment" :disabled="saving">
                  {{ saving ? "Deploying..." : "Finalize Assignment" }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";

const assignments = ref([]);
const pagination = ref({});
const loading = ref(true);
const showModal = ref(false);
const showDrawer = ref(false);
const saving = ref(false);
const formError = ref("");
const meta = ref({ faculty: [], subjects: [], sections: [], courses: [] });
const globalSettings = ref({ academic_year: "", semester: "1st Semester" });
const activeFacultyId = ref(null);

const filters = ref({ query: "", department: "", academic_year: "", semester: "" });
let searchTimeout = null;

const facultyPage = ref(1);
const facultyPerPage = 8;

onMounted(() => {
  fetchAssignments();
  fetchMeta();
  fetchSettings();
});

function getCourseName(courseId) {
  const course = meta.value.courses.find((c) => c.id == courseId);
  return course ? course.name : "Unknown";
}

const form = ref({ faculty_id: "", subject_id: "", section_id: "", academic_year: "", semester: "1st Semester" });

function onFacultyChange() {
  form.value.subject_id = "";
  form.value.section_id = "";
}

const filteredSubjects = computed(() => {
  if (!form.value.faculty_id) return [];
  const fac = meta.value.faculty.find((f) => f.id === form.value.faculty_id);
  if (!fac) return [];
  const matchingCourses = meta.value.courses.filter(
    (c) => c.department === fac.department || c.name === fac.course || fac.department === "General Education",
  );
  if (matchingCourses.length === 0) return meta.value.subjects;
  const validIds = matchingCourses.map((c) => c.id);
  return meta.value.subjects.filter((s) => validIds.includes(s.course_id));
});

const filteredSections = computed(() => {
  if (!form.value.faculty_id) return [];
  const fac = meta.value.faculty.find((f) => f.id === form.value.faculty_id);
  if (!fac) return [];
  const matchingCourses = meta.value.courses.filter(
    (c) => c.department === fac.department || c.name === fac.course || fac.department === "General Education",
  );
  if (matchingCourses.length === 0) return meta.value.sections;
  const validIds = matchingCourses.map((c) => c.id);
  return meta.value.sections.filter((s) => validIds.includes(s.course_id));
});

const facultyGroups = computed(() => {
  const groups = {};
  assignments.value.forEach((a) => {
    const fId = a.faculty_id;
    if (!groups[fId]) {
      groups[fId] = {
        facultyId: fId,
        facultyName: a.faculty?.user?.name || "Unknown Faculty",
        department: a.faculty?.department || "N/A",
        assignments: [],
      };
    }
    groups[fId].assignments.push(a);
  });
  return Object.values(groups);
});

const activeFacultyGroup = computed(
  () => facultyGroups.value.find((g) => g.facultyId === activeFacultyId.value) || null,
);
const totalFacultyPages = computed(() => Math.ceil(facultyGroups.value.length / facultyPerPage));
const paginatedFacultyGroups = computed(() => {
  const start = (facultyPage.value - 1) * facultyPerPage;
  return facultyGroups.value.slice(start, start + facultyPerPage);
});

const availableDepartments = computed(() => {
  if (!meta.value.faculty) return [];
  const depts = meta.value.faculty.map((f) => f.department).filter((d) => !!d);
  const uniqueDepts = [...new Set(depts)];
  return uniqueDepts.sort();
});

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchAssignments(1), 500);
}

const deptOptions = computed(() => [
  { label: "All Departments", value: "" },
  ...availableDepartments.value.map((d) => ({ label: d, value: d })),
]);

const semesterOptions = [
  { label: "All Semesters", value: "" },
  { label: "1st Semester", value: "1st Semester" },
  { label: "2nd Semester", value: "2nd Semester" },
  { label: "Summer", value: "Summer" },
];

const facultyOptions = computed(() => [
  { label: "Select a Faculty Member", value: "" },
  ...meta.value.faculty.map((f) => ({ label: f.user?.name || "Unknown", value: f.id })),
]);

const subjectOptions = computed(() => [
  { label: "Select a Subject", value: "" },
  ...filteredSubjects.value.map((s) => ({ label: `${s.code} - ${s.name}`, value: s.id })),
]);

const sectionOptions = computed(() => [
  { label: "Select a Section", value: "" },
  ...filteredSections.value.map((s) => ({ label: s.name, value: s.id })),
]);

function clearFilters() {
  filters.value = { query: "", department: "", academic_year: "", semester: "" };
  fetchAssignments(1);
}

async function fetchAssignments(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page,
      query: filters.value.query,
      department: filters.value.department,
      academic_year: filters.value.academic_year,
      semester: filters.value.semester,
    });
    const res = await api.get(`/assignments?${params.toString()}`);
    assignments.value = res.data.data;
    pagination.value = res.data;
    facultyPage.value = 1;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function fetchMeta() {
  try {
    const res = await api.get("/assignments/meta");
    meta.value = res.data;
  } catch (e) {
    console.error(e);
  }
}

async function fetchSettings() {
  try {
    const res = await api.get("/settings");
    globalSettings.value.academic_year = res.data.active_academic_year || "";
    globalSettings.value.semester = res.data.active_semester || "1st Semester";
    form.value.academic_year = globalSettings.value.academic_year;
    form.value.semester = globalSettings.value.semester;
  } catch (e) {
    console.error(e);
  }
}

function handleFacultyClick(group) {
  activeFacultyId.value = group.facultyId;
  showDrawer.value = true;
}

function closeDrawer() {
  showDrawer.value = false;
}

function openAddModal() {
  formError.value = "";
  form.value = {
    faculty_id: "",
    subject_id: "",
    section_id: "",
    academic_year: globalSettings.value.academic_year,
    semester: globalSettings.value.semester,
  };
  showModal.value = true;
}

async function saveAssignment() {
  saving.value = true;
  formError.value = "";
  try {
    await api.post("/assignments", form.value);
    showModal.value = false;
    fetchAssignments();
    Swal.fire({ title: "Success", text: "Assignment deployed.", icon: "success", confirmButtonColor: "#0A278A" });
  } catch (e) {
    formError.value = e.response?.data?.message || "Failed to save.";
  } finally {
    saving.value = false;
  }
}

async function deleteAssignment(id) {
  const result = await Swal.fire({
    title: "Unlink?",
    text: "Remove this academic load?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#EF4444",
    cancelButtonColor: "#64748B",
    confirmButtonText: "Yes, Unlink",
  });
  if (result.isConfirmed) {
    try {
      await api.delete(`/assignments/${id}`);
      fetchAssignments();
    } catch (e) {
      Swal.fire("Error", "Failed to unlink.", "error");
    }
  }
}
</script>

<style scoped>
.ls-1 {
  letter-spacing: 0.05em;
}
.fw-800 {
  font-weight: 800;
}
.bg-card {
  background: var(--bg-card);
}

/* Premium Filters Bar */
.search-premium-box.v2 {
  width: 220px;
  position: relative;
}
.search-premium-box.v2 i {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.8rem;
}
.search-premium-box.v2 input {
  width: 100%;
  padding: 0.6rem 0.85rem 0.6rem 2.2rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border-light);
  background: var(--bg-light);
  font-size: 0.85rem;
  font-weight: 600;
}

.filter-dropdown-premium select {
  padding: 0.6rem 1rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border-light);
  background: var(--bg-light);
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-dark);
  cursor: pointer;
  min-width: 160px;
}
.filter-dropdown-premium.sm select {
  min-width: 120px;
}

.btn-reset-premium {
  width: 36px;
  height: 36px;
  border-radius: 0.75rem;
  border: 1px solid var(--border-light);
  background: var(--bg-light);
  color: var(--text-muted);
  transition: all 0.2s;
}
.btn-reset-premium:hover {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

/* Faculty Grid Card */
.faculty-card-premium {
  background: var(--bg-card);
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.2, 0, 0, 1);
}

.faculty-card-premium:hover {
  transform: translateY(-8px);
  border-color: var(--primary);
}

.card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 100px;
  height: 100px;
  background: var(--primary);
  filter: blur(70px);
  opacity: 0.05;
  pointer-events: none;
}

.faculty-avatar-box {
  width: 50px;
  height: 50px;
  border-radius: 1.25rem;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
}

.load-badge {
  padding: 4px 10px;
  background: var(--primary);
  color: white;
  font-size: 0.65rem;
  font-weight: 800;
  border-radius: 6px;
  letter-spacing: 0.05em;
}

.faculty-name-v3 {
  font-size: 1.1rem;
  color: var(--text-dark);
}
.faculty-dept-v3 {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}

.mini-load-dot {
  width: 8px;
  height: 8px;
  background: var(--primary);
  border-radius: 50%;
  border: 2px solid white;
  margin-left: -4px;
}
.mini-load-more {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--text-muted);
  margin-left: 4px;
}

.view-details-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary);
  opacity: 0.4;
  transition: all 0.2s;
}
.faculty-card-premium:hover .view-details-link {
  opacity: 1;
  transform: translateX(4px);
}

/* Load Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  z-index: 1050;
}
.load-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: 500px;
  background: var(--bg-card);
  z-index: 1060;
  box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
}

.drawer-icon-v3 {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.load-item-card {
  background: var(--bg-light);
  padding: 1.25rem;
  border-radius: 1.25rem;
  border: 1px solid var(--border-light);
  transition: all 0.2s;
}

.load-item-card:hover {
  background: white;
  transform: translateX(-4px);
}

.load-code-tag {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--primary);
  background: rgba(25, 25, 112, 0.08);
  padding: 2px 8px;
  border-radius: 4px;
}

.btn-unlink-minimal {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: #fee2e2;
  color: var(--danger);
  font-size: 0.8rem;
  transition: all 0.2s;
}
.btn-unlink-minimal:hover {
  background: var(--danger);
  color: white;
}

.btn-close-drawer {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: none;
  background: var(--bg-light);
  color: var(--text-muted);
  transition: all 0.2s;
}
.btn-close-drawer:hover {
  background: #fee2e2;
  color: var(--danger);
}

/* Modal Premium */
.glass-backdrop-v2 {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(12px);
  z-index: 2000;
}
.glass-modal-centered {
  position: fixed;
  inset: 0;
  z-index: 2010;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3rem 1.5rem;
  overflow-y: auto;
}
.glass-modal-inner {
  margin: auto;
  width: 100%;
  border-radius: var(--card-radius);
  background: var(--bg-card);
  overflow: visible !important;
}

.form-label-premium {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 0.5rem;
}
.form-control-premium {
  width: 100%;
  padding: 0.85rem 1.25rem;
  border-radius: 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  font-weight: 600;
}
.form-control-premium:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
}

.alert-premium-error {
  padding: 0.75rem 1.25rem;
  border-radius: 1rem;
  background: #fee2e2;
  color: var(--danger);
  font-size: 0.85rem;
  font-weight: 700;
  border: 1px solid #fecaca;
}

.btn-primary-premium {
  padding: 1rem;
  border-radius: 1.25rem;
  background: var(--primary);
  border: none;
  color: #fff;
  font-weight: 800;
  transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, color 0.2s ease;
}

.btn.btn-primary-premium:hover:not(:disabled),
.btn.btn-primary-premium:focus-visible:not(:disabled) {
  background: #0041cc;
  border: none;
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(25, 25, 112, 0.3);
}

.btn.btn-primary-premium:active:not(:disabled) {
  background: #0039b3;
  color: #fff;
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(25, 25, 112, 0.25);
}

.btn.btn-primary-premium:disabled {
  background: var(--primary);
  color: #fff;
  opacity: 0.65;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.btn-light-premium {
  padding: 1rem;
  border-radius: 1.25rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-muted);
  font-weight: 700;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.btn.btn-light-premium:hover:not(:disabled),
.btn.btn-light-premium:focus-visible:not(:disabled) {
  background: #fff;
  border-color: var(--primary);
  color: var(--primary);
}

.btn.btn-light-premium:active:not(:disabled) {
  background: var(--bg-light);
  border-color: #0041cc;
  color: #0041cc;
}

/* Transitions */
.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  transform: translateX(100%);
}

.fade-in-up {
  animation: fadeInUp 0.6s ease-out;
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.grid-stagger-enter-active {
  transition: all 0.5s ease;
}
.grid-stagger-enter-from {
  opacity: 0;
  transform: translateY(30px);
}
</style>
