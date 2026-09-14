<template>
  <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold text-primary">
            {{ hasPassword ? t.change_password_title : t.set_password_title }}
          </h5>
          <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Close"
            :disabled="submitting"
          ></button>
        </div>

        <form @submit.prevent="submit">
          <div class="modal-body">
            <p class="text-muted small mb-3">{{ t.password_requirements }}</p>

            <div v-if="hasPassword" class="mb-3">
              <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.current_password }}</label>
              <input
                v-model="form.current_password"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': errors.current_password }"
                autocomplete="current-password"
              />
              <div v-if="errors.current_password" class="invalid-feedback d-block">
                {{ errors.current_password }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.new_password }}</label>
              <input
                v-model="form.password"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': errors.password }"
                autocomplete="new-password"
              />
              <div v-if="errors.password" class="invalid-feedback d-block">{{ errors.password }}</div>
            </div>

            <div class="mb-1">
              <label class="form-label small fw-bold text-muted text-uppercase ls-1">{{ t.confirm_password }}</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': errors.password_confirmation }"
                autocomplete="new-password"
              />
              <div v-if="errors.password_confirmation" class="invalid-feedback d-block">
                {{ errors.password_confirmation }}
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-light flex-fill rounded-3"
              data-bs-dismiss="modal"
              :disabled="submitting"
            >
              {{ t.cancel }}
            </button>
            <button type="submit" class="btn btn-primary flex-fill rounded-3" :disabled="submitting">
              <i v-if="submitting" class="fas fa-spinner fa-spin me-1"></i>
              {{ submitting ? t.saving_password : t.save_password }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, computed, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Modal } from "bootstrap";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "updated"]);

const modalEl = ref(null);
let modal = null;

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value] || translations.en);

const hasPassword = ref(true);
const submitting = ref(false);
const form = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});
const errors = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});

onMounted(() => {
  modal = new Modal(modalEl.value, { focus: false });
  if (props.show) modal.show();
  modalEl.value.addEventListener("hidden.bs.modal", () => emit("close"));
});

onBeforeUnmount(() => {
  modal?.dispose();
  modal = null;
});

function resetForm() {
  form.current_password = "";
  form.password = "";
  form.password_confirmation = "";
  errors.current_password = "";
  errors.password = "";
  errors.password_confirmation = "";
}

function validatePassword(password) {
  if (!password || password.length < 8) {
    return t.value.password_min_length;
  }
  if (!/[A-Z]/.test(password)) {
    return t.value.password_uppercase;
  }
  if (!/[^A-Za-z0-9]/.test(password)) {
    return t.value.password_special;
  }
  return "";
}

function validateClient() {
  errors.current_password = "";
  errors.password = "";
  errors.password_confirmation = "";

  let valid = true;

  if (hasPassword.value && !form.current_password) {
    errors.current_password = t.value.current_password + " is required.";
    valid = false;
  }

  const passwordError = validatePassword(form.password);
  if (passwordError) {
    errors.password = passwordError;
    valid = false;
  }

  if (form.password !== form.password_confirmation) {
    errors.password_confirmation = t.value.password_mismatch;
    valid = false;
  }

  return valid;
}

async function loadUserMeta() {
  try {
    const res = await api.get("/user");
    hasPassword.value = !!res.data.user?.has_password;
    const stored = JSON.parse(localStorage.getItem("user") || "{}");
    if (stored && res.data.user) {
      localStorage.setItem("user", JSON.stringify({ ...stored, has_password: res.data.user.has_password }));
    }
  } catch {
    const stored = JSON.parse(localStorage.getItem("user") || "{}");
    hasPassword.value = !!stored.has_password;
  }
}

async function submit() {
  if (!validateClient()) return;

  submitting.value = true;
  try {
    const payload = {
      password: form.password,
      password_confirmation: form.password_confirmation,
    };
    if (hasPassword.value) {
      payload.current_password = form.current_password;
    }

    await api.put("/user/password", payload);

    const stored = JSON.parse(localStorage.getItem("user") || "{}");
    localStorage.setItem("user", JSON.stringify({ ...stored, has_password: true }));

    await Swal.fire({
      icon: "success",
      title: t.value.password_updated,
      timer: 2000,
      showConfirmButton: false,
    });

    emit("updated");
    emit("close");
    resetForm();
  } catch (err) {
    const data = err.response?.data;
    errors.current_password = "";
    errors.password = "";
    errors.password_confirmation = "";

    if (data?.errors) {
      if (data.errors.current_password) {
        errors.current_password = data.errors.current_password[0];
      }
      if (data.errors.password) {
        errors.password = data.errors.password[0];
      }
      if (data.errors.password_confirmation) {
        errors.password_confirmation = data.errors.password_confirmation[0];
      }
    } else {
      Swal.fire("Error", data?.message || t.value.password_update_failed, "error");
    }
  } finally {
    submitting.value = false;
  }
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      resetForm();
      loadUserMeta();
    }
    nextTick(() => (visible ? modal?.show() : modal?.hide()));
  },
);
</script>
