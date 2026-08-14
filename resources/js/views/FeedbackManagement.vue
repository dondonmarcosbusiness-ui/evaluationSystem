<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Feedback Management</template></Navbar>

      <div class="content-area">
        <div class="analytics-card analytics-card-filters mb-4">
          <!-- Filter Bar -->
          <div class="filter-bar filter-bar-overflow">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
              <div class="d-flex align-items-center gap-3">
                <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                  <i class="fas fa-comments me-2 text-primary opacity-75"></i>
                  Feedback Analytics
                </h5>
                <div class="stat-pill">
                  {{ pagination?.total || 0 }} Total Feedbacks
                </div>
              </div>
            </div>

            <div class="row g-3 mt-3">
              <!-- Search -->
              <div class="col-md-3">
                <div class="input-group-custom">
                  <span class="input-icon"><i class="fas fa-search"></i></span>
                  <input
                    v-model="filters.search"
                    type="text"
                    class="input-custom with-icon"
                    placeholder="Search faculty, subject, feedback..."
                    @input="handleSearch"
                  />
                </div>
              </div>

              <!-- Faculty Filter -->
              <div class="col-md-3">
                <CustomSelect
                  v-model="filters.faculty_id"
                  :options="facultyOptions"
                  placeholder="All Faculty"
                  @change="fetchFeedbacks(1)"
                />
              </div>

              <!-- Department Filter (faculty only) -->
              <div v-if="evaluateeType === 'faculty'" class="col-md-2">
                <CustomSelect
                  v-model="filters.department"
                  :options="departmentOptions"
                  placeholder="All Departments"
                  @change="fetchFeedbacks(1)"
                />
              </div>

              <!-- Rating Filter -->
              <div class="col-md-2">
                <CustomSelect
                  v-model="filters.rating"
                  :options="ratingOptions"
                  placeholder="All Ratings"
                  @change="fetchFeedbacks(1)"
                />
              </div>

              <!-- Reset -->
              <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-light-custom flex-grow-1" @click="resetFilters">
                  <i class="fas fa-redo-alt me-1"></i> Reset
                </button>
              </div>
            </div>

            <!-- Advanced Filters Toggle -->
            <div class="mt-3">
              <button class="btn btn-link btn-sm p-0 text-decoration-none text-muted" @click="showAdvanced = !showAdvanced">
                <i class="fas" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                {{ showAdvanced ? 'Hide' : 'Show' }} Advanced Filters
              </button>
              
              <div v-if="showAdvanced" class="row g-3 mt-1 animate__animated animate__fadeIn">
                <div class="col-md-3">
                  <CustomSelect
                    v-model="filters.semester"
                    :options="semesterOptions"
                    placeholder="All Semesters"
                    @change="fetchFeedbacks(1)"
                  />
                </div>
                <div class="col-md-3">
                  <CustomSelect
                    v-model="filters.academic_year"
                    :options="yearOptions"
                    placeholder="All Academic Years"
                    @change="fetchFeedbacks(1)"
                  />
                </div>
                <div class="col-md-3" v-if="evaluateeType === 'faculty'">
                  <div class="input-group-custom">
                    <span class="input-icon"><i class="fas fa-book"></i></span>
                    <input
                      v-model="filters.subject_code"
                      type="text"
                      class="input-custom with-icon"
                      placeholder="Subject Code..."
                      @input="handleSearch"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Table Content -->
          <div class="card-body p-0">
            <div class="table-responsive">
              <div class="table-scroll" @scroll="onTableScroll">
              <table class="table table-hover mb-0">
                <thead :class="{ 'glass-header': tableScrolled }" class="bg-light">
                  <tr>
                    <th class="ps-4">Faculty Member</th>
                    <th v-if="evaluateeType === 'faculty'">Subject</th>
                    <th v-if="evaluateeType === 'faculty'">Department</th>
                    <th>Rating</th>
                    <th>Feedback Snippet</th>
                    <th>Date Submitted</th>
                    <th class="text-end pe-4">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="loading">
                    <tr v-for="r in 6" :key="r">
                      <td colspan="7" class="ps-4 py-3">
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
                        <div class="text-muted smallest">{{ feedback.department }}</div>
                      </td>
                      <td v-if="evaluateeType === 'faculty'">
                        <span class="badge bg-light text-dark border">{{ feedback.subject_code || 'General' }}</span>
                      </td>
                      <td v-if="evaluateeType === 'faculty'" class="small">{{ feedback.department }}</td>
                      <td>
                        <span class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(feedback.rating)">
                          {{ getRatingLabel(feedback.rating) }} ({{ feedback.rating }})
                        </span>
                      </td>
                      <td class="text-secondary small">
                        <div class="line-clamp-2" style="max-width: 300px">
                          "{{ feedback.text }}"
                        </div>
                      </td>
                      <td class="small text-muted">
                        {{ new Date(feedback.created_at).toLocaleDateString() }}
                      </td>
                      <td class="text-end pe-4">
                        <button class="btn btn-light-custom btn-sm rounded-circle" @click.stop="viewDetails(feedback)">
                          <i class="fas fa-eye"></i>
                        </button>
                      </td>
                    </tr>
                  </template>
                  <tr v-else>
                    <td colspan="7" class="text-center py-5">
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
            <Pagination :pagination="pagination" @change-page="fetchFeedbacks" />
          </div>
        </div>
      </div>

      <!-- Feedback Details Modal -->
      <Transition name="slide-up">
        <div v-if="showDetailModal" class="custom-modal feedback-detail" :class="{ 'is-fullscreen': isFullscreen }">
          <div class="modal-card">
            <div class="modal-header-custom primary text-center d-flex flex-column align-items-center justify-content-center position-relative w-100">
              <h4 class="fw-800 text-white mb-1">Evaluation Detail</h4>
              <p class="text-white opacity-75 small mb-0">Full feedback and quantitative breakdown</p>
              <button class="btn-close-custom position-absolute" style="right: 4rem; top: 1.5rem;" @click="isFullscreen = !isFullscreen" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
                <i :class="isFullscreen ? 'fas fa-compress' : 'fas fa-expand'"></i>
              </button>
              <button class="btn-close-custom position-absolute" style="right: 1.5rem; top: 1.5rem;" @click="showDetailModal = false; isFullscreen = false">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="modal-body-custom p-4">
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
                      <div class="mb-2">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Name:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ currentDetail.faculty?.user?.name }}</div>
                      </div>
                      <div v-if="evaluateeType === 'faculty'" class="mb-2">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Department:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ currentDetail.faculty?.department }}</div>
                      </div>
                      <div class="mb-0">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Position:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ currentDetail.faculty?.position }}</div>
                      </div>
                    </fieldset>

                    <!-- Evaluation Information Fieldset -->
                    <fieldset class="legend-border">
                      <legend class="legend-title">Evaluation Information</legend>
                      <div v-if="evaluateeType === 'faculty'" class="mb-2">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Subjects Included:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ uniqueSubjects }}</div>
                      </div>
                      <div class="mb-2">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Period Filters:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                          {{ filters.semester !== 'all' ? filters.semester : 'All Semesters' }}, {{ filters.academic_year !== 'all' ? filters.academic_year : 'All Years' }}
                        </div>
                      </div>
                      <div class="mb-0">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.72rem;">Total Feedbacks:</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ currentDetail.evaluations?.length || 0 }} comments</div>
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
                              <td class="ps-0 py-2 text-secondary small text-wrap text-break" style="max-width: 800px;">
                                {{ evalItem.comments }}
                              </td>
                              <td class="text-end pe-0 py-2 text-muted small whitespace-nowrap">
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

            <div class="modal-footer-custom bg-light bg-opacity-25 border-top p-3 d-flex justify-content-end">
              <button class="btn btn-primary px-5 rounded-pill shadow-sm" @click="showDetailModal = false">Close</button>
            </div>
          </div>
        </div>
      </Transition>
      <Transition name="fade">
        <div v-if="showDetailModal" class="modal-backdrop-custom" @click="showDetailModal = false"></div>
      </Transition>

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
import CustomSelect from "../components/CustomSelect.vue";
import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";

