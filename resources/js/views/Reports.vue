<template>
  <div class="d-flex">
    <Sidebar class="no-print" />
    <div class="main-wrapper w-100">
      <Navbar class="no-print"><template #title>Evaluation Ratings Overview</template></Navbar>

      <div class="content-area">
        <!-- Admin Faculty Selector -->
        <div
          class="card shadow-none mb-4 no-print mx-3 mx-md-0"
          v-if="$can('view_reports') && user.role !== 'faculty' && user.role !== 'staff'"
          style="position: relative; z-index: 900; overflow: visible !important"
        >
          <div
            class="card-body d-flex gap-3 align-items-center flex-wrap no-print"
            style="overflow: visible !important"
          >
            <!-- Search Filter -->
            <div class="search-pill-container" style="width: 220px">
              <i class="fas fa-search search-icon"></i>
              <input 
                type="text" 
                v-model="searchQuery" 
                class="search-input-field" 
                :placeholder="evaluateeType === 'faculty' ? 'Search faculty...' : 'Search staff...'"
              />
            </div>

            <!-- Dept Filter (faculty only) -->
            <div v-if="evaluateeType === 'faculty'" style="width: 200px">
              <CustomSelect
                v-model="selectedDepartment"
                :options="departmentOptions"
                placeholder="All Departments"
                @change="handleDepartmentChange"
              />
            </div>

            <!-- Main Faculty Selector -->
            <div style="width: 350px">
              <CustomSelect
                v-model="selectedFacultyId"
                :options="facultyOptions"
                :placeholder="evaluateeType === 'faculty' ? 'Select Faculty:' : 'Select Staff:'"
                @change="loadResults"
              />
            </div>

            <!-- Refresh/Reset Button -->
            <button class="refresh-pill-btn" @click="resetFilters" title="Reset Filters">
              <i class="fas fa-undo" :class="{ 'fa-spin': loading }"></i>
            </button>
          </div>
        </div>

        <!-- Results Section -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 text-muted">Analyzing evaluation data...</p>
        </div>

        <div v-else-if="results" class="reports-results">
          <!-- Top Highlights (F-Pattern Header) -->
          <div class="row g-3 mb-4 reports-summary-row">
            <div class="col-12 col-md-4">
              <div class="stat-card shadow-none p-3 p-md-4 h-100 flex-column text-center">
                <div class="stat-label mb-2">Final Evaluation Score</div>
                <div class="stat-value text-success mb-2 report-final-score">
                  {{ results.final_score }}
                </div>
                <span class="badge px-3 px-md-4 py-2 rounded-pill" :class="badgeClass(results.interpretation)">
                  {{ results.interpretation }}
                </span>
              </div>
            </div>
            <div class="col-12 col-md-8">
              <div class="card shadow-none h-100">
                <div class="card-header border-0 py-3">
                  <h6 class="mb-0 fw-bold">Score Visualization</h6>
                </div>
                <div class="card-body py-2 report-chart-body">
                  <canvas id="reportChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Frequency Grid — mobile cards -->
          <div class="d-md-none reports-mobile-categories mb-4">
            <div
              v-for="cat in results?.category_results"
              :key="cat?.category_name + '-mobile'"
              class="reports-category-card"
            >
              <div class="reports-category-card-head">
                <div>
                  <div class="fw-bold">{{ cat.category_name }}</div>
                  <div class="small text-muted">Weight: {{ (cat.weight * 100).toFixed(0) }}%</div>
                </div>
                <div class="text-end">
                  <div class="fw-bold text-primary">{{ Number(cat.average_rating).toFixed(2) }}</div>
                  <div class="x-small text-muted">Avg</div>
                </div>
              </div>
              <div class="reports-freq-grid">
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-success">{{ cat.count_5 }}</span>
                  <span class="reports-freq-lbl">5</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-info">{{ cat.count_4 }}</span>
                  <span class="reports-freq-lbl">4</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val">{{ cat.count_3 }}</span>
                  <span class="reports-freq-lbl">3</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-warning">{{ cat.count_2 }}</span>
                  <span class="reports-freq-lbl">2</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-danger">{{ cat.count_1 }}</span>
                  <span class="reports-freq-lbl">1</span>
                </div>
              </div>
              <div class="reports-category-card-foot">
                <span class="small text-muted">Weighted score</span>
                <span class="fw-bold">{{ (cat.average_rating * cat.weight).toFixed(3) }}</span>
              </div>
            </div>
          </div>

          <!-- Frequency Grid (desktop table) -->
          <div class="card shadow-none mb-4 overflow-hidden reports-table-card d-none d-md-block">
            <div class="card-body p-0">
              <div class="table-responsive reports-table-scroll">
                <table class="table table-borderless table-hover mb-0 align-middle text-center">
                  <thead class="bg-light small">
                    <tr class="text-uppercase text-muted">
                      <th rowspan="2" class="py-3 text-start ps-4" style="min-width: 250px">Category</th>
                      <th colspan="5" class="py-2 border-0">Frequency Distribution</th>
                      <th rowspan="2" class="py-3">
                        Weighted
                        <br />
                        Score
                      </th>
                      <th rowspan="2" class="py-3 pe-4">Total Avg</th>
                    </tr>
                    <tr class="text-muted small">
                      <th style="width: 60px">5</th>
                      <th style="width: 60px">4</th>
                      <th style="width: 60px">3</th>
                      <th style="width: 60px">2</th>
                      <th style="width: 60px">1</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cat in results?.category_results" :key="cat?.category_name">
                      <td class="text-start ps-4">
                        <div class="fw-bold">
                          {{ cat.category_name }}
                        </div>
                        <div class="small text-muted">
                          Weight:
                          {{ (cat.weight * 100).toFixed(0) }}%
                        </div>
                      </td>
                      <td class="fw-bold text-success">
                        {{ cat.count_5 }}
                      </td>
                      <td class="text-info">
                        {{ cat.count_4 }}
                      </td>
                      <td class="text-muted">
                        {{ cat.count_3 }}
                      </td>
                      <td class="text-warning">
                        {{ cat.count_2 }}
                      </td>
                      <td class="text-danger">
                        {{ cat.count_1 }}
                      </td>
                      <td class="fw-bold">
                        {{ (cat.average_rating * cat.weight).toFixed(3) }}
                      </td>
                      <td class="pe-4">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                          <span class="fw-bold text-primary">{{ Number(cat.average_rating).toFixed(2) }}</span>
                          <div class="progress" style="width: 40px; height: 6px">
                            <div
                              class="progress-bar bg-primary"
                              :style="{
                                width: (cat.average_rating / 5) * 100 + '%',
                              }"
                            ></div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Floating AI Button (FAB) — admins only, not faculty/staff dashboards -->
          <div v-if="canUseAiInsights" class="ai-fab-container no-print">
            <button
              class="ai-fab"
              :class="{ 'has-insights': aiInsights, loading: loadingAi, active: showAiOverlay }"
              @click="toggleAiOverlay"
              :title="showAiOverlay ? 'Close Insights' : 'View AI Insights'"
            >
              <i v-if="loadingAi" class="fas fa-spinner fa-spin"></i>
              <i v-else-if="showAiOverlay" class="fas fa-times"></i>
              <AiSparkleIcon v-else :size="28" variant="white" />
              <span v-if="!aiInsights && !loadingAi && !showAiOverlay" class="ai-badge">Get AI Insights</span>
            </button>
          </div>

          <Transition name="drop-down">
            <div v-if="showAiOverlay && canUseAiInsights" class="ai-top-overlay no-print" @click.self="showAiOverlay = false">
              <div class="ai-animated-border-wrapper shadow-2xl" :class="{ 'is-full-screen': isAiFullScreen }">
                <div class="ai-overlay-content border-0 h-100 w-100">
                  <div
                    class="ai-overlay-header ai-gradient-header py-3 px-4 d-flex justify-content-between align-items-center"
                  >
                    <div class="d-flex align-items-center gap-3">
                      <div
                        class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                        style="width: 42px; height: 42px"
                      >
                        <AiSparkleIcon :size="22" />
                      </div>
                      <h5 class="mb-0 fw-800 text-main">AI Feedback Analysis & Suggestions</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <button
                        v-if="aiInsights && !loadingAi"
                        class="btn btn-sm btn-light py-1 px-3 rounded-pill fw-bold text-primary"
                        @click="loadAiInsights"
                      >
                        <i class="fas fa-sync-alt me-1"></i>
                        Refresh
                      </button>
                      <button
                        class="btn btn-sm btn-light rounded-pill px-3 fw-bold d-flex align-items-center justify-content-center"
                        style="height: 32px"
                        @click="toggleAiFullScreen"
                      >
                        <i class="fas" :class="isAiFullScreen ? 'fa-compress' : 'fa-expand'"></i>
                      </button>
                      <button
                        class="btn-close"
                        style="margin-bottom: 2px"
                        @click="showAiOverlay = false"
                      ></button>
                    </div>
                  </div>

                  <div class="ai-overlay-body p-4">
                    <div v-if="loadingAi" class="text-center py-5">
                      <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem"></div>
                      <h4 class="fw-bold">Generating Analytics...</h4>
                      <p class="text-muted">Analyzing metrics and qualitative feedback for your smart dashboard.</p>
                    </div>

                    <div v-else-if="aiError" class="text-center py-5">
                      <div class="mb-3 text-warning">
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                      </div>
                      <h5 class="fw-bold text-dark mb-2">{{ aiError }}</h5>
                      <p class="text-muted mx-auto" style="max-width: 400px">
                        The AI service is currently unavailable or has reached its request limit. Please try again in a
                        few minutes.
                      </p>
                      <button class="btn btn-primary rounded-pill px-4 mt-3 shadow-sm" @click="loadAiInsights">
                        <i class="fas fa-sync-alt me-2"></i>
                        Try Again
                      </button>
                    </div>

                    <div v-else-if="aiInsights" class="dashboard-grid">
                      <!-- 1. Top Summary Cards -->
                      <div class="row g-3 mb-4">
                        <div class="col-md-3">
                          <div class="metric-card p-3 rounded-4 shadow-none h-100">
                            <div class="d-flex align-items-center gap-3">
                              <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-star"></i>
                              </div>
                              <div>
                                <div class="small text-muted fw-bold text-uppercase ls-1">Avg Rating</div>
                                <div class="h4 mb-0 fw-800">{{ aiInsights.metrics?.average_rating || "0.00" }}</div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="metric-card p-3 rounded-4 shadow-none h-100">
                            <div class="d-flex align-items-center gap-3">
                              <div class="metric-icon bg-info bg-opacity-10 text-info">
                                <i class="fas fa-users"></i>
                              </div>
                              <div>
                                <div class="small text-muted fw-bold text-uppercase ls-1">Responses</div>
                                <div class="h4 mb-0 fw-800">{{ aiInsights.metrics?.response_count || 0 }}</div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="metric-card p-3 rounded-4 shadow-none h-100">
                            <div class="d-flex align-items-center gap-3">
                              <div class="metric-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-smile"></i>
                              </div>
                              <div>
                                <div class="small text-muted fw-bold text-uppercase ls-1">Positive</div>
                                <div class="h4 mb-0 fw-800 text-success">{{ aiInsights.sentiment?.positive }}%</div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="metric-card p-3 rounded-4 shadow-none h-100">
                            <div class="d-flex align-items-center gap-3">
                              <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-chart-line"></i>
                              </div>
                              <div>
                                <div class="small text-muted fw-bold text-uppercase ls-1">Trend</div>
                                <div
                                  class="h4 mb-0 fw-800"
                                  :class="
                                    aiInsights.metrics?.previous_rating
                                      ? aiInsights.metrics.average_rating >= aiInsights.metrics.previous_rating
                                        ? 'text-success'
                                        : 'text-danger'
                                      : 'text-muted'
                                  "
                                >
                                  {{
                                    aiInsights.metrics?.previous_rating
                                      ? (aiInsights.metrics.average_rating > aiInsights.metrics.previous_rating
                                          ? "+"
                                          : "") +
                                        (
                                          aiInsights.metrics.average_rating - aiInsights.metrics.previous_rating
                                        ).toFixed(2)
                                      : "N/A"
                                  }}
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 2. Charts Section -->
                      <div class="row g-4 mb-4">
                        <div class="col-md-8">
                          <div class="card shadow-none rounded-4 h-100 overflow-hidden bg-card">
                            <div class="card-header border-0 bg-transparent py-3">
                              <h6 class="mb-0 fw-800 text-uppercase small ls-1">Criteria Breakdown (Quantitative)</h6>
                            </div>
                            <div class="card-body pt-0" style="height: 250px">
                              <canvas id="dashboardBarChart"></canvas>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="card shadow-none rounded-4 h-100 overflow-hidden bg-card">
                            <div class="card-header border-0 bg-transparent py-3">
                              <h6 class="mb-0 fw-800 text-uppercase small ls-1">Sentiment Mix</h6>
                            </div>
                            <div
                              class="card-body pt-0 d-flex align-items-center justify-content-center"
                              style="height: 250px"
                            >
                              <canvas id="dashboardPieChart"></canvas>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 3. Tables Section -->
                      <div class="row g-4 mb-4">
                        <div class="col-md-6">
                          <div class="card shadow-none rounded-4 h-100 overflow-hidden bg-card">
                            <div class="card-header border-0 bg-transparent py-3">
                              <h6 class="mb-0 fw-800 text-uppercase small ls-1">Criteria Performance Table</h6>
                            </div>
                            <div class="card-body p-0">
                              <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                  <thead class="bg-light">
                                    <tr class="x-small text-uppercase text-muted">
                                      <th class="ps-4">Criteria</th>
                                      <th>Rating</th>
                                      <th class="pe-4 text-end">Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="cat in results?.category_results" :key="cat.category_name">
                                      <td class="ps-4 fw-600 small">{{ cat.category_name }}</td>
                                      <td>
                                        <div class="d-flex align-items-center gap-2">
                                          <span class="fw-bold">{{ Number(cat.average_rating).toFixed(2) }}</span>
                                          <div class="progress" style="width: 40px; height: 4px">
                                            <div
                                              class="progress-bar"
                                              :class="getRatingColor(cat.average_rating)"
                                              :style="{ width: (cat.average_rating / 5) * 100 + '%' }"
                                            ></div>
                                          </div>
                                        </div>
                                      </td>
                                      <td class="pe-4 text-end">
                                        <span class="badge rounded-pill" :class="getRatingBadge(cat.average_rating)">
                                          {{ getRatingStatus(cat.average_rating) }}
                                        </span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="card shadow-none rounded-4 h-100 overflow-hidden bg-card">
                            <div class="card-header border-0 bg-transparent py-3">
                              <h6 class="mb-0 fw-800 text-uppercase small ls-1">AI Metric Insights</h6>
                            </div>
                            <div class="card-body p-0">
                              <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                  <thead class="bg-light">
                                    <tr class="x-small text-uppercase text-muted">
                                      <th class="ps-4">Metric</th>
                                      <th>Value</th>
                                      <th class="pe-4">AI Insight</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="mi in aiInsights.metric_insights" :key="mi.metric">
                                      <td class="ps-4 fw-600 small text-primary">{{ mi.metric }}</td>
                                      <td class="fw-bold small text-muted">Analyzed</td>
                                      <td class="pe-4 x-small text-muted">{{ mi.insight }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 4. AI Insights Panel -->
                      <div class="row g-4">
                        <div class="col-md-4">
                          <div class="p-4 rounded-4 shadow-sm h-100 bg-card">
                            <div class="d-flex align-items-center gap-2 mb-3">
                              <i class="fas fa-check-circle text-success fs-5"></i>
                              <h6 class="fw-800 text-success small text-uppercase ls-1 mb-0">Key Strengths</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small fw-600 text-dark">
                              <li v-for="s in aiInsights.strengths" :key="s" class="mb-2 d-flex gap-2">
                                <span class="text-success opacity-50">•</span>
                                {{ s }}
                              </li>
                            </ul>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="p-4 rounded-4 shadow-sm h-100 bg-card">
                            <div class="d-flex align-items-center gap-2 mb-3">
                              <i class="fas fa-exclamation-circle text-danger fs-5"></i>
                              <h6 class="fw-800 text-danger small text-uppercase ls-1 mb-0">Areas for Improvement</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small fw-600 text-dark">
                              <li v-for="i in aiInsights.issues" :key="i" class="mb-2 d-flex gap-2">
                                <span class="text-danger opacity-50">•</span>
                                {{ i }}
                              </li>
                            </ul>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="p-4 rounded-4 shadow-sm h-100 bg-card">
                            <div class="d-flex align-items-center gap-2 mb-3">
                              <i class="fas fa-lightbulb text-warning fs-5"></i>
                              <h6 class="fw-800 text-warning small text-uppercase ls-1 mb-0">AI Recommendations</h6>
                            </div>
                            <ul class="list-unstyled mb-0 small fw-600 text-dark">
                              <li v-for="r in aiInsights.recommendations" :key="r" class="mb-2 d-flex gap-2">
                                <span class="text-warning opacity-50">•</span>
                                {{ r }}
                              </li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-else class="text-center py-5">
                      <div class="mb-4">
                        <div
                          class="bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                          style="width: 100px; height: 100px"
                        >
                          <AiSparkleIcon :size="52" />
                        </div>
                        <h3 class="fw-800">Analyze Feedback with AI</h3>
                        <p class="text-muted mx-auto mb-4" style="max-width: 550px">
                          Let our AI process all qualitative comments to identify performance patterns and professional
                          growth opportunities. This takes only a few seconds.
                        </p>
                      </div>
                      <button class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg d-inline-flex align-items-center gap-2" @click="loadAiInsights">
                        <AiSparkleIcon :size="20" variant="white" />
                        Start Analyzing
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="!loading && selectedFacultyId === '' && $can('view_reports') && user.role !== 'faculty' && user.role !== 'staff'"
          class="card border-0 shadow-sm"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-chart-line fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">Select {{ evaluateeType === 'faculty' ? 'Faculty' : 'Staff' }} to View Overview</h5>
            <p class="mb-0">Please choose a {{ evaluateeType === 'faculty' ? 'faculty member' : 'staff member' }} to see their ratings breakdown.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, inject, onUnmounted, computed, watch } from "vue";
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import AiSparkleIcon from "../components/AiSparkleIcon.vue";
import api from "../services/api.js";

