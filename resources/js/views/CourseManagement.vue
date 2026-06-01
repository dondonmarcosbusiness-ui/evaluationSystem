<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Course Management</template></Navbar>

      <div class="content-area">
        <!-- Slim Stats Bar -->
        <div class="stats-bar-premium mb-5 fade-in-up">
          <div
            class="d-flex align-items-center justify-content-between flex-wrap gap-4 px-4 py-3 rounded-4 shadow-sm bg-card border border-light"
          >
            <div class="d-flex align-items-center gap-5">
              <div class="stat-item-inline">
                <span class="label">Curricula</span>
                <h5 class="value mb-0 mt-1">{{ totalCoursesCount }}</h5>
              </div>
              <div class="stat-divider"></div>
              <div class="stat-item-inline">
                <span class="label">Departments</span>
                <h5 class="value mb-0 mt-1">{{ uniqueDepartmentsCount }}</h5>
              </div>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="search-premium-box">
                <i class="fas fa-search"></i>
                <input v-model="searchQuery" type="text" placeholder="Search course..." @input="handleSearch" />
              </div>
              <button class="btn btn-primary-glass px-4 rounded-pill shadow-sm" @click="openAddModal">
                <i class="fas fa-plus-circle me-2"></i>
                New Course
              </button>
            </div>
          </div>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="mt-3 text-muted fw-bold ls-1">Curating Curricula...</p>
        </div>

        <!-- Grid of Courses -->
        <div v-else class="row g-4 fade-in-up">
          <TransitionGroup name="grid-stagger">
            <div v-for="course in courses" :key="course.id" class="col-md-6 col-lg-4 col-xl-3">
              <div class="course-card-premium" @click="handleCourseClick(course)">
                <div class="card-glow"></div>
                <div class="course-card-inner p-4 h-100 d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="course-icon-box">
                      <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="d-flex gap-1">
                      <button class="btn-action-minimal" @click.stop="openEditModal(course)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn-action-minimal danger" @click.stop="deleteCourse(course.id)">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  </div>

                  <h5 class="course-title-v3 mb-1 fw-800">{{ course.name }}</h5>
                  <span class="course-dept-v3 mb-4">{{ course.department }}</span>

                  <div class="mt-auto pt-3 border-top border-light">
                    <div class="d-flex align-items-center gap-4 mb-3">
                      <div class="text-center">
                        <div class="small fw-800 text-primary">{{ splitList(course.subjects).length }}</div>
                        <div class="tiny-label">SUBJECTS</div>
                      </div>
                      <div class="text-center">
                        <div class="small fw-800 text-primary">{{ splitList(course.sections).length }}</div>
                        <div class="tiny-label">SECTIONS</div>
                      </div>
                    </div>
                    <div class="pt-3 border-top border-light-subtle">
                      <span class="view-details-link small fw-800 text-uppercase ls-1 justify-content-between w-100">
                        Manage Curriculum
                        <i class="fas fa-chevron-right"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </TransitionGroup>

          <!-- Pagination -->
          <div v-if="totalCoursePages > 1" class="col-12 mt-4 d-flex justify-content-center">
            <div
              class="pagination-premium d-flex align-items-center gap-3 bg-card p-2 rounded-pill shadow-sm border border-light"
            >
              <button class="btn btn-icon-sm" :disabled="coursePage === 1" @click="coursePage--">
                <i class="fas fa-chevron-left"></i>
              </button>
              <span class="small fw-800 px-2">Page {{ coursePage }} of {{ totalCoursePages }}</span>
              <button class="btn btn-icon-sm" :disabled="coursePage === totalCoursePages" @click="coursePage++">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!courses.length && !loading" class="col-12 text-center py-5">
            <i class="fas fa-folder-open fa-4x opacity-10 mb-3"></i>
            <h5 class="fw-800">No Curricula Found</h5>
            <p class="text-muted small">Try adjusting your search or add a new course.</p>
          </div>
        </div>

        <!-- Curriculum Drawer -->
        <div v-if="showDrawer" class="drawer-overlay" @click="closeDrawer"></div>
        <Transition name="drawer-slide">
          <div v-if="showDrawer" class="curriculum-drawer">
            <div
              class="drawer-header p-4 d-flex align-items-center justify-content-between border-bottom sticky-top bg-card shadow-sm"
            >
              <div class="d-flex align-items-center gap-3">
                <div class="drawer-icon-v3">
                  <i class="fas fa-book-open"></i>
                </div>
                <div>
                  <h5 class="fw-800 mb-0">{{ activeCourse?.name }}</h5>
                  <span class="small text-muted fw-600 ls-1 text-uppercase">Curriculum Details</span>
                </div>
              </div>
              <button class="btn-close-drawer" @click="closeDrawer">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="drawer-content p-4">
              <!-- Subjects Section -->
              <div class="mb-5">
                <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-bookmark text-primary opacity-50"></i>
                  Subject Bank
                </h6>
                <div class="d-flex flex-column gap-2">
                  <div v-for="(sub, idx) in splitList(activeCourse?.subjects)" :key="idx" class="drawer-item-card">
                    <div class="d-flex align-items-center gap-3">
                      <div class="item-index">{{ idx + 1 }}</div>
                      <span class="fw-600 text-main small">{{ sub }}</span>
                    </div>
                  </div>
                  <div v-if="!splitList(activeCourse?.subjects).length" class="text-center py-4 opacity-50">
                    <p class="small fw-600 mb-0">No subjects listed.</p>
                  </div>
                </div>
              </div>

              <!-- Sections Section -->
              <div>
                <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-users-rectangle text-primary opacity-50"></i>
                  Academic Sections
                </h6>
                <div class="d-flex flex-wrap gap-2">
                  <div v-for="(sec, idx) in splitList(activeCourse?.sections)" :key="idx" class="drawer-pill">
                    <div class="pill-dot"></div>
                    <span>{{ sec }}</span>
                  </div>
                  <div v-if="!splitList(activeCourse?.sections).length" class="text-center w-100 py-4 opacity-50">
                    <p class="small fw-600 mb-0">No sections listed.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>

        <!-- Modals -->
        <Transition name="fade">
          <div v-if="showModal" class="glass-backdrop-v2" @click="closeModal"></div>
        </Transition>

        <Transition name="zoom-in">
          <div v-if="showModal" class="glass-modal-centered">
            <div class="glass-modal-inner card border-0 shadow-lg" style="max-width: 520px">
              <div class="p-4">
                <h5 class="fw-800 mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-graduation-cap text-primary"></i>
                  {{ editMode ? "Update Curriculum" : "New Course Curriculum" }}
                </h5>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label-premium">Course Name</label>
                    <input v-model="form.name" class="form-control-premium" placeholder="e.g. BSIT" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-premium">Department</label>
                    <input v-model="form.department" class="form-control-premium" placeholder="e.g. CIT Dept" />
                  </div>

                  <div class="col-12">
                    <label class="form-label-premium d-flex justify-content-between">
                      Subjects
                      <span class="tiny-label">Press Enter</span>
                    </label>
                    <div class="tag-input-container">
                      <div class="tag-cloud mb-2" v-if="form.subjects.length">
                        <span v-for="(sub, idx) in form.subjects" :key="idx" class="premium-tag">
                          {{ sub }}
                          <i class="fas fa-times ms-1" @click="removeSubject(idx)"></i>
                        </span>
                      </div>
                      <input
                        v-model="subjectInput"
                        class="form-control-premium sm"
                        placeholder="Add subject..."
                        @keydown.enter.prevent="addSubject"
                      />
                    </div>
                  </div>

                  <div class="col-12">
                    <label class="form-label-premium d-flex justify-content-between">
                      Sections
                      <span class="tiny-label">Press Enter</span>
                    </label>
                    <div class="tag-input-container">
                      <div class="tag-cloud mb-2" v-if="form.sections.length">
                        <span v-for="(sec, idx) in form.sections" :key="idx" class="premium-tag secondary">
                          {{ sec }}
                          <i class="fas fa-times ms-1" @click="removeSection(idx)"></i>
                        </span>
                      </div>
                      <input
                        v-model="sectionInput"
                        class="form-control-premium sm"
                        placeholder="Add section..."
                        @keydown.enter.prevent="addSection"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div class="p-4 pt-0 d-flex gap-2">
                <button class="btn btn-light-premium w-100" @click="closeModal">Cancel</button>
                <button class="btn btn-primary-premium w-100" @click="saveCourse" :disabled="saving">
                  {{ saving ? "Processing..." : "Save Curriculum" }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";

const courses = ref([]);
const loading = ref(true);
const saving = ref(false);
const showModal = ref(false);
const showDrawer = ref(false);
const editMode = ref(false);
const editId = ref(null);
const subjectInput = ref("");
const sectionInput = ref("");

const activeCourseId = ref(null);
const activeCourse = computed(() => courses.value.find((c) => c.id === activeCourseId.value) || null);

const coursePage = ref(1);
const coursesPerPage = 8;
const totalCoursePages = ref(1);
const totalCoursesCount = ref(0);
const searchQuery = ref("");
let searchTimeout = null;

const form = ref({ name: "", department: "", subjects: [], sections: [] });

const uniqueDepartmentsCount = computed(() => {
  const depts = courses.value.map((c) => c.department);
  return new Set(depts).size;
});

onMounted(fetchCourses);

function splitList(str) {
  if (!str) return [];
  return str
    .split(",")
    .map((s) => s.trim())
    .filter((s) => s);
}

async function fetchCourses() {
  loading.value = true;
  try {
    let url = `/courses?paginate=true&page=${coursePage.value}&per_page=${coursesPerPage}`;
    if (searchQuery.value) url += `&query=${encodeURIComponent(searchQuery.value)}`;
    const res = await api.get(url);
    courses.value = res.data.data;
    totalCoursePages.value = res.data.last_page;
    totalCoursesCount.value = res.data.total;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

watch(coursePage, fetchCourses);

function handleSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    coursePage.value = 1;
    fetchCourses();
  }, 500);
}

