<template>
  <div class="qr-feedback-page">
    <div class="qr-container">
      <div v-if="loading" class="py-4"><SkeletonLoader variant="form" :rows="2" /></div>
      <div v-else-if="submitted" class="text-center py-5">
        <div class="success-icon mb-3"><i class="fas fa-check-circle"></i></div>
        <h4 class="fw-800 mb-2">Thank You!</h4>
        <p class="text-muted mb-4">Your feedback for <strong>{{ office?.name }}</strong> has been submitted.</p>
        <button class="btn btn-primary" @click="resetForm">Submit Another Feedback</button>
      </div>
      <div v-else-if="error && !office" class="text-center py-5">
        <div class="error-icon mb-3"><i class="fas fa-exclamation-triangle"></i></div>
        <h4 class="fw-800 mb-2">Invalid QR Code</h4>
        <p class="text-muted">{{ error }}</p>
      </div>
      <div v-else>
        <div class="office-header mb-4 text-center">
          <div class="office-icon mx-auto mb-2"><i class="fas fa-building"></i></div>
          <h4 class="fw-800 mb-1">{{ office?.name }}</h4>
          <p class="text-muted small mb-0">{{ office?.description }}</p>
        </div>

        <div v-if="formError" class="alert alert-danger mb-3"><i class="fas fa-exclamation-circle me-2"></i>{{ formError }}</div>

        <!-- Visitor Type Selection -->
        <div class="form-section mb-4">
          <label class="label-custom">I am a... *</label>
          <div class="visitor-types">
            <button v-for="vt in visitorTypes" :key="vt.value" class="visitor-btn" :class="{ active: form.visitor_type === vt.value }" @click="form.visitor_type = vt.value">
              <i :class="vt.icon"></i>
              <span>{{ vt.label }}</span>
            </button>
          </div>
        </div>

        <!-- Gender -->
        <div class="form-section mb-4">
          <label class="label-custom">Gender *</label>
          <div class="visitor-types">
            <button v-for="g in genderOptions" :key="g.value" class="visitor-btn" :class="{ active: form.gender === g.value }" @click="form.gender = g.value">
              <i :class="g.icon"></i>
              <span>{{ g.label }}</span>
            </button>
          </div>
        </div>

        <!-- Non-Student Info -->
        <div v-if="['parent','visitor','others'].includes(form.visitor_type)" class="form-section mb-4">
          <label class="label-custom">Purpose of Visit</label>
          <input v-model="form.purpose_of_visit" class="input-custom" placeholder="Optional" />
        </div>

        <!-- Ratings -->
        <div v-if="form.visitor_type" class="ratings-section">
          <div v-for="cat in categories" :key="cat.id" class="category-card mb-3">
            <div class="category-header"><h6 class="fw-700 mb-0">{{ cat.category_name }}</h6></div>
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
            <textarea v-model="form.comments" class="input-custom" rows="3" placeholder="Share your experience..."></textarea>
          </div>
          <div class="text-center">
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
import { useRoute } from "vue-router";
import api from "../services/api.js";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import { getDeviceId } from "../utils/device.js";

const route = useRoute();
const office = ref(null);
const categories = ref([]);
const answers = ref({});
const loading = ref(true);
const submitting = ref(false);
const submitted = ref(false);
const error = ref("");
const formError = ref("");

const visitorTypes = [
  { value: "student", label: "Student", icon: "fas fa-user-graduate" },
  { value: "parent", label: "Parent", icon: "fas fa-users" },
  { value: "faculty", label: "Faculty", icon: "fas fa-chalkboard-teacher" },
  { value: "alumni", label: "Alumni", icon: "fas fa-user-tie" },
  { value: "visitor", label: "Visitor", icon: "fas fa-id-badge" },
  { value: "others", label: "Others", icon: "fas fa-user" },
];

const genderOptions = [
  { value: "male", label: "Male", icon: "fas fa-mars" },
  { value: "female", label: "Female", icon: "fas fa-venus" },
  { value: "others", label: "Others", icon: "fas fa-user" },
];