const can = inject("can");
const route = useRoute();

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const evaluateeType = ref(user.value.role === 'staff' ? 'staff' : 'faculty');
const facultyList = ref([]);
const selectedFacultyId = ref("");
const results = ref(null);
const aiInsights = ref(null);
const loading = ref(false);
const loadingAi = ref(false);
const showAiOverlay = ref(false);
const isAiFullScreen = ref(false);
const aiError = ref(null);

// New Filters
const searchQuery = ref("");
const selectedDepartment = ref("all");

const departmentOptions = computed(() => [
  { label: "All Departments", value: "all" },
  ...departments.value.map(d => ({ label: d, value: d }))
]);

const departments = computed(() => {
  const depts = (facultyList.value || [])
    .map(f => f.department)
    .filter(d => d);
  return [...new Set(depts)].sort();
});

async function resetFilters() {
  searchQuery.value = "";
  selectedDepartment.value = "all";
  selectedFacultyId.value = "all";
  await loadResults();
}

function toggleAiOverlay() {
  showAiOverlay.value = !showAiOverlay.value;
  if (!showAiOverlay.value) isAiFullScreen.value = false;
}

function toggleAiFullScreen() {
  isAiFullScreen.value = !isAiFullScreen.value;
}

const filteredFacultyList = computed(() => {
  if (!facultyList.value) return [];
  
  let list = facultyList.value.filter(f => f && f.id);

  // Apply Department Filter (faculty only)
  if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
    list = list.filter(f =>
      f.department && f.department.trim() === selectedDepartment.value.trim()
    );
  }

  // Apply Search Filter
  const q = searchQuery.value ? searchQuery.value.trim().toLowerCase() : "";
  if (q) {
    list = list.filter(f => {
      const name = f.user?.name?.toLowerCase() || "";
      if (evaluateeType.value === "staff") {
        const designation = f.designation?.toLowerCase() || "";
        return name.includes(q) || designation.includes(q);
      }
      const dept = f.department?.toLowerCase() || "";
      return name.includes(q) || dept.includes(q);
    });
  }

  return list;
});

