<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar>
        <template #title>Questionnaire Management</template>
      </Navbar>

      <div class="content-area">
        <!-- Stats Bar -->
        <div class="stats-bar-premium mb-5 fade-in-up">
          <div
            class="d-flex align-items-center justify-content-between flex-wrap gap-4 px-4 py-3 rounded-4 shadow-sm bg-card border border-light"
          >
            <div class="d-flex align-items-center gap-5">
              <div class="stat-item-inline">
                <span class="label">Balance</span>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <h5 class="value mb-0">{{ totalWeight }}%</h5>
                  <i
                    :class="
                      totalWeight === 100
                        ? 'fas fa-check-circle text-success'
                        : 'fas fa-exclamation-triangle text-danger'
                    "
                    class="small"
                  ></i>
                </div>
              </div>
              <div class="stat-divider"></div>
              <div class="stat-item-inline">
                <span class="label">Sectors</span>
                <h5 class="value mb-0 mt-1">{{ categories.length }}</h5>
              </div>
              <div class="stat-divider"></div>
              <div class="stat-item-inline">
                <span class="label">Criteria</span>
                <h5 class="value mb-0 mt-1">{{ totalQuestionsCount }}</h5>
              </div>
            </div>
            <button class="btn btn-primary-glass px-4 rounded-pill shadow-sm" @click="openCategoryModal()">
              <i class="fas fa-plus-circle me-2"></i>
              New Sector
            </button>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="cards" :rows="6" />
        </div>

        <!-- Grid of Sectors -->
        <div v-else class="row g-4 fade-in-up">
          <TransitionGroup name="grid-stagger">
            <div v-for="(cat, idx) in categories" :key="cat.id" class="col-md-6 col-lg-4 col-xl-4">
              <div
                class="sector-card-premium"
                :style="{ '--accent-color': primaryColor }"
                @click="handleCategoryClick(cat)"
              >
                <div class="card-glow"></div>
                <div class="sector-card-inner p-4 h-100 d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="sector-icon-box" style="background: rgba(25, 25, 112, 0.1); color: #191970">
                      <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="d-flex gap-1">
                      <button class="btn-action-minimal" @click.stop="openCategoryModal(cat)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn-action-minimal danger" @click.stop="deleteCategory(cat.id)">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  </div>

                  <h4 class="sector-title mb-1 fw-800 text-truncate" :title="cat.category_name">
                    {{ cat.category_name }}
                  </h4>
                  <div class="small text-primary fw-bold text-truncate mb-2" v-if="cat.category_name_tl">
                    {{ cat.category_name_tl }}
                  </div>
                  <div class="small text-danger fw-bold mb-2" v-else>
                    <i class="fas fa-language me-1"></i> No Tagalog translation
                  </div>

                  <div class="mt-auto pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="small fw-700 text-muted ls-1">WEIGHT</span>
                      <span class="small fw-800 text-primary">{{ Math.round(cat.weight * 100) }}%</span>
                    </div>
                    <div class="progress-minimal mb-3">
                      <div
                        class="progress-bar"
                        :style="{ width: Math.round(cat.weight * 100) + '%', background: '#0A278A' }"
                      ></div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-list-check small opacity-50"></i>
                        <span class="small fw-600 text-muted">{{ cat.questions_count || 0 }} Metrics</span>
                      </div>
                      <span class="view-details-link small fw-800 text-uppercase ls-1">
                        Manage
                        <i class="fas fa-chevron-right ms-1"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </TransitionGroup>

          <!-- Empty State for Grid -->
          <div v-if="!categories.length" class="col-12">
            <div class="empty-grid-state text-center py-5 border border-dashed rounded-5">
              <div class="mb-4">
                <i class="fas fa-layer-group fa-4x opacity-10"></i>
              </div>
              <h4 class="fw-800">Start Building Your Evaluation</h4>
              <p class="text-muted mb-4">
                You haven't added any evaluation sectors yet. Create your first one to begin adding questions.
              </p>
              <button class="btn btn-primary px-5 rounded-pill" @click="openCategoryModal()">
                Create First Sector
              </button>
            </div>
          </div>
        </div>

        <!-- Metrics Slide-over Drawer -->
        <div v-if="showDrawer" class="drawer-overlay" @click="closeDrawer"></div>
        <Transition name="drawer-slide">
          <div v-if="showDrawer" class="metrics-drawer">
            <div
              class="drawer-header p-4 d-flex align-items-center justify-content-between border-bottom sticky-top bg-card shadow-sm"
            >
              <div class="d-flex align-items-center gap-3">
                <div
                  class="drawer-icon"
                  :style="{ background: activeCategoryColor + '15', color: activeCategoryColor }"
                >
                  <i class="fas fa-list-ol"></i>
                </div>
                <div>
                  <h5 class="fw-800 mb-0">{{ activeCategory?.category_name }}</h5>
                  <span class="small text-muted fw-600 ls-1 text-uppercase">Managing Metrics</span>
                </div>
              </div>
              <button class="btn-close-drawer" @click="closeDrawer">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="drawer-content p-4">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-0">Criteria List</h6>
                <button
                  class="btn btn-primary btn-sm px-3 rounded-pill fw-bold shadow-sm text-white"
                  @click="openQuestionModal(activeCategory.id)"
                >
                  <i class="fas fa-plus me-1"></i>
                  Add Metric
                </button>
              </div>

              <div v-if="loadingQuestions" class="py-3">
                <SkeletonLoader variant="list" :rows="3" />
              </div>

              <div v-else class="metrics-list d-flex flex-column gap-3">
                <TransitionGroup name="list-stagger">
                  <div
                    v-for="(q, qIdx) in questions"
                    :key="q.id"
                    class="drawer-metric-card"
                    :style="{ '--accent-color': activeCategoryColor }"
                  >
                    <div class="d-flex gap-3">
                      <div class="metric-num">{{ qIdx + 1 }}</div>
                      <div class="flex-grow-1">
                        <p class="mb-1 fw-600 text-main">{{ q.question_text }}</p>
                        <p class="mb-0 small text-primary fw-bold" v-if="q.question_text_tl">
                          <i class="fas fa-language me-1"></i> {{ q.question_text_tl }}
                        </p>
                        <p class="mb-0 small text-danger fw-bold" v-else>
                          <i class="fas fa-exclamation-circle me-1"></i> Missing Tagalog translation
                        </p>
                      </div>
                      <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn-action-icon-sm" @click="openQuestionModal(activeCategory.id, q)">
                          <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn-action-icon-sm danger" @click="deleteQuestion(activeCategory.id, q.id)">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </TransitionGroup>

                <div v-if="!questions.length" class="text-center py-5 opacity-50">
                  <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                  <p class="small fw-bold">No metrics added yet.</p>
                </div>
              </div>
            </div>

            <div class="drawer-footer p-4 border-top text-center">
              <p class="small text-muted mb-0">
                Weight allocation for this sector:
                <strong>{{ Math.round(activeCategory?.weight * 100) }}%</strong>
              </p>
            </div>
          </div>
        </Transition>

        <!-- Shared Modals (Category & Question) -->
        <Transition name="fade">
          <div v-if="showCatModal || showQModal" class="glass-backdrop-v2" @click="closeAllModals"></div>
        </Transition>

        <Transition name="zoom-in">
          <div v-if="showCatModal" class="glass-modal-centered">
            <div class="glass-modal-inner card border-0 shadow-lg" style="max-width: 480px">
              <div class="p-4">
                <h5 class="fw-800 mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-folder-plus text-primary"></i>
                  {{ editCatId ? "Update Sector" : "New Evaluation Sector" }}
                </h5>
                <div class="mb-3">
                  <label class="form-label-premium">Sector Name (English)</label>
                  <input
                    v-model="catForm.category_name"
                    class="form-control-premium"
                    placeholder="e.g., Instructional Competence"
                  />
                </div>
                <div class="mb-4">
                  <label class="form-label-premium text-primary">Sector Name (Tagalog)</label>
                  <input
                    v-model="catForm.category_name_tl"
                    class="form-control-premium border-primary-subtle"
                    placeholder="e.g., Kakayahan sa Pagtuturo"
                  />
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between mb-2">
                    <label class="form-label-premium mb-0">Weight Percentage</label>
                    <span class="fw-800 text-primary">{{ catForm.weight_percent }}%</span>
                  </div>
                  <div class="weight-segments">
                    <button
                      v-for="seg in weightSegments"
                      :key="seg"
                      type="button"
                      class="weight-seg-btn"
                      :class="{ active: catForm.weight_percent === seg }"
                      @click="catForm.weight_percent = seg"
                    >
                      {{ seg }}%
                    </button>
                  </div>
                  <div class="mt-2">
                    <label class="form-label-premium tiny-label">Custom</label>
                    <input
                      v-model.number="catForm.weight_percent"
                      type="number"
                      class="form-control-premium sm"
                      min="0"
                      max="100"
                      placeholder="0-100"
                    />
                  </div>
                </div>
              </div>
              <div class="p-4 pt-0 d-flex gap-2">
                <button class="btn btn-light-premium w-100" @click="showCatModal = false">Cancel</button>
                <button class="btn btn-primary-premium w-100" @click="saveCategory" :disabled="saving">
                  {{ saving ? "Processing..." : "Save Sector" }}
                </button>
              </div>
            </div>
          </div>
        </Transition>

        <Transition name="zoom-in">
          <div v-if="showQModal" class="glass-modal-centered">
            <div class="glass-modal-inner card border-0 shadow-lg" style="max-width: 580px">
              <div class="p-4">
                <h5 class="fw-800 mb-4 d-flex align-items-center gap-2">
                  <i class="fas fa-question-circle text-primary"></i>
                  {{ editQId ? "Edit Item" : "New Metric Question" }}
                </h5>
                <div class="mb-3">
                  <label class="form-label-premium">Instructional Prompt (English)</label>
                  <textarea
                    v-model="qForm.question_text"
                    class="form-control-premium"
                    rows="3"
                    placeholder="Clearly describe what students should evaluate..."
                  ></textarea>
                </div>
                <div class="mb-0">
                  <label class="form-label-premium text-primary">Instructional Prompt (Tagalog)</label>
                  <textarea
                    v-model="qForm.question_text_tl"
                    class="form-control-premium border-primary-subtle"
                    rows="3"
                    placeholder="Isalin sa Tagalog ang katanungan..."
                  ></textarea>
                </div>
              </div>
              <div class="p-4 pt-0 d-flex gap-2">
                <button class="btn btn-light-premium w-100" @click="showQModal = false">Cancel</button>
                <button class="btn btn-primary-premium w-100" @click="saveQuestion" :disabled="saving">
                  {{ saving ? "Publishing..." : "Publish Metric" }}
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
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import Pagination from "../components/Pagination.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";