const can = inject("can");
const route = useRoute();

// State
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const evaluateeType = ref('faculty');
const feedbacks = ref([]);
const pagination = ref({});
const loading = ref(false);
const detailLoading = ref(false);
const showAdvanced = ref(false);
const showDetailModal = ref(false);
const isFullscreen = ref(false);
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

const semesterOptions = [
  { label: "All Semesters", value: "all" },
  { label: "1st Semester", value: "1st Semester" },
  { label: "2nd Semester", value: "2nd Semester" },
  { label: "Summer", value: "Summer" }
];

const yearOptions = computed(() => [
  { label: "All Years", value: "all" },
  ...years.value.map(y => ({ label: y, value: y }))
]);

function getTypeFromRoute() {
  return "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("view_reports") && user.value.role !== "faculty") {
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
  if (can("view_reports") && user.value.role !== "faculty") {
    evaluateeType.value = getTypeFromRoute();
  }
  fetchMeta();
});

// Methods
async function fetchFeedbacks(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: 10,
      evaluatee_type: evaluateeType.value,
      ...filters.value
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
    const [listRes, setRes] = await Promise.all([
      api.get(listUrl),
      api.get("/settings")
    ]);
    
    const data = Array.isArray(listRes.data) ? listRes.data : (listRes.data?.data || []);
    facultyList.value = data;
    
    // Extract unique departments (faculty only)
    const depts = data.map(f => f.department).filter(d => d);
    departments.value = [...new Set(depts)].sort();
    
    // Setup years
    const currentYear = new Date().getFullYear();
    const startYear = 2023;
    const yearList = [];
    for(let y = currentYear + 1; y >= startYear; y--) {
      yearList.push(`${y}-${y+1}`);
    }
    years.value = yearList;

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

function resetFilters() {
  filters.value = {
    search: "",
    faculty_id: "all",
    department: "all",
    rating: "all",
    semester: activePeriod.semester,
    academic_year: activePeriod.academic_year,
    subject_code: "",
  };
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
  background-color: rgba(0, 82, 255, 0.03) !important;
  transform: translateY(-1px);
}

.custom-modal {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 10001;
  pointer-events: none;
  overflow-y: auto;
  padding: 3rem 1rem;
}

.custom-modal .modal-card {
  margin: auto;
  pointer-events: all;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--card-radius) !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  width: 1100px;
  max-width: 95vw;
  overflow: hidden;
  transition: width 0.25s ease, max-width 0.25s ease, height 0.25s ease;
}