function handleCourseClick(course) {
  activeCourseId.value = course.id;
  showDrawer.value = true;
}

function closeDrawer() {
  showDrawer.value = false;
}

function openAddModal() {
  editMode.value = false;
  form.value = { name: "", department: "", subjects: [], sections: [] };
  subjectInput.value = "";
  sectionInput.value = "";
  showModal.value = true;
}

function openEditModal(course) {
  editMode.value = true;
  editId.value = course.id;
  form.value = {
    ...course,
    subjects: splitList(course.subjects),
    sections: splitList(course.sections),
  };
  subjectInput.value = "";
  sectionInput.value = "";
  showModal.value = true;
}

function addSubject() {
  const val = subjectInput.value.trim();
  if (val && !form.value.subjects.includes(val)) {
    form.value.subjects.push(val);
    subjectInput.value = "";
  }
}

function removeSubject(index) {
  form.value.subjects.splice(index, 1);
}

function addSection() {
  const val = sectionInput.value.trim();
  if (val && !form.value.sections.includes(val)) {
    form.value.sections.push(val);
    sectionInput.value = "";
  }
}

function removeSection(index) {
  form.value.sections.splice(index, 1);
}

function closeModal() {
  showModal.value = false;
}

async function saveCourse() {
  if (!form.value.name || !form.value.department) return;
  saving.value = true;
  try {
    const payload = {
      ...form.value,
      subjects: form.value.subjects.join(", "),
      sections: form.value.sections.join(", "),
    };
    if (editMode.value) await api.put(`/courses/${editId.value}`, payload);
    else await api.post("/courses", payload);
    closeModal();
    await fetchCourses();
    Swal.fire({
      icon: "success",
      title: "Saved",
      text: "Curriculum has been saved successfully.",
      timer: 1500,
      showConfirmButton: false,
    });
  } catch (e) {
    Swal.fire("Error", "Failed to save course.", "error");
  } finally {
    saving.value = false;
  }
}

