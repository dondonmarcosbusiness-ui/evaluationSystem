<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Faculty Assignments</template></Navbar>

      <div class="content-area">
        <!-- Premium Filters & Action Bar + Year Tabs (attached, sticky) -->
        <div class="assignment-toolbar-sticky fade-in-up">
          <div class="stats-bar-premium">
            <div
              class="d-flex align-items-center justify-content-between flex-wrap gap-4 px-4 py-3 rounded-top-4 shadow-sm bg-card border border-light border-bottom-0"
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

          <!-- Year Level Tab Menu (stuck directly below filters, no gap) -->
          <div class="year-tab-menu year-tab-menu-attached underline-tabs">
            <button
              v-for="tab in yearTabs"
              :key="tab.value"
              type="button"
              class="year-tab"
              :class="{ active: filters.year_level === tab.value }"
              @click="selectYearTab(tab.value)"
            >
              <i :class="tab.icon" class="tab-ico"></i>
              <span>{{ tab.label }}</span>
            </button>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="table" :rows="8" :cols="5" />
        </div>

        <!-- Faculty Assignment Table -->
        <div v-else class="assignment-table-wrap fade-in-up">
          <table class="assignment-table">
            <thead>
              <tr>
                <th>Faculty Name</th>
                <th>Department</th>
                <th>Loads</th>
                <th>Subjects</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="group in paginatedFacultyGroups"
                :key="group.facultyId"
                class="assignment-row"
                @click="handleFacultyClick(group)"
              >
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <div class="faculty-avatar-sm">
                      <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="fw-700">{{ group.facultyName }}</span>
                  </div>
                </td>
                <td class="text-muted">{{ group.department }}</td>
                <td>
                  <span class="load-count-badge">{{ group.assignments.length }}</span>
                </td>
                <td>
                  <div class="subjects-cell">
                    <span v-for="(a, i) in group.assignments.slice(0, 3)" :key="i" class="subject-chip">
                      {{ a.subject?.code }}
                    </span>
                    <span v-if="group.assignments.length > 3" class="subject-more">
                      +{{ group.assignments.length - 3 }}
                    </span>
                    <span v-if="!group.assignments.length" class="text-muted fst-italic">No assignments</span>
                  </div>
                </td>
                <td class="text-end">
                  <button class="btn-manage-load" @click.stop="handleFacultyClick(group)">
                    Manage
                    <i class="fas fa-chevron-right ms-1"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4">
            <label class="per-page-wrap">
              <span class="per-page-label">Rows:</span>
              <select class="per-page-select" :value="facultyPerPage" @change="changePerPage($event.target.value)">
                <option v-for="n in [10, 20, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </label>
            <div
              v-if="totalFacultyPages > 1"
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
          <div v-if="!paginatedFacultyGroups.length && !loading" class="text-center py-5">
            <i class="fas fa-user-slash fa-4x opacity-10 mb-3"></i>
            <h5 class="fw-800">No Assignments Found</h5>
            <p class="text-muted small">Try different filters or create a new assignment.</p>
          </div>
        </div>

        <!-- Faculty Load Modal -->
        <div ref="loadModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                  <div class="drawer-icon-v3">
                    <i class="fas fa-chalkboard-teacher"></i>
                  </div>
                  <div>
                    <h5 class="modal-title fw-800 mb-0">{{ activeFacultyGroup?.facultyName }}</h5>
                    <span class="small text-muted fw-600 ls-1 text-uppercase">Academic Load Management</span>
                  </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body">
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
                        {{ a.academic_year }} • {{ a.semester }} •
                        {{ a.year_level ?? a.subject?.year_level ?? a.section?.year_level ?? "All years" }}
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
          </div>
        </div>

        <!-- Assignment Modal -->
        <div ref="assignmentModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-800 d-flex align-items-center gap-2">
                  <i class="fas fa-plus-circle text-primary"></i>
                  New Faculty Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div v-if="formError" class="alert-premium-error mb-4">
                  <i class="fas fa-exclamation-circle me-2"></i>
                  {{ formError }}
                </div>

                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label-premium">Filter by Course</label>
                    <div class="course-radio-pills">
                      <label class="course-radio-pill" :class="{ active: !courseFilter }">
                        <input type="radio" :value="''" v-model="courseFilter" @change="onCourseFilterChange" />
                        <span>All</span>
                      </label>
                      <label
                        v-for="opt in courseRadioOptions"
                        :key="opt.value"
                        class="course-radio-pill"
                        :class="{ active: courseFilter === opt.value }"
                      >
                        <input type="radio" :value="opt.value" v-model="courseFilter" @change="onCourseFilterChange" />
                        <span>{{ opt.label }}</span>
                      </label>
                    </div>
                  </div>
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
                  <div class="col-md-6">
                    <label class="form-label-premium">Year Level</label>
                    <CustomSelect
                      v-model="form.year_level"
                      :options="yearModalOptions"
                      placeholder="Select Year"
                    />
                  </div>
                  <div class="col-md-6 d-flex align-items-end">
                    <p class="small text-muted mb-2">Subjects & sections narrow to the chosen course and year.</p>
                  </div>
                  <div class="col-12">
                    <label class="form-label-premium">Target Subject</label>
                    <CustomSelect
                      v-model="form.subject_id"
                      :options="subjectOptions"
                      placeholder="Select Subject"
                      :disabled="!form.faculty_id && !courseFilter"
                      @change="onSubjectChange"
                    />
                  </div>
                  <div class="col-12">
                    <label class="form-label-premium">Academic Section</label>
                    <CustomSelect
                      v-model="form.section_id"
                      :options="sectionOptions"
                      placeholder="Select Section"
                      :disabled="!form.faculty_id && !courseFilter"
                    />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Academic Year</label>
                    <input v-model="form.academic_year" class="form-control-premium" placeholder="2024-2025" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Semester</label>
                    <CustomSelect v-model="form.semester" :options="semesterList" />
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light-premium flex-fill" data-bs-dismiss="modal">Cancel</button>
                <button
                  type="button"
                  class="btn btn-primary-premium flex-fill"
                  @click="saveAssignment"
                  :disabled="saving"
                >
                  {{ saving ? "Deploying..." : "Finalize Assignment" }}
                </button>
              </div>
            </div>
          </div>
        </div>
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
import { useBootstrapModal } from "../composables/useBootstrapModal.js";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";
import { DEFAULT_SEMESTERS, fetchAcademicConfig } from "../helpers/academic.js";

