<template>
  <div class="d-flex">
    <Sidebar class="no-print" />
    <div class="main-wrapper w-100">
      <Navbar class="no-print"><template #title>Evaluation Archive</template></Navbar>

      <div class="content-area">
        <div class="alert alert-secondary border-0 rounded-4 d-flex align-items-start gap-3 mx-3 mx-md-0 mb-4" role="note">
          <i class="fas fa-box-archive mt-1"></i>
          <div class="small">
            <span class="d-block fw-bold mb-1">Read-only history</span>
            <span class="text-muted">
              Review evaluation results from past or archived semesters and academic years. Archived values stay
              out of active evaluation forms but remain selectable here.
            </span>
          </div>
        </div>

        <div class="card shadow-none mb-4 no-print mx-3 mx-md-0" style="position: relative; z-index: 900; overflow: visible !important">
          <div class="card-body d-flex gap-3 align-items-center flex-wrap no-print" style="overflow: visible !important">
            <div style="width: 200px">
              <CustomSelect
                v-model="selectedSemester"
                :options="semesterOptions"
                placeholder="Select Semester"
                @change="loadResults"
              />
            </div>
            <div style="width: 200px">
              <CustomSelect
                v-model="selectedYear"
                :options="yearOptions"
                placeholder="Select Academic Year"
                @change="loadResults"
              />
            </div>
            <div style="width: 200px">
              <CustomSelect
                v-model="selectedDepartment"
                :options="departmentOptions"
                placeholder="All Departments"
                @change="handleDepartmentChange"
              />
            </div>
            <div style="width: 350px">
              <CustomSelect
                v-model="selectedFacultyId"
                :options="facultyOptions"
                placeholder="Select Faculty:"
                @change="loadResults"
              />
            </div>
            <button class="refresh-pill-btn" @click="resetFilters" title="Reset Filters">
              <i class="fas fa-undo" :class="{ 'fa-spin': loading }"></i>
            </button>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="table" :rows="6" :cols="4" />
        </div>

        <div v-else-if="!results" class="card mx-3 mx-md-0">
          <div class="card-body text-center text-muted py-5">
            <i class="fas fa-folder-open fa-3x opacity-25 mb-3 d-block"></i>
            <h5 class="fw-bold mb-1">No archived results to show</h5>
            <p class="small mb-0">Pick a semester, academic year, and faculty member to review past evaluations.</p>
          </div>
        </div>

        <div v-else class="reports-results mx-3 mx-md-0">
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <div class="stat-card shadow-none p-3 p-md-4 h-100 flex-column text-center">
                <div class="stat-label mb-2">Final Evaluation Score</div>
                <div class="stat-value text-success mb-2 report-final-score">
                  {{ results.final_score }}
                </div>
                <span class="badge px-3 px-md-4 py-2 rounded-pill" :class="badgeClass(results.interpretation)">
                  {{ results.interpretation }}
                </span>
                <div class="small text-muted mt-3">{{ selectedSemester }} / {{ selectedYear }}</div>
              </div>
            </div>
            <div class="col-12 col-md-8">
              <div class="card shadow-none h-100">
                <div class="card-header border-0 py-3">
                  <h6 class="mb-0 fw-bold">Category Breakdown</h6>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="bg-light small">
                        <tr>
                          <th>Category</th>
                          <th class="text-center">Average</th>
                          <th class="text-center">Weight</th>
                          <th class="text-center">5</th>
                          <th class="text-center">4</th>
                          <th class="text-center">3</th>
                          <th class="text-center">2</th>
                          <th class="text-center">1</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="cat in results.category_results" :key="cat.category_name">
                          <td class="fw-semibold">{{ cat.category_name }}</td>
                          <td class="text-center">{{ Number(cat.average_rating).toFixed(2) }}</td>
                          <td class="text-center text-muted small">{{ (cat.weight * 100).toFixed(0) }}%</td>
                          <td class="text-center">{{ cat.count_5 }}</td>
                          <td class="text-center">{{ cat.count_4 }}</td>
                          <td class="text-center">{{ cat.count_3 }}</td>
                          <td class="text-center">{{ cat.count_2 }}</td>
                          <td class="text-center">{{ cat.count_1 }}</td>
                        </tr>
                        <tr v-if="!results.category_results?.length">
                          <td colspan="8" class="text-center text-muted py-4">No category data for this period.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import { fetchAcademicPeriods, courseDepartments } from "../helpers/academic.js";

const can = inject("can");

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const facultyList = ref([]);
const coursesList = ref([]);
const selectedFacultyId = ref("all");
const selectedDepartment = ref("all");
const selectedSemester = ref("");
const selectedYear = ref("");
const semesterList = ref([]);
const yearList = ref([]);
const results = ref(null);
const loading = ref(false);

