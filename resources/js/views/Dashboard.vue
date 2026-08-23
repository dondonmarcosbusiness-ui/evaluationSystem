<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>{{ t.dashboard }}</template></Navbar>

      <div class="content-area">
        <!-- Admin/Stats Dashboard -->
        <template v-if="$can('view_dashboard')">
          <!-- Admin dashboard header -->
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3 fade-in-up">
            <div class="d-flex align-items-center gap-2">
              <h4 class="mb-0 fw-800 text-main">Faculty Evaluation Summary</h4>
            </div>
          </div>

          <!-- Admin statistic cards -->
          <div class="row g-4 fade-in-up">
            <div class="col-12">
              <div class="row g-4">
                <div class="col-6 col-xl-3 d-flex">
                  <div class="dashboard-stat-card flex-grow-1">
                    <div class="stat-icon blue">
                      <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div>
                      <div class="stat-label">Total Faculty</div>
                      <div class="stat-value">{{ stats.total_faculty }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-xl-3 d-flex">
                  <div class="dashboard-stat-card flex-grow-1">
                    <div class="stat-icon green">
                      <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                      <div class="stat-label">Total Students</div>
                      <div class="stat-value">{{ stats.total_students }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-xl-3 d-flex">
                  <div class="dashboard-stat-card flex-grow-1">
                    <div class="stat-icon orange">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                      <div class="stat-label">Evaluations</div>
                      <div class="stat-value">{{ stats.total_evaluations }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-xl-3 d-flex">
                  <div class="dashboard-stat-card flex-grow-1">
                    <div class="stat-icon red">
                      <i class="fas fa-star"></i>
                    </div>
                    <div>
                      <div class="stat-label">Avg Rating</div>
                      <div class="stat-value">{{ Number(stats.average_rating).toFixed(2) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 mb-2 mt-2 fade-in-up">
            <span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold">Faculty Summary</span>
            <span class="text-muted small">Faculty evaluation performance and rating distribution</span>
          </div>

          <div class="row g-4 mt-1 fade-in-up">
            <div class="col-lg-7 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-bar"></i>
                  Performance Overview
                </div>
                <div class="card-body flex-grow-1" style="min-height: 350px">
                  <canvas id="facultyChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-5 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-pie"></i>
                  Rating Distribution
                </div>
                <div class="card-body d-flex align-items-center justify-content-center flex-grow-1" style="min-height: 350px">
                  <canvas id="ratingsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 mb-2 mt-3 fade-in-up">
            <span class="badge rounded-pill bg-info-subtle text-info fw-semibold">Office Summary</span>
            <span class="text-muted small">Office feedback trends and visitor insights</span>
          </div>

          <div class="row g-4 mt-1 fade-in-up">
            <div class="col-lg-5 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-line"></i>
                  Office Feedback Trend
                </div>
                <div class="card-body d-flex align-items-center justify-content-center flex-grow-1" style="min-height: 320px">
                  <canvas id="officeTrendChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-4 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-users"></i>
                  Visitor Type Breakdown
                </div>
                <div class="card-body d-flex align-items-center justify-content-center flex-grow-1" style="min-height: 320px">
                  <canvas id="officeVisitorChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-3 d-flex">
              <div class="card shadow-none h-100 flex-grow-1 d-flex flex-column">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-comment-dots text-primary"></i>
                    <div>
                      <h6 class="mb-0 fw-bold">Recent Feedback Feed</h6>
                      <div class="small text-muted">Faculty feedback</div>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0 flex-grow-1 d-flex flex-column">
                  <div class="feedback-feed flex-grow-1" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;" v-if="stats.comments && stats.comments.length > 0">
                    <div
                      v-for="comment in stats.comments.slice(0, 5)"
                      :key="comment.evaluatee_id || comment.faculty_id || comment.faculty_name"
                      class="feedback-item p-3 border-bottom hover-bg-light transition"
                    >
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="faculty-info">
                          <div class="fw-bold small text-dark">{{ comment.faculty_name }}</div>
                          <div class="text-muted smallest text-uppercase fw-semibold">{{ comment.subject_code || 'General' }}</div>
                        </div>
                        <span class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(comment.rating)">
                          {{ getRatingLabel(comment.rating) }}
                        </span>
                      </div>
                      <div class="feedback-text text-secondary small line-clamp-2 mb-2">
                        "{{ comment.text }}"
                      </div>
                      <div class="feedback-meta d-flex justify-content-between align-items-center">
                        <span class="text-muted smallest">
                          <i class="fas fa-calendar-alt me-1"></i>
                          {{ new Date(comment.created_at).toLocaleDateString() }}
                        </span>
                        <span class="text-muted smallest">
                          <i class="fas fa-clock me-1"></i>
                          {{ new Date(comment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="text-center py-5">
                    <div class="mb-3 opacity-25">
                      <i class="fas fa-comments fa-3x"></i>
                    </div>
                    <p class="text-muted small">No recent feedback available.</p>
                  </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-end py-3" v-if="stats.comments && stats.comments.length > 0">
                  <router-link
                    :to="{ path: '/feedbacks', query: { type: activeTab } }"
                    class="btn btn-link btn-sm p-0 fw-bold text-decoration-none"
                  >
                    View All Feedback <i class="fas fa-arrow-right ms-1"></i>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Student Dashboard -->
        <template v-if="user.role === 'student' && !$can('view_dashboard')">
          <div class="card border-0 shadow-none mb-4">
            <div class="card-body text-center py-5">
              <div class="mb-4">
                <img
                  src="/assets/img/neust_logo.webp"
                  alt="NEUST Logo"
                  style="width: 120px; height: 120px; object-fit: contain"
                />
              </div>
              <h4 class="mt-3 fw-bold">{{ t.welcome_user.replace('{name}', user.name) }}</h4>
              <div v-if="user.student?.course || user.student?.section" class="mb-3">
                <span class="badge bg-light text-dark border px-3 py-2">
                  <i class="fas fa-graduation-cap me-2 text-primary"></i>
                  {{ user.student?.course }}
                  {{ user.student?.section ? "- " + user.student?.section : "" }}
                </span>
              </div>
              <p class="text-muted mb-4">{{ t.eval_desc }}</p>
              <router-link
                to="/evaluate"
                class="btn px-4"
                :class="evaluationStatus === 'open' ? 'btn-primary' : 'btn-secondary disabled'"
                :style="evaluationStatus !== 'open' ? 'opacity: 0.6; pointer-events: none;' : ''"
              >
                <i class="fas fa-star me-2"></i>
                {{ evaluationStatus === "open" ? t.start_eval : t.eval_closed }}
              </router-link>
            </div>
          </div>

          <!-- Office Evaluation Section -->
          <div class="office-feedback-header mb-3 mx-3 mx-md-0">
            <h5 class="fw-800 mb-1"><i class="fas fa-building me-2 text-primary"></i>Office Feedback</h5>
            <p class="text-muted small mb-0">Evaluate campus offices to help improve their services.</p>
          </div>
          <div v-if="officesLoading" class="py-3">
            <div class="row g-3 mx-3 mx-md-0">
              <div v-for="i in 3" :key="i" class="col-12 col-sm-6 col-lg-4">
                <div class="sk-list-item" style="height: 120px"></div>
              </div>
            </div>
          </div>
          <div v-else class="row g-3 mx-3 mx-md-0">
            <div v-for="office in offices" :key="office.id" class="col-12 col-sm-6 col-lg-4 d-flex">
              <div class="office-eval-card flex-grow-1">
                <div class="d-flex align-items-start gap-3">
                  <div class="office-icon-sm flex-shrink-0"><i class="fas fa-building"></i></div>
                  <div class="min-w-0 flex-grow-1">
                    <h6 class="fw-700 mb-1 text-truncate">{{ office.name }}</h6>
                    <p class="text-muted office-desc mb-2">{{ office.description || 'No description' }}</p>
                    <div v-if="office.location" class="d-flex align-items-center gap-1 mb-3">
                      <span class="text-muted smallest"><i class="fas fa-map-marker-alt me-1"></i>{{ office.location }}</span>
                    </div>
                    <router-link :to="`/evaluate-office/${office.id}`" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                      <i class="fas fa-star me-1"></i> Evaluate
                    </router-link>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="!offices.length" class="col-12 text-center py-4">
              <p class="text-muted">No offices available for evaluation.</p>
            </div>
          </div>
        </template>

        <template v-if="user.role === 'faculty' && !$can('view_dashboard')">
          <div class="faculty-dashboard fade-in-up">
            <!-- Welcome hero -->
            <section class="faculty-hero">
              <div class="faculty-hero-main">
                <div class="faculty-hero-avatar" aria-hidden="true">{{ userInitials }}</div>
                <div class="faculty-hero-text">
                  <span class="faculty-hero-badge">Faculty</span>
                  <h2 class="faculty-hero-title">Hello, {{ userFirstName }}</h2>
                  <p class="faculty-hero-desc mb-0">
                    Review your teaching effectiveness scores and student comments in one place.
                  </p>
                </div>
              </div>
              <img
                src="/assets/img/neust_logo.webp"
                alt=""
                class="faculty-hero-logo d-none d-md-block"
                aria-hidden="true"
              />
            </section>

            <!-- Quick actions -->
            <section class="faculty-actions" aria-label="Quick actions">
              <router-link
                to="/reports"
                class="faculty-action-card faculty-action-card--primary"
              >
                <div class="faculty-action-icon">
                  <i class="fas fa-chart-line"></i>
                </div>
                <div class="faculty-action-body">
                  <h3 class="faculty-action-title">Ratings Overview</h3>
                  <p class="faculty-action-desc">Charts and performance breakdown</p>
                </div>
                <i class="fas fa-chevron-right faculty-action-arrow"></i>
              </router-link>
              <router-link
                to="/set-report"
                class="faculty-action-card"
              >
                <div class="faculty-action-icon faculty-action-icon--muted">
                  <i class="fas fa-file-invoice"></i>
                </div>
                <div class="faculty-action-body">
                  <h3 class="faculty-action-title">Detailed SET Report</h3>
                  <p class="faculty-action-desc">Full evaluation report document</p>
                </div>
                <i class="fas fa-chevron-right faculty-action-arrow"></i>
              </router-link>
            </section>

            <!-- Student feedback -->
            <section class="faculty-feedback-section">
              <div class="faculty-feedback-toolbar">
                <div class="faculty-feedback-heading">
                  <i class="fas fa-comment-dots"></i>
                  <div>
                    <h2 class="faculty-feedback-title">Student Feedback</h2>
                    <p class="faculty-feedback-subtitle mb-0">
                      {{ myFeedbacks.length }} {{ myFeedbacks.length === 1 ? 'comment' : 'comments' }}
                      <span v-if="!myFeedbackLoading">for selected period</span>
                    </p>
                  </div>
                </div>
                <div class="faculty-feedback-filters">
                  <CustomSelect
                    v-model="feedbackFilters.semester"
                    :options="semesterOptions"
                    class="faculty-filter-select"
                    @change="fetchMyFeedback"
                  />
                  <CustomSelect
                    v-model="feedbackFilters.academic_year"
                    :options="yearOptions"
                    class="faculty-filter-select"
                    @change="fetchMyFeedback"
                  />
                </div>
              </div>

              <div v-if="myFeedbackLoading" class="d-flex flex-column gap-3">
                <div v-for="i in 3" :key="i" class="sk-list-item">
                  <div class="sk-shimmer mb-2" style="width: 40%; height: 14px"></div>
                  <div class="sk-shimmer" style="width: 90%; height: 14px"></div>
                </div>
              </div>

              <div v-else-if="myFeedbacks.length > 0" class="faculty-feedback-list">
                <article
                  v-for="item in myFeedbacks"
                  :key="item.id"
                  class="faculty-feedback-card"
                  :class="getRatingAccentClass(item.rating)"
                >
                  <div class="faculty-feedback-card-top">
                    <span class="faculty-feedback-tag">
                      <template v-if="user.role === 'faculty'">
                        {{ item.subject_code || 'General' }}
                        <span v-if="item.year_section"> · {{ item.year_section }}</span>
                      </template>
                      <template v-else>
                        {{ item.semester }} · {{ item.academic_year }}
                      </template>
                    </span>
                    <span class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(item.rating)">
                      {{ getRatingLabel(item.rating) }}
                    </span>
                  </div>
                  <blockquote class="faculty-feedback-quote">"{{ item.text }}"</blockquote>
                  <footer class="faculty-feedback-meta">
                    <span>
                      <i class="fas fa-calendar-alt me-1"></i>
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </span>
                    <span>
                      <i class="fas fa-clock me-1"></i>
                      {{ new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                  </footer>
                </article>
              </div>

              <div v-else class="faculty-feedback-state">
                <i class="fas fa-inbox fa-2x opacity-25 mb-3"></i>
                <p class="text-muted small mb-0">No student feedback for the selected period.</p>
              </div>
            </section>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, inject, computed } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import api from "../services/api.js";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value]);

const can = inject("can");

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const stats = ref({
  total_faculty: 0,
  total_students: 0,
  total_evaluations: 0,
  average_rating: 0,
  performance_overview: [],
  rating_distribution: [],
  comments: [],
});
const activeTab = ref("faculty");

const officeStats = ref({
  total_offices: 0,
  active_offices: 0,
  total_feedback: 0,
  today_feedback: 0,
  satisfaction_rate: 0,
  monthly_stats: [],
  visitor_type_distribution: {},
});
const officeTrend = ref([]);
const officeVisitorTypes = ref({});

const offices = ref([]);
const officesLoading = ref(false);

const evaluationStatus = ref("closed");
const myFeedbacks = ref([]);
const myFeedbackLoading = ref(false);
const feedbackFilters = ref({
  semester: "all",
  academic_year: "all",
});

const semesterOptions = [
  { label: "All Semesters", value: "all" },
  { label: "1st Semester", value: "1st Semester" },
  { label: "2nd Semester", value: "2nd Semester" },
  { label: "Summer", value: "Summer" },
];

const yearOptions = ref([{ label: "All Years", value: "all" }]);

const userFirstName = computed(() => {
  const name = user.value?.name || "";
  if (name.includes(",")) {
    const given = name.split(",")[1]?.trim() || "";
    return given.split(/\s+/)[0] || name;
  }
  return name.split(/\s+/)[0] || name;
});

const userInitials = computed(() => {
  const name = user.value?.name || "";
  const parts = name.includes(",")
    ? name.split(",").map((p) => p.trim())
    : name.split(/\s+/);
  if (parts.length >= 2) {
    const first = parts[0];
    const last = parts[parts.length - 1];
    return `${first[0] || ""}${last[0] || ""}`.toUpperCase();
  }
  return (parts[0]?.slice(0, 2) || "U").toUpperCase();
});

let facultyChart = null;
let ratingsChart = null;
let officeTrendChart = null;
let officeVisitorChart = null;
let facultyDelayed = false;
let ratingsDelayed = false;

function formatMonthLabel(month) {
  const [year, m] = String(month || "").split("-");
  if (!year || !m) return month;
  const date = new Date(Number(year), Number(m) - 1, 1);
  return date.toLocaleDateString(undefined, { month: "short" });
}

function sparklinePath(data, w = 80, h = 32) {
  if (!data || data.length < 2) return "";
  const mx = Math.max(...data);
  const mn = Math.min(...data);
  const r = mx - mn || 1;
  return data
    .map((v, i) => {
      const x = (i / (data.length - 1)) * w;
      const y = h - ((v - mn) / r) * (h - 6) - 3;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(" ");
}

function sparklineFill(points, h = 32) {
  if (!points) return "";
  const coords = points.split(" ");
  const first = coords[0];
  const last = coords[coords.length - 1];
  return `M${first} L${points} L${last.split(",")[0]},${h} L${first.split(",")[0]},${h}Z`;
}

const sparkFaculty = computed(() => sparklinePath(stats.value.performance_overview?.map((p) => p.average) || []));
const sparkStudents = computed(() => sparklinePath(stats.value.rating_distribution || []));
const sparkEvals = computed(() =>
  sparklinePath(
    (stats.value.performance_overview?.map((p) => p.average) || []).slice(0, -1),
  ),
);
const sparkRating = computed(() => sparklinePath([...(stats.value.rating_distribution || [])].reverse()));
const sparkFacultyFill = computed(() => sparklineFill(sparkFaculty.value));
const sparkStudentsFill = computed(() => sparklineFill(sparkStudents.value));
const sparkEvalsFill = computed(() => sparklineFill(sparkEvals.value));
const sparkRatingFill = computed(() => sparklineFill(sparkRating.value));

const fetchDashboardStats = async (type = "faculty") => {
  try {
    const res = await api.get(`/reports/dashboard?evaluatee_type=${type}`);
    stats.value = res.data;

    await nextTick();
    initCharts();
  } catch (e) {
    console.error("Error fetching dashboard stats:", e);
  }
};

const fetchOfficeStats = async () => {
  try {
    const res = await api.get(`/office-reports/dashboard`);
    officeStats.value = {
      total_offices: res.data.total_offices || 0,
      active_offices: res.data.active_offices || 0,
      total_feedback: res.data.total_feedback || 0,
      today_feedback: res.data.today_feedback || 0,
      satisfaction_rate: res.data.satisfaction_rate || 0,
      monthly_stats: res.data.monthly_stats || [],
      visitor_type_distribution: res.data.visitor_type_distribution || {},
    };
    officeTrend.value = (res.data.monthly_stats || []).map((item) => ({
      month: item.month,
      count: item.count,
      satisfaction_rate: Number(item.satisfaction_rate || 0).toFixed(2),
    }));
    officeVisitorTypes.value = res.data.visitor_type_distribution || {};

    await nextTick();
    initCharts();
  } catch (e) {
    console.error("Error fetching office dashboard stats:", e);
  }
};

const fetchMyFeedback = async () => {
  myFeedbackLoading.value = true;
  try {
    const params = {
      semester: feedbackFilters.value.semester,
      academic_year: feedbackFilters.value.academic_year,
    };
    const res = await api.get("/reports/my-feedback", { params });
    myFeedbacks.value = res.data.feedbacks || [];
  } catch (e) {
    console.error("Error fetching my feedback:", e);
    myFeedbacks.value = [];
  } finally {
    myFeedbackLoading.value = false;
  }
};

const setupFeedbackFilterOptions = (settings) => {
  const currentYear = new Date().getFullYear();
  const startYear = 2023;
  const yearList = [{ label: "All Years", value: "all" }];
  for (let y = currentYear + 1; y >= startYear; y--) {
    yearList.push({ label: `${y}-${y + 1}`, value: `${y}-${y + 1}` });
  }
  yearOptions.value = yearList;

  if (settings.active_semester) {
    feedbackFilters.value.semester = settings.active_semester;
  }
  if (settings.active_academic_year) {
    feedbackFilters.value.academic_year = settings.active_academic_year;
  }
};

onMounted(async () => {
  if (can("view_dashboard")) {
    fetchDashboardStats(activeTab.value);
    fetchOfficeStats();
  }

  try {
    const resSettings = await api.get("/settings");
    evaluationStatus.value = resSettings.data.evaluation_status || "closed";

    if (user.value.role === "faculty" && !can("view_dashboard")) {
      setupFeedbackFilterOptions(resSettings.data);
      await fetchMyFeedback();
    }
  } catch (e) {
    console.error("Error fetching settings:", e);
  }

  // Fetch offices for students
  if (user.value.role === "student" && !can("view_dashboard")) {
    officesLoading.value = true;
    try {
      const res = await api.get("/offices/all");
      offices.value = res.data;
    } catch (e) {
      console.error("Error fetching offices:", e);
    } finally {
      officesLoading.value = false;
    }
  }
});

function getRatingBadgeClass(rating) {
  if (rating >= 4.5) return "bg-success text-white";
  if (rating >= 3.5) return "bg-primary text-white";
  if (rating >= 2.5) return "bg-warning text-dark";
  if (rating >= 1.5) return "bg-orange text-white";
  return "bg-danger text-white";
}

function getRatingAccentClass(rating) {
  if (rating >= 4.5) return "faculty-feedback-card--excellent";
  if (rating >= 3.5) return "faculty-feedback-card--very-good";
  if (rating >= 2.5) return "faculty-feedback-card--good";
  if (rating >= 1.5) return "faculty-feedback-card--fair";
  return "faculty-feedback-card--poor";
}

function getRatingLabel(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Very Good";
  if (rating >= 2.5) return "Good";
  if (rating >= 1.5) return "Fair";
  return "Poor";
}

function destroyCharts() {
  [facultyChart, ratingsChart, officeTrendChart, officeVisitorChart].forEach((chart) => {
    if (chart) {
      chart.destroy();
    }
  });

  facultyChart = null;
  ratingsChart = null;
  officeTrendChart = null;
  officeVisitorChart = null;
}

onUnmounted(() => {
  destroyCharts();
});

function initCharts() {
  // Lazy-load Chart.js only when needed
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    // Reset delay flags for a fresh animation on every init
    facultyDelayed = false;
    ratingsDelayed = false;

    // Destroy existing instances before creating new ones
    destroyCharts();

    const facultyCtx = document.getElementById("facultyChart");
    if (facultyCtx && stats.value.performance_overview) {
      const labels = stats.value.performance_overview.map((item) => item.label);
      const data = stats.value.performance_overview.map((item) => item.average);

      const textPrimary = "#374151";
      const gridColor = "rgba(0, 0, 0, 0.05)";

      facultyChart = new Chart(facultyCtx, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Average Score",
              data: data,
              backgroundColor: "rgba(25,25,112,0.7)",
              borderRadius: 6,
              order: 2,
            },
            {
              label: "Trend",
              data: data,
              type: "line",
              borderColor: "#191970",
              borderWidth: 2,
              pointBackgroundColor: "#191970",
              pointBorderColor: "#fff",
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              tension: 0,
              fill: {
                target: "origin",
                above: "rgba(25,25,112,0.12)",
              },
              order: 1,
            },
          ],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: {
              min: 0,
              max: 5,
              ticks: { stepSize: 1, color: textPrimary },
              grid: { color: gridColor },
            },
            x: {
              ticks: { color: textPrimary },
            },
          },
          responsive: true,
          maintainAspectRatio: false,
          animations: {
            y: {
              from: (context) => (context.type === "data" ? 0 : undefined),
              duration: 1500,
              easing: "easeOutQuart",
              delay: (context) => (context.type === "data" && !facultyDelayed ? context.index * 150 : 0),
            },
            opacity: {
              from: 0,
              duration: 1500,
              delay: (context) => (context.type === "data" && !facultyDelayed ? context.index * 150 : 0),
            },
          },
          animation: {
            onComplete: () => {
              facultyDelayed = true;
            },
          },
        },
      });
    }

    const ratingCtx = document.getElementById("ratingsChart");
    if (ratingCtx && stats.value.rating_distribution) {
      const textPrimary = "#374151";
      ratingsChart = new Chart(ratingCtx, {
        type: "doughnut",
        data: {
          labels: ["Excellent (5)", "Very Good (4)", "Good (3)", "Fair (2)", "Poor (1)"],
          datasets: [
            {
              data: stats.value.rating_distribution,
              backgroundColor: [
                "#0e9f6e", // Green (Excellent)
                "#0A278A", // Navy (Very Good)
                "#FFC107", // Gold (Good)
                "#FADC67", // Lighter Gold (Fair)
                "#f05252", // Red (Poor)
              ],
            },
          ],
        },
        options: {
          cutout: "65%",
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "left",
              labels: {
                padding: 15,
                usePointStyle: true,
                pointStyle: "circle",
                font: { size: 11, weight: "600" },
                color: textPrimary,
              },
            },
          },
          animations: {
            spacing: {
              from: 30,
              duration: 1500,
              easing: "easeOutQuart",
              delay: (context) => (context.type === "data" && !ratingsDelayed ? 1500 + context.index * 200 : 0),
            },
            opacity: {
              from: 0,
              duration: 1500,
              delay: (context) => (context.type === "data" && !ratingsDelayed ? 1500 + context.index * 200 : 0),
            },
          },
          animation: {
            onComplete: () => {
              ratingsDelayed = true;
            },
          },
        },
      });
    }

    const officeTrendCtx = document.getElementById("officeTrendChart");
    if (officeTrendCtx && officeTrend.value.length) {
      const labels = officeTrend.value.map((item) => formatMonthLabel(item.month));
      const data = officeTrend.value.map((item) => Number(item.count) || 0);

      officeTrendChart = new Chart(officeTrendCtx, {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: "Feedback Volume",
              data,
              borderColor: "#191970",
              backgroundColor: "rgba(25,25,112,0.12)",
              borderWidth: 3,
              pointRadius: 5,
              pointHoverRadius: 7,
              pointBackgroundColor: "#ffffff",
              pointBorderColor: "#191970",
              pointBorderWidth: 2,
              tension: 0.35,
              fill: false,
            },
          ],
        },
        options: {
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { color: "#374151" },
              grid: { color: "rgba(0,0,0,0.05)" },
            },
            x: {
              ticks: { color: "#374151" },
              grid: { display: false },
            },
          },
          responsive: true,
          maintainAspectRatio: false,
        },
      });
    }

    const visitorCtx = document.getElementById("officeVisitorChart");
    if (visitorCtx && Object.keys(officeVisitorTypes.value).length) {
      officeVisitorChart = new Chart(visitorCtx, {
        type: "bar",
        data: {
          labels: Object.keys(officeVisitorTypes.value),
          datasets: [
            {
              label: "Visitors",
              data: Object.values(officeVisitorTypes.value).map((v) => Number(v) || 0),
              backgroundColor: [
                "#0e9f6e",
                "#191970",
                "#f59e0b",
                "#e11d48",
                "#232380",
              ],
              borderRadius: 8,
            },
          ],
        },
        options: {
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { color: "#374151" },
              grid: { color: "rgba(0,0,0,0.05)" },
            },
            x: {
              ticks: { color: "#374151" },
              grid: { display: false },
            },
          },
          responsive: true,
          maintainAspectRatio: false,
        },
      });
    }
  });
}
</script>

