<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>{{ t.evaluate_faculty }}</template></Navbar>

      <div class="content-area">
        <!-- Step 1: Select Faculty + Semester -->
        <div
          v-if="step === 1"
          class="card border-0 rounded-4 overflow-visible evaluation-step-card fade-in"
        >
          <div class="evaluatee-tab-panel">
          <div class="text-center mb-4 mt-3">
            <div class="mb-2">
              <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-2">
                Faculty Evaluation
              </span>
            </div>
            <div class="mb-3">
              <i class="fas fa-clipboard-list fa-3x text-primary" style="opacity: 0.8"></i>
            </div>
            <h4 class="fw-bold mb-1">{{ t.evaluate_faculty }}</h4>
            <p class="text-muted small">{{ t.select_faculty_desc }}</p>
          </div>
          <div class="px-3 pb-3">
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.faculty_member }}</label>
              <CustomSelect v-model="selectedFacultyData" :options="facultyOptions" placeholder="-- Select --" />
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.semester }}</label>
                <input type="text" class="form-control bg-light border-0 fw-semibold" :value="semester" readonly />
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.academic_year }}</label>
                <input type="text" class="form-control bg-light border-0 fw-semibold" :value="academicYear" readonly />
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.subject_code }} *</label>
                <input
                  type="text"
                  class="form-control bg-light border-0 fw-semibold"
                  :value="subjectCode"
                  placeholder="Select faculty first"
                  readonly
                />
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.year_section }} *</label>
                <input type="text" class="form-control bg-light border-0 fw-semibold" :value="yearSection" readonly />
              </div>
            </div>
            <div class="mt-3">
              <p class="text-muted small">
                <i class="fas fa-lock me-1"></i>
                {{ t.anonymous_note }}
              </p>
            </div>
            <button
              class="btn btn-primary w-100 mt-2"
              :disabled="!selectedFacultyData || !semester || !academicYear || (!subjectCode || !yearSection)"
              @click="loadQuestions"
            >
              <i class="fas fa-arrow-right me-2"></i>
              {{ t.continue_btn }}
            </button>
          </div>
          </div>
        </div>

        <!-- Step 2: Answer Questions -->
        <div v-if="step === 2">
          <div class="sticky-evaluation-header" :style="{ top: headerTopOffset }">
            <div class="progress" style="height: 6px; border-radius: 0">
              <div
                class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                :style="{ width: progressPercent + '%' }"
              ></div>
            </div>
            <div class="d-flex justify-content-between align-items-center py-2 px-3">
              <h5 class="mb-0 fw-bold">{{ t.evaluation_form }}</h5>
              <div class="text-muted small fw-bold">{{ answeredCount }} / {{ totalQuestions }}</div>
            </div>
          </div>

          <div v-if="loadingQ" class="py-4">
            <SkeletonLoader variant="form" :rows="3" />
          </div>

          <template v-else>
            <div v-for="cat in categories" :key="cat.id" class="mb-4">
              <div v-for="(q, qIndex) in cat.questions" :key="q.id" class="question-card fade-in">
                <div v-if="qIndex === 0" class="question-card-header">
                  {{ currentLang === 'tl' && cat.category_name_tl ? cat.category_name_tl : cat.category_name }}
                </div>
                <div class="question-card-body">
                  <div class="question-text">
                    {{ currentLang === 'tl' && q.question_text_tl ? q.question_text_tl : q.question_text }}
                  </div>

                  <div class="likert-scale">
                    <div v-for="n in 5" :key="n" class="likert-option">
                      <input
                        type="radio"
                        :id="'q' + q.id + '_' + n"
                        :name="'question_' + q.id"
                        :value="n"
                        v-model="answers[q.id]"
                      />
                      <label :for="'q' + q.id + '_' + n" class="likert-label">
                        <span class="likert-val">{{ n }}</span>
                        <span class="likert-text">{{ likertLabels[n] }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="submitError" class="alert alert-danger small">
              {{ submitError }}
            </div>
            <div v-if="submitSuccess" class="alert alert-success">
              <i class="fas fa-check-circle me-2"></i>
              {{ submitSuccess }}
            </div>

            <div class="mb-4">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 gap-2">
                <label class="form-label fw-bold mb-0">{{ t.comments_label }}</label>
                <button
                  v-if="comments.length >= 5"
                  class="btn btn-sm btn-outline-primary border-0 py-1 px-2 d-flex align-items-center gap-1"
                  @click="checkComment"
                  :disabled="analyzing || (user.role === 'student' && cooldownRemaining > 0)"
                >
                  <i class="fas" :class="analyzing ? 'fa-spinner fa-spin' : (user.role === 'student' && cooldownRemaining > 0 ? 'fa-clock' : 'fa-magic')"></i>
                  <span>{{ analyzing ? t.analyzing : (user.role === "student" && cooldownRemaining > 0 ? t.wait_cooldown.replace('{n}', cooldownRemaining) : t.suggest_improvement) }}</span>
                </button>
              </div>
              <textarea
                v-model="comments"
                class="form-control"
                rows="4"
                :placeholder="t.comments_placeholder"
              ></textarea>

              <!-- AI Feedback Area -->
              <div v-if="aiAnalysis" class="mt-2 p-3 rounded-3 border-0 shadow-sm fade-in" :class="aiBgClass">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="small">
                    <div class="fw-bold mb-1 d-flex align-items-center gap-2">
                      <i :class="aiIconClass"></i>
                      <span>{{ t.ai_feedback }}</span>
                      <span class="badge" :class="aiBadgeClass">{{ aiAnalysis.moderation_status }}</span>
                    </div>
                    <p class="mb-2 text-dark opacity-75">{{ aiStatusMessage }}</p>

                    <div v-if="aiAnalysis.suggestion && aiAnalysis.suggestion !== comments" class="mt-2 pt-2 border-top">
                      <div class="fw-bold small text-primary mb-1">{{ currentLang === 'en' ? 'Suggested version:' : 'Mungkahing bersyon:' }}</div>
                      <div class="fst-italic text-muted mb-2">"{{ aiAnalysis.suggestion }}"</div>
                      <button class="btn btn-sm btn-primary py-1 px-3 rounded-pill" @click="applySuggestion">
                        {{ t.apply_suggestion }}
                      </button>
                    </div>
                  </div>
                  <button class="btn-close small" style="font-size: 0.7rem" @click="aiAnalysis = null"></button>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-outline-secondary" @click="step = 1">← {{ t.back_btn }}</button>
              <button
                class="btn btn-success flex-fill"
                :disabled="answeredCount < totalQuestions || submitting || (aiAnalysis && aiAnalysis.moderation_status === 'inappropriate')"
                @click="submitEvaluation"
              >
                <i class="fas fa-paper-plane me-2"></i>
                {{ submitting ? t.submitting : t.submit_btn }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value]);