const canUseAiInsights = computed(
  () => user.value.role !== "faculty" && user.value.role !== "staff",
);

const facultyOptions = computed(() => {
  const allLabel = evaluateeType.value === 'faculty' ? "All Faculty" : "All Staff";
  return [
    { label: allLabel, value: "all" },
    ...filteredFacultyList.value.map((f) => {
      if (evaluateeType.value === "staff") {
        const designationText = f.designation ? ` - ${f.designation}` : "";
        return {
          label: `${f.user?.name}${designationText}`,
          value: f.id,
        };
      }
      return {
        label: `${f.user?.name} (${f.department || "N/A"})`,
        value: f.id,
      };
    }),
  ];
});

async function fetchEvaluateesList() {
  try {
    if (evaluateeType.value === 'faculty') {
      const res = await api.get("/faculty/all");
      facultyList.value = res.data;
    } else {
      const res = await api.get("/reports/staff-list");
      facultyList.value = (res.data || []).map(s => ({
        id: s.id,
        user_id: s.user_id,
        user: { name: s.name },
        department: s.department,
        designation: s.designation,
      }));
    }
  } catch (e) {
    console.error("Error fetching evaluatees list:", e);
  }
}

function getTypeFromRoute() {
  return route.query.type === "staff" ? "staff" : "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("view_reports") && user.value.role !== "faculty" && user.value.role !== "staff") {
    const type = getTypeFromRoute();
    if (evaluateeType.value !== type) {
      evaluateeType.value = type;
      selectedFacultyId.value = "all";
      selectedDepartment.value = "all";
      await fetchEvaluateesList();
      await loadResults();
    }
  }
}

