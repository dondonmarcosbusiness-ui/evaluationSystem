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
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="view-toggle-group" role="group" aria-label="View mode">
                <button
                  type="button"
                  class="view-toggle-btn"
                  :class="{ active: viewMode === 'cards' }"
                  @click="viewMode = 'cards'"
                  title="Card view"
                  aria-label="Card view"
                >
                  <i class="fas fa-grip"></i>
                </button>
                <button
                  type="button"
                  class="view-toggle-btn"
                  :class="{ active: viewMode === 'table' }"
                  @click="viewMode = 'table'"
                  title="Table view"
                  aria-label="Table view"
                >
                  <i class="fas fa-table-list"></i>
                </button>
              </div>
              <button class="btn btn-primary-glass px-4 rounded-pill shadow-sm" @click="openCategoryModal()">
                <i class="fas fa-plus-circle me-2"></i>
                New Sector
              </button>
            </div>
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="cards" :rows="6" />
        </div>

        <!-- Grid of Sectors (Cards view) -->
        <div v-else-if="viewMode === 'cards'" class="row g-4 fade-in-up">
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

        <!-- Table of Sectors (Table view) -->
        <div v-else-if="viewMode === 'table'" class="fade-in-up">
          <div class="sector-table-card">
            <div class="table-responsive">
              <table class="table sector-table mb-0 align-middle">
                <thead>
                  <tr>
                    <th style="width: 48px">#</th>
                    <th>Sector</th>
                    <th>Tagalog Translation</th>
                    <th style="width: 180px">Weight</th>
                    <th style="width: 110px" class="text-center">Metrics</th>
                    <th style="width: 190px" class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(cat, idx) in categories"
                    :key="cat.id"
                    class="sector-table-row"
                    @click="handleCategoryClick(cat)"
                  >
                    <td class="text-muted fw-700">{{ idx + 1 }}</td>
                    <td>
                      <div class="d-flex align-items-center gap-3">
                        <div class="sector-icon-box sector-icon-box-sm" style="background: rgba(25, 25, 112, 0.1); color: #191970">
                          <i class="fas fa-folder-open"></i>
                        </div>
                        <span class="fw-800 text-main">{{ cat.category_name }}</span>
                      </div>
                    </td>
                    <td>
                      <span class="small fw-bold text-primary text-truncate d-inline-block" style="max-width: 240px" v-if="cat.category_name_tl" :title="cat.category_name_tl">
                        {{ cat.category_name_tl }}
                      </span>
                      <span class="small fw-bold text-danger" v-else>
                        <i class="fas fa-language me-1"></i> No Tagalog translation
                      </span>
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="progress-minimal flex-grow-1">
                          <div
                            class="progress-bar"
                            :style="{ width: Math.round(cat.weight * 100) + '%', background: '#0A278A' }"
                          ></div>
                        </div>
                        <span class="small fw-800 text-primary" style="min-width: 38px">{{ Math.round(cat.weight * 100) }}%</span>
                      </div>
                    </td>
                    <td class="text-center">
                      <span class="metric-badge">
                        <i class="fas fa-list-check me-1"></i>
                        {{ cat.questions_count || 0 }}
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1 justify-content-end">
                        <button class="btn-manage-sm" @click.stop="handleCategoryClick(cat)">
                          Manage
                          <i class="fas fa-chevron-right ms-1"></i>
                        </button>
                        <button class="btn-action-minimal" @click.stop="openCategoryModal(cat)" title="Edit sector">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action-minimal danger" @click.stop="deleteCategory(cat.id)" title="Delete sector">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Empty State for Table -->
            <div v-if="!categories.length" class="text-center py-5">
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

        <!-- Metrics Modal -->
        <div ref="metricsModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                  <div
                    class="drawer-icon"
                    :style="{ background: activeCategoryColor + '15', color: activeCategoryColor }"
                  >
                    <i class="fas fa-list-ol"></i>
                  </div>
                  <div>
                    <h5 class="modal-title">{{ activeCategory?.category_name }}</h5>
                    <span class="small text-muted text-uppercase">Managing Metrics</span>
                  </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                  <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-0">Criteria List</h6>
                  <button
                    class="btn btn-primary btn-sm px-3 rounded-pill fw-bold shadow-sm text-white"
                    @click="openQuestionModal(activeCategory?.id)"
                  >
                    <i class="fas fa-plus me-1"></i>
                    Add Metric
                  </button>
                </div>

                <div v-if="loadingQuestions" class="py-3">
                  <SkeletonLoader variant="list" :rows="3" />
                </div>

                <div v-else class="metrics-list">
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
                          <button class="btn-action-icon-sm" @click="openQuestionModal(activeCategory?.id, q)">
                            <i class="fas fa-pen"></i>
                          </button>
                          <button class="btn-action-icon-sm danger" @click="deleteQuestion(activeCategory?.id, q.id)">
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
              <div class="modal-footer">
                <p class="small text-muted mb-0 me-auto">
                  Weight allocation for this sector:
                  <strong>{{ Math.round(activeCategory?.weight * 100) }}%</strong>
                </p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Category Modal -->
        <div ref="catModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                  <i class="fas fa-folder-plus text-primary"></i>
                  {{ editCatId ? "Update Sector" : "New Evaluation Sector" }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
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
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" @click="saveCategory" :disabled="saving">
                  {{ saving ? "Processing..." : "Save Sector" }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Question Modal -->
        <div ref="qModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                  <i class="fas fa-question-circle text-primary"></i>
                  {{ editQId ? "Edit Item" : "New Metric Question" }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
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
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" @click="saveQuestion" :disabled="saving">
                  {{ saving ? "Publishing..." : "Publish Metric" }}
                </button>
              </div>
            </div>
          </div>
        </div>
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
import { confirmAction } from "../composables/useConfirm.js";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";

const route = useRoute();
const categories = ref([]);
const questions = ref([]);
const stats = ref({ total_weight: 0, total_questions: 0, total_categories: 0 });
const evaluateeType = ref('faculty');
const loading = ref(true);
const loadingQuestions = ref(false);
const saving = ref(false);
const showDrawer = ref(false);
const viewMode = ref("cards");

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

const { modalEl: catModalEl } = useBootstrapModal(showCatModal);
// Modal chaining: only one Bootstrap modal open at a time. Opening a
// question from the metrics modal parks the parent; the parent returns
// once the child is fully closed (sequenced via hidden events, no timers).
const resumeMetrics = ref(false);
const pendingQuestion = ref(false);
const { modalEl: qModalEl } = useBootstrapModal(showQModal, {
  onHidden: () => {
    if (resumeMetrics.value) {
      resumeMetrics.value = false;
      showDrawer.value = true;
    }
  },
});
const { modalEl: metricsModalEl } = useBootstrapModal(showDrawer, {
  onHidden: () => {
    if (pendingQuestion.value) {
      pendingQuestion.value = false;
      showQModal.value = true;
    }
  },
});

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
    resumeMetrics.value = false;
    pendingQuestion.value = false;
    showQModal.value = false;
    showDrawer.value = false;
    fetchStats();
    fetchCategories();
  },
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
  resumeMetrics.value = false;
  pendingQuestion.value = false;
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
  const result = await confirmAction({
    title: "Are you sure?",
    message: "Delete this sector and ALL associated metrics? This action is permanent.",
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
  // Ignore re-entry while a chained handoff is already in progress.
  if (pendingQuestion.value || showQModal.value) return;
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
  // Chain: park the metrics parent first; the child opens when the
  // parent's hide transition completes (see metrics modal onHidden).
  resumeMetrics.value = showDrawer.value;
  if (showDrawer.value) {
    pendingQuestion.value = true;
    showDrawer.value = false;
  } else {
    showQModal.value = true;
  }
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
  const result = await confirmAction({
    title: "Are you sure?",
    message: "Permanently remove this metric?",
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

/* View toggle (compact icon-only segmented control) */
.view-toggle-group {
  display: inline-flex;
  align-items: center;
  background: #eef0f6;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  padding: 3px;
  gap: 2px;
}

.view-toggle-btn {
  border: none;
  background: transparent;
  color: #9aa1b5;
  font-size: 0.85rem;
  width: 32px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 7px;
  transition: all 0.2s ease;
}

.view-toggle-btn:hover {
  color: var(--primary);
}

.view-toggle-btn.active {
  background: #fff;
  color: #191970;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
}

/* Sector Table view */
.sector-table-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: var(--card-radius);
  overflow: hidden;
}

.sector-table thead th {
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  background: var(--bg-light);
  border-bottom: 1px solid var(--border-light);
  padding: 0.85rem 1rem;
  white-space: nowrap;
}

.sector-table tbody td {
  padding: 0.9rem 1rem;
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}

.sector-table tbody tr:last-child td {
  border-bottom: none;
}

.sector-table-row {
  cursor: pointer;
  transition: background 0.15s ease;
}

.sector-table-row:hover {
  background: rgba(25, 25, 112, 0.03);
}

.sector-icon-box-sm {
  width: 40px;
  height: 40px;
  font-size: 1rem;
  border-radius: 0.9rem;
}

.metric-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-muted);
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 999px;
  padding: 0.25rem 0.75rem;
  white-space: nowrap;
}

.btn-manage-sm {
  border: none;
  background: transparent;
  color: var(--primary);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.4rem 0.6rem;
  border-radius: 8px;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-manage-sm:hover {
  background: rgba(25, 25, 112, 0.08);
  transform: translateX(2px);
}

.text-main {
  color: var(--text-dark);
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

.drawer-header {
  flex-shrink: 0;
}

.drawer-content {
  overflow-y: auto;
  min-height: 0;
  flex: 1;
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

.drawer-metric-card {
  background: var(--bg-light);
  padding: 1.25rem;
  border-radius: var(--card-radius);
  border: 1px solid var(--border-light);
  transition: all 0.2s;
  height: 100%;
  min-width: 0;
}

.drawer-metric-card:hover {
  border-color: var(--accent-color);
  background: var(--bg-card);
  transform: translateY(-2px);
}

.drawer-metric-card .flex-grow-1 {
  min-width: 0;
}

.drawer-metric-card p {
  overflow-wrap: break-word;
}

.metrics-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-width: 0;
}

@media (min-width: 768px) {
  .metrics-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: stretch;
  }
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
  padding: 0.85rem 1.25rem;
  border-radius: 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  color: var(--text-dark);
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
</style>
