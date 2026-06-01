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
                    :placeholder="evaluateeType === 'faculty' ? 'Search faculty, subject, feedback...' : 'Search staff, feedback...'"
                    @input="handleSearch"
                  />
                </div>
              </div>

              <!-- Faculty Filter -->
              <div class="col-md-3">
                <CustomSelect
                  v-model="filters.faculty_id"
                  :options="facultyOptions"
                  :placeholder="evaluateeType === 'faculty' ? 'All Faculty' : 'All Staff'"
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
              <table class="table table-hover mb-0">
                <thead class="bg-light">
                  <tr>
                    <th class="ps-4">{{ evaluateeType === 'faculty' ? 'Faculty Member' : 'Staff Member' }}</th>
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
                    <tr>
                      <td :colspan="evaluateeType === 'faculty' ? 7 : 5" class="py-5 text-center">
                        <div class="spinner-border text-primary opacity-50" role="status">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted small">Loading feedback analytics...</p>
                      </td>
                    </tr>
                  </template>
                  <template v-else-if="feedbacks.length > 0">
                    <tr v-for="feedback in feedbacks" :key="feedback.id" class="feedback-row" @click="viewDetails(feedback)">
                      <td class="ps-4 py-3">
                        <div class="fw-bold">{{ feedback.faculty_name }}</div>
                        <div class="text-muted smallest" v-if="evaluateeType === 'staff'">{{ feedback.designation }}</div>
                        <div class="text-muted smallest" v-else>{{ feedback.department }}</div>
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
                    <td :colspan="evaluateeType === 'faculty' ? 7 : 5" class="text-center py-5">
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
            
            <!-- Pagination -->
            <Pagination :pagination="pagination" @change-page="fetchFeedbacks" />
          </div>
        </div>
      </div>

      <!-- Feedback Details Modal -->
      <Transition name="slide-up">
        <div v-if="showDetailModal" class="custom-modal feedback-detail">
          <div class="card modal-card">
            <div class="modal-header-custom primary">
              <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-icon bg-primary bg-opacity-10 text-primary">
                  <i class="fas fa-comment-alt"></i>
                </div>
                <div>
                  <h5 class="fw-800 mb-0">Evaluation Detail</h5>
                  <p class="text-muted small mb-0">Full feedback and quantitative breakdown</p>
                </div>
              </div>
              <button class="btn-close-custom" @click="showDetailModal = false">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="modal-body-custom p-4">
              <div v-if="detailLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Fetching details...</p>
              </div>
              <div v-else-if="currentDetail">
                <div class="row g-4">
                  <div class="col-md-6">
                    <div class="form-section-modern p-3 bg-light rounded-4 h-100">
                      <div class="section-label mb-3">
                        <i class="fas fa-user-tie me-2 opacity-50"></i> {{ evaluateeType === 'faculty' ? 'Faculty Information' : 'Staff Information' }}
                      </div>
                      <div class="mb-2">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">Name</label>
                        <div class="fw-bold">{{ currentDetail.faculty?.user?.name }}</div>
                      </div>
                      <div v-if="evaluateeType === 'faculty'" class="mb-2">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">Department</label>
                        <div>{{ currentDetail.faculty?.department }}</div>
                      </div>
                      <div class="mb-2">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">{{ evaluateeType === 'faculty' ? 'Position' : 'Designation' }}</label>
                        <div>{{ evaluateeType === 'faculty' ? currentDetail.faculty?.position : currentDetail.faculty?.designation }}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-section-modern p-3 bg-light rounded-4 h-100">
                      <div class="section-label mb-3">
                        <i class="fas fa-info-circle me-2 opacity-50"></i> Evaluation Info
                      </div>
                      <div class="mb-2" v-if="evaluateeType === 'faculty'">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">Subjects Included</label>
                        <div class="fw-bold">
                          <span v-for="subj in [...new Set(currentDetail.evaluations.map(e => e.subject_code))]" :key="subj" class="badge bg-light text-dark border me-1">{{ subj }}</span>
                        </div>
                      </div>
                      <div class="mb-2">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">Period Filters</label>
                        <div>{{ filters.semester !== 'all' ? filters.semester : 'All Semesters' }}, {{ filters.academic_year !== 'all' ? filters.academic_year : 'All Years' }}</div>
                      </div>
                      <div class="mb-2">
                        <label class="text-muted smallest text-uppercase fw-bold d-block">Total Feedbacks</label>
                        <div>{{ currentDetail.evaluations.length }} comments</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="card shadow-none border rounded-4">
                      <div class="card-header bg-transparent border-bottom py-3">
                        <div class="d-flex justify-content-between align-items-center">
                          <h6 class="mb-0 fw-bold">Quantitative Breakdown</h6>
                          <div class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(currentDetail.overall_rating)">
                            Overall: {{ currentDetail.overall_rating }} / 5.0
                          </div>
                        </div>
                      </div>
                      <div class="card-body p-0">
                        <div class="table-responsive">
                          <table class="table table-sm mb-0">
                            <thead class="bg-light smallest text-uppercase">
                              <tr>
                                <th class="ps-3">Category</th>
                                <th class="text-center">Average Rating</th>
                                <th class="pe-3 text-end">Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="cat in currentDetail.category_scores" :key="cat.category_name">
                                <td class="ps-3 py-2 small">{{ cat.category_name }}</td>
                                <td class="text-center py-2 fw-bold">{{ parseFloat(cat.average_rating).toFixed(2) }}</td>
                                <td class="pe-3 py-2 text-end">
                                  <span class="badge rounded-pill" :class="getRatingBadgeClass(cat.average_rating)">
                                    {{ getRatingLabel(cat.average_rating) }}
                                  </span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="form-section-modern p-4 bg-primary bg-opacity-10 border-primary border-opacity-10 border rounded-4">
                      <div class="section-label mb-3 text-primary d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-quote-left me-2"></i> All Qualitative Feedback</span>
                        <span class="badge bg-primary rounded-pill">{{ currentDetail.evaluations.length }} comments</span>
                      </div>
                      <div class="feedbacks-container pe-2" style="max-height: 400px; overflow-y: auto;">
                        <div v-for="evalItem in currentDetail.evaluations" :key="evalItem.id" class="feedback-item mb-3 pb-3 border-bottom border-primary border-opacity-10">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border" v-if="evaluateeType === 'faculty'">{{ evalItem.subject_code }} - {{ evalItem.year_section }}</span>
                            <small class="text-muted">{{ new Date(evalItem.created_at).toLocaleString() }}</small>
                          </div>
                          <div class="feedback-full-text fst-italic lead" style="font-size: 1.05rem;">
                            "{{ evalItem.comments }}"
                          </div>
                        </div>
                        <div v-if="currentDetail.evaluations.length === 0" class="text-center text-muted py-3">
                            No written feedback available matching current filters.
                        </div>
                      </div>
                    </div>
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
              <th>{{ evaluateeType === 'faculty' ? 'Faculty' : 'Staff' }}</th>
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
import api from "../services/api.js";
import Swal from "sweetalert2";