watch(
  () => route.query.type,
  async () => {
    await applyEvaluateeTypeFromRoute();
  },
);

onMounted(async () => {
  if (can("view_reports") && user.value.role !== "faculty" && user.value.role !== "staff") {
    evaluateeType.value = getTypeFromRoute();
    selectedFacultyId.value = "all";
    await fetchEvaluateesList();
    await loadResults();
  } else if (user.value.role === "faculty") {
    const res = await api.get("/faculty/all");
    const mine = (res.data || []).find((f) => f?.user_id === user.value?.id);
    if (mine) {
      selectedFacultyId.value = mine.id;
      evaluateeType.value = 'faculty';
      await loadResults();
    }
  } else if (user.value.role === "staff") {
    const res = await api.get("/reports/staff-list");
    const list = res.data || [];
    const mine =
      list.find((s) => s?.user_id === user.value?.id || s?.user?.id === user.value?.id) ||
      list.find((s) => s?.name && user.value?.name && s.name.trim() === user.value.name.trim());
    if (mine) {
      selectedFacultyId.value = mine.id;
      evaluateeType.value = "staff";
      await loadResults();
    }
  }
});

async function handleDepartmentChange() {
  if (selectedFacultyId.value !== 'all') {
    const currentFaculty = facultyList.value.find(f => f.id === selectedFacultyId.value);
    if (currentFaculty && currentFaculty.department !== selectedDepartment.value && selectedDepartment.value !== 'all') {
      selectedFacultyId.value = 'all';
    }
  }
  await loadResults();
}

