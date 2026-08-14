<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Evaluate Office</template></Navbar>
      <div class="content-area">
        <div v-if="loading" class="py-4"><SkeletonLoader variant="form" :rows="3" /></div>
        <div v-else-if="submitted" class="text-center py-5">
          <div class="success-icon mb-3"><i class="fas fa-check-circle"></i></div>
          <h4 class="fw-800 mb-2">Thank You!</h4>
          <p class="text-muted mb-4">Your feedback for <strong>{{ office?.name }}</strong> has been submitted successfully.</p>
          <button class="btn btn-primary" @click="goBack"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</button>
        </div>
        <div v-else>
          <div class="office-header mb-4">
            <div class="office-icon"><i class="fas fa-building"></i></div>
            <div>
              <div class="badge bg-primary bg-opacity-10 text-primary fw-semibold mb-2">Office Evaluation</div>
              <h4 class="fw-800 mb-0">{{ office?.name }}</h4>
              <p class="text-muted mb-0">{{ office?.description }}</p>
            </div>
          </div>
          <div v-if="error" class="alert alert-danger mb-4"><i class="fas fa-exclamation-circle me-2"></i>{{ error }}</div>
          <div class="form-section mb-4">
            <label class="label-custom">Gender *</label>
            <div class="gender-group">
              <button type="button" v-for="g in genderOptions" :key="g.value" class="gender-btn" :class="{ active: gender === g.value }" @click="gender = g.value">
                <i :class="g.icon"></i> {{ g.label }}
              </button>
            </div>
          </div>
          <div v-for="cat in categories" :key="cat.id" class="category-card mb-4">
            <div class="category-header">
              <h6 class="fw-700 mb-0">{{ cat.category_name }}</h6>
              <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ Math.round(cat.weight * 100) }}%</span>
            </div>
            <div v-for="q in cat.questions" :key="q.id" class="question-row">
              <p class="question-text mb-2">{{ q.question_text }}</p>
              <div class="yesno-group">
                <button type="button" class="yesno-btn yes" :class="{ active: answers[q.id] === true }" @click="answers[q.id] = true">
                  <i class="fas fa-check-circle"></i> Yes
                </button>
                <button type="button" class="yesno-btn no" :class="{ active: answers[q.id] === false }" @click="answers[q.id] = false">
                  <i class="fas fa-times-circle"></i> No
                </button>
              </div>
            </div>
          </div>
          <div class="category-card mb-4">
            <div class="category-header"><h6 class="fw-700 mb-0">Comments / Suggestions</h6></div>
            <textarea v-model="comments" class="input-custom" rows="4" placeholder="Share your experience..."></textarea>
          </div>
          <div class="text-end">
            <button class="btn btn-primary btn-lg px-5" @click="submitFeedback" :disabled="submitting">
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
import { useRoute, useRouter } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import api from "../services/api.js";

const route = useRoute();
const router = useRouter();
const office = ref(null);
const categories = ref([]);
const answers = ref({});
const gender = ref("");
const comments = ref("");
const loading = ref(true);
const submitting = ref(false);
const submitted = ref(false);
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

function goBack() { router.push("/dashboard"); }

const genderOptions = [
  { value: "male", label: "Male", icon: "fas fa-mars" },
  { value: "female", label: "Female", icon: "fas fa-venus" },
  { value: "others", label: "Others", icon: "fas fa-user" },
];

async function submitFeedback() {
  if (!gender.value) { error.value = "Please select your gender."; return; }
  const questionIds = categories.value.flatMap((cat) => cat.questions.map((q) => q.id));
  const unanswered = questionIds.filter((id) => answers.value[id] === null || answers.value[id] === undefined).length;
  if (unanswered > 0) { error.value = `Please answer all ${unanswered} remaining question(s) with Yes or No.`; return; }
  error.value = ""; submitting.value = true;
  try {
    const payload = {
      office_id: route.params.officeId,
      visitor_type: "student",
      gender: gender.value,
      comments: comments.value,
      answers: questionIds.map((id) => ({ question_id: id, answer: answers.value[id] })),
    };
    await api.post("/office-feedback", payload);
    submitted.value = true;
  } catch (e) { error.value = e.response?.data?.message || "Failed to submit feedback."; } finally { submitting.value = false; }
}
</script>

<style scoped>
.office-header { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--card-radius); }
.office-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(0, 82, 255, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.form-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1.25rem; }
.label-custom { display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; }
.gender-group { display: flex; gap: 8px; flex-wrap: wrap; }
.gender-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.55rem 1.4rem; border: 2px solid var(--border-light); border-radius: 10px; background: var(--bg-card); color: var(--text-muted); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
.gender-btn:hover { border-color: var(--primary); color: var(--text-dark); }
.gender-btn.active { border-color: var(--primary); background: rgba(0, 82, 255, 0.06); color: var(--primary); }
.category-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--card-radius); overflow: hidden; }
.category-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: rgba(0, 82, 255, 0.02); }
.question-row { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light); }
.question-row:last-child { border-bottom: none; }
.question-text { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); }
.yesno-group { display: flex; gap: 8px; }
.yesno-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.55rem 1.4rem; border: 2px solid var(--border-light); border-radius: 10px; background: var(--bg-card); color: var(--text-muted); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
.yesno-btn:hover { border-color: var(--primary); color: var(--text-dark); }
.yesno-btn.yes.active { border-color: #22c55e; background: rgba(34, 197, 94, 0.08); color: #16a34a; }
.yesno-btn.no.active { border-color: #ef4444; background: rgba(239, 68, 68, 0.08); color: #dc2626; }
.input-custom { width: 100%; padding: 0.75rem 1.15rem; border-radius: 0!important; border: 2px solid var(--border-light); background: var(--bg-card); color: var(--text-dark); font-size: 0.9rem; transition: all 0.2s ease; }
.input-custom:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10, 39, 138, 0.1); }
.success-icon { font-size: 4rem; color: #22c55e; }
.alert-danger { border-radius: 12px; }
</style>
