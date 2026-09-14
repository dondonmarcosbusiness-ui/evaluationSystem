<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Course Management</template></Navbar>

      <div class="content-area">
        <!-- Slim Stats Bar -->
        <div class="stats-bar-premium mb-5 fade-in-up">
          <div
            class="d-flex align-items-center justify-content-between flex-wrap gap-4 px-4 py-3 rounded-4 shadow-sm bg-card border border-light"
          >
            <div class="d-flex align-items-center gap-5">
              <div class="stat-item-inline">
                <span class="label">Curricula</span>
                <h5 class="value mb-0 mt-1">{{ totalCoursesCount }}</h5>
              </div>
              <div class="stat-divider"></div>
              <div class="stat-item-inline">
                <span class="label">Departments</span>
                <h5 class="value mb-0 mt-1">{{ uniqueDepartmentsCount }}</h5>
              </div>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="search-premium-box">
                <i class="fas fa-search"></i>
                <input v-model="searchQuery" type="text" placeholder="Search course..." @input="handleSearch" />
              </div>
              <button class="btn btn-primary-glass px-4 rounded-pill shadow-sm" @click="openAddModal">
                <i class="fas fa-plus-circle me-2"></i>
                New Course
              </button>
            </div>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="cards" :rows="8" />
        </div>

        <!-- Courses Table -->
        <div v-else class="fade-in-up">
          <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="bg-light small">
                  <tr>
                    <th style="width: 50px">#</th>
                    <th>Course</th>
                    <th>Department</th>
                    <th class="text-center">Subjects</th>
                    <th class="text-center">Sections</th>
                    <th class="text-end" style="width: 210px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(course, idx) in courses" :key="course.id">
                    <td class="text-muted small">
                      {{ (coursePage - 1) * coursesPerPage + idx + 1 }}
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-3">
                        <div class="course-icon-box sm">
                          <i class="fas fa-graduation-cap"></i>
                        </div>
                        <span class="fw-800">{{ course.name }}</span>
                      </div>
                    </td>
                    <td class="text-muted small">{{ course.department }}</td>
                    <td class="text-center">
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ courseSubjects(course).length }}
                      </span>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-primary bg-opacity-10 text-primary">
                        {{ courseSections(course).length }}
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <button
                          class="btn-action-minimal"
                          title="Manage curriculum"
                          @click="openDetail(course)"
                        >
                          <i class="fas fa-cog"></i>
                        </button>
                        <button class="btn-action-minimal" title="Edit course" @click="openEditModal(course)">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button
                          class="btn-action-minimal danger"
                          title="Delete course"
                          @click="deleteCourse(course.id)"
                        >
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!courses.length">
                    <td colspan="6" class="text-center py-5">
                      <i class="fas fa-folder-open fa-3x opacity-10 mb-3 d-block"></i>
                      <h5 class="fw-800 mb-1">No Curricula Found</h5>
                      <p class="text-muted small mb-0">Try adjusting your search or add a new course.</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pagination -->
          <div class="mt-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <label class="per-page-wrap">
              <span class="per-page-label">Rows:</span>
              <select class="per-page-select" :value="coursesPerPage" @change="changePerPage($event.target.value)">
                <option v-for="n in [10, 20, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </label>
            <div
              v-if="totalCoursePages > 1"
              class="pagination-premium d-flex align-items-center gap-3 bg-card p-2 rounded-pill shadow-sm border border-light"
            >
              <button class="btn btn-icon-sm" :disabled="coursePage === 1" @click="coursePage--">
                <i class="fas fa-chevron-left"></i>
              </button>
              <span class="small fw-800 px-2">Page {{ coursePage }} of {{ totalCoursePages }}</span>
              <button class="btn btn-icon-sm" :disabled="coursePage === totalCoursePages" @click="coursePage++">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Curriculum Detail Modal -->
        <div ref="detailModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                  <div class="detail-icon-box">
                    <i class="fas fa-graduation-cap"></i>
                  </div>
                  <div>
                    <h5 class="modal-title fw-800 mb-0">{{ activeCourse?.name }}</h5>
                    <span class="detail-dept">{{ activeCourse?.department }}</span>
                  </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="detail-stats-row px-0">
                  <div class="detail-stat-chip">
                    <i class="fas fa-bookmark"></i>
                    <span>{{ courseSubjects(activeCourse).length }} Subjects</span>
                  </div>
                  <div class="detail-stat-chip">
                    <i class="fas fa-users"></i>
                    <span>{{ courseSections(activeCourse).length }} Sections</span>
                  </div>
                </div>

                  <!-- Content Tabs: Subjects / Sections -->
                  <div class="year-tab-menu content-tabs mb-3">
                    <button
                      type="button"
                      class="year-tab"
                      :class="{ active: detailContentTab === 'subjects' }"
                      @click="detailContentTab = 'subjects'"
                    >
                      <i class="fas fa-bookmark me-1"></i>
                      Subjects
                    </button>
                    <button
                      type="button"
                      class="year-tab"
                      :class="{ active: detailContentTab === 'sections' }"
                      @click="detailContentTab = 'sections'"
                    >
                      <i class="fas fa-users-rectangle me-1"></i>
                      Sections
                    </button>
                  </div>

                  <!-- Year Level Tab Menu -->
                  <div class="year-pills mb-4">
                    <button
                      v-for="tab in detailYearTabs"
                      :key="tab.value"
                      type="button"
                      class="year-pill"
                      :class="{ active: detailYearTab === tab.value }"
                      @click="detailYearTab = tab.value"
                    >
                      {{ tab.label }}
                    </button>
                  </div>

                  <div v-if="detailContentTab === 'subjects'" class="detail-section">
                    <h6 class="detail-section-heading">
                      <span>
                        <i class="fas fa-bookmark text-primary opacity-60"></i>
                        Subject Bank
                      </span>
                      <div class="d-flex gap-2 flex-shrink-0">
                        <button class="btn-add-inline" @click="openSubjectUpload">
                          <i class="fas fa-upload"></i>
                          Upload
                        </button>
                        <button class="btn-add-inline" @click="openSubjectAdder">
                          <i class="fas fa-plus"></i>
                          Add Subject{{ detailYearTab !== "all" ? ` to ${detailYearTab} Year` : "" }}
                        </button>
                      </div>
                    </h6>
                    <div v-if="uploadingSubjects" class="detail-upload-panel">
                      <p class="small text-muted mb-2">
                        CSV headers: <strong>code</strong>, <strong>subject</strong>, optional
                        <strong>year level</strong>. Rows without a year use the active tab{{
                          detailYearTab !== "all" ? ` (${detailYearTab} Year)` : " (untagged)"
                        }}.
                      </p>
                      <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <button class="btn-inline-cancel" @click="downloadSubjectTemplate" type="button">
                          <i class="fas fa-download me-1"></i>
                          Template
                        </button>
                        <input type="file" ref="subjectFileInput" class="form-control flex-grow-1" accept=".csv" style="min-width: 200px" />
                      </div>
                      <div v-if="subjectUploadError" class="upload-status upload-status-error mb-2">
                        {{ subjectUploadError }}
                      </div>
                      <div v-if="subjectUploadSuccess" class="upload-status upload-status-success mb-2">
                        {{ subjectUploadSuccess }}
                      </div>
                      <div class="d-flex gap-2">
                        <button class="btn-inline-save" :disabled="subjectUploading" @click="uploadSubjects">
                          {{ subjectUploading ? "Uploading…" : "Upload" }}
                        </button>
                        <button class="btn-inline-cancel" @click="uploadingSubjects = false">Cancel</button>
                      </div>
                    </div>
                    <div v-if="addingSubject" class="detail-add-row">
                      <input
                        v-model="newSubjectText"
                        class="form-control-premium sm flex-grow-1"
                        placeholder="e.g. IT101 - Programming"
                        @keydown.enter.prevent="saveDetailSubject"
                      />
                      <button class="btn-inline-save" :disabled="detailSaving" @click="saveDetailSubject">
                        Save
                      </button>
                      <button class="btn-inline-cancel" @click="addingSubject = false">Cancel</button>
                    </div>
                    <div class="detail-subject-list">
                      <div v-if="selectedSubjectIds.length" class="detail-bulk-bar">
                        <input
                          type="checkbox"
                          class="form-check-input detail-check flex-shrink-0 m-0"
                          :checked="allVisibleSubjectsSelected"
                          :indeterminate="isSubjectSelectionPartial"
                          :aria-label="allVisibleSubjectsSelected ? 'Deselect all subjects' : 'Select all subjects'"
                          @change="toggleSelectAllSubjects"
                        />
                        <span class="bulk-toast-number">{{ selectedSubjectIds.length }}</span>
                        <span class="small fw-600 text-muted">selected</span>
                        <button class="btn-inline-save danger ms-auto" :disabled="!selectedSubjectIds.length || bulkDeletingSubjects" @click="bulkDeleteSubjects">
                          <i class="fas fa-trash-alt me-1"></i>
                          {{ bulkDeletingSubjects ? "Deleting…" : "Delete" }}
                        </button>
                        <button class="btn-inline-cancel" @click="selectedSubjectIds = []">Clear</button>
                      </div>
                      <div v-for="(sub, idx) in detailSubjects" :key="sub.id ?? idx" class="detail-subject-item">
                        <input
                          v-if="sub.id"
                          v-model="selectedSubjectIds"
                          :value="sub.id"
                          type="checkbox"
                          class="form-check-input detail-check flex-shrink-0 m-0"
                          :aria-label="`Select ${sub.name}`"
                        />
                        <div class="detail-item-idx">{{ idx + 1 }}</div>
                        <span v-if="sub.code" class="subject-code-chip">{{ sub.code }}</span>
                        <span class="fw-600 text-main small">{{ sub.name }}</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-auto small">{{ sub.year_level ?? "All years" }}</span>
                        <button v-if="sub.id" class="btn-remove-inline" title="Remove subject" @click="removeDetailSubject(sub.id)">
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div v-if="!detailSubjects.length" class="detail-empty">
                        <p class="small fw-600 mb-0">No subjects for this year yet.</p>
                      </div>
                    </div>
                  </div>

                  <div v-if="detailContentTab === 'sections'" class="detail-section">
                    <h6 class="detail-section-heading">
                      <span>
                        <i class="fas fa-users-rectangle text-primary opacity-60"></i>
                        Academic Sections
                      </span>
                      <button class="btn-add-inline" @click="openSectionAdder">
                        <i class="fas fa-plus"></i>
                        Add Section{{ detailYearTab !== "all" ? ` to ${detailYearTab} Year` : "" }}
                      </button>
                    </h6>
                    <div v-if="addingSection" class="detail-add-row">
                      <input
                        v-model="newSectionText"
                        class="form-control-premium sm flex-grow-1"
                        placeholder="e.g. 1-A"
                        @keydown.enter.prevent="saveDetailSection"
                      />
                      <button class="btn-inline-save" :disabled="detailSaving" @click="saveDetailSection">
                        Save
                      </button>
                      <button class="btn-inline-cancel" @click="addingSection = false">Cancel</button>
                    </div>
                    <div class="detail-section-pills">
                      <div v-for="(sec, idx) in detailSections" :key="sec.id ?? idx" class="detail-pill">
                        <div class="pill-dot"></div>
                        <span>{{ sec.text }}</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-1 small">{{ sec.year_level ?? "All years" }}</span>
                        <button v-if="sec.id" class="btn-remove-inline" title="Remove section" @click="removeDetailSection(sec.id)">
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div v-if="!detailSections.length" class="detail-empty">
                        <p class="small fw-600 mb-0">No sections for this year yet.</p>
                      </div>
                    </div>
                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="fas fa-arrow-left me-1"></i>
                  Back to Courses
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Curriculum Form Modal -->
        <div ref="courseModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title fw-800 d-flex align-items-center gap-2">
                  <i class="fas fa-graduation-cap text-primary"></i>
                  {{ editMode ? "Update Curriculum" : "New Course Curriculum" }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="row g-4 px-1 py-1">
                  <div class="col-md-6">
                    <label class="form-label-premium">Course Name</label>
                    <input v-model="form.name" class="form-control-premium" placeholder="e.g. BSIT" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Department</label>
                    <input v-model="form.department" class="form-control-premium" placeholder="e.g. CIT Dept" />
                  </div>

                  <div class="col-12">
                    <p class="small text-muted mb-0">
                      Subjects and sections are managed per year level inside the curriculum detail after saving.
                    </p>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" @click="saveCourse" :disabled="saving">
                  {{ saving ? "Processing..." : "Save Curriculum" }}
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
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";

const courses = ref([]);
const loading = ref(true);
const saving = ref(false);
const showModal = ref(false);
const editMode = ref(false);
const editId = ref(null);


const activeCourseId = ref(null);
const activeCourse = computed(() => courses.value.find((c) => c.id === activeCourseId.value) || null);

const detailReady = ref(false);
const { modalEl: courseModalEl } = useBootstrapModal(showModal);
const { modalEl: detailModalEl } = useBootstrapModal(detailReady);

const coursePage = ref(1);
const coursesPerPage = ref(10);
const totalCoursePages = ref(1);

function changePerPage(n) {
  const size = parseInt(n, 10);
  if (!Number.isFinite(size) || size === coursesPerPage.value) return;
  coursesPerPage.value = size;
  coursePage.value = 1;
  fetchCourses();
}
const totalCoursesCount = ref(0);
const searchQuery = ref("");
let searchTimeout = null;

const form = ref({ name: "", department: "" });

const uniqueDepartmentsCount = computed(() => {
  const depts = courses.value.map((c) => c.department);
  return new Set(depts).size;
});

onMounted(() => {
  fetchCourses();
  document.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleEscape);
});