<style scoped>
.dashboard-type-toggle {
  display: flex;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 4px;
  gap: 4px;
}

.toggle-option {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: calc(var(--card-radius) - 4px);
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-muted);
  transition: all 0.2s ease;
  user-select: none;
}

.toggle-option input {
  display: none;
}

.toggle-option:hover {
  color: var(--text-dark);
  background: rgba(25, 25, 112, 0.05);
}

.toggle-option.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(25, 25, 112, 0.25);
}

@media (max-width: 575.98px) {
  .dashboard-type-toggle {
    width: 100%;
  }
  .toggle-option {
    flex: 1;
    justify-content: center;
  }
}

/* ── Faculty dashboard ───────────────── */
.faculty-dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  max-width: 1100px;
  margin: 0 auto;
}

.faculty-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

.faculty-hero-main {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  min-width: 0;
}

.faculty-hero-avatar {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  font-weight: 800;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
}

.faculty-hero-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--primary);
  background: rgba(25, 25, 112, 0.1);
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  margin-bottom: 0.35rem;
}

.faculty-hero-title {
  font-size: clamp(1.25rem, 4vw, 1.5rem);
  font-weight: 800;
  color: var(--text-main);
  margin: 0 0 0.35rem;
  line-height: 1.25;
}

.faculty-hero-desc {
  font-size: 0.9rem;
  color: var(--text-muted);
  line-height: 1.5;
}