const headerTopOffset = ref('60px');

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});

const updateTopOffset = () => {
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    headerTopOffset.value = topbar.offsetHeight + 'px';
  }
};

const step = ref(1);
const evaluateeType = ref('faculty');
const facultyList = ref([]);
const facultyOptions = computed(() => {
  return [
    { label: "-- Select --", value: null },
    ...facultyList.value.map((f) => {
      const labelSuffix = ` (${f.subject_code})`;
      return {
        label: `${f.user?.name}${labelSuffix}${f.is_evaluated ? " ✓ " + t.value.evaluated_badge : ""}`,
        value: f,
        disabled: f.is_evaluated,
      };
    }),
  ];
});

async function fetchEvaluatees() {
  try {
    const res = await api.get("/evaluations/evaluatees", {
      params: { evaluatee_type: evaluateeType.value }
    });
    facultyList.value = res.data;
  } catch (e) {
    console.error("Failed to fetch evaluatees:", e);
  }
}

function setEvaluateeType(type) {
  evaluateeType.value = type;
  selectedFacultyData.value = null;
  fetchEvaluatees();
}
const selectedFacultyData = ref(null);
const selectedFaculty = computed(() => selectedFacultyData.value?.id || "");
const semester = ref("");
const academicYear = ref("");
const categories = ref([]);
const answers = ref({});
const subjectCode = computed(() => selectedFacultyData.value?.subject_code || "");
const yearSection = computed(() => selectedFacultyData.value?.section_name || "");
const comments = ref("");
const loadingQ = ref(false);
const submitting = ref(false);
const submitError = ref("");
const submitSuccess = ref("");

// AI Features
const aiAnalysis = ref(null);
const analyzing = ref(false);
const cooldownRemaining = ref(0);
let cooldownInterval = null;

const getCooldownKey = () => `ai_cooldown_end_${user.value.id || "guest"}`;

const startCooldownTimer = (seconds) => {
  cooldownRemaining.value = seconds;
  if (cooldownInterval) clearInterval(cooldownInterval);

  cooldownInterval = setInterval(() => {
    cooldownRemaining.value--;
    if (cooldownRemaining.value <= 0) {
      clearInterval(cooldownInterval);
      cooldownRemaining.value = 0;
      localStorage.removeItem(getCooldownKey());
    }
  }, 1000);
};

