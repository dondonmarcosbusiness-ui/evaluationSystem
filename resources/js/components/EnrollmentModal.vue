<template>
  <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-800 mb-0">{{ student?.name }}</h5>
            <span class="small text-muted fw-600 ls-1 text-uppercase">Irregular Enrollment Management</span>
          </div>
          <button type="button" class="btn-close" aria-label="Close" @click="requestClose"></button>
        </div>

        <div class="modal-body">
          <!-- Current Enrollments -->
          <div class="mb-4 d-flex justify-content-between align-items-center">
            <h6 class="text-uppercase ls-1 fw-800 small text-muted mb-0">Enrolled Subjects</h6>
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">
              {{ enrollments.length }} Items
            </span>
          </div>

          <div v-if="loading" class="py-3">
            <SkeletonLoader variant="list" :rows="4" />
          </div>

          <div v-else class="load-list d-flex flex-column gap-3 mb-5">
            <div v-for="e in enrollments" :key="e.id" class="load-item-card border-success border-opacity-25">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <span class="load-code-tag bg-success bg-opacity-10 text-success">{{ e.subject?.code }}</span>
                  <h6 class="fw-800 mb-1 mt-2">{{ e.subject?.name }}</h6>
                  <div class="d-flex align-items-center gap-2 text-muted small fw-600">
                    <i class="fas fa-user-tie"></i>
                    <span>Prof. {{ e.instructor?.user?.name }}</span>
                  </div>
                </div>
                <button class="btn-unlink-minimal" @click="removeEnrollment(e.id)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <div class="pt-2 border-top border-light-subtle d-flex justify-content-between align-items-center">
                <div class="small text-muted fw-600">
                  <i class="fas fa-calendar-alt me-1 opacity-50"></i>
                  {{ e.academic_year }} • {{ e.semester }} •
                  {{ e.year_level ?? e.subject?.year_level ?? "All years" }}
                </div>
              </div>
            </div>

            <div v-if="!enrollments.length" class="text-center py-5 opacity-50 border rounded-4 border-dashed">
              <i class="fas fa-book-open fa-3x mb-3"></i>
              <p class="small fw-bold">No subjects enrolled yet.</p>
            </div>
          </div>

          <!-- Add New Enrollment Form -->
          <div class="add-enrollment-box p-4 rounded-4 bg-light border">
            <h6 class="fw-800 mb-3 d-flex align-items-center gap-2 text-primary">
              <i class="fas fa-plus-circle"></i>
              Enroll New Subject
            </h6>

            <div v-if="formError" class="alert alert-danger small py-2 mb-3">
              {{ formError }}
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label-premium">Subject</label>
                <CustomSelect
                  v-model="form.subject_id"
                  :options="subjectOptions"
                  placeholder="Search & Select Subject"
                  @change="onSubjectChange"
                />
              </div>
              <div class="col-12">
                <label class="form-label-premium">Instructor</label>
                <CustomSelect
                  v-model="form.instructor_id"
                  :options="instructorOptions"
                  placeholder="Search & Select Instructor"
                />
              </div>
              <div class="col-md-6">
                <label class="form-label-premium">Academic Year</label>
                <input v-model="form.academic_year" class="form-control-premium" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label-premium">Semester</label>
                <input v-model="form.semester" class="form-control-premium" readonly />
              </div>
              <div class="col-12">
                <label class="form-label-premium">Year Level</label>
                <CustomSelect
                  v-model="form.year_level"
                  :options="yearLevelOptions"
                  placeholder="Select Year"
                />
              </div>
              <div class="col-12">
                <button
                  class="btn btn-primary w-100 py-3 rounded-3 fw-800 mt-2 shadow-sm"
                  @click="addEnrollment"
                  :disabled="saving || !form.subject_id || !form.instructor_id"
                >
                  <i class="fas fa-plus me-2"></i>
                  {{ saving ? 'Enrolling...' : 'Enroll Subject' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="requestClose">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import { Modal } from "bootstrap";
import CustomSelect from "./CustomSelect.vue";
import SkeletonLoader from "./SkeletonLoader.vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";

const props = defineProps({
  student: Object
});

const emit = defineEmits(['close']);

const modalEl = ref(null);
let modal = null;

const enrollments = ref([]);
const loading = ref(true);
const saving = ref(false);
const formError = ref("");

const meta = ref({ faculty: [], subjects: [] });
const settings = ref({ active_academic_year: "", active_semester: "" });

const form = ref({
  subject_id: "",
  instructor_id: "",
  academic_year: "",
  semester: "",
  year_level: ""
});

const yearLevelOptions = [
  { label: "1st Year", value: "1st" },
  { label: "2nd Year", value: "2nd" },
  { label: "3rd Year", value: "3rd" },
  { label: "4th Year", value: "4th" },
  { label: "Irregular", value: "Irregular" },
];

const instructorOptions = computed(() => [
  { label: "-- Select Instructor --", value: "" },
  ...meta.value.faculty.map(f => ({ label: f.user?.name || 'N/A', value: f.id }))
]);

const subjectOptions = computed(() => [
  { label: "-- Select Subject --", value: "" },
  ...meta.value.subjects.map(s => ({
    label: `${s.code} - ${s.name} (${s.year_level ?? "All years"})`,
    value: s.id,
  }))
]);

onMounted(async () => {
  modal = new Modal(modalEl.value, { focus: false });
  modal.show();
  modalEl.value.addEventListener("hidden.bs.modal", () => emit("close"));

  await Promise.all([
    fetchEnrollments(),
    fetchMeta(),
    fetchSettings()
  ]);
});

onBeforeUnmount(() => {
  modal?.dispose();
  modal = null;
});

function requestClose() { modal?.hide(); }

async function fetchEnrollments() {
  loading.value = true;
  try {
    const res = await api.get(`/students/${props.student.id}/enrollments`);
    enrollments.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function fetchMeta() {
  try {
    const res = await api.get("/assignments/meta");
    meta.value = res.data;
  } catch (e) {
    console.error(e);
  }
}

async function fetchSettings() {
  try {
    const res = await api.get("/settings");
    settings.value = res.data;
    form.value.academic_year = res.data.active_academic_year;
    form.value.semester = res.data.active_semester;
  } catch (e) {
    console.error(e);
  }
}

async function onSubjectChange() {
  if (!form.value.subject_id) return;

  const sub = meta.value.subjects.find(s => s.id === form.value.subject_id);
  if (sub?.year_level && !form.value.year_level) {
    form.value.year_level = sub.year_level;
  }

  try {
    const res = await api.get('/assignments', {
      params: {
        subject_id: form.value.subject_id,
        academic_year: form.value.academic_year,
        semester: form.value.semester
      }
    });

    if (res.data.data && res.data.data.length > 0) {
      form.value.instructor_id = res.data.data[0].faculty_id;
    } else {
      form.value.instructor_id = "";
    }
  } catch (e) {
    console.error("Autofill failed:", e);
  }
}

async function addEnrollment() {
  saving.value = true;
  formError.value = "";
  try {
    await api.post(`/students/${props.student.id}/enrollments`, form.value);
    form.value.subject_id = "";
    form.value.instructor_id = "";
    form.value.year_level = "";
    await fetchEnrollments();
    Swal.fire({
      icon: 'success',
      title: 'Enrolled',
      text: 'Subject has been enrolled for the student.',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (e) {
    formError.value = e.response?.data?.message || "Failed to enroll subject.";
  } finally {
    saving.value = false;
  }
}

async function removeEnrollment(id) {
  const result = await confirmAction({
    title: 'Remove Enrollment?',
    message: 'This will remove the subject from the student\'s evaluation list.',
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/enrollments/${id}`);
      await fetchEnrollments();
      Swal.fire('Removed!', 'Enrollment has been removed.', 'success');
    } catch (e) {
      Swal.fire('Error', 'Failed to remove enrollment.', 'error');
    }
  }
}
</script>

<style scoped>
.load-item-card {
  background: var(--bg-light);
  padding: 1.25rem;
  border-radius: var(--card-radius);
  border: 1px solid var(--border-light);
  transition: all 0.2s;
}
.load-code-tag {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 4px;
}
.btn-unlink-minimal {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: #fee2e2;
  color: var(--danger);
  font-size: 0.8rem;
}
.btn-unlink-minimal:hover {
  background: var(--danger);
  color: white;
}
.form-label-premium {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 0.4rem;
}
.form-control-premium {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  font-weight: 600;
  font-size: 0.9rem;
}
.border-dashed {
  border-style: dashed !important;
}
</style>