.faculty-hero-logo {
  width: 64px;
  height: 64px;
  object-fit: contain;
  opacity: 0.85;
  flex-shrink: 0;
}

.faculty-actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

@media (min-width: 576px) {
  .faculty-actions {
    grid-template-columns: repeat(2, 1fr);
  }
}

.faculty-action-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  text-decoration: none;
  color: inherit;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.faculty-action-card:hover {
  border-color: var(--primary);
  box-shadow: var(--card-shadow-hover);
  transform: translateY(-1px);
  color: inherit;
}

.faculty-action-card--primary {
  border-color: rgba(25, 25, 112, 0.25);
  background: linear-gradient(135deg, var(--bg-card) 0%, rgba(25, 25, 112, 0.04) 100%);
}

.faculty-action-icon {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.faculty-action-icon--muted {
  background: var(--bg-light);
  color: var(--primary);
  border: 1px solid var(--border-color);
}

.faculty-action-body {
  flex: 1;
  min-width: 0;
}

.faculty-action-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin: 0 0 0.15rem;
  color: var(--text-main);
}

.faculty-action-desc {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.35;
}

.faculty-action-arrow {
  flex-shrink: 0;
  color: var(--text-muted);
  font-size: 0.75rem;
  opacity: 0.6;
}

.faculty-feedback-section {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  overflow: hidden;
}