onUnmounted(() => {});

async function loadResults() {
  if (!selectedFacultyId.value) return;
  loading.value = true;
  results.value = null;
  try {
    const params = { evaluatee_type: evaluateeType.value };
    if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
      params.department = selectedDepartment.value;
    }
    const res = await api.get(`/evaluations/results/${selectedFacultyId.value}`, { params });

    results.value = res.data;
    aiInsights.value = null;

    await nextTick();
    drawChart();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function loadAiInsights() {
  if (!selectedFacultyId.value || !canUseAiInsights.value) return;
  loadingAi.value = true;
  aiInsights.value = null;
  aiError.value = null;
  try {
    const params = { evaluatee_type: evaluateeType.value };
    if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
      params.department = selectedDepartment.value;
    }
    const res = await api.get(`/reports/ai-insights/${selectedFacultyId.value}`, { params });
    aiInsights.value = res.data;
    await nextTick();
    renderDashboardCharts();
  } catch (e) {
    console.error("AI insights load failed", e);
    aiError.value = e.response?.data?.message || "An unexpected error occurred while generating insights.";
  } finally {
    loadingAi.value = false;
  }
}

function renderDashboardCharts() {
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    // Destroy existing charts to prevent overlap
    const existingBar = Chart.getChart("dashboardBarChart");
    if (existingBar) existingBar.destroy();
    const existingPie = Chart.getChart("dashboardPieChart");
    if (existingPie) existingPie.destroy();

    // 1. Criteria Bar Chart
    const ctxBar = document.getElementById("dashboardBarChart");
    if (ctxBar && results.value) {
      new Chart(ctxBar, {
        type: "bar",
        data: {
          labels: results.value.category_results.map((c) => c.category_name),
          datasets: [
            {
              label: "Average Rating",
              data: results.value.category_results.map((c) => Number(c.average_rating).toFixed(2)),
              backgroundColor: "#1a56db",
              borderRadius: 8,
              barThickness: 30,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { max: 5, beginAtZero: true } },
        },
      });
    }

    // 2. Sentiment Pie Chart
    const ctxPie = document.getElementById("dashboardPieChart");
    if (ctxPie && aiInsights.value) {
      new Chart(ctxPie, {
        type: "doughnut",
        data: {
          labels: ["Positive", "Neutral", "Negative"],
          datasets: [
            {
              data: [
                aiInsights.value.sentiment.positive,
                aiInsights.value.sentiment.neutral,
                aiInsights.value.sentiment.negative,
              ],
              backgroundColor: ["#0e9f6e", "#ffc107", "#f05252"],
              borderWidth: 0,
              hoverOffset: 10,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "70%",
          plugins: { legend: { position: "bottom", labels: { usePointStyle: true, padding: 20 } } },
        },
      });
    }
  });
}

function getRatingStatus(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Strong";
  if (rating >= 2.5) return "Average";
  return "Needs Improvement";
}

function getRatingBadge(rating) {
  if (rating >= 4.5) return "bg-success text-white shadow-sm";
  if (rating >= 3.5) return "bg-info text-white shadow-sm";
  if (rating >= 2.5) return "bg-warning text-dark shadow-sm";
  return "bg-danger text-white shadow-sm";
}

function getRatingColor(rating) {
  if (rating >= 3.5) return "bg-success";
  if (rating >= 2.5) return "bg-warning";
  return "bg-danger";
}

function drawChart() {
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);
    const existing = Chart.getChart("reportChart");
    if (existing) existing.destroy();

    const ctx = document.getElementById("reportChart");
    if (!ctx || !results.value) return;

    const textColor = "#374151";
    const gridColor = "rgba(0, 0, 0, 0.1)";

    new Chart(ctx, {
      type: "line",
      data: {
        labels: results.value.category_results.map((c) => c.category_name),
        datasets: [
          {
            label: "Average Rating",
            data: results.value.category_results.map((c) => Number(c.average_rating).toFixed(2)),
            borderColor: "#1a56db",
            backgroundColor: (context) => {
              const chart = context.chart;
              const { ctx, chartArea } = chart;
              if (!chartArea) return null;
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "rgba(26, 86, 219, 0.2)");
              gradient.addColorStop(1, "rgba(26, 86, 219, 0)");
              return gradient;
            },
            borderWidth: 3,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: "#1a56db",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            max: 5,
            grid: { color: gridColor },
            ticks: { color: textColor, stepSize: 1 },
          },
          x: {
            grid: { display: false },
            ticks: {
              color: textColor,
              maxRotation: window.innerWidth < 768 ? 45 : 0,
              minRotation: window.innerWidth < 768 ? 25 : 0,
              autoSkip: window.innerWidth < 768,
              maxTicksLimit: window.innerWidth < 768 ? 6 : undefined,
            },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#ffffff",
            titleColor: "#1e293b",
            bodyColor: "#475569",
            padding: 12,
            borderColor: "rgba(0, 0, 0, 0.1)",
            borderWidth: 1,
            displayColors: false,
          },
        },
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  });
}

