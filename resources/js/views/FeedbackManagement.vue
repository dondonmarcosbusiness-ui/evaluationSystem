<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Feedback Management</template></Navbar>

      <div class="content-area">
        <div class="card accounts-card">
          <div class="card-header border-0 py-3 px-4 accounts-head">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 w-100">
              <div class="d-flex flex-column">
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                  <h5 class="mb-0 fw-800 text-main">Feedback Analytics</h5>
                  <span class="head-label">{{ pagination?.total || 0 }} Records</span>
                </div>
                <p class="head-desc mb-0">Review qualitative feedback and ratings submitted through evaluations.</p>
              </div>

              <div class="d-flex align-items-center gap-2 flex-wrap">
                <button
                  class="btn btn-ghost"
                  :class="{ 'is-active': showFilters }"
                  title="Show or hide filters"
                  @click="showFilters = !showFilters"
                >
                  <i class="fas fa-filter"></i>
                  <span>Filters</span>
                  <span v-if="activeFilterCount" class="filter-count">{{ activeFilterCount }}</span>
                </button>
              </div>
            </div>

            <Transition name="panel">
              <div v-show="showFilters" class="filters-panel">
                <div class="filter-toolbar">
                  <div class="premium-filter-group" style="width: 220px">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input
                      v-model="filters.search"
                      type="text"
                      class="form-control"
                      placeholder="Search faculty, subject, feedback..."
                      @input="handleSearch"
                    />
                  </div>

                  <div v-if="evaluateeType === 'faculty'" class="premium-filter-group" style="width: 180px">
                    <span class="input-group-text"><i class="fas fa-book"></i></span>
                    <input
                      v-model="filters.subject_code"
                      type="text"
                      class="form-control"
                      placeholder="Subject Code..."
                      @input="handleSearch"
                    />
                  </div>

                  <button class="btn-premium-reset" @click="clearFilters" title="Reset Filters">
                    <i class="fas fa-redo-alt small"></i>
                  </button>
                </div>

                <FilterTree :groups="feedbackFilterGroups" @select="onTreeSelect" />
              </div>
            </Transition>
          </div>

          <!-- Table Content -->
          <div class="card-body p-0">
            <div class="table-responsive">
              <div class="table-scroll" @scroll="onTableScroll">
              <table class="table table-hover mb-0">
                <thead :class="{ 'glass-header': tableScrolled }">
                  <tr>
                    <th class="ps-4 th-sortable" @click="toggleSort('faculty_name')">
                      Faculty Member <i class="fas sort-arrow" :class="sortIcon('faculty_name')"></i>
                    </th>
                    <th v-if="evaluateeType === 'faculty'">Subject</th>
                    <th v-if="evaluateeType === 'faculty'" class="th-sortable" @click="toggleSort('department')">
                      Department <i class="fas sort-arrow" :class="sortIcon('department')"></i>
                    </th>
                    <th class="th-sortable" @click="toggleSort('rating')">
                      Rating <i class="fas sort-arrow" :class="sortIcon('rating')"></i>
                    </th>
                    <th>Feedback Snippet</th>
                    <th class="th-sortable pe-4" @click="toggleSort('created_at')">
                      Date Submitted <i class="fas sort-arrow" :class="sortIcon('created_at')"></i>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="loading">
                    <tr v-for="r in 6" :key="r">
                      <td colspan="6" class="ps-4 py-3">
                        <div class="d-flex align-items-center gap-4">
                          <div class="sk-shimmer" style="width: 22%; height: 14px"></div>
                          <div class="sk-shimmer" style="width: 14%; height: 14px"></div>
                          <div class="sk-shimmer" style="width: 12%; height: 14px"></div>
                          <div class="sk-shimmer" style="width: 26%; height: 14px"></div>
                          <div class="sk-shimmer" style="width: 16%; height: 14px"></div>
                        </div>
                      </td>
                    </tr>
                  </template>
                  <template v-else-if="feedbacks.length > 0">
                    <tr v-for="feedback in feedbacks" :key="feedback.id" class="feedback-row" @click="viewDetails(feedback)">
                      <td class="ps-4 py-3">
                        <div class="fw-bold">{{ feedback.faculty_name }}</div>
                      </td>
                      <td v-if="evaluateeType === 'faculty'">
                        <span class="badge bg-light text-dark border">{{ feedback.subject_code || 'General' }}</span>
                      </td>
                      <td v-if="evaluateeType === 'faculty'" class="small">{{ feedback.department }}</td>
                      <td>
                        <div class="rating-chip" :class="ratingTone(feedback.rating)">
                          <i class="fas fa-star"></i>
                          <span class="rating-score">{{ ratingValue(feedback.rating) }}</span>
                          <span class="rating-label">{{ getRatingLabel(feedback.rating) }}</span>
                        </div>
                        <div class="rating-bar">
                          <span :style="{ width: ratingPercent(feedback.rating) }"></span>
                        </div>
                      </td>
                      <td class="small">
                        <button class="btn btn-view-feedback btn-sm" @click.stop="viewDetails(feedback)">
                          <i class="fas fa-comment-dots"></i>
                          <span>View Feedback</span>
                        </button>
                      </td>
                      <td class="small text-muted pe-4">
                        {{ new Date(feedback.created_at).toLocaleDateString() }}
                      </td>
                    </tr>
                  </template>
                  <tr v-else>
                    <td colspan="6" class="text-center py-5">
                      <div class="mb-3 opacity-25">
                        <i class="fas fa-comments fa-4x"></i>
                      </div>
                      <h6 class="fw-bold text-muted">No recent feedback available.</h6>
                      <p class="text-muted small">Try adjusting your filters or search terms.</p>
                    </td>
                  </tr>
                </tbody>
              </table>
              </div>
            </div>
            
            <!-- Pagination -->
            <Pagination
              :pagination="pagination"
              :per-page="perPage"
              @change-page="fetchFeedbacks"
              @update:per-page="changePerPage"
            />
          </div>
        </div>
      </div>

      <!-- Feedback Details Modal -->
      <div ref="detailModalEl" class="modal fade eval-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" :class="isFullscreen ? 'modal-fullscreen' : 'modal-xl'">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title">Evaluation Detail</h5>
                <p class="text-muted small mb-0">Full feedback and quantitative breakdown</p>
              </div>
              <div class="d-flex gap-2 ms-auto align-items-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="isFullscreen = !isFullscreen" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
                  <i :class="isFullscreen ? 'fas fa-compress' : 'fas fa-expand'"></i>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
            </div>

            <div class="modal-body">
              <div v-if="detailLoading" class="py-4">
                <SkeletonLoader variant="list" :rows="4" />
              </div>
              <div v-else-if="currentDetail">
                <div class="row g-3 align-items-start">
                  <!-- Left Column -->
                  <div class="col-md-3 d-flex flex-column gap-3">
                    <!-- Faculty Information Fieldset -->
                    <fieldset class="legend-border">
                      <legend class="legend-title">Faculty Information</legend>
                      <div class="mb-3">
                        <div class="eval-field-label">Name:</div>
                        <div class="eval-field-value">{{ currentDetail.faculty?.user?.name }}</div>
                      </div>
                      <div v-if="evaluateeType === 'faculty'" class="mb-3">
                        <div class="eval-field-label">Department:</div>
                        <div class="eval-field-value">{{ currentDetail.faculty?.department }}</div>
                      </div>
                      <div class="mb-0">
                        <div class="eval-field-label">Position:</div>
                        <div class="eval-field-value">{{ currentDetail.faculty?.position }}</div>
                      </div>
                    </fieldset>

                    <!-- Evaluation Information Fieldset -->
                    <fieldset class="legend-border">
                      <legend class="legend-title">Evaluation Information</legend>
                      <div v-if="evaluateeType === 'faculty'" class="mb-3">
                        <div class="eval-field-label">Subjects Included:</div>
                        <div class="eval-field-value">{{ uniqueSubjects }}</div>
                      </div>
                      <div class="mb-3">
                        <div class="eval-field-label">Period Filters:</div>
                        <div class="eval-field-value">
                          {{ filters.semester !== 'all' ? filters.semester : 'All Semesters' }}, {{ filters.academic_year !== 'all' ? filters.academic_year : 'All Years' }}
                        </div>
                      </div>
                      <div class="mb-0">
                        <div class="eval-field-label">Total Feedbacks:</div>
                        <div class="eval-field-value">{{ currentDetail.evaluations?.length || 0 }} comments</div>
                      </div>
                    </fieldset>
                  </div>

                  <!-- Right Column: Quantitative Breakdown + All Qualitative Feedback -->
                  <div class="col-md-9 d-flex flex-column gap-3">
                    <fieldset class="legend-border">
                      <legend class="legend-title">Quantitative Breakdown</legend>
                      <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                          <thead>
                            <tr class="border-bottom text-muted small">
                              <th class="ps-0 py-2 fw-bold text-dark" style="width: 50%;">Category</th>
                              <th class="text-center py-2 fw-bold text-dark" style="width: 25%;">Average Rating</th>
                              <th class="text-end pe-0 py-2 fw-bold text-dark" style="width: 25%;">Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="cat in currentDetail.category_scores" :key="cat.category_name" class="border-bottom-light">
                              <td class="ps-0 py-2 text-dark small">{{ cat.category_name }}</td>
                              <td class="text-center py-2 fw-bold text-dark small">{{ parseFloat(cat.average_rating).toFixed(2) }}</td>
                              <td class="text-end pe-0 py-2">
                                <span class="badge rounded-pill" :class="getRatingBadgeClass(cat.average_rating)">
                                  {{ getRatingLabel(cat.average_rating) }} ({{ parseFloat(cat.average_rating).toFixed(2) }})
                                </span>
                              </td>
                            </tr>
                            <tr v-if="!currentDetail.category_scores || currentDetail.category_scores.length === 0">
                              <td colspan="3" class="text-center py-3 text-muted small">No quantitative scores available.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </fieldset>

                    <!-- All Qualitative Feedback -->
                    <fieldset class="legend-border">
                      <legend class="legend-title">All Qualitative Feedback</legend>
                      <div class="feedbacks-table-container" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-borderless align-middle mb-0 w-100">
                          <thead>
                            <tr class="border-bottom text-muted small position-sticky top-0 bg-white z-1" style="background-color: var(--bg-card) !important;">
                              <th class="ps-0 py-2 fw-bold text-dark">Feedback</th>
                              <th class="text-end pe-0 py-2 fw-bold text-dark" style="width: 150px;">Date Submitted</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="evalItem in currentDetail.evaluations" :key="evalItem.id" class="border-bottom-light">
                              <td class="ps-0 py-2 text-muted small text-wrap text-break" style="max-width: 800px;">
                                {{ evalItem.comments }}
                              </td>
                              <td class="text-end pe-0 py-2 text-muted small text-nowrap">
                                {{ formatDate(evalItem.created_at) }}
                              </td>
                            </tr>
                            <tr v-if="!currentDetail.evaluations || currentDetail.evaluations.length === 0">
                              <td colspan="2" class="text-center py-4 text-muted small">
                                No written feedback available matching current filters.
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </fieldset>
                  </div>
                </div>

              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Hidden Print Area -->
      <div id="print-area" class="d-none">
        <div class="print-header text-center mb-4">
          <img src="/assets/img/neust_logo.webp" style="width: 80px; height: 80px;" alt="Logo">
          <h3 class="mt-2">NEUST Faculty Evaluation System</h3>
          <h5>Feedback Analytics Report</h5>
          <p class="text-muted small">Generated on {{ new Date().toLocaleString() }}</p>
          <hr>
        </div>
        <table class="table table-bordered w-100">
          <thead>
            <tr>
              <th>Faculty</th>
              <th v-if="evaluateeType === 'faculty'">Subject</th>
              <th>Rating</th>
              <th>Feedback</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in feedbacks" :key="f.id">
              <td>{{ f.faculty_name }}</td>
              <td v-if="evaluateeType === 'faculty'">{{ f.subject_code }}</td>
              <td>{{ f.rating }}</td>
              <td style="max-width: 300px;">{{ f.text }}</td>
              <td>{{ new Date(f.created_at).toLocaleDateString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject, watch } from "vue";
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import FilterTree from "../components/FilterTree.vue";
import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";
import { DEFAULT_SEMESTERS, defaultAcademicYears, asStringArray, fetchAcademicConfig, courseDepartments } from "../helpers/academic.js";

const can = inject("can");
const route = useRoute();

// State
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const evaluateeType = ref('faculty');
const feedbacks = ref([]);
const pagination = ref({});
const perPage = ref(10);

function changePerPage(n) {
  perPage.value = n;
  fetchFeedbacks(1);
}
const loading = ref(false);
const detailLoading = ref(false);
const showDetailModal = ref(false);
const isFullscreen = ref(false);
const showFilters = ref(false);
const sort = ref({ by: "", dir: "" });
const { modalEl: detailModalEl } = useBootstrapModal(showDetailModal);
const currentDetail = ref(null);
const facultyList = ref([]);
const departments = ref([]);
const years = ref([]);
const tableScrolled = ref(false);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

const activePeriod = { semester: "all", academic_year: "all" };

const filters = ref({
  search: "",
  faculty_id: "all",
  department: "all",
  rating: "all",
  semester: "all",
  academic_year: "all",
  subject_code: "",
});

let searchTimeout = null;

const activeFilterCount = computed(
  () => Object.values(filters.value).filter((value) => value !== "" && value !== null && value !== "all").length,
);

function optionLabel(options, value) {
  return options.find((o) => o.value === value)?.label ?? options[0]?.label ?? "";
}

// Options
const facultyOptions = computed(() => {
  const prefix = "All Faculty";
  return [
    { label: prefix, value: "all" },
    ...facultyList.value.map(f => {
      return { 
        label: `${f.user?.name || ''}`, 
        value: f.id 
      };
    })
  ];
});

const departmentOptions = computed(() => [
  { label: "All Departments", value: "all" },
  ...departments.value.map(d => ({ label: d, value: d }))
]);

const ratingOptions = [
  { label: "All Ratings", value: "all" },
  { label: "5 - Excellent", value: "5" },
  { label: "4 - Very Good", value: "4" },
  { label: "3 - Good", value: "3" },
  { label: "2 - Fair", value: "2" },
  { label: "1 - Poor", value: "1" },
];

const semesterList = ref([...DEFAULT_SEMESTERS]);

const semesterOptions = computed(() => [
  { label: "All Semesters", value: "all" },
  ...semesterList.value.map((s) => ({ label: s, value: s })),
]);

const yearOptions = computed(() => [
  { label: "All Years", value: "all" },
  ...years.value.map(y => ({ label: y, value: y }))
]);

const feedbackFilterGroups = computed(() => {
  const groups = [
    {
      key: "faculty_id",
      label: "Faculty",
      value: filters.value.faculty_id,
      valueLabel: optionLabel(facultyOptions.value, filters.value.faculty_id),
      options: facultyOptions.value,
    },
  ];

  if (evaluateeType.value === "faculty") {
    groups.push({
      key: "department",
      label: "Departments",
      value: filters.value.department,
      valueLabel: optionLabel(departmentOptions.value, filters.value.department),
      options: departmentOptions.value,
    });
  }

  groups.push(
    {
      key: "rating",
      label: "Rating",
      value: filters.value.rating,
      valueLabel: optionLabel(ratingOptions, filters.value.rating),
      options: ratingOptions,
    },
    {
      key: "semester",
      label: "Semester",
      value: filters.value.semester,
      valueLabel: optionLabel(semesterOptions.value, filters.value.semester),
      options: semesterOptions.value,
    },
    {
      key: "academic_year",
      label: "Academic Year",
      value: filters.value.academic_year,
      valueLabel: optionLabel(yearOptions.value, filters.value.academic_year),
      options: yearOptions.value,
    },
  );

  return groups;
});

function onTreeSelect({ key, value }) {
  filters.value[key] = value;
  fetchFeedbacks(1);
}

function getTypeFromRoute() {
  return "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("report.view.all") && user.value.role !== "faculty") {
    const type = getTypeFromRoute();
    if (evaluateeType.value !== type) {
      await switchTab(type);
    }
  }
}