function handleEscape(e) {
  if (e.key === "Escape") {
    if (detailReady.value) closeDetail();
    else if (showModal.value) closeModal();
  }
}

function splitList(str) {
  if (!str) return [];
  return str
    .split(",")
    .map((s) => s.trim())
    .filter((s) => s);
}

async function fetchCourses() {
  loading.value = true;
  try {
    let url = `/courses?paginate=true&page=${coursePage.value}&per_page=${coursesPerPage.value}`;
    if (searchQuery.value) url += `&query=${encodeURIComponent(searchQuery.value)}`;
    const res = await api.get(url);
    courses.value = res.data.data;
    totalCoursePages.value = res.data.last_page;
    totalCoursesCount.value = res.data.total;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

watch(coursePage, fetchCourses);

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    coursePage.value = 1;
    fetchCourses();
  }, 500);
}

function openDetail(course) {
  activeCourseId.value = course.id;
  detailContentTab.value = "subjects";
  detailYearTab.value = "all";
  addingSubject.value = false;
  addingSection.value = false;
  uploadingSubjects.value = false;
  subjectUploadError.value = "";
  subjectUploadSuccess.value = "";
  selectedSubjectIds.value = [];
  detailReady.value = true;
}

function closeDetail() {
  detailReady.value = false;
}

