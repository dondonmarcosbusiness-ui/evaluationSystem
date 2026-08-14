<template>
  <div
    v-if="show"
    class="change-password-overlay d-flex"
    @click.self="$emit('close')"
  >
    <div class="change-password-card card border-0 shadow-lg">
      <div class="card-header border-0 py-3 bg-transparent">
        <div class="d-flex justify-content-between align-items-center w-100">
          <h5 class="mb-0 fw-bold text-primary">
            {{ hasPassword ? t.change_password_title : t.set_password_title }}
          </h5>
          <button type="button" class="btn-close" @click="$emit('close')" :disabled="submitting"></button>
        </div>
      </div>

      <form class="card-body pt-0 change-password-form" @submit.prevent="submit">
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

        <div class="mb-4">
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

        <div class="change-password-actions d-flex gap-2">
          <button type="button" class="btn btn-light flex-fill rounded-3" :disabled="submitting" @click="$emit('close')">
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
</template>

<script setup>
import { ref, reactive, watch, computed } from "vue";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "updated"]);

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
  },
);
</script>

<style scoped>
.change-password-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
}

.change-password-card {
  width: 440px;
  max-width: 95vw;
  border-radius: var(--card-radius);
}

.change-password-form {
  display: flex;
  flex-direction: column;
}

@media (max-width: 767.98px) {
  .change-password-overlay {
    align-items: stretch;
    justify-content: stretch;
    background: var(--bg-card, #fff);
  }

  .change-password-card {
    width: 100%;
    max-width: none;
    height: 100%;
    border-radius: 0 !important;
    box-shadow: none !important;
    display: flex;
    flex-direction: column;
  }

  .change-password-form {
    flex: 1;
    overflow-y: auto;
  }

  .change-password-actions {
    margin-top: auto;
    padding-top: 1rem;
  }
}
</style>
