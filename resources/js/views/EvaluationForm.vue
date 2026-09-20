<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>{{ t.evaluate_faculty }}</template></Navbar>

      <div class="content-area">
        <!-- Step 1: Select Faculty + Semester -->
        <div
          v-if="step === 1"
          class="card border-0 overflow-visible evaluation-step-card fade-in"
        >
          <div class="evaluatee-tab-panel">
          <div class="text-center mb-4 mt-3">
            <div class="mb-3">
              <i class="fas fa-clipboard-list fa-3x text-primary" style="opacity: 0.8"></i>
            </div>
            <h4 class="fw-bold mb-1">{{ t.evaluate_faculty }}</h4>
            <p class="text-muted small">{{ t.select_faculty_desc }}</p>
          </div>
          <div class="px-3 pb-3">
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.faculty_member }}</label>
              <CustomSelect v-model="selectedFacultyId" :options="facultyOptions" placeholder="-- Select --" />
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
              :disabled="!selectedFacultyId || !semester || !academicYear || (!subjectCode || !yearSection)"
              @click="loadQuestions"
            >
              <i class="fas fa-arrow-right me-2"></i>
              {{ t.continue_btn }}
            </button>
          </div>
          </div>
        </div>

        <!-- Step 2: Answer Questions -->
        <div v-if="step === 2" class="eval-wrap">
          <div v-if="loadingQ" class="py-4">
            <SkeletonLoader variant="form" :rows="3" />
          </div>

          <template v-else>
            <div v-for="cat in categories" :key="cat.id" class="eval-category fade-in">
              <div class="eval-category-head">
                <span class="eval-category-name">
                  {{ currentLang === 'tl' && cat.category_name_tl ? cat.category_name_tl : cat.category_name }}
                </span>
                <span class="eval-category-count">{{ cat.questions?.length || 0 }} questions</span>
              </div>
              <div v-for="q in cat.questions" :key="q.id" class="eval-question">
                <p class="question-text">
                  {{ currentLang === 'tl' && q.question_text_tl ? q.question_text_tl : q.question_text }}
                </p>

                <div class="likert-scale" role="radiogroup">
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

            <div v-if="submitError" class="alert alert-danger small">
              {{ submitError }}
            </div>
            <div v-if="submitSuccess" class="alert alert-success">
              <i class="fas fa-check-circle me-2"></i>
              {{ submitSuccess }}
            </div>

            <div class="mb-4">
              <!-- AI Feedback Area -->
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 gap-2">
                <label class="form-label fw-bold mb-0">{{ t.comments_label }}</label>
                <!-- <button
                  v-if="comments.length >= 5"
                  class="btn btn-sm btn-outline-primary border-0 py-1 px-2 d-flex align-items-center gap-1"
                  @click="checkComment"
                  :disabled="analyzing || (user.role === 'student' && cooldownRemaining > 0)"
                >
                  <i class="fas" :class="analyzing ? 'fa-spinner fa-spin' : (user.role === 'student' && cooldownRemaining > 0 ? 'fa-clock' : 'fa-magic')"></i>
                  <span>{{ analyzing ? t.analyzing : (user.role === "student" && cooldownRemaining > 0 ? t.wait_cooldown.replace('{n}', cooldownRemaining) : t.suggest_improvement) }}</span>
                </button> -->
              </div>
              <textarea
                v-model="comments"
                class="form-control"
                rows="4"
                :placeholder="t.comments_placeholder"
              ></textarea>

              <!-- AI Feedback Area -->
              <div v-if="aiAnalysis" class="mt-2 p-3 border-0 fade-in" :class="aiBgClass">
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

            <div class="d-flex gap-2 mt-3 eval-actions">
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

          <!-- Floating circular progress, bottom-right -->
          <div
            v-if="!loadingQ && totalQuestions > 0"
            class="eval-fab"
            :class="{ 'is-complete': answeredCount >= totalQuestions }"
            role="progressbar"
            aria-label="Evaluation progress"
            :aria-valuenow="answeredCount"
            aria-valuemin="0"
            :aria-valuemax="totalQuestions"
            :title="answeredCount + '/' + totalQuestions + ' answered'"
          >
            <svg class="eval-fab__ring" viewBox="0 0 64 64" aria-hidden="true">
              <circle class="eval-fab__track" cx="32" cy="32" :r="ringRadius" />
              <circle
                class="eval-fab__fill"
                cx="32"
                cy="32"
                :r="ringRadius"
                :stroke-dasharray="ringCircumference"
                :stroke-dashoffset="ringDashOffset"
              />
            </svg>
            <div class="eval-fab__label">
              <strong>{{ Math.round(progressPercent) }}%</strong>
              <small>{{ answeredCount }}/{{ totalQuestions }}</small>
            </div>
          </div>
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
import { notifyInfo } from "../composables/useSnackbar.js";

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value]);

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});