function openAddModal() {
  editMode.value = false;
  form.value = { name: "", department: "" };
  showModal.value = true;
}

function openEditModal(course) {
  editMode.value = true;
  editId.value = course.id;
  form.value = { name: course.name, department: course.department };
  showModal.value = true;
}

// Display helpers: prefer academic relations (carry year levels), fall back to legacy strings.
function courseSubjects(course) {
  if (!course) return [];
  if (course.academic_subjects?.length) {
    return course.academic_subjects.map((s) => ({
      id: s.id,
      code: s.code || null,
      name: s.name,
      text: s.code ? `${s.code} - ${s.name}` : s.name,
      year_level: s.year_level ?? null,
    }));
  }
  return splitList(course.subjects).map((t) => ({ id: null, code: null, name: t, text: t, year_level: null }));
}

function courseSections(course) {
  if (!course) return [];
  if (course.academic_sections?.length) {
    return course.academic_sections.map((s) => ({ id: s.id, text: s.name, year_level: s.year_level ?? null }));
  }
  return splitList(course.sections).map((t) => ({ id: null, text: t, year_level: null }));
}

// ── Detail tab state ──
const detailContentTab = ref("subjects");
const detailYearTab = ref("all");
const detailYearTabs = [
  { label: "All", value: "all" },
  { label: "1st Year", value: "1st" },
  { label: "2nd Year", value: "2nd" },
  { label: "3rd Year", value: "3rd" },
  { label: "4th Year", value: "4th" },
  { label: "Irregular", value: "Irregular" },
];

