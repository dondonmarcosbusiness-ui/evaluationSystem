<template>
  <div class="d-flex">
    <Sidebar class="no-print" />
    <div class="main-wrapper w-100">
      <Navbar class="no-print"><template #title>Detailed SET Report</template></Navbar>

      <div class="content-area">
        <!-- Print Only Header -->
        <div class="print-only report-header mb-3">
          <div class="report-masthead">
            <div class="masthead-logos">
              <img :src="`${basePath}/assets/img/bagong_pilipinas_logo.png`" alt="Bagong Pilipinas" @error="(e) => e.target.style.display='none'" />
              <img :src="`${basePath}/assets/img/neust_logo.webp`" alt="NEUST Logo" />
            </div>
            <div class="masthead-text">
              <p class="mb-0 masthead-republic">Republic of the Philippines</p>
              <p class="mb-0 masthead-university">NUEVA ECIJA UNIVERSITY OF SCIENCE AND TECHNOLOGY</p>
              <p class="mb-0 masthead-campus">Carranglan Off-Campus</p>
            </div>
            <div class="masthead-right">
              <img :src="`${basePath}/assets/img/cict_logo.png`" alt="CICT Logo" @error="(e) => e.target.style.display='none'" />
            </div>
          </div>

          <div class="masthead-rule" aria-hidden="true"><span class="rule-gold"></span><span class="rule-navy"></span></div>

          <h5 class="print-report-title text-center fw-bold mt-3">NEUST EVALUATION REPORT</h5>

          <div class="print-meta-section mt-2 mb-3">
            <p class="print-meta-heading mb-2">{{ printInfoSectionTitle }}</p>
            <table class="print-meta-table">
              <tbody>
                <tr>
                  <th>{{ printEvaluateeFieldLabel }}</th>
                  <td>{{ printEvaluateeValue }}</td>
                </tr>
                <tr v-if="evaluateeType === 'faculty'">
                  <th>Department</th>
                  <td>{{ printDepartmentValue }}</td>
                </tr>
                <tr>
                  <th>Semester / Academic Year</th>
                  <td>{{ systemSettings?.active_semester }} / {{ systemSettings?.active_academic_year }}</td>
                </tr>
                <tr>
                  <th>Date Generated</th>
                  <td>{{ new Date().toLocaleDateString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Admin Action Bar -->
        <div
          class="card mb-4 no-print shadow-none mx-3 mx-md-0"
          v-if="$can('view_reports') && user.role !== 'faculty'"
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
                placeholder="Search faculty..."
              />
            </div>

            <!-- Dept Filter (faculty only) -->
            <div v-if="evaluateeType === 'faculty'" style="width: 200px">
              <CustomSelect
                v-model="selectedDepartmentFilter"
                :options="departmentOptions"
                placeholder="All Departments"
                @change="handleDepartmentChange"
              />
            </div>

            <!-- Year Level Filter -->
            <div style="width: 170px">
              <CustomSelect
                v-model="selectedYearFilter"
                :options="yearLevelOptions"
                placeholder="All Year Levels"
                @change="loadResults"
              />
            </div>

            <!-- Main Faculty Selector -->
            <div style="width: 350px">
              <CustomSelect
                v-model="selectedFacultyId"
                :options="facultyOptions"
                placeholder="Select Faculty:"
                @change="loadResults"
              />
            </div>

            <!-- Reset Button -->
            <button class="refresh-pill-btn" @click="resetFilters" title="Reset Filters">
              <i class="fas fa-undo" :class="{ 'fa-spin': loading }"></i>
            </button>

            <div class="flex-grow-1"></div>

            <button
              class="btn btn-primary d-flex align-items-center justify-content-center text-white"
              @click="printReport"
              :disabled="!detailedResults || loading"
              title="Print Report"
              style="background-color: #191970; border-color: #191970; border-radius: 8px; width: 42px; height: 42px; flex-shrink: 0"
            >
              <i class="fas fa-print text-white"></i>
            </button>
          </div>
        </div>

        <!-- Faculty View Header (Non-admin) -->
        <div class="card mb-4 no-print shadow-none set-report-header mx-0" v-if="user.role === 'faculty'">
          <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3">
            <div class="min-w-0">
              <h5 class="mb-0 fw-bold">My Detailed SET Report</h5>
              <p class="mb-0 text-muted small text-truncate">Performance summary for {{ user.name }}</p>
            </div>
            <button
              class="btn btn-primary btn-sm fw-bold flex-shrink-0 align-self-stretch align-self-sm-auto"
              @click="printReport"
              :disabled="!detailedResults || loading"
            >
              <i class="fas fa-print me-2"></i>
              Print
            </button>
          </div>
        </div>

        <!-- Results -->
        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="table" :rows="6" :cols="7" />
        </div>

        <div v-else-if="detailedResults">
          <!-- Stat Cards -->
          <div class="row g-3 mb-4 no-print set-report-stats">
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon">
                    <i class="fas fa-star"></i>
                  </div>
                  <div>
                    <div class="stat-label">Overall SET Rating</div>
                    <div class="stat-value">
                      {{ detailedResults.overall_set_rating.toFixed(2) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon">
                    <i class="fas fa-users"></i>
                  </div>
                  <div>
                    <div class="stat-label">Total Respondents</div>
                    <div class="stat-value">
                      {{ detailedResults.total_students }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                  </div>
                  <div>
                    <div class="stat-label">Weighted Score</div>
                    <div class="stat-value">
                      {{ Number(detailedResults.total_weighted_score).toLocaleString() }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Print-only KPI band (screen uses the stat cards above) -->
          <div class="print-only exec-kpi-band">
            <div class="exec-kpi"><span class="kpi-label">Faculty</span><span class="kpi-value">{{ printEvaluateeValue }}</span></div>
            <div class="exec-kpi"><span class="kpi-label">Respondents</span><span class="kpi-value">{{ detailedResults.total_students }}</span></div>
            <div class="exec-kpi"><span class="kpi-label">Overall SET</span><span class="kpi-value">{{ detailedResults.overall_set_rating.toFixed(2) }}</span></div>
            <div class="exec-kpi"><span class="kpi-label">Weighted Score</span><span class="kpi-value">{{ Number(detailedResults.total_weighted_score).toLocaleString() }}</span></div>
            <div class="exec-kpi"><span class="kpi-label">Rating</span><span class="kpi-value">{{ getRatingStatus(detailedResults.overall_set_rating) }}</span></div>
          </div>

          <!-- Executive charts: respondents donut + SET bars, free-floating side by side -->
          <div class="exec-charts-section" v-if="execChartReady">
            <!-- Screen: responsive, full data, scrolls when many subjects -->
            <div class="no-print mb-3">
              <h6 class="mb-0 fw-bold">
                <i class="fas fa-chart-pie me-2 text-primary"></i>
                Performance Overview
              </h6>
            </div>
            <div class="exec-charts no-print">
              <div class="exec-chart-box">
                <div class="exec-chart-title">{{ execDonutData.title }}</div>
                <div class="exec-chart-wrap exec-chart-wrap-donut"><canvas id="execDonut"></canvas></div>
              </div>
              <div class="exec-chart-box exec-chart-box-wide">
                <div class="exec-chart-title">Average SET Rating by Course</div>
                <div class="exec-chart-scroll">
                  <div class="exec-chart-wrap exec-chart-wrap-bar" :style="execBarScrollStyle"><canvas id="execBar"></canvas></div>
                </div>
              </div>
            </div>
            <!-- Print: fixed bitmap, aggregated Top N + Others, deterministic -->
            <div class="print-only exec-section-heading">Performance Overview</div>
            <div class="print-only exec-charts-print">
              <div class="exec-chart-box">
                <div class="exec-chart-title">{{ execDonutPrintData.title }}</div>
                <div class="exec-chart-wrap-print"><canvas id="execDonutPrint" width="290" height="230"></canvas></div>
              </div>
              <div class="exec-chart-box">
                <div class="exec-chart-title">Average SET Rating by Course</div>
                <div class="exec-chart-wrap-print"><canvas id="execBarPrint" width="390" height="230"></canvas></div>
              </div>
            </div>
            <div class="print-only exec-agg-note" v-if="execPrintTruncated">
              Charts show top subjects — see tables below for the complete list.
            </div>
          </div>

          <!-- No Data Notice -->
          <div
            class="alert alert-info border-0 shadow-sm text-center py-4 mb-4"
            v-if="filteredCourseSummaries.length === 0"
          >
            <i class="fas fa-info-circle fa-2x mb-2 text-primary opacity-75"></i>
            <h6 class="fw-bold mb-0">No Evaluation Data</h6>
            <p class="mb-0 small">
              There are no evaluation ratings recorded for this faculty member yet.
            </p>
          </div>

          <!-- Main Data Table per Course -->
          <template v-if="evaluateeType === 'faculty'">
            <div
              class="mb-4 set-report-course-block"
              v-for="(summary, index) in filteredCourseSummaries"
              :key="index"
            >
              <!-- Mobile course rows -->
              <div class="d-md-none set-report-mobile-list">
                <h6 class="fw-bold mb-3 px-1">
                  <i class="fas fa-table me-2 text-primary"></i>
                  {{ summary.course_name }}
                </h6>
                <div
                  v-for="(row, rIndex) in summary.rows"
                  :key="'course-m-' + index + '-' + rIndex"
                  class="set-report-mobile-card"
                >
                  <div class="set-report-mobile-card-title">
                    <span class="badge bg-light text-dark border me-2">{{ rIndex + 1 }}</span>
                    {{ row.course_code }}
                  </div>
                  <dl class="set-report-mobile-dl">
                    <div><dt>Year/Section</dt><dd>{{ row.year_section }}</dd></div>
                    <div><dt>Students</dt><dd class="fw-bold">{{ row.no_of_students }}</dd></div>
                    <div><dt>Avg SET</dt><dd>{{ Number(row.average_set_rating).toFixed(2) }}</dd></div>
                    <div><dt>Weighted</dt><dd class="fw-bold">{{ Number(row.weighted_set_score).toLocaleString() }}</dd></div>
                  </dl>
                </div>
                <div class="set-report-mobile-total">
                  <span class="fw-bold">Course Total</span>
                  <span>
                    <span class="text-muted small d-block">Avg {{ summary.course_average_rating.toFixed(2) }}</span>
                    <span class="fw-bold">{{ Number(summary.course_total_weighted_score).toLocaleString() }}</span>
                  </span>
                </div>
                <div class="set-report-mobile-overall no-print">
                  <div>
                    <div class="fw-bold small text-uppercase">Overall Course SET Rating</div>
                    <div class="fs-4 fw-800">{{ summary.course_average_rating.toFixed(2) }}</div>
                  </div>
                  <span class="badge rounded-pill px-3 py-2" :class="getRatingBadge(summary.course_average_rating)">
                    {{ getRatingStatus(summary.course_average_rating) }}
                  </span>
                </div>
              </div>

              <!-- Desktop table -->
              <div class="card shadow-none overflow-hidden report-table-card d-none d-md-block">
              <div class="card-header bg-white py-3 no-print">
                <h6 class="mb-0 fw-bold">
                  <i class="fas fa-table me-2 text-primary"></i>
                  Summary of Average SET Rating -
                  {{ summary.course_name }}
                </h6>
              </div>
              <div class="card-body p-0 print-table-block mx-2">
                <div class="print-only print-table-title">
                  Summary of Average SET Rating — <span class="text-uppercase">{{ summary.course_name }}</span>
                </div>
                <div class="table-responsive set-report-table-scroll print-table-container" @scroll="onTableScroll">
                  <table class="table table-bordered table-hover mb-0 align-middle text-center print-table">
                    <thead :class="{ 'glass-header': tableScrolled }" class="small">
                      <tr class="print-header-row">
                        <th class="print-col-header py-3 fw-normal text-capitalize">Seq</th>
                        <th class="print-col-header py-3 fw-normal text-capitalize">Course Code</th>
                        <th class="print-col-header py-3 fw-normal text-capitalize">Year/Section</th>
                        <th class="print-col-header py-3 fw-normal text-capitalize">No. Of<br class="print-only"/> Students</th>
                        <th class="print-col-header py-3 fw-normal text-capitalize">Average<br class="print-only"/> SET Rating</th>
                        <th class="print-col-header py-3 fw-normal text-capitalize">Weighted<br class="print-only"/> SET Score</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, rIndex) in summary.rows" :key="rIndex">
                        <td class="print-text-black fw-bold">
                          {{ rIndex + 1 }}
                        </td>
                        <td class="print-text-black fw-normal">
                          {{ row.course_code }}
                        </td>
                        <td class="print-text-black fw-normal">
                          {{ row.year_section }}
                        </td>
                        <td class="print-text-black fw-bold">
                          {{ row.no_of_students }}
                        </td>
                        <td class="print-text-black fw-normal">
                          {{ Number(row.average_set_rating).toFixed(2) }}
                        </td>
                        <td class="print-text-black fw-bold">
                          {{ Number(row.weighted_set_score).toLocaleString() }}
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="3" class="text-center print-text-black fw-bold">
                          TOTAL
                        </td>
                        <td class="print-text-black fw-bold">
                          {{ summary.course_total_students }}
                        </td>
                        <td class="print-text-black fw-bold">
                          {{ summary.course_average_rating.toFixed(2) }}
                        </td>
                        <td class="print-text-black fw-bold">
                          {{ Number(summary.course_total_weighted_score).toLocaleString() }}
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Overall Performance Footer -->
                <div class="p-3 bg-light border-top d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 no-print set-report-overall-footer">
                  <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3">
                      <i class="fas fa-chart-line text-primary"></i>
                    </div>
                    <div>
                      <div class="fw-bold small text-dark text-uppercase ls-1">Overall Course SET Rating</div>
                      <div class="text-muted x-small">Combined performance across all sections</div>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                      <div class="fs-4 fw-800 text-dark lh-1">{{ summary.course_average_rating.toFixed(2) }}</div>
                    </div>
                    <span class="badge rounded-pill px-3 py-2 fw-bold text-uppercase small ls-1" :class="getRatingBadge(summary.course_average_rating)">
                      {{ getRatingStatus(summary.course_average_rating) }}
                    </span>
                  </div>
                </div>

                <!-- Print Only Overall Summary -->
                <div class="print-only print-table-summary">
                  Overall SET Rating for {{ summary.course_name }}:
                  <strong>{{ summary.course_average_rating.toFixed(2) }}</strong>
                  ({{ getRatingStatus(summary.course_average_rating) }})
                </div>
              </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Print-only legend, signatories and document note -->
        <div class="print-only report-print-footer" v-if="detailedResults && filteredCourseSummaries.length > 0">
          <div class="legend-block">
            <span class="legend-title">Rating Scale:</span>
            <span>4.50&ndash;5.00 Excellent &middot; 3.50&ndash;4.49 Very Good &middot; 2.50&ndash;3.49 Good &middot; 1.50&ndash;2.49 Fair &middot; 1.00&ndash;1.49 Poor</span>
          </div>
          <div class="signatory-row">
            <div class="signatory">
              <span class="sig-label">Prepared by:</span>
              <span class="sig-line" aria-hidden="true"></span>
              <span class="sig-name">{{ user.name }}</span>
            </div>
            <div class="signatory">
              <span class="sig-label">Noted by:</span>
              <span class="sig-line" aria-hidden="true"></span>
              <span class="sig-name">&nbsp;</span>
            </div>
          </div>
          <p class="system-note">System-generated report &middot; NEUST Carranglan Off-Campus &middot; {{ new Date().toLocaleString() }}</p>
        </div>

        <div
          v-else-if="!loading && selectedFacultyId === '' && $can('view_reports') && user.role !== 'faculty'"
          class="card shadow-none mx-3 mx-md-0"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-file-invoice fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">No Faculty Selected</h5>
            <p class="mb-0">Please select a faculty member to generate their detailed performance report.</p>
          </div>
        </div>

        <div
          v-else-if="!loading && !detailedResults && (user.role === 'faculty')"
          class="card shadow-none"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-chart-bar fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">No Report Data Available</h5>
            <p class="mb-0 small">
              Your faculty profile could not be loaded, or no evaluations have been submitted for you in the active semester yet.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import { courseDepartments } from "../helpers/academic.js";

const can = inject("can");
const route = useRoute();

const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const facultyList = ref([]);
const allCoursesList = ref([]);
const selectedFacultyId = ref("all");
const selectedCourseFilter = ref("All");
const detailedResults = ref(null);
const loading = ref(false);
const tableScrolled = ref(false);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

// Filters
const searchQuery = ref("");
const selectedDepartmentFilter = ref("all");
const selectedYearFilter = ref("all");
const systemSettings = ref(null);

const yearLevelOptions = [
  { label: "All Year Levels", value: "all" },
  { label: "1st Year", value: "1st" },
  { label: "2nd Year", value: "2nd" },
  { label: "3rd Year", value: "3rd" },
  { label: "4th Year", value: "4th" },
  { label: "Irregular", value: "Irregular" },
];

const safeFacultyList = computed(() => {
  return (facultyList.value || []).filter((f) => f && f.id);
});

const safeCoursesList = computed(() => {
  return (allCoursesList.value || []).filter((c) => c && c.id);
});

const filteredCourseSummaries = computed(() => {
  if (!detailedResults.value || !detailedResults.value.course_summaries) return [];
  if (selectedCourseFilter.value === "All") {
    return detailedResults.value.course_summaries;
  }
  return detailedResults.value.course_summaries.filter((s) => s.course_name === selectedCourseFilter.value);
});

// Executive charts: everything derives from the already-fetched summaries.
const execChartReady = computed(() => filteredCourseSummaries.value.length > 0);

// Print aggregation caps (paper can't scroll): Top N + "Others".
const PRINT_BAR_TOP_N = 12;
const PRINT_DONUT_TOP_N = 8;

// Flat subject rows shared by screen + print datasets.
const execSubjectRows = computed(() => {
  const list = filteredCourseSummaries.value;
  if (list.length > 1) {
    return list.map((s) => ({
      label: s.course_name,
      respondents: s.course_total_students,
      rating: Number(s.course_average_rating),
    }));
  }
  const rows = list[0]?.rows || [];
  return rows.map((r) => ({
    label: `${r.course_code} · ${r.year_section}`,
    respondents: r.no_of_students,
    rating: Number(r.average_set_rating),
  }));
});

const execSubjectCount = computed(() => execSubjectRows.value.length);
const execPrintTruncated = computed(() => execSubjectCount.value > PRINT_DONUT_TOP_N);

// Short axis labels (full names stay in tooltips / tables).
function shortChartLabel(label) {
  const text = String(label ?? "");
  const max = 22;
  if (text.length <= max) return text;
  const parts = text.split(" · ");
  const code = parts[0].split(",")[0].trim();
  const tail = parts[1] ? ` · ${parts[1]}` : "";
  const s = `${code}${tail}`;
  return s.length <= max ? s : `${s.slice(0, max - 1)}…`;
}

function topNOthers(rows, n, valueOf) {
  const sorted = [...rows].sort((a, b) => b.respondents - a.respondents);
  if (sorted.length <= n) return { rows: sorted, aggregated: false };
  const top = sorted.slice(0, n);
  const rest = sorted.slice(n);
  const restRespondents = rest.reduce((s, r) => s + r.respondents, 0);
  const restWeighted = rest.reduce((s, r) => s + valueOf(r) * r.respondents, 0);
  top.push({
    label: `Others (${rest.length})`,
    respondents: restRespondents,
    __aggValue: restRespondents > 0 ? restWeighted / restRespondents : 0,
  });
  return { rows: top, aggregated: true };
}

const execDonutData = computed(() => {
  const list = filteredCourseSummaries.value;
  if (list.length > 1) {
    return {
      title: "Respondents by Course",
      labels: list.map((s) => s.course_name),
      data: list.map((s) => s.course_total_students),
    };
  }
  const rows = list[0]?.rows || [];
  return {
    title: "Respondents by Subject",
    labels: rows.map((r) => `${r.course_code} · ${r.year_section}`),
    data: rows.map((r) => r.no_of_students),
  };
});

const execBarData = computed(() => {
  const list = filteredCourseSummaries.value;
  if (list.length > 1) {
    return {
      labels: list.map((s) => s.course_name),
      data: list.map((s) => Number(s.course_average_rating)),
    };
  }
  const rows = list[0]?.rows || [];
  return {
    labels: rows.map((r) => `${r.course_code} · ${r.year_section}`),
    data: rows.map((r) => Number(r.average_set_rating)),
  };
});

// Print datasets: aggregated, pre-shortened labels, full names for tooltips.
const execDonutPrintData = computed(() => {
  const { rows } = topNOthers(execSubjectRows.value, PRINT_DONUT_TOP_N, (r) => r.rating);
  return {
    title: execDonutData.value.title,
    labels: rows.map((r) => shortChartLabel(r.label)),
    full: rows.map((r) => r.label),
    data: rows.map((r) => r.respondents),
  };
});

const execBarPrintData = computed(() => {
  const { rows } = topNOthers(execSubjectRows.value, PRINT_BAR_TOP_N, (r) => r.rating);
  return {
    labels: rows.map((r) => shortChartLabel(r.label)),
    full: rows.map((r) => r.label),
    data: rows.map((r) => (r.__aggValue ?? r.rating)),
  };
});

// Screen bar wrap grows so bars never squeeze when many subjects evaluate.
const execBarScrollStyle = computed(() => {
  const n = execBarData.value.labels.length;
  return n > 6 ? { minWidth: `${n * 72}px` } : {};
});

const execPalette = ["#191970", "#facd04", "#2f6fed", "#38bdf8", "#f59e0b", "#64748b", "#0ea5e9", "#a78bfa"];

function renderExecCharts() {
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    ["execDonut", "execBar", "execDonutPrint", "execBarPrint"].forEach((id) => {
      const existing = Chart.getChart(id);
      if (existing) existing.destroy();
    });
    if (!execChartReady.value) return;

    const screenTick = {
      color: "#111827",
      maxRotation: 45,
      minRotation: 0,
      autoSkip: false,
      callback: function (value) { return shortChartLabel(this.getLabelForValue(value)); },
    };
    const screenBarTooltip = {
      callbacks: {
        label: (ctx) => ` ${execBarData.value.labels[ctx.dataIndex]}: ${ctx.parsed.y}`,
        title: () => "Avg SET Rating",
      },
    };

    const donutCtx = document.getElementById("execDonut");
    if (donutCtx) {
      new Chart(donutCtx, {
        type: "doughnut",
        data: {
          labels: execDonutData.value.labels,
          datasets: [{
            data: execDonutData.value.data,
            backgroundColor: execDonutData.value.labels.map((_, i) => execPalette[i % execPalette.length]),
            borderWidth: 2,
            borderColor: "#ffffff",
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "58%",
          plugins: {
            legend: { position: "bottom", labels: { boxWidth: 12, boxHeight: 12, padding: 12, color: "#111827" } },
          },
        },
      });
    }

    const barCtx = document.getElementById("execBar");
    if (barCtx) {
      new Chart(barCtx, {
        type: "bar",
        data: {
          labels: execBarData.value.labels,
          datasets: [{
            label: "Avg SET Rating",
            data: execBarData.value.data,
            backgroundColor: "#191970",
            borderRadius: 6,
            categoryPercentage: 0.7,
            barPercentage: 0.85,
          }],
        },
        options: {
          indexAxis: "x",
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { min: 0, max: 100, ticks: { color: "#111827" }, grid: { color: "#e5e7eb" } },
            x: { ticks: screenTick, grid: { display: false } },
          },
          plugins: { legend: { display: false }, tooltip: screenBarTooltip },
        },
      });
    }

    // Print twins: fixed bitmap, no animation/resize timing hazards, aggregated.
    const printTick = {
      color: "#111827",
      maxRotation: 45,
      minRotation: 0,
      autoSkip: false,
    };

    const donutPrintCtx = document.getElementById("execDonutPrint");
    if (donutPrintCtx) {
      const d = execDonutPrintData.value;
      new Chart(donutPrintCtx, {
        type: "doughnut",
        data: {
          labels: d.labels,
          datasets: [{
            data: d.data,
            backgroundColor: d.labels.map((_, i) => execPalette[i % execPalette.length]),
            borderWidth: 2,
            borderColor: "#ffffff",
          }],
        },
        options: {
          responsive: false,
          animation: false,
          cutout: "58%",
          plugins: {
            legend: { position: "bottom", labels: { boxWidth: 12, boxHeight: 12, padding: 12, color: "#111827" } },
            tooltip: { callbacks: { label: (ctx) => ` ${d.full[ctx.dataIndex]}: ${ctx.parsed}` } },
          },
        },
      });
    }

    const barPrintCtx = document.getElementById("execBarPrint");
    if (barPrintCtx) {
      const b = execBarPrintData.value;
      new Chart(barPrintCtx, {
        type: "bar",
        data: {
          labels: b.labels,
          datasets: [{
            label: "Avg SET Rating",
            data: b.data,
            backgroundColor: "#191970",
            borderRadius: 6,
            categoryPercentage: 0.7,
            barPercentage: 0.85,
          }],
        },
        options: {
          indexAxis: "x",
          responsive: false,
          animation: false,
          scales: {
            y: { min: 0, max: 100, ticks: { color: "#111827" }, grid: { color: "#e5e7eb" } },
            x: { ticks: printTick, grid: { display: false } },
          },
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (ctx) => ` ${b.full[ctx.dataIndex]}: ${ctx.parsed.y}` } },
          },
        },
      });
    }
  }).catch((e) => console.error("Exec charts failed", e));
}

const filteredFacultyList = computed(() => {
  if (!facultyList.value) return [];
  
  let list = facultyList.value.filter(f => f && f.id);

  if (evaluateeType.value === "faculty" && selectedDepartmentFilter.value && selectedDepartmentFilter.value !== "all") {
    list = list.filter(f =>
      f.department && f.department.trim() === selectedDepartmentFilter.value.trim()
    );
  }

  const q = searchQuery.value ? searchQuery.value.trim().toLowerCase() : "";
  if (q) {
    list = list.filter(f => {
      const name = f.user?.name?.toLowerCase() || "";
      const dept = f.department?.toLowerCase() || "";
      return name.includes(q) || dept.includes(q);
    });
  }

  return list;
});

const evaluateeType = ref('faculty');

const printInfoSectionTitle = computed(() => "A. Faculty Information");

const printEvaluateeFieldLabel = computed(() => "Faculty");

const printEvaluateeValue = computed(() => {
  if (selectedFacultyId.value === "all") {
    return "All Faculty";
  }
  return detailedResults.value?.faculty_name || "N/A";
});

const printDepartmentValue = computed(() => {
  if (selectedFacultyId.value !== "all") {
    return detailedResults.value?.department || "N/A";
  }
  if (selectedDepartmentFilter.value !== "all") {
    return selectedDepartmentFilter.value;
  }
  return "All Departments";
});

const facultyOptions = computed(() => {
  const allLabel = "All Faculty";
  return [
    { label: allLabel, value: "all" },
    ...filteredFacultyList.value.map((f) => {
      return {
        label: `${f.user?.name} (${f.department || "N/A"})`,
        value: f.id,
      };
    }),
  ];
});

const departmentOptions = computed(() => {
  // Department filter options come from the Course List (single source of
  // truth) — never from faculty records, which can hold stale departments
  // whose courses were deleted.
  return [
    { label: "All Departments", value: "all" },
    ...courseDepartments(safeCoursesList.value).map(d => ({ label: d, value: d }))
  ];
});

const courseOptions = computed(() => [
  { label: "All Courses", value: "All" },
  ...safeCoursesList.value.map((c) => ({ label: c.name, value: c.name })),
]);

async function fetchEvaluateesList() {
  try {
    // Only faculty lists are supported now (staff reports removed)
    const res = await api.get("/faculty/all");
    facultyList.value = res.data;
  } catch (e) {
    console.error("Error fetching evaluatees list:", e);
  }
}

function getTypeFromRoute() {
  return "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("view_reports") && user.value.role !== "faculty") {
    const type = getTypeFromRoute();
    if (evaluateeType.value !== type) {
      evaluateeType.value = type;
      selectedFacultyId.value = "all";
      selectedDepartmentFilter.value = "all";
      detailedResults.value = null;
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
  try {
    const [coursesRes, settingsRes] = await Promise.all([
      api.get("/courses"),
      api.get("/settings")
    ]);
    allCoursesList.value = coursesRes.data;
    systemSettings.value = settingsRes.data;
  } catch (e) {
    console.error("Failed to load initial data", e);
  }

  if (can("view_reports") && user.value.role !== "faculty") {
    evaluateeType.value = getTypeFromRoute();
    await fetchEvaluateesList();
    await loadResults();
  } else if (user.value.role === "faculty") {
    evaluateeType.value = 'faculty';
    const res = await api.get("/faculty/all");
    const mine = (res.data || []).find((f) => f?.user_id === user.value?.id);
    if (mine) {
      selectedFacultyId.value = mine.id;
      await loadResults();
    }
  }
});

async function handleDepartmentChange() {
  if (selectedFacultyId.value !== 'all') {
    const currentFaculty = facultyList.value.find(f => f.id === selectedFacultyId.value);
    if (currentFaculty && currentFaculty.department !== selectedDepartmentFilter.value && selectedDepartmentFilter.value !== 'all') {
      selectedFacultyId.value = 'all';
    }
  }
  await loadResults();
}

async function loadResults() {
  if (!selectedFacultyId.value) return;
  loading.value = true;
  detailedResults.value = null;
  try {
    const params = { evaluatee_type: evaluateeType.value };
    if (evaluateeType.value === "faculty" && selectedDepartmentFilter.value && selectedDepartmentFilter.value !== "all") {
      params.department = selectedDepartmentFilter.value;
    }
    if (selectedYearFilter.value && selectedYearFilter.value !== "all") {
      params.year_level = selectedYearFilter.value;
    }
    const res = await api.get(`/reports/evaluatee/${selectedFacultyId.value}`, { params });
    detailedResults.value = res.data;
    await nextTick();
    renderExecCharts();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function resetFilters() {
  searchQuery.value = "";
  selectedDepartmentFilter.value = "all";
  selectedYearFilter.value = "all";
  selectedFacultyId.value = "all";
  await loadResults();
}

function printReport() {
  window.print();
}

function getRatingStatus(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Very Good";
  if (rating >= 2.5) return "Good";
  if (rating >= 1.5) return "Fair";
  return "Poor";
}

function getRatingBadge(rating) {
  if (rating >= 4.5) return "bg-success text-white shadow-sm";
  if (rating >= 3.5) return "bg-primary text-white shadow-sm";
  if (rating >= 2.5) return "bg-info text-white shadow-sm";
  if (rating >= 1.5) return "bg-warning text-dark shadow-sm";
  return "bg-danger text-white shadow-sm";
}
</script>

<style scoped>
@media screen {
  .print-only {
    display: none !important;
  }
}

@media print {
  .set-report-mobile-list {
    display: none !important;
  }

  .report-table-card.d-none.d-md-block {
    display: block !important;
  }

  @page {
    size: A4 portrait;
    margin: 12mm 10mm 14mm;
  }
  .content-area {
    --set-report-print-blue: #191970;
    --set-report-print-gold: #facd04;
    --set-report-print-line: #c7cde0;
    --set-report-print-zebra: #f2f4fa;
    --set-report-print-tint: #e9edf7;
    padding: 0 !important;
    margin-top: 0 !important;
  }
  .main-wrapper {
    background: white !important;
  }
  .no-print {
    display: none !important;
  }
  .print-only {
    display: block !important;
  }
  tr.print-only {
    display: table-row !important;
  }

  .report-header {
    margin-top: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
  }

  /* Official masthead: balanced grid keeps the institution name optically centered */
  .report-masthead {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    width: 100%;
  }
  .masthead-logos {
    display: flex;
    align-items: center;
    gap: 6px;
    justify-self: start;
  }
  .masthead-logos img {
    object-fit: contain;
  }
  .masthead-logos img:first-child {
    width: 68px;
    height: 68px;
  }
  .masthead-logos img:last-child {
    width: 62px;
    height: 62px;
  }
  .masthead-text {
    text-align: center;
    line-height: 1.25;
    justify-self: center;
  }
  .masthead-republic,
  .masthead-campus {
    font-family: 'Times New Roman', Times, serif !important;
    font-size: 11pt !important;
    color: #000 !important;
  }
  .masthead-university {
    font-family: 'Times New Roman', Times, serif !important;
    font-size: 12pt !important;
    font-weight: 700 !important;
    color: #191970 !important;
    letter-spacing: 0.02em;
    white-space: nowrap !important;
  }
  .masthead-right {
    justify-self: end;
  }
  .masthead-right img {
    width: 68px;
    height: 68px;
    object-fit: contain;
  }
  .masthead-rule {
    margin-top: 6px;
  }
  .masthead-rule .rule-gold {
    display: block;
    height: 3px;
    background-color: var(--set-report-print-gold, #facd04) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .masthead-rule .rule-navy {
    display: block;
    height: 9px;
    background-color: var(--set-report-print-blue, #191970) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .print-report-title {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 14pt !important;
    letter-spacing: 0.08em !important;
    color: #000 !important;
  }

  .print-meta-section {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10pt !important;
    color: #000 !important;
    margin-bottom: 1.25rem !important;
  }

  .print-meta-heading {
    font-weight: 700 !important;
    color: #000 !important;
    font-size: 11pt !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #000 !important;
    padding: 0 0 0.35rem 0;
    margin-bottom: 0.6rem !important;
  }

  .print-meta-table {
    width: 100%;
    border-collapse: collapse !important;
    font-size: 10pt !important;
  }
  .print-meta-table th,
  .print-meta-table td {
    border: 1px solid #a8afc2 !important;
    padding: 6px 10px !important;
    text-align: left;
    vertical-align: middle;
  }
  .print-meta-table th {
    width: 38%;
    font-weight: 600 !important;
    color: #000 !important;
    background: none !important;
  }
  .print-meta-table td {
    font-weight: 700 !important;
    color: #000 !important;
    background: none !important;
  }

  .print-table-block {
    margin-top: 0.75rem;
  }

  /* Clean monochrome section title: no color band */
  .print-table-title {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10.5pt !important;
    font-weight: 700 !important;
    text-align: center !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #000 !important;
    background: none !important;
    padding: 0 0 0.45rem 0 !important;
    border: none !important;
    border-bottom: 1px solid #000 !important;
    margin: 0 0 0.55rem 0 !important;
  }

  .print-table-container {
    border: none !important;
  }

  .print-table-summary {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10pt !important;
    font-weight: 600 !important;
    text-align: center !important;
    color: #000 !important;
    background: none !important;
    border: none !important;
    padding: 0.6rem 0 0 0 !important;
    margin: 0 !important;
  }

  .print-table-summary strong {
    font-weight: 800;
    font-size: 11pt;
    color: #000 !important;
  }

  .print-table {
    border-collapse: collapse !important;
    width: 100% !important;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10pt !important;
    font-variant-numeric: tabular-nums;
    border: none !important;
    margin: 0 !important;
  }

  .print-table thead {
    display: table-header-group;
  }
  .print-table tfoot {
    display: table-footer-group;
  }
  .print-table tr {
    page-break-inside: avoid;
  }

  /* Pencil-line grid: thin gray rules, crisp black header/tfoot rules */
  .print-table thead th {
    border: 1px solid #a8afc2 !important;
    border-bottom: 1.5px solid #000 !important;
    background: none !important;
    color: #000 !important;
    padding: 7px 8px !important;
    vertical-align: middle !important;
    font-weight: 700 !important;
    font-size: 8.5pt !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.35;
  }

  .print-table.table > :not(caption) > thead > tr > th,
  .print-table.table > :not(caption) > thead > tr > td {
    border-width: 1px !important;
    border-style: solid !important;
    border-color: #a8afc2 !important;
  }

  .print-table tbody td {
    border: 1px solid #a8afc2 !important;
    padding: 7px 8px !important;
    vertical-align: middle !important;
    color: #000 !important;
    font-weight: 400 !important;
    background: none !important;
  }

  .print-table tfoot td {
    border: 1px solid #a8afc2 !important;
    border-top: 1.5px solid #000 !important;
    background: none !important;
    color: #000 !important;
    padding: 8px !important;
    vertical-align: middle !important;
    font-weight: 700 !important;
    font-size: 10pt !important;
  }

  .print-text-black,
  .print-text-gray {
    color: #000 !important;
  }

  .print-fw-normal {
    font-weight: 400 !important;
  }

  .print-fw-bold {
    font-weight: 700 !important;
  }

  .report-table-card {
    border: none !important;
    box-shadow: none !important;
    margin-bottom: 2rem !important;
    border-radius: 0 !important;
    page-break-inside: avoid;
  }

  .report-table-card .card-body.print-table-block {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  /* Rating legend, signatories and document note */
  .report-print-footer {
    margin-top: 1.1rem;
    font-family: Arial, Helvetica, sans-serif !important;
    color: #000 !important;
    page-break-inside: avoid;
  }
  .legend-block {
    font-size: 9pt !important;
    border: 1px solid #a8afc2 !important;
    background: none !important;
    padding: 8px 12px !important;
    margin-bottom: 1.4rem !important;
  }
  .legend-title {
    font-weight: 700 !important;
    margin-right: 6px;
  }
  .signatory-row {
    display: grid !important;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    margin: 0 8px 0.6rem 8px;
  }
  .signatory {
    display: flex;
    flex-direction: column;
  }
  .sig-label {
    font-size: 10pt !important;
    margin-bottom: 2.2rem;
  }
  .sig-line {
    display: block;
    border-bottom: 1px solid #000 !important;
    margin-bottom: 4px;
  }
  .sig-name {
    font-size: 10pt !important;
    font-weight: 700 !important;
    text-align: center;
  }
  .system-note {
    margin-top: 1rem !important;
    padding-top: 0.5rem;
    border-top: 1px solid var(--set-report-print-line, #c7cde0) !important;
    font-size: 8pt !important;
    color: #555 !important;
    text-align: center;
  }

  /* Executive summary (KPI band + charts) */
  .exec-kpi-band {
    display: grid !important;
    grid-template-columns: repeat(5, 1fr);
    border: 1px solid #a8afc2 !important;
    margin-bottom: 1rem !important;
    page-break-inside: avoid;
  }
  .exec-kpi {
    padding: 8px 6px !important;
    text-align: center;
    border-right: 1px solid #a8afc2 !important;
  }
  .exec-kpi:last-child {
    border-right: none !important;
  }
  .kpi-label {
    display: block;
    font-size: 7.5pt !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #444 !important;
    margin-bottom: 2px;
  }
  .kpi-value {
    display: block;
    font-size: 11.5pt !important;
    font-weight: 800 !important;
    color: #000 !important;
  }
  .exec-charts-section {
    margin-bottom: 1.25rem !important;
    page-break-inside: avoid;
  }
  .exec-section-heading {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10.5pt !important;
    font-weight: 700 !important;
    text-align: center !important;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #000 !important;
    margin: 0 0 0.6rem 0 !important;
  }
  .exec-charts-print {
    display: grid !important;
    grid-template-columns: 5fr 7fr !important;
    gap: 1.25rem;
    align-items: center;
    page-break-inside: avoid;
  }
  .exec-chart-wrap-print {
    line-height: 0;
  }
  .exec-chart-wrap-print canvas {
    max-width: 100%;
  }
  .exec-agg-note {
    font-size: 8.5pt !important;
    font-style: italic;
    color: #555 !important;
    text-align: center;
    margin-top: 0.4rem !important;
  }
  .exec-chart-title {
    color: #000 !important;
  }
  .exec-chart-wrap {
    height: 230px;
  }
  .exec-chart-wrap-donut {
    max-width: 300px;
    margin: 0 auto;
    width: 100%;
  }

  .table-responsive {
    overflow: visible !important;
  }
}

/* Theme-aware Search and Reset Styles */
.search-pill-container {
  display: flex;
  align-items: center;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0 16px;
  height: 40px;
  min-height: 40px;
  transition: all 0.3s ease;
}

[data-theme="dark"] .search-pill-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
}

.search-pill-container:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

.search-icon {
  color: #191970;
  margin-right: 0.75rem;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

[data-theme="dark"] .search-icon {
  color: #60a5fa;
}

.search-input-field {
  background: transparent;
  border: none;
  color: var(--text-main);
  width: 100%;
  font-size: 14px;
  font-weight: 500;
  outline: none;
}

[data-theme="dark"] .search-input-field {
  color: var(--text-main);
}

.search-input-field::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}

[data-theme="dark"] .search-input-field::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}

.refresh-pill-btn {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  cursor: pointer;
}

[data-theme="dark"] .refresh-pill-btn {
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
}

.refresh-pill-btn:hover {
  background: var(--primary);
  color: white !important;
  border-color: var(--primary);
}

.refresh-pill-btn:hover i {
  color: white !important;
}
.ls-1 {
  letter-spacing: 1px;
}

.fw-800 {
  font-weight: 800;
}

.set-report-header {
  background: var(--bg-card) !important;
  border: 1px solid var(--border-color);
}

.set-report-stats {
  margin-left: 0;
  margin-right: 0;
}

/* Sticky Table Header with Glassmorphism */
.set-report-table-scroll { max-height: 60vh; overflow-y: auto; border-radius: 8px; }

/* Executive summary charts: free-floating, horizontally aligned (screen + print) */
.exec-charts-section {
  margin-bottom: 1.5rem;
}
.exec-charts {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: 1.25rem;
  align-items: center;
}
.exec-chart-box {
  min-width: 0;
}
.exec-chart-title {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
  text-align: center;
}
.exec-chart-wrap-donut {
  position: relative;
  height: 230px;
  max-width: 320px;
  margin: 0 auto;
  width: 100%;
}
.exec-chart-wrap-bar {
  position: relative;
  height: 230px;
}
.exec-chart-scroll {
  overflow-x: auto;
}
@media screen and (max-width: 767.98px) {
  .exec-charts {
    grid-template-columns: 1fr;
  }
}
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

.set-report-mobile-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.set-report-mobile-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 0.85rem 1rem;
}

.set-report-mobile-card-title {
  font-weight: 700;
  font-size: 0.9rem;
  margin-bottom: 0.65rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border-color);
  line-height: 1.35;
}

.set-report-mobile-dl {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1rem;
  margin: 0;
}

.set-report-mobile-dl > div {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.set-report-mobile-dl dt {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  margin: 0;
}

.set-report-mobile-dl dd {
  margin: 0;
  font-size: 0.9rem;
}

.set-report-mobile-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.85rem 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  margin-top: 0.25rem;
}

.set-report-mobile-overall {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  margin-top: 0.5rem;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

.set-report-overall-footer .d-flex.align-items-center.gap-3 {
  flex-wrap: wrap;
}

@media (max-width: 575.98px) {
  .set-report-mobile-dl {
    grid-template-columns: 1fr;
  }

  .set-report-overall-footer .fs-4 {
    font-size: 1.5rem !important;
  }
}
</style>

<style>
/* Unscoped — overrides global .table thead th when printing */
@media print {
  table.print-table {
    border-collapse: collapse !important;
    --set-report-print-blue: #191970;
  }

  table.print-table .print-table-title,
  .print-table-title {
    background: none !important;
    border: none !important;
    border-bottom: 1px solid #000 !important;
    color: #000 !important;
  }

  table.print-table th.print-col-header,
  table.print-table thead th {
    border: 1px solid #a8afc2 !important;
    border-bottom: 1.5px solid #000 !important;
    color: #000 !important;
    background: none !important;
    font-weight: 700 !important;
  }

  table.print-table tbody td {
    border: 1px solid #a8afc2 !important;
    color: #000 !important;
    background: none !important;
  }

  table.print-table tfoot td {
    border: 1px solid #a8afc2 !important;
    border-top: 1.5px solid #000 !important;
    color: #000 !important;
    background: none !important;
  }

  .print-table-container {
    border: none !important;
  }

  .print-table-summary {
    border: none !important;
    background: none !important;
  }
}
</style>