.faculty-feedback-toolbar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-light);
}

@media (min-width: 768px) {
  .faculty-feedback-toolbar {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.faculty-feedback-heading {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.faculty-feedback-heading > i {
  font-size: 1.25rem;
  color: var(--primary);
  margin-top: 0.15rem;
}

.faculty-feedback-title {
  font-size: 1rem;
  font-weight: 800;
  margin: 0 0 0.15rem;
  color: var(--text-main);
}

.faculty-feedback-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.faculty-feedback-filters {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

@media (min-width: 576px) {
  .faculty-feedback-filters {
    flex-direction: row;
    width: auto;
  }
}

.faculty-filter-select {
  min-width: 0;
  width: 100%;
}

@media (min-width: 576px) {
  .faculty-filter-select {
    width: auto;
    min-width: 140px;
  }
}

.faculty-feedback-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  max-height: min(520px, 60vh);
  overflow-y: auto;
}

.faculty-feedback-card {
  padding: 1rem 1rem 1rem 1.15rem;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  border-left: 4px solid var(--border-color);
}

.faculty-feedback-card--excellent {
  border-left-color: var(--success);
}
.faculty-feedback-card--very-good {
  border-left-color: var(--primary);
}
.faculty-feedback-card--good {
  border-left-color: var(--warning);
}
.faculty-feedback-card--fair {
  border-left-color: #f97316;
}
.faculty-feedback-card--poor {
  border-left-color: var(--danger);
}

.faculty-feedback-card-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.faculty-feedback-tag {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
}

.faculty-feedback-quote {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--text-main);
  border: none;
  padding: 0;
}

.faculty-feedback-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.faculty-feedback-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  text-align: center;
}

@media (max-width: 575.98px) {
  .faculty-hero {
    padding: 1rem;
  }

  .faculty-hero-avatar {
    width: 44px;
    height: 44px;
    font-size: 0.85rem;
  }

  .faculty-feedback-list {
    max-height: none;
    padding: 0.75rem;
  }
}

/* ── Animated gradient border for dashboard type selector ── */
@property --border-angle {
  syntax: "<angle>";
  initial-value: 0deg;
  inherits: false;
}

.dashboard-type-select {
  position: relative;
  border-radius: 50px;
  padding: 1px;
  overflow: hidden;
}

.dashboard-type-select::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50px;
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
  padding: 1px;
}