function matchesDetailYear(itemYear) {
  if (detailYearTab.value === "all") return true;
  if (!itemYear || itemYear === "Irregular" || detailYearTab.value === "Irregular") return true;
  return itemYear === detailYearTab.value;
}

const detailSubjects = computed(() => courseSubjects(activeCourse.value).filter((s) => matchesDetailYear(s.year_level)));
const detailSections = computed(() => courseSections(activeCourse.value).filter((s) => matchesDetailYear(s.year_level)));

// Inline add state (year comes from the active tab; "All" tab asks per row)
const addingSubject = ref(false);
const addingSection = ref(false);
const newSubjectText = ref("");
const newSectionText = ref("");
const detailSaving = ref(false);

// Batch subject upload (CSV) — inline panel, same conventions as single-add.
const uploadingSubjects = ref(false);
const subjectFileInput = ref(null);
const subjectUploading = ref(false);
const subjectUploadError = ref("");
const subjectUploadSuccess = ref("");
const selectedSubjectIds = ref([]);
const bulkDeletingSubjects = ref(false);

function openSubjectUpload() {
  uploadingSubjects.value = true;
  addingSubject.value = false;
  addingSection.value = false;
  subjectUploadError.value = "";
  subjectUploadSuccess.value = "";
  if (subjectFileInput.value) subjectFileInput.value.value = null;
}