const route = useRoute();
const categories = ref([]);
const questions = ref([]);
const stats = ref({ total_weight: 0, total_questions: 0, total_categories: 0 });
const evaluateeType = ref('faculty');
const loading = ref(true);
const loadingQuestions = ref(false);
const saving = ref(false);
const showDrawer = ref(false);

const showCatModal = ref(false);
const editCatId = ref(null);
const catForm = ref({ category_name: "", category_name_tl: "", weight_percent: 20 });
const weightSegments = [5, 10, 15, 20, 25, 30, 40, 50];

const activeCategoryId = ref(null);
const selectedCategory = ref(null);
const activeCategory = computed(() => selectedCategory.value);

const showQModal = ref(false);
const activeCatId = ref(null);
const editQId = ref(null);
const qForm = ref({ question_text: "", question_text_tl: "" });

const totalWeight = computed(() => stats.value.total_weight);
const totalQuestionsCount = computed(() => stats.value.total_questions);

// Standardized primary color for all accents
const primaryColor = "#0A278A";

const activeCategoryColor = computed(() => primaryColor);

onMounted(() => {
  fetchCategories();
  fetchStats();
});

// Watch for route changes
watch(
  () => route.path,
  () => {
    showDrawer.value = false;
    fetchStats();
    fetchCategories();
  }
);

