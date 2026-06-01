<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>{{ t.dashboard }}</template></Navbar>

      <div class="content-area">
        <!-- Admin/Stats Dashboard -->
        <template v-if="$can('view_dashboard')">
          <!-- Segmented Tab Toggle for Faculty vs Staff -->
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 fade-in-up">
            <h4 class="mb-0 fw-bold text-dark">
              {{ activeTab === 'faculty' ? 'Faculty Evaluation Summary' : 'Staff Evaluation Summary' }}
            </h4>
            <div class="dashboard-type-select">
              <CustomSelect
                v-model="activeTab"
                :options="evaluateeTypeOptions"
                placeholder="Select type"
                @change="onEvaluateeTypeChange"
              />
            </div>
          </div>

          <!-- Main Dashboard Grid -->
          <div class="row g-4 fade-in-up">
            <!-- Top Left: Stats Grid (8/12) -->
            <div class="col-lg-8 d-flex flex-column">
              <div class="row g-4 flex-grow-1">
                <div class="col-sm-6 d-flex">
                  <div class="stat-card flex-grow-1">
                    <div class="stat-icon blue">
                      <i :class="activeTab === 'faculty' ? 'fas fa-chalkboard-teacher' : 'fas fa-user-tie'"></i>
                    </div>
                    <div>
                      <div class="stat-label">{{ activeTab === 'faculty' ? 'Total Faculty' : 'Total Staff' }}</div>
                      <div class="stat-value">{{ stats.total_faculty }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 d-flex">
                  <div class="stat-card flex-grow-1">
                    <div class="stat-icon green">
                      <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                      <div class="stat-label">Total Students</div>
                      <div class="stat-value">{{ stats.total_students }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 d-flex">
                  <div class="stat-card flex-grow-1">
                    <div class="stat-icon orange">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                      <div class="stat-label">Evaluations</div>
                      <div class="stat-value">{{ stats.total_evaluations }}</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6 d-flex">
                  <div class="stat-card flex-grow-1">
                    <div class="stat-icon red">
                      <i class="fas fa-star"></i>
                    </div>
                    <div>
                      <div class="stat-label">Avg Rating</div>
                      <div class="stat-value">
                        {{ Number(stats.average_rating).toFixed(2) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Top Right: Rating Distribution (4/12) -->
            <div class="col-lg-4 d-flex">
              <div class="card h-100 flex-grow-1 d-flex flex-column">
                <div class="card-header">
                  <i class="fas fa-chart-pie"></i>
                  Rating Distribution
                </div>
                <div class="card-body d-flex align-items-center justify-content-center flex-grow-1" style="min-height: 250px">
                  <canvas id="ratingsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Row -->
          <div class="row g-4 mt-1 fade-in-up" style="animation-delay: 0.2s">
            <!-- Bottom Left: Faculty Performance (8/12) -->
            <div class="col-lg-8 d-flex">
              <div class="card h-100 flex-grow-1 d-flex flex-column">
                <div class="card-header">
                  <i class="fas fa-chart-bar"></i>
                  Performance Overview
                </div>
                <div class="card-body flex-grow-1" style="min-height: 350px">
                  <canvas id="facultyChart"></canvas>
                </div>
              </div>
            </div>

            <!-- Bottom Right: Feedback/Comments (4/12) -->
            <div class="col-lg-4 d-flex">
              <div class="card shadow-none h-100 flex-grow-1 d-flex flex-column">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-comment-dots text-primary"></i>
                    <h6 class="mb-0 fw-bold">Recent Feedback Feed</h6>
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
                          <i class="far fa-calendar-alt me-1"></i>
                          {{ new Date(comment.created_at).toLocaleDateString() }}
                        </span>
                        <span class="text-muted smallest">
                          <i class="far fa-clock me-1"></i>
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
          <div class="card border-0 shadow-none">
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
        </template>

        <template v-if="(user.role === 'faculty' || user.role === 'staff') && !$can('view_dashboard')">
          <div class="faculty-dashboard fade-in-up">
            <!-- Welcome hero -->
            <section class="faculty-hero">
              <div class="faculty-hero-main">
                <div class="faculty-hero-avatar" aria-hidden="true">{{ userInitials }}</div>
                <div class="faculty-hero-text">
                  <span class="faculty-hero-badge">{{ user.role === 'faculty' ? 'Faculty' : 'Staff' }}</span>
                  <h2 class="faculty-hero-title">Hello, {{ userFirstName }}</h2>
                  <p class="faculty-hero-desc mb-0">
                    {{
                      user.role === 'faculty'
                        ? 'Review your teaching effectiveness scores and student comments in one place.'
                        : 'Review your service evaluation scores and feedback from students.'
                    }}
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
                :to="user.role === 'staff' ? { path: '/reports', query: { type: 'staff' } } : '/reports'"
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
                :to="user.role === 'staff' ? { path: '/set-report', query: { type: 'staff' } } : '/set-report'"
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
                  <select
                    v-model="feedbackFilters.semester"
                    class="form-select form-select-sm faculty-filter-select"
                    @change="fetchMyFeedback"
                  >
                    <option v-for="opt in semesterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <select
                    v-model="feedbackFilters.academic_year"
                    class="form-select form-select-sm faculty-filter-select"
                    @change="fetchMyFeedback"
                  >
                    <option v-for="opt in yearOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </div>
              </div>

              <div v-if="myFeedbackLoading" class="faculty-feedback-state">
                <div class="spinner-border text-primary opacity-50" role="status"></div>
                <p class="text-muted small mb-0 mt-2">Loading feedback...</p>
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
                      <i class="far fa-calendar-alt me-1"></i>
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </span>
                    <span>
                      <i class="far fa-clock me-1"></i>
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

const evaluateeTypeOptions = [
  { label: "Faculty", value: "faculty" },
  { label: "Staff", value: "staff" },
];
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
let facultyDelayed = false;
let ratingsDelayed = false;

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

const onEvaluateeTypeChange = () => {
  fetchDashboardStats(activeTab.value);
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
  }

  try {
    const resSettings = await api.get("/settings");
    evaluationStatus.value = resSettings.data.evaluation_status || "closed";

    if ((user.value.role === "faculty" || user.value.role === "staff") && !can("view_dashboard")) {
      setupFeedbackFilterOptions(resSettings.data);
      await fetchMyFeedback();
    }
  } catch (e) {
    console.error("Error fetching settings:", e);
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

onUnmounted(() => {
  if (facultyChart) {
    facultyChart.destroy();
    facultyChart = null;
  }
  if (ratingsChart) {
    ratingsChart.destroy();
    ratingsChart = null;
  }
});

function initCharts() {
  // Lazy-load Chart.js only when needed
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    // Reset delay flags for a fresh animation on every init
    facultyDelayed = false;
    ratingsDelayed = false;

    // Destroy existing instances if they exist
    if (facultyChart) facultyChart.destroy();
    if (ratingsChart) ratingsChart.destroy();

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
              backgroundColor: "rgba(26,86,219,0.7)",
              borderRadius: 6,
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
  });
}
</script>

<style scoped>
.dashboard-type-select {
  width: 200px;
  flex-shrink: 0;
}

@media (max-width: 575.98px) {
  .dashboard-type-select {
    width: 100%;
  }
}

/* ── Faculty / Staff dashboard ───────────────── */
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
  border-radius: 8px;
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
  background: rgba(0, 82, 255, 0.1);
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
  border-radius: 8px;
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
  border-color: rgba(0, 82, 255, 0.25);
  background: linear-gradient(135deg, var(--bg-card) 0%, rgba(0, 82, 255, 0.04) 100%);
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
  border-radius: 8px;
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
  border-radius: 8px;
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
    #0a278a,
    #1e40af,
    #2563eb,
    #0a278a,
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
</style>
