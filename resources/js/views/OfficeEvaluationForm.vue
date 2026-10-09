<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Evaluate Office</template></Navbar>
      <div class="content-area">
        <div v-if="loading" class="py-4"><SkeletonLoader variant="form" :rows="3" /></div>
        <div v-else class="office-eval-page">
          <div class="office-header">
            <div class="office-icon"><i class="fas fa-building"></i></div>
            <div class="office-header__body">
              <span class="office-chip">Office Evaluation</span>
              <h4 class="office-title">{{ office?.name }}</h4>
              <p class="office-desc">{{ office?.description }}</p>
            </div>
          </div>

          <div v-if="error" class="eval-alert">
            <i class="fas fa-circle-exclamation"></i>
            <span>{{ error }}</span>
          </div>

          <div class="form-section mb-4">
            <label class="label-custom">Gender <span class="req">*</span></label>
            <div class="gender-group">
              <button
                type="button"
                v-for="g in genderOptions"
                :key="g.value"
                class="gender-btn"
                :class="{ active: gender === g.value }"
                :aria-pressed="gender === g.value"
                @click="gender = g.value"
              >
                <i :class="g.icon"></i> {{ g.label }}
              </button>
            </div>
          </div>

          <div v-for="cat in categories" :key="cat.id" class="category-card mb-4">
            <div class="category-header">
              <h6 class="category-title">{{ cat.category_name }}</h6>
              <span class="weight-chip">{{ Math.round(cat.weight * 100) }}%</span>
            </div>
            <div v-for="q in cat.questions" :key="q.id" class="question-row">
              <p class="question-text mb-3">{{ q.question_text }}</p>
              <div class="yesno-group">
                <button
                  type="button"
                  class="yesno-btn yes"
                  :class="{ active: answers[q.id] === true }"
                  :aria-pressed="answers[q.id] === true"
                  @click="answers[q.id] = true"
                >
                  <i class="fas fa-check-circle"></i> Yes
                </button>
                <button
                  type="button"
                  class="yesno-btn no"
                  :class="{ active: answers[q.id] === false }"
                  :aria-pressed="answers[q.id] === false"
                  @click="answers[q.id] = false"
                >
                  <i class="fas fa-times-circle"></i> No
                </button>
              </div>
            </div>
          </div>

          <div class="category-card mb-4">
            <div class="category-header"><h6 class="category-title">Comments / Suggestions</h6></div>
            <div class="card-body-pad">
              <textarea
                v-model="comments"
                class="input-custom"
                rows="4"
                placeholder="Share your experience..."
              ></textarea>
            </div>
          </div>

          <div class="text-center">
            <button class="btn btn-primary btn-lg px-5 submit-btn" @click="submitFeedback" :disabled="submitting">
              <i class="fas fa-paper-plane me-2"></i>{{ submitting ? "Submitting..." : "Submit Feedback" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";
import { getDeviceId } from "../utils/device.js";
import { notifySuccess } from "../composables/useSnackbar.js";

const route = useRoute();
const office = ref(null);
const categories = ref([]);
const answers = ref({});
const gender = ref("");
const comments = ref("");
const loading = ref(true);
const submitting = ref(false);
const error = ref("");

onMounted(async () => {
  const officeId = route.params.officeId;
  try {
    const [offRes, catRes] = await Promise.all([
      api.get(`/offices/${officeId}`),
      api.get("/office-categories?paginate=false"),
    ]);
    office.value = offRes.data;
    categories.value = catRes.data;
    categories.value.forEach((cat) => cat.questions.forEach((q) => { answers.value[q.id] = null; }));
  } catch (e) { error.value = "Failed to load office data."; } finally { loading.value = false; }
});

const genderOptions = [
  { value: "male", label: "Male", icon: "fas fa-mars" },
  { value: "female", label: "Female", icon: "fas fa-venus" },
  { value: "others", label: "Others", icon: "fas fa-user" },
];

function resetForm() {
  gender.value = "";
  comments.value = "";
  categories.value.forEach((cat) => cat.questions.forEach((q) => { answers.value[q.id] = null; }));
}

async function submitFeedback() {
  if (!gender.value) { error.value = "Please select your gender."; return; }
  const questionIds = categories.value.flatMap((cat) => cat.questions.map((q) => q.id));
  const unanswered = questionIds.filter((id) => answers.value[id] === null || answers.value[id] === undefined).length;
  if (unanswered > 0) { error.value = `Please answer all ${unanswered} remaining question(s) with Yes or No.`; return; }
  error.value = ""; submitting.value = true;
  try {
    const payload = {
      office_id: route.params.officeId,
      device_id: getDeviceId(),
      visitor_type: "student",
      gender: gender.value,
      comments: comments.value,
      answers: questionIds.map((id) => ({ question_id: id, answer: answers.value[id] })),
    };
    await api.post("/office-feedback", payload);
    notifySuccess(
      `Your feedback for ${office.value?.name || "this office"} has been submitted successfully.`,
      { title: "Thank You!", duration: 5000 }
    );
    resetForm();
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (e) {
    error.value = e.response?.data?.message || "Failed to submit feedback.";
    window.scrollTo({ top: 0, behavior: "smooth" });
  } finally { submitting.value = false; }
}
</script>

<style scoped>
/* ── Google / Material tokens (scoped to this page) ───────────── */
.office-eval-page {
  --g-blue: #4285f4;
  --g-blue-dark: #1a73e8;
  --g-blue-fill: #e8f0fe;
  --g-blue-text: #1a73e8;
  --g-green: #34a853;
  --g-green-text: #137333;
  --g-red: #ea4335;
  --g-red-text: #c5221f;
  --g-outline: var(--border-color);
  --g-elev-1: 0 1px 2px rgba(60, 64, 67, 0.1), 0 1px 3px 1px rgba(60, 64, 67, 0.06);
  --g-elev-2: 0 1px 3px rgba(60, 64, 67, 0.12), 0 4px 8px 3px rgba(60, 64, 67, 0.08);
}

[data-theme="dark"] .office-eval-page {
  --g-blue-fill: rgba(138, 180, 248, 0.16);
  --g-blue-text: #8ab4f8;
  --g-green-text: #34a853;
  --g-red-text: #f28b82;
}

/* Keep side gutters on small screens, where .content-area padding is 0 */
@media (max-width: 576px) {
  .office-eval-page {
    padding-top: 1.25rem;
    padding-bottom: 2rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

/* ── Office header card ───────────────────────────────────────── */
.office-header {
  display: flex;
  align-items: center;
  gap: 1.1rem;
  padding: 1.5rem 1.75rem;
  margin-bottom: 1.5rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  box-shadow: var(--g-elev-1);
}

.office-icon {
  width: 60px;
  height: 60px;
  border-radius: 18px;
  background: rgba(66, 133, 244, 0.12);
  color: var(--g-blue);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  flex-shrink: 0;
}

.office-header__body {
  min-width: 0;
}

.office-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.3rem 0.75rem;
  margin-bottom: 0.5rem;
  border-radius: 999px;
  background: var(--g-blue-fill);
  color: var(--g-blue-text);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.office-title {
  margin: 0 0 0.2rem;
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text-dark);
}

.office-desc {
  margin: 0;
  max-width: 60ch;
  font-size: 0.92rem;
  line-height: 1.55;
  color: var(--text-muted);
}

/* ── Inline error alert ───────────────────────────────────────── */
.eval-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  padding: 0.85rem 1.1rem;
  margin-bottom: 1.25rem;
  border-radius: 12px;
  background: rgba(234, 67, 94, 0.08);
  border: 1px solid rgba(234, 67, 94, 0.35);
  color: var(--g-red-text);
  font-size: 0.88rem;
  font-weight: 500;
}

.eval-alert i {
  color: var(--g-red);
  margin-top: 0.12rem;
}

/* ── Cards ────────────────────────────────────────────────────── */
.form-section,
.category-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  box-shadow: var(--g-elev-1);
  overflow: hidden;
}

.form-section {
  padding: 1.4rem 1.5rem;
}

.category-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-light);
}