function badgeClass(interpretation) {
  const map = {
    Excellent: "badge-excellent",
    "Very Good": "badge-verygood",
    Good: "badge-good",
    Fair: "badge-fair",
    Poor: "badge-poor",
  };
  return map[interpretation] || "";
}
</script>

<style scoped>
.search-pill-container {
  display: flex;
  align-items: center;
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 50px;
  padding: 0.5rem 1.25rem;
  transition: all 0.3s ease;
}

[data-theme="dark"] .search-pill-container {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.search-pill-container:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.1);
}

.search-icon {
  color: #3b82f6; /* Vibrant Blue from reference */
  margin-right: 0.75rem;
  font-size: 1rem;
  font-weight: 900;
}

[data-theme="dark"] .search-icon {
  color: #60a5fa;
}

.search-input-field {
  background: transparent;
  border: none;
  color: #1e293b;
  width: 100%;
  font-size: 0.95rem;
  font-weight: 400;
  outline: none;
}

[data-theme="dark"] .search-input-field {
  color: white;
}

.search-input-field::placeholder {
  color: #94a3b8;
}

[data-theme="dark"] .search-input-field::placeholder {
  color: rgba(255, 255, 255, 0.3);
}

.refresh-pill-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.1);
  background: rgba(0, 0, 0, 0.05);
  color: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  cursor: pointer;
}