async function deleteCourse(id) {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Do you want to delete this curriculum? This cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/courses/${id}`);
    await fetchCourses();
    if (activeCourseId.value === id) showDrawer.value = false;
    Swal.fire("Deleted!", "Curriculum has been deleted.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete course.", "error");
  }
}
</script>

<style scoped>
.ls-1 {
  letter-spacing: 0.05em;
}
.fw-800 {
  font-weight: 800;
}
.fw-900 {
  font-weight: 900;
}

.bg-card {
  background: var(--bg-card);
}

/* Slim Stats Bar */
.stat-item-inline .label {
  font-size: 0.65rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.stat-item-inline .value {
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--text-dark);
}
.stat-divider {
  width: 1px;
  height: 32px;
  background: var(--border-light);
}

.search-premium-box {
  position: relative;
  width: 280px;
}

.search-premium-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.9rem;
}

.search-premium-box input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  background: var(--bg-light);
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.3s;
}

.search-premium-box input:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
  box-shadow: 0 4px 12px rgba(10, 39, 138, 0.08);
}

/* Course Grid Card */
.course-card-premium {
  background: var(--bg-card);
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.2, 0, 0, 1);
  height: 100%;
}

.course-card-premium:hover {
  transform: translateY(-8px) scale(1.02);
  border-color: var(--primary);
}

.card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 120px;
  height: 120px;
  background: var(--primary);
  filter: blur(80px);
  opacity: 0.05;
  pointer-events: none;
}

.course-icon-box {
  width: 50px;
  height: 50px;
  border-radius: 1rem;
  background: rgba(10, 39, 138, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.course-title-v3 {
  font-size: 1.15rem;
  color: var(--text-dark);
  letter-spacing: -0.01em;
}

.course-dept-v3 {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.tiny-label {
  font-size: 0.6rem;
  font-weight: 800;
  color: var(--text-muted);
  letter-spacing: 0.05em;
  margin-top: 2px;
}

.btn-action-minimal {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.8rem;
  transition: all 0.2s;
}

.btn-action-minimal:hover {
  background: var(--primary);
  color: white;
}

.btn-action-minimal.danger:hover {
  background: var(--danger);
  color: white;
}

.view-details-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary);
  opacity: 0.5;
  transition: all 0.2s;
}

.course-card-premium:hover .view-details-link {
  opacity: 1;
  transform: translateX(4px);
}

/* Curriculum Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  z-index: 1050;
}

.curriculum-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: 520px;
  background: var(--bg-card);
  z-index: 1060;
  box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
}

.drawer-icon-v3 {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(10, 39, 138, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.drawer-item-card {
  background: var(--bg-light);
  padding: 1rem;
  border-radius: 1rem;
  border: 1px solid var(--border-light);
  transition: all 0.2s;
}

.drawer-item-card:hover {
  background: white;
  transform: translateX(-4px);
}

.item-index {
  width: 24px;
  height: 24px;
  background: var(--primary);
  color: white;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 800;
}

.drawer-pill {
  background: white;
  padding: 0.5rem 1rem;
  border-radius: 2rem;
  border: 1px solid var(--border-light);
  font-size: 0.85rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pill-dot {
  width: 6px;
  height: 6px;
  background: var(--primary);
  border-radius: 50%;
}

.btn-close-drawer {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: none;
  background: var(--bg-light);
  color: var(--text-muted);
  transition: all 0.2s;
}

.btn-close-drawer:hover {
  background: #fee2e2;
  color: var(--danger);
}

/* Modals */
.glass-backdrop-v2 {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(12px);
  z-index: 2000;
}

.glass-modal-centered {
  position: fixed;
  inset: 0;
  z-index: 2010;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3rem 1.5rem;
  overflow-y: auto;
}

.glass-modal-inner {
  margin: auto;
  width: 100%;
  border-radius: 2.5rem;
  background: var(--bg-card);
  overflow: visible !important;
}

.form-label-premium {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 0.5rem;
}

.form-control-premium {
  width: 100%;
  padding: 0.85rem 1.25rem;
  border-radius: 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  font-weight: 600;
  transition: all 0.3s;
}

.form-control-premium.sm {
  padding: 0.6rem 1rem;
  font-size: 0.85rem;
}

.form-control-premium:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
}

.premium-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
  background: rgba(10, 39, 138, 0.08);
  color: var(--primary);
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 700;
}

.premium-tag.secondary {
  background: rgba(16, 185, 129, 0.08);
  color: #10b981;
}

.premium-tag i {
  cursor: pointer;
  margin-left: 0.5rem;
  opacity: 0.5;
}

.btn-primary-premium {
  padding: 1rem;
  border-radius: 1.25rem;
  background: var(--primary);
  border: none;
  color: #fff;
  font-weight: 800;
  transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, color 0.2s ease;
}

.btn.btn-primary-premium:hover:not(:disabled),
.btn.btn-primary-premium:focus-visible:not(:disabled) {
  background: #0041cc;
  border: none;
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(0, 82, 255, 0.3);
}

.btn.btn-primary-premium:active:not(:disabled) {
  background: #0039b3;
  color: #fff;
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(0, 82, 255, 0.25);
}

.btn.btn-primary-premium:disabled {
  background: var(--primary);
  color: #fff;
  opacity: 0.65;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.btn-light-premium {
  padding: 1rem;
  border-radius: 1.25rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-muted);
  font-weight: 700;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.btn.btn-light-premium:hover:not(:disabled),
.btn.btn-light-premium:focus-visible:not(:disabled) {
  background: #fff;
  border-color: var(--primary);
  color: var(--primary);
}

.btn.btn-light-premium:active:not(:disabled) {
  background: var(--bg-light);
  border-color: #0041cc;
  color: #0041cc;
}

/* Animations */
.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  transform: translateX(100%);
}

.fade-in-up {
  animation: fadeInUp 0.6s ease-out;
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.grid-stagger-enter-active {
  transition: all 0.5s ease;
}
.grid-stagger-enter-from {
  opacity: 0;
  transform: translateY(30px);
}
</style>