async function fetchStats() {
  try {
    const res = await api.get(`/questionnaire/stats?evaluatee_type=${evaluateeType.value}`);
    stats.value = res.data;
  } catch (e) {
    console.error("Failed to fetch questionnaire stats:", e);
  }
}

async function fetchCategories() {
  loading.value = true;
  try {
    const res = await api.get(`/categories?paginate=false&evaluatee_type=${evaluateeType.value}`);
    categories.value = res.data;
  } catch (e) {
    console.error("Failed to fetch categories:", e);
  } finally {
    loading.value = false;
  }
}

async function fetchQuestions() {
  if (!activeCategoryId.value) return;
  loadingQuestions.value = true;
  try {
    const res = await api.get(`/categories/${activeCategoryId.value}/questions`);
    questions.value = res.data;
  } catch (e) {
    console.error("Failed to fetch questions:", e);
  } finally {
    loadingQuestions.value = false;
  }
}

function handleCategoryClick(cat) {
  selectedCategory.value = cat;
  activeCategoryId.value = cat.id;
  showDrawer.value = true;
  fetchQuestions();
}

function closeDrawer() {
  showDrawer.value = false;
}

function closeAllModals() {
  showCatModal.value = false;
  showQModal.value = false;
}

function openCategoryModal(cat = null) {
  if (cat) {
    editCatId.value = cat.id;
    catForm.value = {
      category_name: cat.category_name,
      category_name_tl: cat.category_name_tl || "",
      weight_percent: Math.round(cat.weight * 100),
    };
  } else {
    editCatId.value = null;
    catForm.value = { category_name: "", category_name_tl: "", weight_percent: 20 };
  }
  showCatModal.value = true;
}