const assignments = ref([]);
const pagination = ref({});
const loading = ref(true);
const showModal = ref(false);
const showDrawer = ref(false);
const { modalEl: assignmentModalEl } = useBootstrapModal(showModal);
const { modalEl: loadModalEl } = useBootstrapModal(showDrawer);
const saving = ref(false);
const formError = ref("");
const meta = ref({ faculty: [], subjects: [], sections: [], courses: [], year_levels: [] });
const globalSettings = ref({ academic_year: "", semester: "1st Semester" });
const activeFacultyId = ref(null);
const fallbackYearLevels = ["1st", "2nd", "3rd", "4th", "Irregular"];

const filters = ref({ query: "", department: "", academic_year: "", semester: "", year_level: "" });
let searchTimeout = null;

const facultyPage = ref(1);
const facultyPerPage = ref(10);

function changePerPage(n) {
  const size = parseInt(n, 10);
  if (!Number.isFinite(size) || size === facultyPerPage.value) return;
  facultyPerPage.value = size;
  fetchAssignments(1);
}

onMounted(() => {
  fetchAssignments();
  fetchMeta();
  fetchSettings();
  fetchAcademicConfig().then((c) => {
    semesterList.value = c.semesters;
  });
});

function getCourseName(courseId) {
  const course = meta.value.courses.find((c) => c.id == courseId);
  return course ? course.name : "Unknown";
}

const form = ref({
  faculty_id: "",
  subject_id: "",
  section_id: "",
  academic_year: "",
  semester: "1st Semester",
  year_level: "",
});

function onFacultyChange() {
  form.value.subject_id = "";
  form.value.section_id = "";
}

// Course radio filter (kept outside `form` so it is never POSTed).
// "" = All courses (today's behavior). Otherwise a course id from meta.courses.
const courseFilter = ref("");

const selectedCourse = computed(() => meta.value.courses.find((c) => c.id === courseFilter.value) || null);

// Department-aware match (pick 1b): faculty of the course itself,
// faculty in the same department, plus General Education faculty.
function facultyPassesCourseFilter(fac) {
  if (!selectedCourse.value) return true;
  if (!fac) return false;
  return (
    fac.course === selectedCourse.value.name ||
    fac.department === selectedCourse.value.department ||
    fac.department === "General Education"
  );
}