const form = ref({ visitor_type: "", gender: "", purpose_of_visit: "", comments: "" });

onMounted(async () => {
  const token = route.params.token;
  try {
    const res = await api.get(`/qr/${token}`);
    office.value = res.data.office;
    const catRes = await api.get("/office-categories?paginate=false");
    categories.value = catRes.data;
    categories.value.forEach((cat) => cat.questions.forEach((q) => { answers.value[q.id] = null; }));
  } catch (e) { error.value = "This QR code is invalid or inactive."; } finally { loading.value = false; }
});

function resetForm() {
  submitted.value = false;
  form.value = { visitor_type: "", gender: "", purpose_of_visit: "", comments: "" };
  Object.keys(answers.value).forEach((k) => { answers.value[k] = null; });
}

async function submitFeedback() {
  if (!form.value.visitor_type) { formError.value = "Please select your visitor type."; return; }
  if (!form.value.gender) { formError.value = "Please select your gender."; return; }
  const questionIds = categories.value.flatMap((cat) => cat.questions.map((q) => q.id));
  const unanswered = questionIds.filter((id) => answers.value[id] === null || answers.value[id] === undefined).length;
  if (unanswered > 0) { formError.value = `Please answer all ${unanswered} remaining question(s) with Yes or No.`; return; }
  formError.value = ""; submitting.value = true;
  try {
    const payload = {
      office_id: office.value.id,
      device_id: getDeviceId(),
      ...form.value,
      answers: questionIds.map((id) => ({ question_id: id, answer: answers.value[id] })),
    };
    await api.post("/office-feedback", payload);
    submitted.value = true;
  } catch (e) {
    formError.value = e.response?.data?.message || "Failed to submit.";
    window.scrollTo({ top: 0, behavior: "smooth" });
  } finally { submitting.value = false; }
}
</script>

<style scoped>
.qr-feedback-page { min-height: 100vh; background: #ffffff; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
.qr-container { width: 100%; max-width: 560px; }
.office-header { padding: 1.5rem; background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; }
.office-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(25, 25, 112, 0.1); color: #191970; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.form-section { background: #fff; border-radius: 16px; padding: 1.25rem; border: 1px solid #e2e8f0; }
.label-custom { display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.65rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.08em; }
.input-custom { width: 100%; padding: 0.75rem 1.15rem; border-radius: 0!important; border: 2px solid #e2e8f0; background: #fff; color: #1e293b; font-size: 0.9rem; transition: all 0.2s ease; }
.input-custom:focus { outline: none; border-color: #191970; box-shadow: 0 0 0 4px rgba(25, 25, 112, 0.1); }
.visitor-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
.visitor-btn { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; padding: 0.75rem 0.5rem; border: 2px solid #e2e8f0; border-radius: 12px; background: #fff; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; font-weight: 600; color: #6b7280; }
.visitor-btn i { font-size: 1.1rem; }
.visitor-btn.active { border-color: #191970; background: rgba(25, 25, 112, 0.05); color: #191970; }
.category-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
.category-header { padding: 0.75rem 1.25rem; border-bottom: 1px solid #f1f5f9; background: rgba(25, 25, 112, 0.02); }
.question-row { padding: 0.75rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
.question-row:last-child { border-bottom: none; }
.question-text { font-size: 0.85rem; font-weight: 500; color: #1e293b; }
.yesno-group { display: flex; gap: 8px; }
.yesno-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1.25rem; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; color: #6b7280; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
.yesno-btn:hover { border-color: #191970; color: #1e293b; }
.yesno-btn.yes.active { border-color: #22c55e; background: rgba(34, 197, 94, 0.08); color: #16a34a; }
.yesno-btn.no.active { border-color: #ef4444; background: rgba(239, 68, 68, 0.08); color: #dc2626; }
.success-icon { font-size: 4rem; color: #22c55e; }
.error-icon { font-size: 4rem; color: #ef4444; }
.alert { border-radius: 12px; }
</style>