async function saveCategory() {
  if (!catForm.value.category_name.trim()) return;
  saving.value = true;
  const payload = {
    category_name: catForm.value.category_name,
    category_name_tl: catForm.value.category_name_tl,
    weight: catForm.value.weight_percent / 100,
    evaluatee_type: evaluateeType.value,
  };

  try {
    if (editCatId.value) {
      await api.put(`/categories/${editCatId.value}`, payload);
    } else {
      await api.post("/categories", payload);
    }
    showCatModal.value = false;
    await fetchCategories();
    await fetchStats();
    // Update active category if drawer is open
    if (activeCategory.value && activeCategory.value.id === editCatId.value) {
      selectedCategory.value = categories.value.find((c) => c.id === editCatId.value);
    }
  } catch (e) {
    Swal.fire("Error", e.response?.data?.message || "Failed to save", "error");
  } finally {
    saving.value = false;
  }
}

async function deleteCategory(id) {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Delete this sector and ALL associated metrics? This action is permanent.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/categories/${id}`);
    await fetchCategories();
    await fetchStats();
    if (activeCategoryId.value === id) showDrawer.value = false;
    Swal.fire("Deleted!", "Sector has been removed.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete category", "error");
  }
}

function openQuestionModal(catId, q = null) {
  activeCatId.value = catId;
  if (q) {
    editQId.value = q.id;
    qForm.value = { 
      question_text: q.question_text,
      question_text_tl: q.question_text_tl || "" 
    };
  } else {
    editQId.value = null;
    qForm.value = { question_text: "", question_text_tl: "" };
  }
  showQModal.value = true;
}

async function saveQuestion() {
  if (!qForm.value.question_text.trim()) return;
  saving.value = true;
  const payload = { ...qForm.value, category_id: activeCatId.value };

  try {
    if (editQId.value) {
      await api.put(`/questions/${editQId.value}`, payload);
    } else {
      await api.post("/questions", payload);
    }
    showQModal.value = false;
    await fetchQuestions();
    await fetchStats();
    // Update category question count in main grid
    const cat = categories.value.find((c) => c.id === activeCatId.value);
    if (cat) cat.questions_count = (cat.questions_count || 0) + (editQId.value ? 0 : 1);
  } catch (e) {
    Swal.fire("Error", e.response?.data?.message || "Failed to save", "error");
  } finally {
    saving.value = false;
  }
}

async function deleteQuestion(catId, qId) {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Permanently remove this metric?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (!result.isConfirmed) return;

  try {
    await api.delete(`/questions/${qId}`);
    await fetchQuestions();
    await fetchStats();
    // Update category question count
    const cat = categories.value.find((c) => c.id === catId);
    if (cat) cat.questions_count = Math.max(0, (cat.questions_count || 1) - 1);
    Swal.fire("Deleted!", "Metric has been removed.", "success");
  } catch (e) {
    Swal.fire("Error", "Failed to delete question", "error");
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
.fw-700 {
  font-weight: 700;
}
.fw-600 {
  font-weight: 600;
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

/* Sector Grid Card */
.sector-card-premium {
  background: var(--bg-card);
  border-radius: var(--card-radius);
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.2, 0, 0, 1);
  height: 100%;
}

.sector-card-premium:hover {
  transform: translateY(-8px) scale(1.02);
  border-color: var(--accent-color);
}

.card-glow {
  position: absolute;
  top: 0;
  right: 0;
  width: 120px;
  height: 120px;
  background: var(--accent-color);
  filter: blur(80px);
  opacity: 0.05;
  pointer-events: none;
}

.sector-icon-box {
  width: 54px;
  height: 54px;
  border-radius: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
}

.sector-title {
  font-size: 1.25rem;
  color: var(--text-dark);
}

.weight-segments {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.weight-seg-btn {
  padding: 0.45rem 0.85rem;
  border-radius: 0.6rem;
  border: 1.5px solid var(--border-light);
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1;
}

.weight-seg-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(25, 25, 112, 0.04);
}

.weight-seg-btn.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
  box-shadow: 0 2px 8px rgba(25, 25, 112, 0.2);
}

.progress-minimal {
  height: 6px;
  background: var(--bg-light);
  border-radius: 3px;
  overflow: hidden;
}

.progress-minimal .progress-bar {
  height: 100%;
  border-radius: 3px;
  transition: width 1s ease;
}

.btn-action-minimal {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  border: none;
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.85rem;
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
  color: var(--accent-color);
  transition: all 0.2s;
}

.sector-card-premium:hover .view-details-link {
  transform: translateX(4px);
}

/* Slide-over Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  z-index: 1050;
}

.metrics-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: 500px;
  background: var(--bg-card);
  z-index: 1060;
  box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
}

.drawer-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
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

.drawer-metric-card {
  background: var(--bg-light);
  padding: 1.25rem;
  border-radius: var(--card-radius);
  border: 1px solid var(--border-light);
  transition: all 0.2s;
}

.drawer-metric-card:hover {
  border-color: var(--accent-color);
  background: var(--bg-card);
  transform: translateX(-4px);
}

.metric-num {
  width: 28px;
  height: 28px;
  background: var(--accent-color);
  color: white;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.btn-action-icon-sm {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: white;
  color: var(--text-muted);
  font-size: 0.8rem;
  transition: all 0.2s;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.btn-action-icon-sm:hover {
  background: var(--primary);
  color: white;
}

.btn-action-icon-sm.danger:hover {
  background: var(--danger);
  color: white;
}

/* Modals Refined */
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
  border-radius: var(--card-radius);
  background: var(--bg-card);
  overflow: visible !important;
}

.form-label-premium {
  display: block;
  font-size: 0.75rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 0.75rem;
}

.form-control-premium {
  width: 100%;
  padding: 1rem 1.25rem;
  border-radius: 1.25rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-dark);
  font-weight: 600;
  transition: all 0.3s;
}

.form-control-premium:focus {
  outline: none;
  border-color: var(--primary);
  background: white;
  box-shadow: 0 0 0 4px rgba(25, 25, 112, 0.1);
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
  box-shadow: 0 10px 20px rgba(25, 25, 112, 0.3);
}

.btn.btn-primary-premium:active:not(:disabled) {
  background: #0039b3;
  color: #fff;
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(25, 25, 112, 0.25);
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

.zoom-in-enter-active,
.zoom-in-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.zoom-in-enter-from,
.zoom-in-leave-to {
  opacity: 0;
  transform: scale(0.94) translateY(10px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.grid-stagger-enter-active {
  transition: all 0.5s ease;
}
.grid-stagger-enter-from {
  opacity: 0;
  transform: translateY(30px);
}

.list-stagger-enter-active {
  transition: all 0.3s ease;
}
.list-stagger-enter-from {
  opacity: 0;
  transform: translateX(10px);
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

@media (max-width: 576px) {
  .metrics-drawer {
    width: 100%;
    max-width: 100%;
  }
}
</style>