const aiBgClass = computed(() => {
  if (!aiAnalysis.value) return "";
  if (aiAnalysis.value.moderation_status === "inappropriate") return "bg-danger bg-opacity-10";
  if (aiAnalysis.value.moderation_status === "too_vague") return "bg-warning bg-opacity-10";
  return "bg-primary bg-opacity-10";
});

const aiIconClass = computed(() => {
  if (!aiAnalysis.value) return "";
  if (aiAnalysis.value.moderation_status === "inappropriate") return "fas fa-exclamation-triangle text-danger";
  if (aiAnalysis.value.moderation_status === "too_vague") return "fas fa-info-circle text-warning";
  return "fas fa-check-circle text-primary";
});

const aiBadgeClass = computed(() => {
  if (!aiAnalysis.value) return "";
  if (aiAnalysis.value.moderation_status === "inappropriate") return "bg-danger";
  if (aiAnalysis.value.moderation_status === "too_vague") return "bg-warning text-dark";
  return "bg-primary";
});

const aiStatusMessage = computed(() => {
  if (!aiAnalysis.value) return "";
  if (aiAnalysis.value.moderation_status === "inappropriate") {
    return aiAnalysis.value.moderation_reason || "This comment contains inappropriate language and cannot be submitted.";
  }
  if (aiAnalysis.value.moderation_status === "too_vague") {
    return aiAnalysis.value.moderation_reason || "This comment is a bit vague. Try to be more specific.";
  }
  return "Your feedback looks good! The AI has categorized this under " + aiAnalysis.value.category + ".";
});

async function checkComment() {
  if (comments.value.trim().length < 5) {
    aiAnalysis.value = null;
    return;
  }

  // Frontend cooldown check
  if (user.value.role === "student" && cooldownRemaining.value > 0) {
    Swal.fire({
      icon: "warning",
      title: "Cooldown Active",
      html: `Please wait before generating another feedback.<br><br><strong style="font-size: 1.2rem;">${cooldownRemaining.value}s remaining</strong>`,
      timer: cooldownRemaining.value * 1000,
      timerProgressBar: true,
      showConfirmButton: true,
      confirmButtonText: "Got it",
      confirmButtonColor: "#3085d6",
      didOpen: () => {
        const b = Swal.getHtmlContainer().querySelector("strong");
        const timerInterval = setInterval(() => {
          if (cooldownRemaining.value <= 0) {
            clearInterval(timerInterval);
            Swal.close();
          } else if (b) {
            b.textContent = `${cooldownRemaining.value}s remaining`;
          }
        }, 1000);
      },
    });
    return;
  }

  analyzing.value = true;
  try {
    const res = await api.post("/ai/analyze-comment", { comment: comments.value });
    aiAnalysis.value = res.data;

    // Set cooldown for students after successful analysis
    if (user.value.role === "student") {
      const endTime = Date.now() + 60000;
      localStorage.setItem(getCooldownKey(), endTime.toString());
      startCooldownTimer(60);
    }
  } catch (e) {
    if (e.response?.status === 429) {
      const remaining = e.response.data.remaining_seconds || 60;
      const endTime = Date.now() + remaining * 1000;
      localStorage.setItem(getCooldownKey(), endTime.toString());
      startCooldownTimer(remaining);

      Swal.fire({
        icon: "warning",
        title: "Cooldown Active",
        text: e.response.data.message || "Please wait before generating another feedback.",
        confirmButtonColor: "#3085d6",
      });
    } else {
      console.error("AI analysis failed", e);
    }
  } finally {
    analyzing.value = false;
  }
}

function applySuggestion() {
  if (aiAnalysis.value?.suggestion) {
    comments.value = aiAnalysis.value.suggestion;
    aiAnalysis.value = null;
  }
}

const likertLabels = computed(() => t.value.likert);

const currentYear = new Date().getFullYear();

const totalQuestions = computed(() => categories.value.reduce((s, c) => s + (c.questions?.length || 0), 0));
const answeredCount = computed(() => Object.keys(answers.value).length);
const progressPercent = computed(() => (totalQuestions.value ? (answeredCount.value / totalQuestions.value) * 100 : 0));

onMounted(async () => {
  updateTopOffset();
  window.addEventListener('resize', updateTopOffset);

  try {
    const setRes = await api.get("/settings");
    semester.value = setRes.data.active_semester || "";
    academicYear.value = setRes.data.active_academic_year || "";

    await fetchEvaluatees();

    // Initialize cooldown timer from localStorage
    const storedEnd = localStorage.getItem(getCooldownKey());
    if (storedEnd) {
      const remaining = Math.ceil((parseInt(storedEnd) - Date.now()) / 1000);
      if (remaining > 0) {
        startCooldownTimer(remaining);
      } else {
        localStorage.removeItem(getCooldownKey());
      }
    }
  } catch (e) {
    console.error("Error loading initial data", e);
  }
});