[data-theme="dark"] .refresh-pill-btn {
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.6);
}

.refresh-pill-btn:hover {
  background: rgba(26, 86, 219, 0.1);
  color: var(--primary);
  border-color: var(--primary);
  transform: rotate(-30deg);
}

.ai-fab-container {
  position: fixed;
  bottom: 2.5rem;
  right: 2.5rem;
  z-index: 2100;
}

.ai-fab {
  width: 65px;
  height: 65px;
  border-radius: 50%;
  background: var(--primary);
  color: white;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  box-shadow: 0 10px 30px -5px rgba(26, 86, 219, 0.5);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  cursor: pointer;
  position: relative;
}

.ai-fab:hover {
  transform: scale(1.1) rotate(5deg);
  background: #1e40af;
}

.ai-fab.active {
  transform: rotate(90deg);
  background: #374151;
  box-shadow: none;
}

.ai-fab.loading i {
  animation: spin 2s linear infinite;
}

.ai-badge {
  position: absolute;
  top: -45px;
  right: -10px;
  background: #f87171;
  color: white;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.4rem 0.8rem;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(248, 113, 113, 0.4);
  white-space: nowrap;
  animation: floatBubble 3s ease-in-out infinite;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-badge::after {
  content: "";
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  border-width: 6px 6px 0;
  border-style: solid;
  border-color: #f87171 transparent transparent;
}

@keyframes floatBubble {
  0%,
  100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-5px) scale(1.05);
  }
}

.ai-top-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(12px);
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 1.5rem;
}