// Course ids in scope of the radio filter (course itself + same department).
const courseScopeIds = computed(() => {
  if (!selectedCourse.value) return [];
  return meta.value.courses
    .filter((c) => c.id === selectedCourse.value.id || c.department === selectedCourse.value.department)
    .map((c) => c.id);
});

function onCourseFilterChange() {
  // Clear the professor if they no longer match the course filter (cascades to subject/section).
  const fac = meta.value.faculty.find((f) => f.id === form.value.faculty_id);
  if (form.value.faculty_id && !facultyPassesCourseFilter(fac)) {
    form.value.faculty_id = "";
    onFacultyChange();
  }
  // No professor picked: still validate subject/section against the newly narrowed lists.
  if (!form.value.faculty_id) {
    if (form.value.subject_id && !filteredSubjects.value.some((s) => s.id === form.value.subject_id)) {
      form.value.subject_id = "";
    }
    if (form.value.section_id && !filteredSections.value.some((s) => s.id === form.value.section_id)) {
      form.value.section_id = "";
    }
  }
}

// Untagged (null) or Irregular years are visible under every year filter.
function matchesYear(itemYear, wanted) {
  if (!wanted) return true;
  if (!itemYear || itemYear === "Irregular" || wanted === "Irregular") return true;
  return itemYear === wanted;
}

function onSubjectChange() {
  const sub = meta.value.subjects.find((s) => s.id === form.value.subject_id);
  if (sub?.year_level && !form.value.year_level) {
    form.value.year_level = sub.year_level;
  }
}

const filteredSubjects = computed(() => {
  // No professor yet: narrow by the course radio filter if one is marked.
  if (!form.value.faculty_id) {
    if (!selectedCourse.value) return [];
    return meta.value.subjects.filter(
      (s) => courseScopeIds.value.includes(s.course_id) && matchesYear(s.year_level, form.value.year_level),
    );
  }
  const fac = meta.value.faculty.find((f) => f.id === form.value.faculty_id);
  if (!fac) return [];
  const matchingCourses = meta.value.courses.filter(
    (c) => c.department === fac.department || c.name === fac.course || fac.department === "General Education",
  );
  let list =
    matchingCourses.length === 0
      ? meta.value.subjects
      : meta.value.subjects.filter((s) => matchingCourses.map((c) => c.id).includes(s.course_id));
  return list.filter((s) => matchesYear(s.year_level, form.value.year_level));
});