onUnmounted(() => {
  window.removeEventListener('resize', updateTopOffset);
});

async function loadQuestions() {
  loadingQ.value = true;
  step.value = 2;
  answers.value = {};
  try {
    const res = await api.get("/categories", {
      params: { evaluatee_type: evaluateeType.value }
    });
    categories.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingQ.value = false;
  }
}

async function submitEvaluation() {
  submitting.value = true;
  submitError.value = "";
  submitSuccess.value = "";
  const payload = {
    evaluatee_id: selectedFaculty.value,
    evaluatee_type: evaluateeType.value,
    faculty_id: selectedFaculty.value,
    semester: semester.value,
    academic_year: academicYear.value,
    subject_code: subjectCode.value,
    year_section: yearSection.value,
    comments: comments.value,
    ai_analysis: aiAnalysis.value,
    answers: Object.entries(answers.value).map(([question_id, rating]) => ({
      question_id,
      rating,
    })),
  };
  try {
    await api.post("/evaluations", payload);
    Swal.fire({
      icon: "success",
      title: "Submitted!",
      text: "Thank you! Your evaluation has been submitted anonymously.",
      confirmButtonText: "OK",
      confirmButtonColor: "#3085d6",
    });
    step.value = 1;
    selectedFacultyData.value = null;

    // Refresh evaluatees list to show the evaluated professor as disabled
    await fetchEvaluatees();
  } catch (e) {
    Swal.fire({
      icon: "error",
      title: "Submission Failed",
      text: e.response?.data?.message || "Submission failed.",
      confirmButtonColor: "#d33",
    });
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.sticky-evaluation-header {
  position: sticky;
  top: 60px;
  z-index: 1000;
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-light);
  backdrop-filter: blur(10px);
}

.question-card {
  background: var(--bg-card);
  border-radius: 1rem;
  border: 1px solid var(--border-light);
  overflow: hidden;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease;
}

.question-card:hover {
  transform: translateY(-2px);
}

.question-card-header {
  background: #0a278a;
  color: white;
  padding: 0.75rem 1.25rem;
  font-weight: 800;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.question-card-body {
  padding: 1.5rem;
}

.question-text {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 1.5rem;
}

.likert-scale {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.likert-option {
  position: relative;
}

.likert-option input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.likert-label {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 1.25rem;
  padding: 0.85rem 1.5rem;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
}

.likert-option input:checked + .likert-label {
  background: #0a278a;
  border-color: #0a278a;
  color: white;
}

.likert-val {
  width: 32px;
  height: 32px;
  background: white;
  color: #0a278a;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.likert-option input:checked + .likert-label .likert-val {
  background: white;
  color: #0a278a;
}

.likert-text {
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.9rem;
  letter-spacing: 0.5px;
}

.fade-in {
  animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (min-width: 768px) {
  .likert-scale {
    flex-direction: row;
    justify-content: space-between;
  }
  
  .likert-option {
    flex: 1;
  }
  
  .likert-label {
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1rem;
    border-radius: 1rem;
    text-align: center;
    height: 100%;
  }
  
  .likert-val {
    margin: 0 auto;
  }
  
  .likert-text {
    font-size: 0.75rem;
    width: 100%;
    text-align: center;
  }
}

.evaluation-step-card {
  max-width: 600px;
  margin: 0 auto;
  position: relative;
  z-index: 900;
}

.evaluatee-tabs {
  display: flex;
  border-bottom: 1px solid var(--border-light) !important;
  padding: 0 0.5rem;
  background: var(--bg-card, #fff);
  border-radius: 1rem 1rem 0 0;
}

.evaluatee-tabs .nav-link.evaluatee-tab {
  width: 100%;
  border: none;
  border-bottom: 3px solid transparent;
  border-radius: 0;
  margin-bottom: -1px;
  padding: 0.875rem 1rem;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-muted);
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s ease, border-color 0.2s ease;
}

.evaluatee-tabs .nav-link.evaluatee-tab:hover,
.evaluatee-tabs .nav-link.evaluatee-tab:focus {
  color: var(--primary, #0a278a);
  border-color: transparent;
  background: transparent;
  isolation: auto;
}

.evaluatee-tabs .nav-link.evaluatee-tab.active {
  color: var(--primary, #0a278a);
  background: transparent;
  border-bottom-color: var(--primary, #0a278a);
  font-weight: 700;
}

.evaluatee-tab-panel {
  padding-top: 0.25rem;
}
</style>