.ai-animated-border-wrapper {
  position: relative;
  width: 100%;
  max-width: 1200px;
  height: 90vh;
  max-height: 90vh;
  border-radius: 8px;
  overflow: hidden;
  padding: 3px;
  display: flex;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: rgba(15, 23, 42, 0.1);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.ai-animated-border-wrapper::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 150%;
  height: 150%;
  transform: translate(-50%, -50%);
  background: conic-gradient(from 0deg, transparent 20%, #1a56db, #3b82f6, #8b5cf6, transparent 80%);
  animation: rotateBorder 4s linear infinite;
  z-index: 0;
}

@keyframes rotateBorder {
  from {
    transform: translate(-50%, -50%) rotate(0deg);
  }
  to {
    transform: translate(-50%, -50%) rotate(360deg);
  }
}

.ai-animated-border-wrapper.is-full-screen {
  max-width: 98vw;
  height: 96vh;
  max-height: 96vh;
  border-radius: 8px;
}

@keyframes aiBorderAnimation {
  0% {
    background-position: 0% 0;
  }
  100% {
    background-position: 300% 0;
  }
}

.ai-overlay-content {
  background: var(--bg-card);
  border-radius: calc(8px - 3px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
}

.ai-animated-border-wrapper.is-full-screen .ai-overlay-content {
  border-radius: calc(8px - 3px);
}

.ai-gradient-header {
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-color);
}

.ai-overlay-body {
  overflow-y: auto;
  flex: 1;
  background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-card) 100%);
  position: relative;
}

.ai-overlay-body::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top right, rgba(139, 92, 246, 0.03), transparent 400px),
    radial-gradient(circle at bottom left, rgba(26, 86, 219, 0.03), transparent 400px);
  pointer-events: none;
}

.ai-overlay-body::-webkit-scrollbar {
  width: 8px;
}
.ai-overlay-body::-webkit-scrollbar-track {
  background: transparent;
}
.ai-overlay-body::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
}

.drop-down-enter-active,
.drop-down-leave-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.drop-down-enter-from,
.drop-down-leave-to {
  transform: translateY(-30px) scale(0.95);
  opacity: 0;
}

.suggestion-scroll::-webkit-scrollbar {
  width: 6px;
}
.suggestion-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.suggestion-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
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

.fw-800 {
  font-weight: 800;
}

.metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.dashboard-grid {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.tabs-premium-container {
  display: inline-flex;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  padding: 6px;
  border-radius: 50px;
  gap: 8px;
}

.tab-btn-premium {
  border: none;
  background: transparent;
  padding: 10px 24px;
  border-radius: 50px;
  font-weight: 700;
  color: var(--text-muted);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 0.95rem;
  display: flex;
  align-items: center;
}

.tab-btn-premium:hover {
  color: var(--primary);
  background: rgba(10, 39, 138, 0.05);
}

.tab-btn-premium.active {
  background: #0a278a;
  color: white;
  box-shadow: 0 4px 12px rgba(10, 39, 138, 0.25);
}

/* ── Mobile-friendly report layout (faculty / staff) ── */
.reports-summary-row {
  margin-left: 0;
  margin-right: 0;
}

.report-final-score {
  font-size: clamp(2.25rem, 10vw, 3.5rem);
  line-height: 1.1;
}

.report-chart-body {
  position: relative;
  min-height: 200px;
  height: 220px;
}

@media (min-width: 768px) {
  .report-chart-body {
    height: 200px;
    min-height: 180px;
  }
}

.reports-mobile-categories {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.reports-category-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1rem;
}

.reports-category-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.reports-freq-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}

.reports-freq-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: 0.35rem 0.2rem;
  background: var(--bg-light);
  border-radius: 6px;
}

.reports-freq-val {
  font-weight: 700;
  font-size: 0.95rem;
}

.reports-freq-lbl {
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}

.reports-category-card-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--border-color);
}

.reports-table-scroll {
  -webkit-overflow-scrolling: touch;
}

@media print {
  .reports-mobile-categories {
    display: none !important;
  }

  .reports-table-card.d-none.d-md-block {
    display: block !important;
  }
}

@media (max-width: 767.98px) {
  .ai-fab-container {
    bottom: 1.25rem;
    right: 1.25rem;
  }

  .ai-fab {
    width: 56px;
    height: 56px;
    font-size: 1.5rem;
  }

  .ai-top-overlay {
    padding: 0.5rem;
    align-items: flex-end;
  }

  .ai-animated-border-wrapper {
    height: 95vh;
    max-height: 95vh;
    border-radius: 12px 12px 0 0;
  }

  .ai-overlay-header {
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 1rem !important;
  }

  .ai-overlay-header h5 {
    font-size: 0.95rem;
  }

  .ai-overlay-body {
    padding: 1rem !important;
  }
}
</style>