/* Fullscreen state */
.custom-modal.is-fullscreen {
  padding: 0;
  align-items: stretch;
}

.custom-modal.is-fullscreen .modal-card {
  width: 100%;
  max-width: 100%;
  height: 100vh;
  display: flex;
  flex-direction: column;
  box-shadow: none;
  border: none;
}

.custom-modal.is-fullscreen .modal-body-custom {
  flex: 1;
  overflow-y: auto;
}

.modal-backdrop-custom {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(8px);
  z-index: 10000;
}

.modal-header-custom {
  padding: 1.75rem 2rem;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header-custom.primary {
  background: #0a278a;
  padding: 1.5rem 2rem;
}

.modal-body-custom {
  padding: 2rem;
}

.modal-footer-custom {
  padding: 1.5rem 2rem;
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
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

.btn-close-custom {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: #ffffff;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-close-custom:hover {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
  transform: rotate(90deg);
}

fieldset.legend-border {
  border: 1px solid var(--border-color, #e2e8f0) !important;
  padding: 1.25rem !important;
  border-radius: 6px;
  margin-bottom: 1rem;
}

fieldset.legend-border legend.legend-title {
  float: none;
  width: auto;
  padding: 0 0.5rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-dark, #0a0b0d);
  margin-bottom: 0;
}

.border-bottom-light {
  border-bottom: 1px solid var(--border-light, #f1f5f9);
}

.feedbacks-table-container::-webkit-scrollbar {
  width: 6px;
}

.feedbacks-table-container::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 3px;
}

[data-theme="dark"] legend.legend-title {
  color: #ffffff !important;
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

.analytics-card-filters {
  overflow: visible !important;
}

.filter-bar-overflow {
  position: relative;
  z-index: 50;
  overflow: visible !important;
}

@media print {
  .no-print, .sidebar, .navbar, .filter-bar, .pagination, .btn-link, .modal-footer-custom, .custom-modal .btn-close-custom {
    display: none !important;
  }
  .main-wrapper { margin-left: 0 !important; padding: 0 !important; }
  .content-area { padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
  .d-none { display: block !important; }
  .custom-modal { position: static !important; display: block !important; padding: 0 !important; }
  .custom-modal .modal-card { width: 100% !important; border: none !important; box-shadow: none !important; }
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