watch(
  () => route.query.type,
  async () => {
    await applyEvaluateeTypeFromRoute();
  },
);

// Lifecycle
onMounted(() => {
  if (can("report.view.all") && user.value.role !== "faculty") {
    evaluateeType.value = getTypeFromRoute();
  }
  fetchMeta();
  fetchAcademicConfig().then((c) => {
    semesterList.value = c.semesters;
  });
});

// Methods
async function fetchFeedbacks(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: perPage.value,
      evaluatee_type: evaluateeType.value,
      ...filters.value,
      sort_by: sort.value.by,
      sort_dir: sort.value.dir
    };
    const res = await api.get("/reports/feedbacks", { params });
    feedbacks.value = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error("Failed to fetch feedbacks", e);
    Swal.fire("Error", "Could not load feedback data.", "error");
  } finally {
    loading.value = false;
  }
}

async function fetchMeta() {
  try {
    const listUrl = "/faculty/all";
    const [listRes, setRes, coursesRes] = await Promise.all([
      api.get(listUrl),
      api.get("/settings"),
      api.get("/courses")
    ]);
    
    const data = Array.isArray(listRes.data) ? listRes.data : (listRes.data?.data || []);
    facultyList.value = data;
    
    // Department filter options come from the Course List (single source of
    // truth) — never from faculty records, which can hold stale departments
    // whose courses were deleted.
    departments.value = courseDepartments(coursesRes.data);
    
    // Year options come from System Settings so the filter only offers
    // configured academic years (plus the active year as a safety net).
    years.value = asStringArray(setRes.data.academic_year_options, defaultAcademicYears());
    if (setRes.data.active_academic_year && !years.value.includes(setRes.data.active_academic_year)) {
      years.value.push(setRes.data.active_academic_year);
    }

    // Default to active period if not "all"
    if (setRes.data.active_semester) {
      activePeriod.semester = setRes.data.active_semester;
      filters.value.semester = setRes.data.active_semester;
    }
    if (setRes.data.active_academic_year) {
      activePeriod.academic_year = setRes.data.active_academic_year;
      filters.value.academic_year = setRes.data.active_academic_year;
    }
    
    // Refresh with defaults
    fetchFeedbacks(1);
    
  } catch (e) {
    console.error("Failed to fetch meta", e);
  }
}