.category-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text-dark);
}

.weight-chip {
  flex-shrink: 0;
  padding: 0.25rem 0.7rem;
  border-radius: 999px;
  background: var(--g-blue-fill);
  color: var(--g-blue-text);
  font-size: 0.72rem;
  font-weight: 700;
}

.question-row {
  padding: 1.15rem 1.5rem;
  border-bottom: 1px solid var(--border-light);
}

.question-row:last-child {
  border-bottom: none;
}

.question-text {
  font-size: 0.93rem;
  font-weight: 500;
  line-height: 1.5;
  color: var(--text-dark);
}

.card-body-pad {
  padding: 1.25rem 1.5rem;
}

/* ── Section label (Material overline) ────────────────────────── */
.office-eval-page .label-custom {
  display: block;
  margin-bottom: 0.85rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.label-custom .req {
  color: var(--g-red);
}

/* ── Gender choice chips ──────────────────────────────────────── */
.gender-group {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.gender-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0.7rem 1.25rem;
  border: 1px solid var(--g-outline);
  border-radius: 999px;
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.gender-btn:hover {
  background: var(--bg-light);
  color: var(--text-dark);
}

.gender-btn:focus-visible {
  outline: 2px solid var(--g-blue);
  outline-offset: 2px;
}

.gender-btn.active {
  background: var(--g-blue-fill);
  border-color: var(--g-blue);
  color: var(--g-blue-text);
  font-weight: 600;
}

.gender-btn.active i {
  color: var(--g-blue);
}

/* ── Yes / No segmented pills ─────────────────────────────────── */
.yesno-group {
  display: flex;
  gap: 10px;
}

.yesno-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 116px;
  padding: 0.6rem 1.5rem;
  border: 1px solid var(--g-outline);
  border-radius: 999px;
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.yesno-btn i {
  opacity: 0.5;
  transition: opacity 0.15s ease, color 0.15s ease;
}

.yesno-btn:hover {
  background: var(--bg-light);
  color: var(--text-dark);
}

.yesno-btn:focus-visible {
  outline: 2px solid var(--g-blue);
  outline-offset: 2px;
}

.yesno-btn.active {
  font-weight: 700;
}

.yesno-btn.active i {
  opacity: 1;
}

.yesno-btn.yes.active {
  background: rgba(52, 168, 83, 0.12);
  border-color: var(--g-green);
  color: var(--g-green-text);
}

.yesno-btn.yes.active i {
  color: var(--g-green);
}

.yesno-btn.no.active {
  background: rgba(234, 67, 94, 0.1);
  border-color: var(--g-red);
  color: var(--g-red-text);
}

.yesno-btn.no.active i {
  color: var(--g-red);
}

/* ── Comments textarea (outlined field) ───────────────────────── */
.office-eval-page .input-custom {
  width: 100%;
  padding: 0.85rem 1rem;
  border: 1px solid var(--g-outline);
  border-radius: 12px;
  background: var(--bg-card);
  color: var(--text-dark);
  font-size: 0.92rem;
  line-height: 1.5;
  min-height: 40px;
  resize: vertical;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.office-eval-page .input-custom::placeholder {
  color: var(--text-muted);
  opacity: 0.75;
}

.office-eval-page .input-custom:focus {
  outline: none;
  border-color: var(--g-blue);
  box-shadow: 0 0 0 1px var(--g-blue);
}

/* ── Submit (filled Material button) ──────────────────────────── */
.office-eval-page .submit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.8rem 2.5rem;
  border: none;
  border-radius: 999px;
  background: var(--g-blue-dark);
  color: #ffffff;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  box-shadow: 0 1px 2px rgba(66, 133, 244, 0.35), 0 4px 14px -4px rgba(66, 133, 244, 0.5);
  transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.office-eval-page .submit-btn:hover:not(:disabled) {
  background: #1b66c9;
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(66, 133, 244, 0.35), 0 8px 20px -6px rgba(66, 133, 244, 0.55);
}

.office-eval-page .submit-btn:active:not(:disabled) {
  transform: translateY(0) scale(0.98);
}

.office-eval-page .submit-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.4);
}

.office-eval-page .submit-btn:disabled {
  background: #9aa0a6;
  box-shadow: none;
  transform: none;
}

/* ── Small screens ────────────────────────────────────────────── */
@media (max-width: 576px) {
  .office-header {
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1.25rem;
  }

  .office-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    font-size: 1.35rem;
  }

  .office-title {
    font-size: 1.25rem;
  }

  .form-section,
  .category-header,
  .question-row,
  .card-body-pad {
    padding-left: 1.1rem;
    padding-right: 1.1rem;
  }

  .gender-btn {
    padding: 0.65rem 0.5rem;
    gap: 6px;
    font-size: 0.82rem;
  }

  .yesno-group {
    width: 100%;
  }

  .yesno-btn {
    flex: 1;
    min-width: 0;
    padding: 0.6rem 1rem;
  }
}
</style>
