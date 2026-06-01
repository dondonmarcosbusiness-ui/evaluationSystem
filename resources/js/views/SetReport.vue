<template>
  <div class="d-flex">
    <Sidebar class="no-print" />
    <div class="main-wrapper w-100">
      <Navbar class="no-print"><template #title>Detailed SET Report</template></Navbar>

      <div class="content-area">
        <!-- Print Only Header -->
        <div class="print-only report-header mb-4 mt-2 mx-3 mx-md-0">
          <div class="header-grid d-flex align-items-center justify-content-center gap-2" style="width: 100%;">
            <div class="d-flex gap-1 justify-content-start">
              <img :src="`${basePath}/assets/img/bagong_pilipinas_logo.png`" alt="Bagong Pilipinas" style="width: 75px; height: 75px; object-fit: contain;" @error="(e) => e.target.style.display='none'" />
              <img :src="`${basePath}/assets/img/neust_logo.webp`" alt="NEUST Logo" style="width: 70px; height: 70px; object-fit: contain;" />
            </div>
            <div class="d-flex gap-1 align-items-center">
              <div style="border-left: 3px solid #facd04; height: 75px; -webkit-print-color-adjust: exact; print-color-adjust: exact;"></div>
              <div style="border-left: 3px solid #facd04; height: 75px; -webkit-print-color-adjust: exact; print-color-adjust: exact;"></div>
              <div style="border-left: 8px solid #0a278a; height: 75px; -webkit-print-color-adjust: exact; print-color-adjust: exact;"></div>
            </div>
            <div class="text-start lh-1">
              <p class="mb-0 text-dark" style="font-family: 'Times New Roman', Times, serif; font-size: 14px;">Republic of the Philippines</p>
              <p class="mb-0 fw-bold" style="font-family: 'Times New Roman', Times, serif; font-size: 16px; color: #5c7081;">NUEVA ECIJA UNIVERSITY OF SCIENCE AND TECHNOLOGY</p>
              <p class="mb-0 text-dark" style="font-family: 'Times New Roman', Times, serif; font-size: 14px; color: #5c7081;">Carranglan off-Campus</p>
            </div>
            <div></div> <!-- Spacer to maintain center -->
          </div>

          <div class="mt-1" style="background-color: #0a278a; height: 15px; -webkit-print-color-adjust: exact; print-color-adjust: exact; width: 100%;"></div>

          <h5 class="print-report-title text-center fw-bold mt-4">NEUST EVALUATION REPORT</h5>

          <div class="print-meta-section text-start mx-2 mt-2 mb-3">
            <p class="print-meta-heading mb-3">{{ printInfoSectionTitle }}</p>
            <div class="print-meta-row">
              <span class="print-meta-label">{{ printEvaluateeFieldLabel }}</span>
              <span class="print-meta-value">{{ printEvaluateeValue }}</span>
            </div>
            <div v-if="evaluateeType === 'faculty'" class="print-meta-row">
              <span class="print-meta-label">Department</span>
              <span class="print-meta-value">{{ printDepartmentValue }}</span>
            </div>
            <div class="print-meta-row">
              <span class="print-meta-label">Semester/Academic Year</span>
              <span class="print-meta-value">{{ systemSettings?.active_semester }} / {{ systemSettings?.active_academic_year }}</span>
            </div>
            <div class="print-meta-row">
              <span class="print-meta-label">Date generated</span>
              <span class="print-meta-value">{{ new Date().toLocaleDateString() }}</span>
            </div>
          </div>
        </div>

        <!-- Admin Action Bar -->
        <div
          class="card mb-4 no-print shadow-none mx-3 mx-md-0"
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
                v-model="selectedDepartmentFilter"
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

            <!-- Reset Button -->
            <button class="refresh-pill-btn" @click="resetFilters" title="Reset Filters">
              <i class="fas fa-undo" :class="{ 'fa-spin': loading }"></i>
            </button>

            <div class="flex-grow-1"></div>

            <button
              class="btn btn-primary d-flex align-items-center gap-2 shadow-sm px-4 text-white"
              @click="printReport"
              :disabled="!detailedResults || loading"
              style="background-color: #0a278a; border-color: #0a278a; border-radius: 50px; height: 42px"
            >
              <i class="fas fa-print text-white"></i>
              <span class="fw-bold text-white">Print Report</span>
            </button>
          </div>
        </div>

        <!-- Faculty/Staff View Header (Non-admin) -->
        <div class="card mb-4 no-print shadow-none set-report-header mx-0" v-if="user.role === 'faculty' || user.role === 'staff'">
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
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 text-muted">Generating report info...</p>
        </div>

        <div v-else-if="detailedResults">
          <!-- Stat Cards -->
          <div class="row g-3 mb-4 no-print set-report-stats">
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon orange">
                    <i class="fas fa-star text-warning"></i>
                  </div>
                  <div>
                    <div class="stat-label">Overall SET Rating</div>
                    <div class="stat-value text-warning">
                      {{ detailedResults.overall_set_rating.toFixed(2) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon blue">
                    <i class="fas fa-users text-primary"></i>
                  </div>
                  <div>
                    <div class="stat-label">Total Respondents</div>
                    <div class="stat-value text-primary">
                      {{ detailedResults.total_students }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="stat-card p-3 h-100 shadow-none">
                <div class="d-flex align-items-center gap-3">
                  <div class="stat-icon green">
                    <i class="fas fa-calculator text-success"></i>
                  </div>
                  <div>
                    <div class="stat-label">Weighted Score</div>
                    <div class="stat-value text-success">
                      {{ Number(detailedResults.total_weighted_score).toLocaleString() }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- No Data Notice -->
          <div
            class="alert alert-info border-0 shadow-sm text-center py-4 mb-4"
            v-if="(evaluateeType === 'faculty' && filteredCourseSummaries.length === 0) || (evaluateeType === 'staff' && (!detailedResults.category_scores || detailedResults.category_scores.length === 0))"
          >
            <i class="fas fa-info-circle fa-2x mb-2 text-primary opacity-75"></i>
            <h6 class="fw-bold mb-0">No Evaluation Data</h6>
            <p class="mb-0 small">
              There are no evaluation ratings recorded for this {{ evaluateeType === 'faculty' ? 'faculty member' : 'staff member' }} yet.
            </p>
          </div>

          <!-- Staff Category Scores — mobile -->
          <div
            class="d-md-none set-report-mobile-list mb-4"
            v-if="evaluateeType === 'staff' && detailedResults.category_scores && detailedResults.category_scores.length > 0"
          >
            <h6 class="fw-bold mb-3 px-1">
              <i class="fas fa-table me-2 text-primary"></i>
              Summary of Category Ratings
            </h6>
            <div
              v-for="(cat, catIndex) in detailedResults.category_scores"
              :key="'staff-m-' + catIndex"
              class="set-report-mobile-card"
            >
              <div class="set-report-mobile-card-title">
                <span class="badge bg-light text-dark border me-2">{{ catIndex + 1 }}</span>
                {{ cat.category_name }}
              </div>
              <dl class="set-report-mobile-dl">
                <div><dt>Weight</dt><dd>{{ Math.round(cat.weight * 100) }}%</dd></div>
                <div><dt>Avg Rating</dt><dd class="fw-bold">{{ Number(cat.average_rating).toFixed(2) }}</dd></div>
                <div><dt>Weighted Score</dt><dd class="fw-bold">{{ (Number(cat.average_rating) * cat.weight * 20).toFixed(2) }}%</dd></div>
              </dl>
            </div>
            <div class="set-report-mobile-total">
              <span class="fw-bold">Overall Rating</span>
              <span class="fw-bold text-primary">{{ detailedResults.overall_set_rating.toFixed(2) }}%</span>
            </div>
          </div>

          <!-- Staff Category Scores Table (desktop) -->
          <div
            class="card shadow-none mb-4 overflow-hidden report-table-card d-none d-md-block"
            v-if="evaluateeType === 'staff' && detailedResults.category_scores && detailedResults.category_scores.length > 0"
          >
            <div class="card-header bg-white py-3 no-print">
              <h6 class="mb-0 fw-bold">
                <i class="fas fa-table me-2 text-primary"></i>
                Summary of Category Ratings
              </h6>
            </div>
            <div class="card-body p-0 print-table-block mx-2">
              <div class="print-only print-table-title">
                Summary of Category Ratings
              </div>
              <div class="table-responsive set-report-table-scroll print-table-container">
                <table class="table table-bordered table-hover mb-0 align-middle text-center print-table">
                  <thead class="small">
                    <tr class="print-header-row">
                      <th class="print-col-header py-3 fw-normal text-capitalize">Seq</th>
                      <th class="print-col-header py-3 fw-normal text-capitalize text-start ps-4">Category Name</th>
                      <th class="print-col-header py-3 fw-normal text-capitalize">Weight</th>
                      <th class="print-col-header py-3 fw-normal text-capitalize">Average Rating (1-5)</th>
                      <th class="print-col-header py-3 fw-normal text-capitalize">Weighted Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(cat, catIndex) in detailedResults.category_scores" :key="catIndex">
                      <td class="print-text-black fw-bold">
                        {{ catIndex + 1 }}
                      </td>
                      <td class="print-text-black fw-semibold text-start ps-4">
                        {{ cat.category_name }}
                      </td>
                      <td class="print-text-black fw-normal">
                        {{ Math.round(cat.weight * 100) }}%
                      </td>
                      <td class="print-text-black fw-bold">
                        {{ Number(cat.average_rating).toFixed(2) }}
                      </td>
                      <td class="print-text-black fw-bold">
                        {{ (Number(cat.average_rating) * cat.weight * 20).toFixed(2) }}%
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3" class="text-center print-text-black fw-bold">
                        OVERALL RATING
                      </td>
                      <td class="print-text-black fw-bold">
                        {{ (detailedResults.overall_set_rating / 20).toFixed(2) }}
                      </td>
                      <td class="print-text-black fw-bold">
                        {{ detailedResults.overall_set_rating.toFixed(2) }}%
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
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
                <div class="table-responsive set-report-table-scroll print-table-container">
                  <table class="table table-bordered table-hover mb-0 align-middle text-center print-table">
                    <thead class="small">
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

        <div
          v-else-if="!loading && selectedFacultyId === '' && $can('view_reports') && user.role !== 'faculty' && user.role !== 'staff'"
          class="card shadow-none mx-3 mx-md-0"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-file-invoice fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">No {{ evaluateeType === 'faculty' ? 'Faculty' : 'Staff' }} Selected</h5>
            <p class="mb-0">Please select a {{ evaluateeType === 'faculty' ? 'faculty member' : 'staff member' }} to generate their detailed performance report.</p>
          </div>
        </div>

        <div
          v-else-if="!loading && !detailedResults && (user.role === 'staff' || user.role === 'faculty')"
          class="card shadow-none"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-chart-bar fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">No Report Data Available</h5>
            <p class="mb-0 small" v-if="user.role === 'staff'">
              Your staff profile could not be loaded, or no evaluations have been submitted for you in the active semester yet.
            </p>
            <p class="mb-0 small" v-else>
              Your faculty profile could not be loaded, or no evaluations have been submitted for you in the active semester yet.
            </p>
          </div>
        </div>
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
import api from "../services/api.js";

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

// Filters
const searchQuery = ref("");
const selectedDepartmentFilter = ref("all");
const systemSettings = ref(null);

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

const evaluateeType = ref(user.value.role === 'staff' ? 'staff' : 'faculty');

const printInfoSectionTitle = computed(() =>
  evaluateeType.value === "staff" ? "A. Staff information" : "A. Faculty information",
);

const printEvaluateeFieldLabel = computed(() =>
  evaluateeType.value === "staff" ? "Staff" : "Faculty",
);

const printEvaluateeValue = computed(() => {
  if (selectedFacultyId.value === "all") {
    return evaluateeType.value === "staff" ? "All Staff" : "All Faculty";
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
  const allLabel = evaluateeType.value === 'faculty' ? "All Faculty" : "All Staff";
  return [
    { label: allLabel, value: "all" },
    ...filteredFacultyList.value.map((f) => {
      if (evaluateeType.value === "staff") {
        const suffix = f.designation ? ` - ${f.designation}` : "";
        return {
          label: `${f.user?.name}${suffix}`,
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

const departmentOptions = computed(() => {
  const depts = (facultyList.value || [])
    .map(f => f.department)
    .filter(d => d);
  return [
    { label: "All Departments", value: "all" },
    ...[...new Set(depts)].sort().map(d => ({ label: d, value: d }))
  ];
});

const courseOptions = computed(() => [
  { label: "All Courses", value: "All" },
  ...safeCoursesList.value.map((c) => ({ label: c.name, value: c.name })),
]);

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

  if (can("view_reports") && user.value.role !== "faculty" && user.value.role !== "staff") {
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
  } else if (user.value.role === "staff") {
    evaluateeType.value = "staff";
    const staffId = await resolveMyStaffId();
    if (staffId) {
      selectedFacultyId.value = staffId;
      await loadResults();
    }
  }
});

async function resolveMyStaffId() {
  try {
    const res = await api.get("/reports/staff-list");
    const list = res.data || [];
    const mine = list.find(
      (s) => s?.user_id === user.value?.id || s?.user?.id === user.value?.id,
    );
    if (mine?.id) return mine.id;
    // Fallback: match by display name if user_id was missing on older records
    const byName = list.find(
      (s) => s?.name && user.value?.name && s.name.trim() === user.value.name.trim(),
    );
    return byName?.id ?? null;
  } catch (e) {
    console.error("Failed to resolve staff profile:", e);
    return null;
  }
}

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
    const res = await api.get(`/reports/evaluatee/${selectedFacultyId.value}`, { params });
    detailedResults.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function resetFilters() {
  searchQuery.value = "";
  selectedDepartmentFilter.value = "all";
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
    margin: 0;
  }
  .content-area {
    --set-report-print-blue: #0a278a;
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
    margin-left: 0 !important;
    margin-right: 0 !important;
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
    border: none !important;
    padding: 0 0 0.35rem 0;
    margin-bottom: 0.5rem !important;
  }

  .print-meta-row {
    display: flex !important;
    align-items: baseline;
    margin-bottom: 0.4rem !important;
    gap: 0.5rem;
  }

  .print-meta-label {
    flex: 0 0 200px;
    color: #000 !important;
    font-weight: 400 !important;
  }

  .print-meta-label::after {
    content: ":";
  }

  .print-meta-value {
    font-weight: 600 !important;
    color: #000 !important;
  }

  .print-table-block {
    margin-top: 0.5rem;
  }

  .print-table-title {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10.5pt !important;
    font-weight: 700 !important;
    text-align: center !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #fff !important;
    background-color: var(--set-report-print-blue, #0a278a) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    padding: 10px 14px !important;
    border: 1px solid var(--set-report-print-blue, #0a278a) !important;
    margin: 0 !important;
  }

  .print-table-container {
    border-left: 1px solid var(--set-report-print-blue, #0a278a) !important;
    border-right: 1px solid var(--set-report-print-blue, #0a278a) !important;
    border-top: none !important;
  }

  .print-table-summary {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 10pt !important;
    font-weight: 600 !important;
    text-align: center !important;
    color: #000 !important;
    background-color: #fff !important;
    border: 1px solid var(--set-report-print-blue, #0a278a);
    border-top: none;
    padding: 11px 14px !important;
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
    border: none !important;
    margin: 0 !important;
  }

  .print-table thead th {
    border: 1px solid var(--set-report-print-blue, #0a278a) !important;
    border-bottom: 2px solid var(--set-report-print-blue, #0a278a) !important;
    background-color: #fff !important;
    color: #000 !important;
    padding: 9px 8px !important;
    vertical-align: middle !important;
    font-weight: 600 !important;
    font-size: 8.5pt !important;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.3;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .print-table.table > :not(caption) > thead > tr > th,
  .print-table.table > :not(caption) > thead > tr > td {
    border-width: 1px !important;
    border-style: solid !important;
    border-color: var(--set-report-print-blue, #0a278a) !important;
  }

  .print-table tbody td {
    border: 1px solid var(--set-report-print-blue, #0a278a) !important;
    padding: 9px 8px !important;
    vertical-align: middle !important;
    color: #000 !important;
    font-weight: 400 !important;
    background-color: #fff !important;
  }

  .print-table tfoot td {
    border: 1px solid var(--set-report-print-blue, #0a278a) !important;
    background-color: #fff !important;
    color: #000 !important;
    padding: 10px 8px !important;
    vertical-align: middle !important;
    font-weight: 700 !important;
    font-size: 10pt !important;
  }

  .print-text-black,
  .print-text-gray,
  .print-header-row th {
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

  .table-responsive {
    overflow: visible !important;
  }
}

/* Theme-aware Search and Reset Styles */
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
  color: #3b82f6;
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

.set-report-table-scroll {
  -webkit-overflow-scrolling: touch;
}

.set-report-mobile-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.set-report-mobile-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
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
  border-radius: 8px;
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
  border-radius: 8px;
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
    --set-report-print-blue: #0a278a;
  }

  table.print-table .print-table-title,
  .print-table-title {
    background-color: #0a278a !important;
    border-color: #0a278a !important;
  }

  table.print-table th.print-col-header,
  table.print-table thead th {
    border: 1px solid #0a278a !important;
    border-bottom: 2px solid #0a278a !important;
    color: #000 !important;
    background-color: #fff !important;
    font-weight: 700 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  table.print-table tbody td,
  table.print-table tfoot td {
    border: 1px solid #0a278a !important;
    color: #000 !important;
    background-color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .print-table-container {
    border-left-color: #0a278a !important;
    border-right-color: #0a278a !important;
  }

  .print-table-summary {
    border-color: #0a278a !important;
  }
}
</style>