async function switchTab(type) {
  evaluateeType.value = type;
  filters.value.faculty_id = "all";
  filters.value.department = "all";
  filters.value.subject_code = "";
  facultyList.value = [];
  departments.value = [];
  
  await fetchMeta();
}

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchFeedbacks(1);
  }, 500);
}

function toggleSort(column) {
  if (sort.value.by !== column) {
    sort.value = { by: column, dir: "asc" };
  } else if (sort.value.dir === "asc") {
    sort.value = { by: column, dir: "desc" };
  } else {
    sort.value = { by: "", dir: "" };
  }
  fetchFeedbacks(1);
}

function sortIcon(column) {
  if (sort.value.by !== column) return "fa-arrow-down is-idle";
  return sort.value.dir === "asc" ? "fa-arrow-up is-active" : "fa-arrow-down is-active";
}

function clearFilters() {
  filters.value = {
    search: "",
    faculty_id: "all",
    department: "all",
    rating: "all",
    semester: activePeriod.semester,
    academic_year: activePeriod.academic_year,
    subject_code: "",
  };
  sort.value = { by: "", dir: "" };
  fetchFeedbacks(1);
}

async function viewDetails(feedback) {
  currentDetail.value = null;
  showDetailModal.value = true;
  detailLoading.value = true;
  
  try {
    const params = { 
      ...filters.value,
      evaluatee_type: evaluateeType.value
    };
    const res = await api.get(`/reports/feedbacks/${feedback.id}`, { params });
    currentDetail.value = res.data;
  } catch (e) {
    console.error(e);
    Swal.fire("Error", "Could not load evaluation details.", "error");
    showDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
}

function getRatingBadgeClass(rating) {
  if (rating >= 4.5) return "bg-success text-white";
  if (rating >= 3.5) return "bg-primary text-white";
  if (rating >= 2.5) return "bg-warning text-dark";
  if (rating >= 1.5) return "bg-orange text-white";
  return "bg-danger text-white";
}

function getRatingLabel(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Very Good";
  if (rating >= 2.5) return "Good";
  if (rating >= 1.5) return "Fair";
  return "Poor";
}

function ratingTone(rating) {
  if (rating >= 4.5) return "is-excellent";
  if (rating >= 3.5) return "is-very-good";
  if (rating >= 2.5) return "is-good";
  if (rating >= 1.5) return "is-fair";
  return "is-poor";
}

function ratingValue(rating) {
  const n = parseFloat(rating);
  return isNaN(n) ? rating : n.toFixed(2);
}

function ratingPercent(rating) {
  const n = parseFloat(rating);
  if (isNaN(n)) return "0%";
  return `${Math.max(0, Math.min(100, (n / 5) * 100))}%`;
}

function exportToCSV() {
  if (feedbacks.value.length === 0) return;
  
  const headers = ["Faculty", "Subject", "Department", "Rating", "Feedback", "Date Submitted"];

  const rows = feedbacks.value.map(f => {
    const row = [
      f.faculty_name,
      f.subject_code,
      f.department,
      f.rating,
      f.text.replace(/"/g, '""'),
      new Date(f.created_at).toLocaleDateString()
    ];
    return row;
  });
  
  let csvContent = "data:text/csv;charset=utf-8," 
    + headers.join(",") + "\n"
    + rows.map(e => e.map(cell => `"${cell}"`).join(",")).join("\n");
    
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `${evaluateeType.value}_feedback_report_${new Date().toISOString().split('T')[0]}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function printReport() {
  const printEl = document.getElementById('print-area');
  printEl.classList.remove('d-none');
  window.print();
  printEl.classList.add('d-none');
}

function printCurrentFeedback() {
  if (!currentDetail.value) return;
  window.print();
}

const uniqueSubjects = computed(() => {
  if (!currentDetail.value || !currentDetail.value.evaluations) return "N/A";
  const subjs = currentDetail.value.evaluations
    .map(e => e.subject_code)
    .filter(Boolean);
  const unique = [...new Set(subjs)];
  return unique.length > 0 ? unique.join(", ") : "N/A";
});

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
</script>

<style scoped>
.feedback-row {
  cursor: pointer;
  transition: all 0.2s ease;
}

.feedback-row:hover {
  background-color: rgba(25, 25, 112, 0.03) !important;
  transform: translateY(-1px);
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

/* ── Evaluation Detail modal (scoped to .eval-detail-modal only) ── */
.eval-detail-modal .modal-header,
.eval-detail-modal .modal-footer {
  background: var(--bg-light);
  border-color: var(--border-color) !important;
}

.eval-detail-modal .modal-header .btn-outline-secondary {
  color: var(--text-muted);
  border-color: var(--border-color);
  background: transparent;
  width: 32px;
  height: 32px;
  min-width: 32px;
  min-height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.eval-detail-modal .modal-header .btn-outline-secondary i {
  line-height: 1;
  font-size: 0.875rem;
}

.eval-detail-modal .modal-header .btn-close {
  box-sizing: border-box;
  width: 32px;
  height: 32px;
  padding: 0;
  background-position: center;
  background-size: 1em auto;
}

.eval-detail-modal .modal-header .btn-outline-secondary:hover,
.eval-detail-modal .modal-header .btn-outline-secondary:focus {
  background: var(--bg-card);
  color: var(--text-main);
  border-color: var(--text-muted);
}

[data-theme="dark"] .eval-detail-modal .modal-header .btn-outline-secondary {
  color: #e6edf3;
  border-color: #6e7681;
  background: rgba(255, 255, 255, 0.02);
}

[data-theme="dark"] .eval-detail-modal .modal-header .btn-outline-secondary:hover,
[data-theme="dark"] .eval-detail-modal .modal-header .btn-outline-secondary:focus {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border-color: #8b949e;
}

[data-theme="dark"] .eval-detail-modal .modal-header .btn-close {
  filter: invert(1) grayscale(1);
}

.eval-detail-modal .modal-footer .btn-secondary {
  background: var(--bg-card);
  border-color: var(--border-color);
  color: var(--text-main);
}

.eval-detail-modal .modal-footer .btn-secondary:hover,
.eval-detail-modal .modal-footer .btn-secondary:focus {
  background: var(--bg-body);
  border-color: var(--text-muted);
  color: var(--text-main);
}

.eval-detail-modal fieldset.legend-border {
  border: 1px solid var(--border-color) !important;
  background: var(--bg-body);
  padding: 1rem 1.1rem !important;
  border-radius: 8px;
  margin-bottom: 0;
}

.eval-detail-modal fieldset.legend-border legend.legend-title {
  float: none;
  width: auto;
  padding: 0;
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 0.85rem;
}

.eval-detail-modal fieldset.legend-border legend.legend-title::before {
  content: "";
  display: inline-block;
  width: 3px;
  height: 11px;
  border-radius: 2px;
  background: var(--primary);
  margin-right: 0.5rem;
  vertical-align: -1px;
}

.eval-detail-modal .eval-field-label {
  font-size: 0.66rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 0.2rem;
}

.eval-detail-modal .eval-field-value {
  font-size: 0.85rem;
  line-height: 1.45;
  color: var(--text-main);
  word-break: break-word;
}

.eval-detail-modal .table thead th {
  background: var(--bg-light) !important;
  background-color: var(--bg-light) !important;
  color: var(--text-muted) !important;
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--border-color) !important;
  border-right: none !important;
  white-space: nowrap;
}

.eval-detail-modal .badge.rounded-pill {
  font-size: 0.72rem;
  font-weight: 500;
  padding: 0.4em 0.8em;
}

.border-bottom-light {
  border-bottom: 1px solid var(--border-light, #f1f5f9);
}

.eval-detail-modal .feedbacks-table-container {
  scrollbar-width: thin;
  scrollbar-color: var(--border-color) transparent;
}

.eval-detail-modal .feedbacks-table-container::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.eval-detail-modal .feedbacks-table-container::-webkit-scrollbar-track {
  background: transparent;
}

.eval-detail-modal .feedbacks-table-container::-webkit-scrollbar-thumb {
  background: var(--border-color);
  border-radius: 3px;
}

.feedback-full-text {
  line-height: 1.6;
  color: var(--text-dark);
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Rating column chip + score bar */
.rating-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.28rem 0.7rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
  border: 1px solid transparent;
  white-space: nowrap;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.rating-chip:hover {
  transform: translateY(-1px);
}

.rating-chip i {
  font-size: 0.68rem;
}

.rating-chip .rating-score {
  font-size: 0.85rem;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.rating-chip .rating-label {
  font-weight: 600;
  opacity: 0.85;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rating-chip.is-excellent {
  color: #0f7b3f;
  background: rgba(25, 135, 84, 0.12);
  border-color: rgba(25, 135, 84, 0.3);
}
.rating-chip.is-very-good {
  color: #1f5fd6;
  background: rgba(31, 95, 214, 0.12);
  border-color: rgba(31, 95, 214, 0.3);
}
.rating-chip.is-good {
  color: #9a6b00;
  background: rgba(255, 193, 7, 0.16);
  border-color: rgba(255, 193, 7, 0.4);
}
.rating-chip.is-fair {
  color: #c2410c;
  background: rgba(249, 115, 22, 0.12);
  border-color: rgba(249, 115, 22, 0.32);
}
.rating-chip.is-poor {
  color: #b3261e;
  background: rgba(220, 53, 69, 0.12);
  border-color: rgba(220, 53, 69, 0.32);
}

.rating-bar {
  width: 130px;
  height: 4px;
  margin-top: 0.4rem;
  border-radius: 999px;
  background: var(--border-light, rgba(0, 0, 0, 0.08));
  overflow: hidden;
}

.rating-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s ease;
}

.rating-chip.is-excellent + .rating-bar span { background: #198754; }
.rating-chip.is-very-good + .rating-bar span { background: #1f5fd6; }
.rating-chip.is-good + .rating-bar span { background: #f0ad4e; }
.rating-chip.is-fair + .rating-bar span { background: #f97316; }
.rating-chip.is-poor + .rating-bar span { background: #dc3545; }

[data-theme="dark"] .rating-chip.is-excellent { color: #7ee2a8; }
[data-theme="dark"] .rating-chip.is-very-good { color: #8ab4ff; }
[data-theme="dark"] .rating-chip.is-good { color: #ffd666; }
[data-theme="dark"] .rating-chip.is-fair { color: #ffb38a; }
[data-theme="dark"] .rating-chip.is-poor { color: #ff9b94; }

/* View Feedback button — animated so it reads as clickable */
.btn-view-feedback {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--primary, #191970);
  background: rgba(25, 25, 112, 0.08);
  border: 1px dashed var(--primary, #191970);
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.78rem;
  padding: 0.35rem 0.85rem;
  cursor: pointer;
  transition: all 0.25s ease;
  animation: feedback-btn-pulse 2.4s ease-in-out infinite;
}

.btn-view-feedback i {
  animation: feedback-btn-nudge 2.4s ease-in-out infinite;
}

.btn-view-feedback:hover,
.btn-view-feedback:focus-visible {
  background: var(--primary, #191970);
  color: #fff;
  border-style: solid;
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(25, 25, 112, 0.25);
  animation: none;
}

.btn-view-feedback:hover i,
.btn-view-feedback:focus-visible i {
  animation: none;
  transform: scale(1.15);
}

@keyframes feedback-btn-pulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(25, 25, 112, 0.28);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(25, 25, 112, 0);
  }
}

@keyframes feedback-btn-nudge {
  0%, 70%, 100% {
    transform: translateX(0);
  }
  80% {
    transform: translateX(2px);
  }
  90% {
    transform: translateX(-1px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .btn-view-feedback,
  .btn-view-feedback i {
    animation: none;
  }
}

@media print {
  .no-print, .sidebar, .navbar, .filters-panel, .accounts-head, .pagination, .btn-link, .modal-footer, .modal .btn-close {
    display: none !important;
  }
  .main-wrapper { margin-left: 0 !important; padding: 0 !important; }
  .content-area { padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
  .d-none { display: block !important; }
  .modal { position: static !important; display: block !important; padding: 0 !important; }
  .modal .modal-content { width: 100% !important; border: none !important; box-shadow: none !important; }
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
