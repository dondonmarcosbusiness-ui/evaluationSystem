<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Office Reports</template></Navbar>

      <div class="content-area">
        <!-- Loading State -->
        <div v-if="loadingDashboard" class="py-4">
          <SkeletonLoader variant="table" :rows="6" :cols="5" />
        </div>

        <!-- Summary View -->
        <template v-else>
          <!-- Office Summary Table -->
          <div v-if="!selectedOffice">
            <!-- Dashboard Stats Row -->
            <div class="row g-3 mb-4 mx-3 mx-md-0">
              <div class="col-6 col-md-3">
                <div class="stat-card shadow-none p-3 h-100">
                  <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                      <i class="fas fa-building"></i>
                    </div>
                    <div>
                      <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em">Total Offices</div>
                      <div class="h4 mb-0 fw-800">{{ dashboardStats.total_offices || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stat-card shadow-none p-3 h-100">
                  <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                      <i class="fas fa-comments"></i>
                    </div>
                    <div>
                      <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em">Total Feedback</div>
                      <div class="h4 mb-0 fw-800">{{ dashboardStats.total_feedback || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stat-card shadow-none p-3 h-100">
                  <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                      <i class="fas fa-thumbs-up"></i>
                    </div>
                    <div>
                      <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em">Satisfaction Rate</div>
                      <div class="d-flex align-items-center gap-2">
                        <span class="h4 mb-0 fw-800">{{ Number(dashboardStats.satisfaction_rate || 0).toFixed(1) }}%</span>
                        <span
                          class="badge px-2 py-1 rounded-pill"
                          :class="getSatisfactionBadge(dashboardStats.satisfaction_rate)"
                          style="font-size: 0.65rem"
                        >
                          {{ getSatisfactionLabel(dashboardStats.satisfaction_rate) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stat-card shadow-none p-3 h-100">
                  <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                      <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                      <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em">Today's Feedback</div>
                      <div class="h4 mb-0 fw-800">{{ dashboardStats.today_feedback || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card shadow-none mx-3 mx-md-0">
            <div class="card-header border-0 py-3 px-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center py-1 gap-3">
                  <h5 class="mb-0 fw-800 text-main d-flex align-items-center">
                    <i class="fas fa-chart-bar me-2 text-primary opacity-75"></i>
                    Office Summary
                  </h5>
                  <span
                    class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-700"
                    style="font-size: 0.75rem"
                  >
                    {{ summaryList.length }} Offices
                  </span>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <button
                    class="btn btn-primary btn-sm d-flex align-items-center gap-2"
                    @click="exportCsv"
                    :disabled="exporting"
                  >
                    <i class="fas fa-download"></i>
                    <span class="d-none d-xl-inline">{{ exporting ? 'Exporting...' : 'Export CSV' }}</span>
                  </button>
                </div>
              </div>
            </div>
            <div class="card-body p-0">
              <Transition name="fade" mode="out-in">
                <div v-if="loadingSummary" key="loading">
                  <SkeletonLoader variant="table" :rows="6" :cols="8" />
                </div>
                <div v-else-if="summaryList.length" key="table" class="table-scroll" @scroll="onTableScroll">
                  <table class="table table-hover mb-0">
                    <thead :class="{ 'glass-header': tableScrolled }">
                    <tr>
                      <th>#</th>
                      <th>Office Name</th>
                      <th class="text-center">Total Feedback</th>
                      <th class="text-center">Satisfaction Rate</th>
                      <th class="text-center d-none d-md-table-cell">Yes / No</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(office, i) in summaryList" :key="office.id">
                      <td class="text-muted small">{{ i + 1 }}</td>
                      <td class="fw-semibold">{{ office.name }}</td>
                      <td class="text-center">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">
                          {{ office.feedbacks_count || 0 }}
                        </span>
                      </td>
                      <td class="text-center">
                        <span
                          class="badge px-3 py-2 rounded-pill"
                          :class="getSatisfactionBadge(office.satisfaction_rate)"
                        >
                          {{ Number(office.satisfaction_rate || 0).toFixed(1) }}%
                        </span>
                      </td>
                      <td class="text-center d-none d-md-table-cell">
                        <span class="fw-semibold text-success">{{ office.yes_count || 0 }}</span>
                        <span class="text-muted"> / </span>
                        <span class="fw-semibold text-danger">{{ office.no_count || 0 }}</span>
                      </td>
                      <td class="text-center">
                        <button
                          class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2"
                          @click="loadDetailedReport(office)"
                        >
                          <i class="fas fa-eye"></i>
                          <span class="d-none d-sm-inline">View Details</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                </div>
                <div v-else key="empty" class="text-center py-5 text-muted">
                  <div class="mb-3">
                    <i class="fas fa-building fa-4x opacity-25"></i>
                  </div>
                  <h5 class="fw-bold mb-1">No Office Data</h5>
                  <p class="mb-0">No office feedback reports are available yet.</p>
                </div>
              </Transition>
            </div>
          </div>
          </div>

          <!-- Detailed Report Section -->
          <template v-else>
            <!-- Back Button & Export -->
            <div class="d-flex justify-content-between align-items-center mb-4 mx-3 mx-md-0">
              <button
                class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2 rounded-pill"
                @click="selectedOffice = null"
              >
                <i class="fas fa-arrow-left"></i>
                Back to Summary
              </button>
              <button
                class="btn btn-primary btn-sm d-flex align-items-center gap-2"
                @click="exportCsv"
                :disabled="exporting"
              >
                <i class="fas fa-download"></i>
                <span>{{ exporting ? 'Exporting...' : 'Export CSV' }}</span>
              </button>
            </div>

            <!-- Office Header -->
            <div class="card shadow-none mb-4 mx-3 mx-md-0">
              <div class="card-body py-4">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-building"></i>
                  </div>
                  <div>
                    <h4 class="mb-0 fw-800">{{ selectedOffice.name }}</h4>
                    <p class="text-muted small mb-0">Detailed Feedback Report</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Filter Bar -->
            <div class="card filter-bar-card border-0 mb-4 mx-3 mx-md-0">
              <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">
                  <div class="col-12 col-sm-6 col-md-3">
                    <label class="filter-label">Date From</label>
                    <input
                      v-model="detailFilters.date_from"
                      type="date"
                      class="filter-input"
                    />
                  </div>
                  <div class="col-12 col-sm-6 col-md-3">
                    <label class="filter-label">Date To</label>
                    <input
                      v-model="detailFilters.date_to"
                      type="date"
                      class="filter-input"
                    />
                  </div>
                  <div class="col-12 col-sm-6 col-md-3">
                    <label class="filter-label">Visitor Type</label>
                    <CustomSelect
                      v-model="detailFilters.visitor_type"
                      :options="visitorTypeOptions"
                      placeholder="All Types"
                      class="filter-select"
                    />
                  </div>
                  <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                    <button
                      class="btn filter-btn filter-btn-apply flex-grow-1"
                      @click="loadDetailedReport(selectedOffice)"
                    >
                      <i class="fas fa-filter me-1"></i> Apply
                    </button>
                    <button
                      class="btn filter-btn filter-btn-reset"
                      @click="resetDetailFilters"
                    >
                      <i class="fas fa-undo me-1"></i> Reset
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Loading Detail -->
            <div v-if="loadingDetail" class="py-4">
            <SkeletonLoader variant="table" :rows="6" :cols="4" />
          </div>

            <!-- Detailed Content -->
            <template v-else-if="detailReport">
              <!-- Category Satisfaction -->
              <div class="card shadow-none mb-4 mx-3 mx-md-0">
                <div class="card-header border-0 py-3 px-4">
                  <h6 class="mb-0 fw-800 text-uppercase small ls-1">Category Satisfaction</h6>
                </div>
                <div class="card-body py-3">
                  <div v-if="categorySatisfaction.length" class="row g-4">
                    <div v-for="cat in categorySatisfaction" :key="cat.id" class="col-6 col-md-4 col-lg">
                      <div class="text-center p-3 rounded-4" style="background: var(--bg-light)">
                        <div class="small text-muted fw-bold mb-2">{{ cat.category_name }}</div>
                        <div class="h5 mb-0 fw-800" :class="getSatisfactionColorClass(cat.satisfaction_rate)">
                          {{ Number(cat.satisfaction_rate).toFixed(1) }}%
                        </div>
                        <div class="progress mt-2" style="height: 4px">
                          <div
                            class="progress-bar"
                            :class="getSatisfactionBarClass(cat.satisfaction_rate)"
                            :style="{ width: (cat.satisfaction_rate || 0) + '%' }"
                          ></div>
                        </div>
                        <div class="x-small text-muted mt-1">
                          <span class="text-success">{{ cat.yes }} Yes</span> /
                          <span class="text-danger">{{ cat.no }} No</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="text-center text-muted py-4">No category data yet</div>
                </div>
              </div>

              <!-- Yes/No Distribution & Monthly Trend Row -->
              <div class="row g-4 mx-3 mx-md-0 mb-4">
                <!-- Yes/No Distribution -->
                <div class="col-md-6">
                  <div class="card shadow-none h-100">
                    <div class="card-header border-0 py-3 px-4">
                      <h6 class="mb-0 fw-800 text-uppercase small ls-1">Yes / No Distribution</h6>
                    </div>
                    <div class="card-body py-3">
                      <div v-if="detailReport.satisfaction_distribution && (detailReport.satisfaction_distribution.yes || detailReport.satisfaction_distribution.no)">
                        <div v-for="opt in satisfactionOptions" :key="opt.key" class="d-flex align-items-center gap-3 mb-3">
                          <span class="fw-bold text-muted" style="width: 40px; text-align: center">{{ opt.label }}</span>
                          <div class="progress flex-grow-1" style="height: 24px; border-radius: 6px">
                            <div
                              class="progress-bar rounded-start"
                              :class="opt.color"
                              :style="{ width: getDistributionWidth(detailReport.satisfaction_distribution[opt.key]) + '%' }"
                            ></div>
                          </div>
                          <span class="fw-bold small text-muted" style="min-width: 40px; text-align: right">
                            {{ detailReport.satisfaction_distribution[opt.key] || 0 }}
                          </span>
                        </div>
                      </div>
                      <div v-else class="text-center text-muted py-4">No distribution data</div>
                    </div>
                  </div>
                </div>

                <!-- Monthly Trend -->
                <div class="col-md-6">
                  <div class="card shadow-none h-100">
                    <div class="card-header border-0 py-3 px-4">
                      <h6 class="mb-0 fw-800 text-uppercase small ls-1">Monthly Trend</h6>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                          <thead class="bg-light">
                            <tr class="x-small text-uppercase text-muted">
                              <th class="ps-4">Month</th>
                              <th class="text-center">Count</th>
                              <th class="pe-4 text-center">Satisfaction</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="m in detailReport.monthly_trend" :key="m.month">
                              <td class="ps-4 fw-semibold small">{{ formatMonth(m.month) }}</td>
                              <td class="text-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ m.count }}</span>
                              </td>
                              <td class="pe-4 text-center">
                                <span class="fw-bold" :class="getSatisfactionColorClass(m.satisfaction_rate)">
                                  {{ Number(m.satisfaction_rate || 0).toFixed(1) }}%
                                </span>
                              </td>
                            </tr>
                            <tr v-if="!detailReport.monthly_trend?.length">
                              <td colspan="3" class="text-center text-muted py-4">No trend data</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Suggestions / Comments -->
              <div class="card shadow-none mb-4 mx-3 mx-md-0">
                <div class="card-header border-0 py-3 px-4">
                  <h6 class="mb-0 fw-800 text-uppercase small ls-1">Suggestions &amp; Comments</h6>
                </div>
                <div class="card-body p-0">
                  <div v-if="detailReport.suggestions && detailReport.suggestions.length" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="bg-light">
                        <tr class="x-small text-uppercase text-muted">
                          <th class="ps-4">Comment</th>
                          <th>Purpose of Visit</th>
                          <th>Gender</th>
                          <th>Visitor Type</th>
                          <th class="pe-4 text-end">Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="s in detailReport.suggestions" :key="s.id || s.comment">
                          <td class="ps-4 small" style="max-width: 320px">
                            <div class="text-truncate">{{ s.comment || s.text || s }}</div>
                          </td>
                          <td class="small">{{ s.purpose_of_visit || '—' }}</td>
                          <td class="small">{{ formatGender(s.gender) }}</td>
                          <td class="small">{{ s.visitor_type || '—' }}</td>
                          <td class="pe-4 text-end small text-muted">{{ formatDate(s.date) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else class="text-center text-muted py-4">
                    <i class="fas fa-comments fa-2x opacity-25 mb-2 d-block"></i>
                    No suggestions or comments yet.
                  </div>
                </div>
              </div>

              <!-- Feedbacks Table -->
              <div class="card shadow-none mx-3 mx-md-0">
                <div class="card-header border-0 py-3 px-4">
                  <h6 class="mb-0 fw-800 text-uppercase small ls-1">Feedback Entries</h6>
                </div>
                <div class="card-body p-0">
                  <Transition name="fade" mode="out-in">
                    <div v-if="loadingFeedbacks" key="loading">
                      <SkeletonLoader variant="table" :rows="6" :cols="8" />
                    </div>
                    <div v-else-if="feedbackList.length" key="table">
                      <div class="table-responsive">
                        <div class="table-scroll" @scroll="onTableScroll">
                        <table class="table table-hover align-middle mb-0">
                          <thead :class="{ 'glass-header': tableScrolled }" class="bg-light">
                            <tr class="x-small text-uppercase text-muted">
                              <th class="ps-4">Visitor Type</th>
                              <th>IP Address</th>
                              <th class="text-center">Yes / No</th>
                              <th>Comments</th>
                              <th class="pe-4 text-end">Date</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="fb in feedbackList" :key="fb.id">
                              <td class="ps-4">
                                <span class="badge bg-light text-dark rounded-pill small">{{ fb.visitor_type || 'N/A' }}</span>
                              </td>
                              <td class="small">{{ fb.ip_address || '—' }}</td>
                              <td class="text-center small">
                                <span class="fw-bold text-success">{{ feedbackYesCount(fb) }}</span>
                                <span class="text-muted"> / </span>
                                <span class="fw-bold text-danger">{{ feedbackNoCount(fb) }}</span>
                              </td>
                              <td class="small text-muted" style="max-width: 200px">
                                <div class="text-truncate">{{ fb.comments || '—' }}</div>
                              </td>
                              <td class="pe-4 text-end small text-muted">{{ formatDate(fb.created_at) }}</td>
                            </tr>
                          </tbody>
                        </table>
                        </div>
                      </div>
                      <Pagination :pagination="feedbackPagination" @change-page="fetchFeedbacks" />
                    </div>
                    <div v-else key="empty" class="text-center py-5 text-muted">
                      <i class="fas fa-inbox fa-2x opacity-25 mb-2"></i>
                      <p class="mb-0">No feedback entries found.</p>
                    </div>
                  </Transition>
                </div>
              </div>
            </template>

            <!-- Empty Detail State -->
            <div v-else class="text-center py-5 text-muted">
              <i class="fas fa-chart-pie fa-4x opacity-25 mb-3"></i>
              <h5 class="fw-bold">No Report Data</h5>
              <p class="mb-0">Unable to load detailed report for this office.</p>
            </div>
          </template>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import Pagination from "../components/Pagination.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";

const visitorTypeOptions = [
  { label: "All Types", value: "" },
  { label: "Student", value: "student" },
  { label: "Parent", value: "parent" },
  { label: "Faculty", value: "faculty" },
  { label: "Alumni", value: "alumni" },
  { label: "Visitor", value: "visitor" },
  { label: "Others", value: "others" },
];

const loadingDashboard = ref(true);
const loadingSummary = ref(true);
const loadingDetail = ref(false);
const loadingFeedbacks = ref(false);
const exporting = ref(false);
const tableScrolled = ref(false);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

const dashboardStats = ref({});
const summaryList = ref([]);
const selectedOffice = ref(null);
const detailReport = ref(null);

const detailFilters = ref({ date_from: "", date_to: "", visitor_type: "" });

const feedbackList = ref([]);
const feedbackPagination = ref({});

const categorySatisfaction = computed(() => detailReport.value?.category_satisfaction || []);

const satisfactionOptions = [
  { key: "yes", label: "Yes", color: "bg-success" },
  { key: "no", label: "No", color: "bg-danger" },
];

function getSatisfactionBadge(rate) {
  const r = Number(rate) || 0;
  if (r >= 80) return "bg-success text-white shadow-sm";
  if (r >= 60) return "bg-info text-white shadow-sm";
  if (r >= 40) return "bg-warning text-dark shadow-sm";
  return "bg-danger text-white shadow-sm";
}

function getSatisfactionLabel(rate) {
  const r = Number(rate) || 0;
  if (r >= 80) return "Excellent";
  if (r >= 60) return "Good";
  if (r >= 40) return "Fair";
  return "Poor";
}

function getSatisfactionColorClass(rate) {
  const r = Number(rate) || 0;
  if (r >= 80) return "text-success";
  if (r >= 60) return "text-info";
  if (r >= 40) return "text-warning";
  return "text-danger";
}

function getSatisfactionBarClass(rate) {
  const r = Number(rate) || 0;
  if (r >= 80) return "bg-success";
  if (r >= 60) return "bg-info";
  if (r >= 40) return "bg-warning";
  return "bg-danger";
}

function getDistributionWidth(count) {
  const distribution = detailReport.value?.satisfaction_distribution || {};
  const maxCount = Math.max(...Object.values(distribution).map((c) => Number(c) || 0), 1);
  return (Number(count) / maxCount) * 100;
}

function feedbackYesCount(fb) {
  return (fb.answers || []).filter((a) => a.answer).length;
}

function feedbackNoCount(fb) {
  return (fb.answers || []).filter((a) => !a.answer).length;
}

function formatMonth(monthStr) {
  if (!monthStr) return "—";
  const [year, month] = monthStr.split("-");
  const date = new Date(Number(year), Number(month) - 1);
  return date.toLocaleString("default", { month: "short", year: "numeric" });
}

function formatDate(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
}

function formatGender(gender) {
  if (!gender) return "—";
  const map = { male: "Male", female: "Female", others: "Others" };
  return map[gender] || gender;
}

async function fetchDashboard() {
  try {
    const res = await api.get("/office-reports/dashboard");
    dashboardStats.value = res.data;
  } catch (e) {
    console.error("Failed to load dashboard stats:", e);
  }
}

async function fetchSummary() {
  loadingSummary.value = true;
  try {
    const res = await api.get("/office-reports/summary");
    summaryList.value = res.data.data || res.data;
  } catch (e) {
    console.error("Failed to load summary:", e);
    summaryList.value = [];
  } finally {
    loadingSummary.value = false;
  }
}

async function loadDetailedReport(office) {
  selectedOffice.value = office;
  loadingDetail.value = true;
  detailReport.value = null;
  feedbackList.value = [];
  feedbackPagination.value = {};

  const params = {};
  if (detailFilters.value.date_from) params.from = detailFilters.value.date_from;
  if (detailFilters.value.date_to) params.to = detailFilters.value.date_to;
  if (detailFilters.value.visitor_type) params.visitor_type = detailFilters.value.visitor_type;

  try {
    const res = await api.get(`/office-reports/${office.id}`, { params });
    detailReport.value = res.data;
    await fetchFeedbacks(1);
  } catch (e) {
    console.error("Failed to load detailed report:", e);
    detailReport.value = null;
  } finally {
    loadingDetail.value = false;
  }
}

async function fetchFeedbacks(page = 1) {
  if (!selectedOffice.value) return;
  loadingFeedbacks.value = true;
  feedbackList.value = [];

  const params = { page };
  if (detailFilters.value.date_from) params.date_from = detailFilters.value.date_from;
  if (detailFilters.value.date_to) params.date_to = detailFilters.value.date_to;
  if (detailFilters.value.visitor_type) params.visitor_type = detailFilters.value.visitor_type;

  try {
    const res = await api.get(`/office-reports/${selectedOffice.value.id}/feedbacks`, { params });
    feedbackList.value = res.data.data || res.data;
    feedbackPagination.value = res.data;
  } catch (e) {
    console.error("Failed to load feedbacks:", e);
    feedbackList.value = [];
  } finally {
    loadingFeedbacks.value = false;
  }
}

function resetDetailFilters() {
  detailFilters.value = { date_from: "", date_to: "", visitor_type: "" };
  if (selectedOffice.value) {
    loadDetailedReport(selectedOffice.value);
  }
}

async function exportCsv() {
  exporting.value = true;
  const params = {};
  if (selectedOffice.value) params.office_id = selectedOffice.value.id;
  if (detailFilters.value.date_from) params.date_from = detailFilters.value.date_from;
  if (detailFilters.value.date_to) params.date_to = detailFilters.value.date_to;
  if (detailFilters.value.visitor_type) params.visitor_type = detailFilters.value.visitor_type;

  try {
    const res = await api.get("/office-reports/export/csv", {
      params,
      responseType: "blob",
    });
    const blob = new Blob([res.data], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    const filename = selectedOffice.value
      ? `office-report-${selectedOffice.value.name.toLowerCase().replace(/\s+/g, "-")}.csv`
      : "office-reports.csv";
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error("Export failed:", e);
  } finally {
    exporting.value = false;
  }
}

onMounted(async () => {
  loadingDashboard.value = true;
  await Promise.all([fetchDashboard(), fetchSummary()]);
  loadingDashboard.value = false;
});
</script>

<style scoped>
.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}

/* ── Modern Clean Filter Bar ── */
.filter-bar-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color) !important;
  border-radius: 16px !important;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03) !important;
  transition: all 0.25s ease;
}

[data-theme="dark"] .filter-bar-card {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25) !important;
}

.filter-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  margin-bottom: 0.4rem;
  margin-left: 0.25rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.filter-input {
  width: 100%;
  height: 42px;
  padding: 0.5rem 1rem;
  border-radius: 50px;
  border: 1.5px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-main);
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: none;
}

.filter-input:hover {
  border-color: var(--primary);
}

.filter-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3.5px rgba(0, 82, 255, 0.12);
  background: var(--bg-card);
  color: var(--text-main);
}

.filter-input::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.6;
  transition: opacity 0.2s ease, transform 0.2s ease;
  filter: invert(0.4);
  padding: 2px;
}

.filter-input::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
  transform: scale(1.1);
}

[data-theme="dark"] .filter-input::-webkit-calendar-picker-indicator {
  filter: invert(0.9);
}

.filter-select :deep(.custom-select-trigger) {
  height: 42px;
  min-height: 42px;
  border-radius: 50px;
  border: 1.5px solid var(--border-color);
  background: var(--bg-card);
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.5rem 1.15rem;
  color: var(--text-main);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.filter-select :deep(.custom-select-trigger:hover) {
  border-color: var(--primary);
  box-shadow: 0 2px 8px rgba(0, 82, 255, 0.08);
}

.filter-select :deep(.custom-select-trigger.active) {
  border-color: var(--primary);
  box-shadow: 0 0 0 3.5px rgba(0, 82, 255, 0.12);
}

.filter-btn {
  height: 42px;
  padding: 0.5rem 1.25rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
}

.filter-btn-apply {
  background: var(--primary);
  border: 1.5px solid var(--primary);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 82, 255, 0.25);
}

.filter-btn-apply:hover {
  background: #0045d8;
  border-color: #0045d8;
  box-shadow: 0 6px 16px rgba(0, 82, 255, 0.35);
  transform: translateY(-1px);
  color: #ffffff;
}

.filter-btn-apply:active {
  transform: translateY(0);
}

.filter-btn-reset {
  background: var(--bg-card);
  border: 1.5px solid var(--border-color);
  color: var(--text-muted);
}

.filter-btn-reset:hover {
  background: rgba(0, 0, 0, 0.04);
  border-color: var(--border-color);
  color: var(--text-main);
  transform: translateY(-1px);
}

[data-theme="dark"] .filter-btn-reset:hover {
  background: rgba(255, 255, 255, 0.08);
  color: var(--text-main);
}

.label-custom {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  margin-bottom: 0.35rem;
  margin-left: 0.15rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.input-custom {
  width: 100%;
  padding: 0.55rem 0.85rem;
  border-radius: 10px;
  border: 1.5px solid var(--border-light);
  background: var(--bg-card);
  color: var(--text-main);
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.input-custom:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(10, 39, 138, 0.08);
}

.x-small {
  font-size: 0.65rem;
}

.fw-500 {
  font-weight: 500;
}

.fw-600 {
  font-weight: 600;
}

.fw-700 {
  font-weight: 700;
}

.fw-800 {
  font-weight: 800;
}

.ls-1 {
  letter-spacing: 0.08em;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 767.98px) {
  .stat-card {
    min-height: 80px;
  }
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