.dashboard-type-select > * {
  position: relative;
  z-index: 1;
  border-radius: 49px;
}

.dashboard-type-select :deep(.custom-select-trigger) {
  background: var(--bg-card) !important;
  border: none !important;
}

@keyframes spin-border {
  to {
    --border-angle: 360deg;
  }
}

/* ── Admin Dashboard Heading Dropdown ── */
.dashboard-heading-custom-select {
  min-width: 320px;
}

@media (max-width: 575.98px) {
  .dashboard-heading-custom-select {
    width: 100%;
    min-width: 0;
  }
}

.dashboard-heading-custom-select :deep(.custom-select-trigger) {
  font-size: 1.25rem;
  font-weight: 200;
  padding: 0.35rem 0.5rem;
  background: transparent;
  border: none !important;
  box-shadow: none !important;
  color: var(--text-main);
  transition: all 0.2s ease;
  min-height: auto;
}

.dashboard-heading-custom-select :deep(.custom-select-trigger:hover),
.dashboard-heading-custom-select :deep(.custom-select-trigger.active) {
  border: none !important;
  box-shadow: none !important;
  transform: none;
  opacity: 0.85;
}

.dashboard-heading-custom-select :deep(.selected-text) {
  letter-spacing: -0.01em;
}

.office-feedback-header {
  padding: 0 0.25rem;
}

.office-eval-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 1.1rem 1.15rem;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  min-width: 0;
  overflow: hidden;
}

.office-eval-card:hover {
  border-color: var(--primary);
  box-shadow: 0 4px 16px rgba(25, 25, 112, 0.1);
}

.office-icon-sm {
  width: 40px;
  height: 40px;
  min-width: 40px;
  border-radius: 10px;
  background: rgba(25, 25, 112, 0.1);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

.office-desc {
  font-size: 0.78rem;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}

.min-w-0 {
  min-width: 0;
}

.smallest {
  font-size: 0.65rem;
}

@media (max-width: 575.98px) {
  .office-eval-card {
    padding: 1rem;
  }

  .office-icon-sm {
    width: 36px;
    height: 36px;
    min-width: 36px;
    font-size: 0.9rem;
  }
}
</style>