const filteredSections = computed(() => {
  // No professor yet: narrow by the course radio filter if one is marked.
  if (!form.value.faculty_id) {
    if (!selectedCourse.value) return [];
    return meta.value.sections.filter(
      (s) => courseScopeIds.value.includes(s.course_id) && matchesYear(s.year_level, form.value.year_level),
    );
  }
  const fac = meta.value.faculty.find((f) => f.id === form.value.faculty_id);
  if (!fac) return [];
  const matchingCourses = meta.value.courses.filter(
    (c) => c.department === fac.department || c.name === fac.course || fac.department === "General Education",
  );
  let list =
    matchingCourses.length === 0
      ? meta.value.sections
      : meta.value.sections.filter((s) => matchingCourses.map((c) => c.id).includes(s.course_id));
  return list.filter((s) => matchesYear(s.year_level, form.value.year_level));
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
const totalFacultyPages = computed(() => Math.ceil(facultyGroups.value.length / facultyPerPage.value));
const paginatedFacultyGroups = computed(() => {
  const start = (facultyPage.value - 1) * facultyPerPage.value;
  return facultyGroups.value.slice(start, start + facultyPerPage.value);
});

const availableDepartments = computed(() => {
  if (!meta.value.courses) return [];
  const depts = meta.value.courses.map((c) => c.department).filter((d) => !!d);
  const uniqueDepts = [...new Set(depts)];
  if (!uniqueDepts.includes("General Education")) uniqueDepts.push("General Education");
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

const semesterList = ref([...DEFAULT_SEMESTERS]);

const semesterOptions = computed(() => [
  { label: "All Semesters", value: "" },
  ...semesterList.value.map((s) => ({ label: s, value: s })),
]);

const filteredFaculty = computed(() => meta.value.faculty.filter(facultyPassesCourseFilter));

const courseRadioOptions = computed(() => meta.value.courses.map((c) => ({ label: c.name, value: c.id })));

const facultyOptions = computed(() => [
  { label: "Select a Faculty Member", value: "" },
  ...filteredFaculty.value.map((f) => ({ label: f.user?.name || "Unknown", value: f.id })),
]);

const yearList = computed(() => meta.value.year_levels?.length ? meta.value.year_levels : fallbackYearLevels);

const yearTabs = computed(() => [
  { label: "All Years", value: "", icon: "fas fa-home" },
  ...yearList.value.map((y) => ({
    label: y === "Irregular" ? "Irregular" : `${y} Year`,
    value: y,
    icon:
      y === "1st"
        ? "fas fa-chart-line"
        : y === "2nd"
          ? "fas fa-list-ul"
          : y === "3rd"
            ? "fas fa-layer-group"
            : y === "4th"
              ? "fas fa-graduation-cap"
              : "fas fa-inbox",
  })),
]);

function selectYearTab(value) {
  if (filters.value.year_level === value) return;
  filters.value.year_level = value;
  fetchAssignments(1);
}

const yearModalOptions = computed(() => yearList.value.map((y) => ({ label: `${y} Year`, value: y })));

const subjectOptions = computed(() => [
  { label: "Select a Subject", value: "" },
  ...filteredSubjects.value.map((s) => ({
    label: subjectOptionLabel(s),
    value: s.id,
  })),
]);

const sectionOptions = computed(() => [
  { label: "Select a Section", value: "" },
  ...filteredSections.value.map((s) => ({
    label: sectionOptionLabel(s),
    value: s.id,
  })),
]);

// All Years mode: show year tags so every option's scope is visible
// (e.g. "5A (3rd)", "1A (1st, 2nd, 3rd, 4th)").
// Specific year mode: list is already strictly filtered, so show plain names.
function sectionOptionLabel(s) {
  if (form.value.year_level) return s.name;
  if (s.year_level) return `${s.name} (${s.year_level})`;
  return `${s.name} (1st, 2nd, 3rd, 4th)`;
}

function subjectOptionLabel(s) {
  if (form.value.year_level) return `${s.code} - ${s.name}`;
  if (s.year_level) return `${s.code} - ${s.name} (${s.year_level})`;
  return `${s.code} - ${s.name} (1st, 2nd, 3rd, 4th)`;
}

function clearFilters() {
  filters.value = { query: "", department: "", academic_year: "", semester: "", year_level: "" };
  fetchAssignments(1);
}

async function fetchAssignments(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page,
      per_page: facultyPerPage.value,
      query: filters.value.query,
      department: filters.value.department,
      academic_year: filters.value.academic_year,
      semester: filters.value.semester,
      year_level: filters.value.year_level,
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

function openAddModal() {
  formError.value = "";
  courseFilter.value = "";
  form.value = {
    faculty_id: "",
    subject_id: "",
    section_id: "",
    academic_year: globalSettings.value.academic_year,
    semester: globalSettings.value.semester,
    year_level: filters.value.year_level || "",
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
  const result = await confirmAction({
    title: "Unlink?",
    message: "Remove this academic load?",
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
/* Sticky toolbar: filters + tabs attached with no gap */
.assignment-toolbar-sticky {
  position: sticky;
  top: 0;
  z-index: 1020;
}
.assignment-toolbar-sticky .stats-bar-premium {
  margin: 0 !important;
}
.assignment-toolbar-sticky .rounded-top-4 {
  border-top-left-radius: 1rem !important;
  border-top-right-radius: 1rem !important;
  border-bottom-left-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

/* Underline tab menu — like Dashboard / Transactions / Products / Messages */
.assignment-toolbar-sticky .year-tab-menu-attached.underline-tabs {
  margin: 0 !important;
  display: flex !important;
  align-items: stretch !important;
  gap: 28px !important;
  background: var(--bg-card) !important;
  border: 1px solid var(--border-color) !important;
  border-top: 0 !important;
  border-radius: 0 0 1rem 1rem !important;
  padding: 0 20px !important;
  overflow-x: auto !important;
  scrollbar-width: none !important;
}
.assignment-toolbar-sticky .year-tab-menu-attached.underline-tabs::-webkit-scrollbar {
  display: none !important;
}
.assignment-toolbar-sticky .underline-tabs .year-tab {
  flex: 0 0 auto !important;
  position: relative !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 8px !important;
  background: transparent !important;
  border: 0 !important;
  border-radius: 0 !important;
  padding: 14px 4px 13px !important;
  font-size: 0.92rem !important;
  font-weight: 700 !important;
  letter-spacing: -0.01em !important;
  color: #64748b !important;
  white-space: nowrap !important;
  cursor: pointer !important;
}
.assignment-toolbar-sticky .underline-tabs .year-tab .tab-ico {
  font-size: 0.95rem;
  opacity: 0.85;
}
.assignment-toolbar-sticky .underline-tabs .year-tab::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 2px;
  border-radius: 2px;
  background: transparent;
  transition: background-color 0.15s ease;
}
.assignment-toolbar-sticky .underline-tabs .year-tab:hover {
  background: transparent !important;
  color: var(--text-main) !important;
}
.assignment-toolbar-sticky .underline-tabs .year-tab.active {
  background: transparent !important;
  color: var(--success) !important;
}
.assignment-toolbar-sticky .underline-tabs .year-tab.active::after {
  background: var(--success);
}
.assignment-toolbar-sticky .underline-tabs .year-tab.active .tab-ico {
  opacity: 1;
}

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
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.85rem;
}
.search-premium-box.v2 input {
  width: 100%;
  height: 40px;
  min-height: 40px;
  padding: 10px 16px 10px 36px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-main);
  font-size: 14px;
  font-weight: 500;
}
.search-premium-box.v2 input::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}
.search-premium-box.v2 input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

.filter-dropdown-premium select {
  padding: 10px 40px 10px 16px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  font-size: 14px;
  font-weight: 500;
  color: var(--text-dark);
  cursor: pointer;
  min-width: 160px;
  min-height: 40px;
}
.filter-dropdown-premium select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}
.filter-dropdown-premium.sm select {
  min-width: 120px;
}

.btn-reset-premium {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  transition: all 0.2s;
}
.btn-reset-premium:hover {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}
.btn-reset-premium:hover i {
  color: white !important;
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

/* Faculty Load Modal (Bootstrap 5) */
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

.form-label-premium {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 0.5rem;
}

/* Course radio pills — filter for the Professor dropdown */
.course-radio-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-height: 96px;
  overflow-y: auto;
  padding: 2px;
}
.course-radio-pill {
  display: inline-flex;
  align-items: center;
  margin: 0;
  cursor: pointer;
}
.course-radio-pill input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.course-radio-pill span {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 700;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.course-radio-pill:hover span {
  border-color: var(--primary);
  color: var(--primary);
}
.course-radio-pill.active span {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
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
  padding: 0.6rem 1.5rem;
  border-radius: 0.5rem;
  background: var(--primary);
  border: none;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.btn.btn-primary-premium:hover:not(:disabled),
.btn.btn-primary-premium:focus-visible:not(:disabled) {
  background: var(--primary) !important;
  border: none;
  color: #fff !important;
  transform: scale(1.03);
  box-shadow: none;
}

.btn.btn-primary-premium:active:not(:disabled) {
  background: var(--primary) !important;
  color: #fff !important;
  transform: scale(0.98);
}

.btn.btn-primary-premium:disabled {
  background: var(--primary);
  color: #fff;
  opacity: 0.65;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-light-premium {
  padding: 0.6rem 1.5rem;
  border-radius: 0.5rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-muted);
  font-size: 0.875rem;
  font-weight: 700;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.btn.btn-light-premium:hover:not(:disabled),
.btn.btn-light-premium:focus-visible:not(:disabled) {
  background: #fff !important;
  border-color: var(--primary) !important;
  color: var(--primary) !important;
  transform: scale(1.03);
}

.btn.btn-light-premium:active:not(:disabled) {
  background: var(--bg-light) !important;
  border-color: #0041cc !important;
  color: #0041cc !important;
  transform: scale(0.98);
}

/* Transitions */
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

/* ── Table Styles ─────── */
.assignment-table-wrap {
  margin-top: 24px;
}
.assignment-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
  font-size: 0.9rem;
}
.assignment-table thead th {
  padding: 14px 16px;
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
}
.assignment-table tbody td {
  padding: 14px 16px;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-color);
  color: var(--text-main);
}
.assignment-table tbody tr:last-child td {
  border-bottom: none;
}
.assignment-row {
  cursor: pointer;
  transition: background 0.15s ease;
}
.assignment-row:hover {
  background: var(--bg-light);
}
.faculty-avatar-sm {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.load-count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding: 0 8px;
  border-radius: 8px;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  font-size: 0.8rem;
  font-weight: 800;
}
.subjects-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}
.subject-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-main);
  white-space: nowrap;
}
.subject-more {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
}
.btn-manage-load {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--primary);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-manage-load:hover {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}
</style>