const can = inject("can");
const route = useRoute();

// State
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const evaluateeType = ref(user.value.role === 'staff' ? 'staff' : 'faculty');
const feedbacks = ref([]);
const pagination = ref({});
const loading = ref(false);
const detailLoading = ref(false);
const showAdvanced = ref(false);
const showDetailModal = ref(false);
const currentDetail = ref(null);
const facultyList = ref([]);
const departments = ref([]);
const years = ref([]);

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
  const prefix = evaluateeType.value === 'faculty' ? 'All Faculty' : 'All Staff';
  return [
    { label: prefix, value: "all" },
    ...facultyList.value.map(f => {
      const designationText = evaluateeType.value === 'staff' && f.designation ? ` - ${f.designation}` : '';
      return { 
        label: `${f.user?.name || ''}${designationText}`, 
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
  return route.query.type === "staff" ? "staff" : "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("view_reports") && user.value.role !== "faculty" && user.value.role !== "staff") {
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
  if (can("view_reports") && user.value.role !== "faculty" && user.value.role !== "staff") {
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
    if (evaluateeType.value === "staff") {
      params.evaluatee_id = filters.value.faculty_id;
      delete params.department;
    }
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
    const listUrl = evaluateeType.value === "faculty" ? "/faculty/all" : "/staff";
    const [listRes, setRes] = await Promise.all([
      api.get(listUrl),
      api.get("/settings")
    ]);
    
    facultyList.value = listRes.data;
    
    // Extract unique departments (faculty only)
    if (evaluateeType.value === "faculty") {
      const depts = listRes.data.map(f => f.department).filter(d => d);
      departments.value = [...new Set(depts)].sort();
    } else {
      departments.value = [];
    }
    
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
    if (evaluateeType.value === 'staff') {
      params.evaluatee_id = filters.value.faculty_id;
    }
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
  
  const isFac = evaluateeType.value === 'faculty';
  const headers = isFac
    ? ["Faculty", "Subject", "Department", "Rating", "Feedback", "Date Submitted"]
    : ["Staff", "Designation", "Rating", "Feedback", "Date Submitted"];

  const rows = feedbacks.value.map(f => {
    const row = [
      f.faculty_name,
      ...(isFac ? [f.subject_code, f.department] : [f.designation || ""]),
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
  z-index: 2001;
  pointer-events: none;
  overflow-y: auto;
  padding: 3rem 1rem;
}

.custom-modal .card.modal-card {
  margin: auto;
  pointer-events: all;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 2rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  width: 800px;
  max-width: 100%;
  overflow: visible;
}

.modal-backdrop-custom {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(8px);
  z-index: 2000;
}

.modal-header-custom {
  padding: 1.75rem 2rem;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
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
  .custom-modal .card.modal-card { width: 100% !important; border: none !important; box-shadow: none !important; }
}
</style>