function downloadSubjectTemplate() {
  const headers = ["code", "subject", "year level"];
  const example = ["IT101", "Programming", "1st"];
  const csv = "\uFEFF" + headers.join(",") + "\n" + example.join(",") + "\n";
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = "subjects_template.csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

async function uploadSubjects() {
  const file = subjectFileInput.value?.files[0];
  if (!file || !activeCourse.value) {
    subjectUploadError.value = "Please select a CSV file.";
    return;
  }

  subjectUploading.value = true;
  subjectUploadError.value = "";
  subjectUploadSuccess.value = "";

  const formData = new FormData();
  formData.append("file", file);
  // Rows without their own year inherit the active tab (All → untagged).
  if (detailYearTab.value !== "all") formData.append("year_level", detailYearTab.value);

  try {
    const res = await api.post(`/courses/${activeCourse.value.id}/subjects/import`, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    subjectUploadSuccess.value = `Imported ${res.data.imported} subjects. Failed: ${res.data.failed}.`;
    await fetchCourses();
    if (subjectFileInput.value) subjectFileInput.value.value = null;
  } catch (e) {
    subjectUploadError.value = e.response?.data?.message || "Upload failed.";
  } finally {
    subjectUploading.value = false;
  }
}

function openSubjectAdder() {
  addingSubject.value = true;
  addingSection.value = false;
  uploadingSubjects.value = false;
  newSubjectText.value = "";
}

function openSectionAdder() {
  addingSection.value = true;
  addingSubject.value = false;
  newSectionText.value = "";
}

async function saveDetailSubject() {
  const text = newSubjectText.value.trim();
  if (!text || !activeCourse.value) return;
  detailSaving.value = true;
  try {
    await api.post(`/courses/${activeCourse.value.id}/subjects`, {
      text,
      // All tab → null (adopted by all years); otherwise the active tab's year.
      year_level: detailYearTab.value !== "all" ? detailYearTab.value : null,
    });
    newSubjectText.value = "";
    addingSubject.value = false;
    await fetchCourses();
  } catch (e) {
    Swal.fire("Error", e.response?.data?.message || "Failed to add subject.", "error");
  } finally {
    detailSaving.value = false;
  }
}

async function saveDetailSection() {
  const text = newSectionText.value.trim();
  if (!text || !activeCourse.value) return;
  detailSaving.value = true;
  try {
    await api.post(`/courses/${activeCourse.value.id}/sections`, {
      text,
      // All tab → null (adopted by all years); otherwise the active tab's year.
      year_level: detailYearTab.value !== "all" ? detailYearTab.value : null,
    });
    newSectionText.value = "";
    addingSection.value = false;
    await fetchCourses();
  } catch (e) {
    Swal.fire("Error", e.response?.data?.message || "Failed to add section.", "error");
  } finally {
    detailSaving.value = false;
  }
}

async function removeDetailSubject(id) {
  if (!id || !activeCourse.value) return;
  try {
    await api.delete(`/courses/${activeCourse.value.id}/subjects/${id}`);
    selectedSubjectIds.value = selectedSubjectIds.value.filter((sid) => sid !== id);
    await fetchCourses();
  } catch (e) {
    Swal.fire("Error", "Failed to remove subject.", "error");
  }
}

// Ids of currently visible rows that can actually be deleted (legacy
// comma-string rows have no record id and are skipped).
const deletableSubjectIds = computed(() =>
  detailSubjects.value.filter((s) => s.id).map((s) => s.id),
);

const allVisibleSubjectsSelected = computed(
  () =>
    deletableSubjectIds.value.length > 0 &&
    deletableSubjectIds.value.every((id) => selectedSubjectIds.value.includes(id)),
);

const isSubjectSelectionPartial = computed(
  () =>
    selectedSubjectIds.value.length > 0 && !allVisibleSubjectsSelected.value,
);

function toggleSelectAllSubjects() {
  if (allVisibleSubjectsSelected.value) {
    const visible = new Set(deletableSubjectIds.value);
    selectedSubjectIds.value = selectedSubjectIds.value.filter((id) => !visible.has(id));
  } else {
    const selected = new Set(selectedSubjectIds.value);
    deletableSubjectIds.value.forEach((id) => selected.add(id));
    selectedSubjectIds.value = [...selected];
  }
}

async function bulkDeleteSubjects() {  if (!selectedSubjectIds.value.length || !activeCourse.value) return;
  const result = await confirmAction({
    title: `Delete ${selectedSubjectIds.value.length} subjects?`,
    message: "Delete the selected subjects? This cannot be undone.",
  });
  if (!result.isConfirmed) return;
  bulkDeletingSubjects.value = true;
  try {
    await api.post(`/courses/${activeCourse.value.id}/subjects/bulk-delete`, {
      ids: selectedSubjectIds.value,
    });
    selectedSubjectIds.value = [];
    await fetchCourses();
  } catch (e) {
    Swal.fire("Error", "Failed to delete subjects.", "error");
  } finally {
    bulkDeletingSubjects.value = false;
  }
}

async function removeDetailSection(id) {
  if (!id || !activeCourse.value) return;
  try {
    await api.delete(`/courses/${activeCourse.value.id}/sections/${id}`);
    await fetchCourses();
  } catch (e) {
    Swal.fire("Error", "Failed to remove section.", "error");
  }
}

function closeModal() {
  showModal.value = false;
}

async function saveCourse() {
  if (!form.value.name || !form.value.department) return;
  saving.value = true;
  try {
    const payload = { name: form.value.name, department: form.value.department };
    if (editMode.value) await api.put(`/courses/${editId.value}`, payload);
    else await api.post("/courses", payload);
    closeModal();
    await fetchCourses();
    Swal.fire({
      icon: "success",
      title: "Saved",
      text: "Curriculum has been saved successfully.",
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (e) {
    Swal.fire("Error", "Failed to save course.", "error");
  } finally {
    saving.value = false;
  }
}

async function deleteCourse(id) {
  const result = await confirmAction({
    title: "Are you sure?",
    message: "Do you want to delete this curriculum? This cannot be undone.",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/courses/${id}`);
    await fetchCourses();
    if (activeCourseId.value === id) { detailReady.value = false; }
    Swal.fire("Deleted!", "Curriculum has been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete course.", "error");
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
.fw-900 {
  font-weight: 900;
}

.bg-card {
  background: var(--bg-card);
}

/* Slim Stats Bar */
.stat-item-inline .label {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.stat-item-inline .value {
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--text-dark);
}
.stat-divider {
  width: 1px;
  height: 32px;
  background: var(--border-light);
}

.search-premium-box {
  position: relative;
  width: 280px;
}

.search-premium-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.85rem;
}

.search-premium-box input {
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
  transition: all 0.3s;
}

.search-premium-box input::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}

.search-premium-box input:focus {
  outline: none;
  border-color: var(--primary);
  background: var(--bg-card);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

/* Course Grid Card */
.course-card-premium {
  background: var(--bg-card);
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.2, 0, 0, 1);
  height: 100%;
}

.course-card-premium:hover {
  transform: translateY(-8px) scale(1.02);
  border-color: var(--primary);
}

.card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 120px;
  height: 120px;
  background: var(--primary);
  filter: blur(80px);
  opacity: 0.05;
  pointer-events: none;
}

.course-icon-box {
  width: 50px;
  height: 50px;
  border-radius: 1rem;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.course-title-v3 {
  font-size: 1.15rem;
  color: var(--text-dark);
  letter-spacing: -0.01em;
}

.course-dept-v3 {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.tiny-label {
  font-size: 0.6rem;
  font-weight: 800;
  color: var(--text-muted);
  letter-spacing: 0.05em;
  margin-top: 2px;
}

.btn-action-minimal {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: #ffffff;
  color: var(--text-muted);
  font-size: 0.8rem;
  transition: all 0.2s;
}

.btn-action-minimal:hover {
  background: var(--primary);
  color: white;
}

.btn-action-minimal.danger:hover {
  background: var(--danger);
  color: white;
}

.course-icon-box.sm {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  font-size: 0.9rem;
  flex-shrink: 0;
}



.view-details-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary);
  opacity: 0.5;
  transition: all 0.2s;
}

.course-card-premium:hover .view-details-link {
  opacity: 1;
  transform: translateX(4px);
}

/* Detail inner styles */
.detail-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 1rem;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.detail-dept {
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.detail-stats-row {
  display: flex;
  gap: 0.5rem;
  padding: 0 1.5rem 1rem;
  flex-shrink: 0;
}

.detail-stat-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.85rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 2rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-main);
}

.detail-stat-chip i {
  color: var(--primary);
  font-size: 0.65rem;
  opacity: 0.6;
}

.detail-section {
  margin-bottom: 1.5rem;
}

.detail-section-heading {
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--text-muted);
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.btn-add-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--primary);
  background: transparent;
  color: var(--primary);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
  border-radius: 8px;
  padding: 5px 10px;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.btn-add-inline:hover {
  background: var(--primary);
  color: #fff;
}

[data-theme="dark"] .btn-add-inline {
  color: #79c0ff;
  border-color: #79c0ff;
}

[data-theme="dark"] .btn-add-inline:hover {
  background: #79c0ff;
  color: #0d1117;
}

.detail-add-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0.6rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  padding: 8px;
}

.detail-add-row .form-control-premium.sm {
  height: 36px;
  background: var(--bg-card);
}

.btn-inline-save,
.btn-inline-cancel {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.btn-inline-save {
  border: 1px solid var(--primary);
  background: var(--primary);
  color: #fff;
}

.btn-inline-save:hover:not(:disabled) {
  background: #232380;
  border-color: #232380;
}

.btn-inline-save:disabled {
  opacity: 0.6;
  cursor: default;
}

.btn-inline-cancel {
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
}

.btn-inline-cancel:hover {
  background: var(--bg-light);
  color: var(--text-main);
}

.detail-upload-panel {
  margin-bottom: 0.6rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  padding: 12px;
}

.detail-upload-panel strong {
  color: var(--text-dark);
}

.upload-status {
  font-size: 0.8rem;
  font-weight: 600;
}

.upload-status-error {
  color: #dc2626;
}
.upload-status-success {
  color: var(--success);
}

[data-theme="dark"] .upload-status-error {
  color: #ff7b72;
}

[data-theme="dark"] .upload-status-success {
  color: #3fb950;
}

.subject-code-chip {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.03em;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  color: var(--text-main);
  white-space: nowrap;
  flex-shrink: 0;
}

.detail-bulk-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0.6rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  padding: 8px 12px;
}

.btn-inline-save.danger {
  background: var(--danger);
  border-color: var(--danger);
}

.btn-inline-save.danger:hover:not(:disabled) {
  filter: brightness(0.92);
}

.detail-check {
  width: 1rem;
  height: 1rem;
  cursor: pointer;
}

.btn-remove-inline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 4px;
  border: 0;
  background: transparent;
  color: var(--text-muted);
  font-size: 0.65rem;
  cursor: pointer;
  flex-shrink: 0;
}

.btn-remove-inline:hover {
  background: var(--badge-danger-bg);
  color: var(--badge-danger-text);
}

/* Content switcher: underline tabs (distinct from the year pill menu below) */
.year-tab-menu.content-tabs {
  background: transparent;
  border: 0;
  border-bottom: 1px solid var(--border-color);
  border-radius: 0;
  padding: 0;
  gap: 28px;
  justify-content: center;
}

.year-tab-menu.content-tabs .year-tab {
  flex: 0 0 auto;
  border-radius: 0;
  padding: 10px 4px;
  font-size: 0.88rem;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}

.year-tab-menu.content-tabs .year-tab:hover {
  background: transparent;
  color: var(--text-main);
}

.year-tab-menu.content-tabs .year-tab.active {
  background: transparent;
  color: var(--primary);
  border-bottom-color: var(--primary);
}

/* Year Level switcher: borderless chip row (no outer box). */
.year-pills {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 0;
  margin: 0;
  background: transparent;
  border: 0;
}

.year-pill {
  border: 1px solid transparent;
  background: transparent;
  color: var(--text-muted);
  font-size: 0.82rem;
  font-weight: 600;
  padding: 7px 16px;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
  transition:
    background-color 0.15s ease,
    color 0.15s ease,
    border-color 0.15s ease;
}

.year-pill:hover {
  background: var(--bg-light);
  color: var(--text-main);
}

.year-pill.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

[data-theme="dark"] .year-pill:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
}

[data-theme="dark"] .year-pill.active {
  background: #1f6feb;
  border-color: #1f6feb;
  color: #fff;
}

.detail-subject-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.detail-subject-item {
  background: var(--bg-light);
  padding: 0.7rem 0.85rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border-light);
  display: flex;
  align-items: center;
  gap: 0.65rem;
  transition: all 0.2s;
}

.detail-subject-item:hover {
  background: white;
  transform: translateX(-3px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.detail-item-idx {
  width: 24px;
  height: 24px;
  background: var(--primary);
  color: white;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 800;
  flex-shrink: 0;
}

.detail-section-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.detail-pill {
  background: white;
  padding: 0.45rem 0.9rem;
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: all 0.2s;
}

.detail-pill:hover {
  border-color: var(--primary);
  transform: translateY(-1px);
}

.detail-pill .pill-dot {
  width: 5px;
  height: 5px;
  background: var(--primary);
  border-radius: 50%;
}

.detail-empty {
  text-align: center;
  padding: 1.25rem;
  opacity: 0.5;
}

.form-label-premium {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 0.5rem;
}

.form-control-premium {
  width: 100%;
  padding: 0.85rem 1.25rem;
  border-radius: 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  font-weight: 600;
  transition: all 0.3s;
}

.form-control-premium.sm {
  padding: 0.6rem 1rem;
  font-size: 0.85rem;
}

.form-control-premium:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
}

.premium-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
  background: rgba(25, 25, 112, 0.08);
  color: var(--primary);
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 700;
}

.premium-tag.secondary {
  background: rgba(16, 185, 129, 0.08);
  color: #10b981;
}

.premium-tag i {
  cursor: pointer;
  margin-left: 0.5rem;
  opacity: 0.5;
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

/* Animations */

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