// Small screens get non-blocking toasts instead of centered modals.
const isSmallScreen = () =>
  typeof window !== "undefined" &&
  (window.matchMedia?.("(max-width: 576px)").matches ?? window.innerWidth <= 576);

function showAlert({ icon, title, text, html, timer, timerProgressBar, confirmButtonText = "OK", confirmButtonColor = "#3085d6", didOpen }) {
  if (isSmallScreen()) {
    return Swal.fire({
      toast: true,
      position: "top-end",
      icon,
      title,
      text,
      showConfirmButton: false,
      timer: timer ?? 3500,
      timerProgressBar: timerProgressBar ?? true,
    });
  }
  return Swal.fire({
    icon,
    title,
    text,
    html,
    timer,
    timerProgressBar,
    showConfirmButton: true,
    confirmButtonText,
    confirmButtonColor,
    didOpen,
  });
}

const step = ref(1);
const evaluateeType = ref('faculty');
const facultyList = ref([]);
const facultyOptions = computed(() => {
  return [
    { label: "-- Select --", value: "" },
    ...facultyList.value.map((f) => {
      const labelSuffix = ` (${f.subject_code})`;
      return {
        label: `${f.user?.name}${labelSuffix}${f.is_evaluated ? " ✓ " + t.value.evaluated_badge : ""}`,
        value: f.id,
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
  selectedFacultyId.value = "";
  fetchEvaluatees();
}
const selectedFacultyId = ref("");
const selectedFacultyData = computed(() => facultyList.value.find((f) => f.id === selectedFacultyId.value) || null);
const selectedFaculty = computed(() => selectedFacultyId.value || "");
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
    if (isSmallScreen()) {
      showAlert({
        icon: "warning",
        title: "Cooldown Active",
        text: `Please wait ${cooldownRemaining.value}s before generating another feedback.`,
        timer: cooldownRemaining.value * 1000,
      });
      return;
    }
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

      showAlert({
        icon: "warning",
        title: "Cooldown Active",
        text: e.response.data.message || "Please wait before generating another feedback.",
      });
    } else {
      console.error("AI analysis failed", e);
      notifyInfo("Your comment can still be submitted. Please try the suggestion again later.", {
        title: "AI analysis unavailable",
      });
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

// Floating circular progress ring (SVG)
const ringRadius = 26;
const ringCircumference = 2 * Math.PI * ringRadius;
const ringDashOffset = computed(() => ringCircumference * (1 - progressPercent.value / 100));

onMounted(async () => {
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
  if (cooldownInterval) clearInterval(cooldownInterval);
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
    showAlert({
      icon: "success",
      title: "Submitted!",
      text: "Thank you! Your evaluation has been submitted anonymously.",
    });
    step.value = 1;
    selectedFacultyId.value = "";

    // Refresh evaluatees list to show the evaluated professor as disabled
    await fetchEvaluatees();
  } catch (e) {
    showAlert({
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
/* ── Centered reading column: side margins on all screens ── */
.eval-wrap {
  max-width: 880px;
  margin: 0 auto;
  padding: 0.5rem 2rem 7rem;
}

/* ── Floating circular progress, bottom-right ── */
.eval-fab {
  position: fixed;
  right: 1.5rem;
  bottom: 1.5rem;
  width: 76px;
  height: 76px;
  z-index: 1030;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-light);
  border-radius: 50%;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
  pointer-events: none;
}

.eval-fab__ring {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.eval-fab__track {
  fill: none;
  stroke: var(--border-light);
  stroke-width: 6;
}

.eval-fab__fill {
  fill: none;
  stroke: var(--primary, #191970);
  stroke-width: 6;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.35s ease;
}

.eval-fab.is-complete .eval-fab__fill {
  stroke: #16a34a;
}

.eval-fab__label {
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.1;
}

.eval-fab__label strong {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--text-dark);
  font-variant-numeric: tabular-nums;
}

.eval-fab__label small {
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}

.eval-fab.is-complete .eval-fab__label strong {
  color: #16a34a;
}

/* ── Minimal category + divider layout (no cards) ── */
.eval-category {
  margin-bottom: 2rem;
}

.eval-category-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  padding-bottom: 0.6rem;
  border-bottom: 2px solid var(--text-dark);
  margin-bottom: 0.25rem;
}

.eval-category-name {
  font-weight: 800;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-dark);
}

.eval-category-count {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-muted);
  white-space: nowrap;
}

.eval-question {
  padding: 1.15rem 0 1.25rem;
  border-bottom: 1px solid var(--border-light);
}

.eval-question:last-child {
  border-bottom: none;
}

.question-text {
  font-size: 1rem;
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 1rem;
  line-height: 1.5;
}

/* ── Minimal likert options with dividers ── */
.likert-scale {
  display: flex;
  flex-direction: row;
  align-items: stretch;
}

.likert-option {
  position: relative;
  flex: 1;
  min-width: 0;
}

.likert-option + .likert-option {
  border-left: 1px solid var(--border-light);
}

.likert-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.likert-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  padding: 0.7rem 0.5rem;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.15s ease;
  height: 100%;
  text-align: center;
}

.likert-label:hover {
  background: rgba(25, 25, 112, 0.04);
}

.likert-option input:focus-visible + .likert-label {
  outline: 2px solid var(--primary, #191970);
  outline-offset: 2px;
}

.likert-option input:checked + .likert-label {
  background: rgba(25, 25, 112, 0.06);
}

.likert-val {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 1.5px solid var(--border-color);
  background: transparent;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.9rem;
  transition: all 0.15s ease;
}

.likert-option input:checked + .likert-label .likert-val {
  background: var(--primary, #191970);
  border-color: var(--primary, #191970);
  color: #fff;
}

.likert-text {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.68rem;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  line-height: 1.3;
}

.likert-option input:checked + .likert-label .likert-text {
  color: var(--primary, #191970);
  font-weight: 700;
}

.fade-in {
  animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 767.98px) {
  .eval-wrap {
    padding: 0.25rem 1.25rem 7rem;
  }

  .eval-fab {
    right: 1rem;
    bottom: 1rem;
    width: 68px;
    height: 68px;
  }

  .eval-actions {
    padding-bottom: 1rem;
  }

  .likert-scale {
    flex-direction: column;
    align-items: stretch;
  }

  .likert-option + .likert-option {
    border-left: none;
    border-top: 1px solid var(--border-light);
  }

  .likert-label {
    flex-direction: row;
    justify-content: flex-start;
    text-align: left;
    gap: 0.85rem;
    padding: 0.6rem 0.25rem;
  }

  .likert-val {
    width: 30px;
    height: 30px;
    font-size: 0.82rem;
    flex-shrink: 0;
  }

  .likert-text {
    font-size: 0.72rem;
  }

  .eval-question {
    padding: 1rem 0 1.1rem;
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
  color: var(--primary, #191970);
  border-color: transparent;
  background: transparent;
  isolation: auto;
}

.evaluatee-tabs .nav-link.evaluatee-tab.active {
  color: var(--primary, #191970);
  background: transparent;
  border-bottom-color: var(--primary, #191970);
  font-weight: 700;
}

.evaluatee-tab-panel {
  padding-top: 0.25rem;
}
</style>