const semesterOptions = computed(() => semesterList.value.map((s) => ({ label: s, value: s })));
const yearOptions = computed(() => yearList.value.map((y) => ({ label: y, value: y })));

const departments = computed(() => {
  // Department filter options come from the Course List (single source of
  // truth) — never from faculty records, which can hold stale departments
  // whose courses were deleted.
  return courseDepartments(coursesList.value);
});

const departmentOptions = computed(() => [
  { label: "All Departments", value: "all" },
  ...departments.value.map((d) => ({ label: d, value: d })),
]);

const filteredFacultyList = computed(() => {
  let list = (facultyList.value || []).filter((f) => f && f.id);
  if (selectedDepartment.value && selectedDepartment.value !== "all") {
    list = list.filter((f) => f.department && f.department.trim() === selectedDepartment.value.trim());
  }
  return list;
});

const facultyOptions = computed(() => [
  { label: "All Faculty", value: "all" },
  ...filteredFacultyList.value.map((f) => ({
    label: `${f.user?.name} (${f.department || "N/A"})`,
    value: f.id,
  })),
]);

function badgeClass(interpretation) {
  switch ((interpretation || "").toLowerCase()) {
    case "excellent":
      return "bg-success";
    case "very good":
      return "bg-primary";
    case "good":
      return "bg-info";
    case "fair":
      return "bg-warning text-dark";
    default:
      return "bg-danger";
  }
}

onMounted(async () => {
  try {
    const periods = await fetchAcademicPeriods();
    semesterList.value = periods.semesters || [];
    yearList.value = periods.years || [];
    // Default to the active period so the page opens on current history.
    selectedSemester.value = periods.activeSemester && semesterList.value.includes(periods.activeSemester)
      ? periods.activeSemester
      : semesterList.value[0] || "";
    selectedYear.value = periods.activeAcademicYear && yearList.value.includes(periods.activeAcademicYear)
      ? periods.activeAcademicYear
      : yearList.value[0] || "";
  } catch {
    /* selects stay empty; user can still retry */
  }

  if (user.value.role === "faculty") {
    try {
      const [facultyRes, coursesRes] = await Promise.all([
        api.get("/faculty/all"),
        api.get("/courses")
      ]);
      const mine = (facultyRes.data || []).find((f) => f?.user_id === user.value?.id);
      facultyList.value = facultyRes.data || [];
      coursesList.value = coursesRes.data || [];
      if (mine) selectedFacultyId.value = mine.id;
    } catch (e) {
      console.error("Error fetching faculty list:", e);
    }
  } else {
    await fetchEvaluateesList();
  }
  await loadResults();
});

async function fetchEvaluateesList() {
  try {
    const [facultyRes, coursesRes] = await Promise.all([
      api.get("/faculty/all"),
      api.get("/courses")
    ]);
    facultyList.value = facultyRes.data;
    coursesList.value = coursesRes.data;
  } catch (e) {
    console.error("Error fetching evaluatees list:", e);
  }
}

async function handleDepartmentChange() {
  if (selectedFacultyId.value !== "all") {
    const current = facultyList.value.find((f) => f.id === selectedFacultyId.value);
    if (current && current.department !== selectedDepartment.value && selectedDepartment.value !== "all") {
      selectedFacultyId.value = "all";
    }
  }
  await loadResults();
}

async function resetFilters() {
  try {
    const periods = await fetchAcademicPeriods(true);
    semesterList.value = periods.semesters || [];
    yearList.value = periods.years || [];
    selectedSemester.value = periods.activeSemester || semesterList.value[0] || "";
    selectedYear.value = periods.activeAcademicYear || yearList.value[0] || "";
  } catch {
    /* keep current selections */
  }
  selectedDepartment.value = "all";
  if (user.value.role !== "faculty" || !can("view_reports")) {
    selectedFacultyId.value = "all";
  }
  await loadResults();
}

async function loadResults() {
  if (!selectedFacultyId.value || !selectedSemester.value || !selectedYear.value) {
    results.value = null;
    return;
  }
  loading.value = true;
  results.value = null;
  try {
    const params = {
      evaluatee_type: "faculty",
      semester: selectedSemester.value,
      academic_year: selectedYear.value,
    };
    if (selectedDepartment.value && selectedDepartment.value !== "all") {
      params.department = selectedDepartment.value;
    }
    const res = await api.get(`/evaluations/results/${selectedFacultyId.value}`, { params });
    // Empty result sets come back as empty category lists — surface the
    // empty state instead of a zeroed scorecard.
    results.value = res.data?.category_results?.length ? res.data : null;
  } catch (e) {
    console.error(e);
    results.value = null;
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  display: flex;
}

.stat-label {
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
}

.stat-value {
  font-size: 2rem;
  font-weight: 900;
}

.report-final-score {
  line-height: 1;
}

.refresh-pill-btn {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.refresh-pill-btn:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
</style>
