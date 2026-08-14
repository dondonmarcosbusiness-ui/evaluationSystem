<template>
  <div class="login-wrapper">
    <!-- Brand / Hero Section (Left on Desktop) -->
    <div
      class="login-hero d-none d-lg-flex"
      :style="{
        backgroundImage: `url(${basePath}/assets/img/modern_login_hero.png)`,
      }"
    >
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <img :src="`${basePath}/assets/img/neust_logo.webp`" alt="NEUST Logo" class="hero-logo mb-4" />
        <h1 class="hero-title text-white fw-bold mb-3">Empowering Faculty Excellence</h1>
        <p class="hero-subtitle text-white-50 fs-5 mb-0">
          Join the NEUST Carranglan community and participate in shaping the future of education.
        </p>
      </div>
      <div class="hero-footer text-white-50 small">
        &copy; {{ new Date().getFullYear() }} NEUST Carranglan Off-Campus. All rights reserved.
      </div>
    </div>

    <!-- Form Section (Right on Desktop, Full Width on Mobile) -->
    <div class="login-form-section">
      <div class="login-form-container">
        <div class="form-header mb-5 text-center text-lg-start">
          <img
            :src="`${basePath}/assets/img/neust_logo.webp`"
            alt="NEUST Logo"
            class="mobile-logo d-lg-none mb-4"
            style="width: 72px; height: auto"
          />
          <h2 class="fw-bold text-dark mb-2 text-center">
            {{ isSettingUp ? "Complete Your Profile" : "Welcome Back" }}
          </h2>
          <p class="text-muted text-center">
            {{
              isSettingUp
                ? "Please provide your details to finish setting up your account."
                : "Sign in to the Faculty Evaluation System."
            }}
          </p>
        </div>

        <div
          v-if="error"
          class="alert alert-danger py-2 small mb-4 d-flex align-items-center gap-2 alert-slide border-0 shadow-sm"
          style="background-color: #fee2e2; color: #991b1b"
        >
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ error }}</span>
        </div>

        <div v-if="!isSettingUp">
          <form @submit.prevent="login" class="mb-4">
            <div class="mb-4">
              <label class="form-label fw-semibold text-dark small mb-2">ID Number or Email</label>
              <div class="input-group input-group-lg login-input-group">
                <span class="input-group-text bg-transparent border-end-0 pe-2">
                  <i class="fas fa-id-card text-muted"></i>
                </span>
                <input
                  v-model="form.login"
                  type="text"
                  class="form-control border-start-0 ps-1"
                  placeholder="Enter your ID or Email"
                  required
                />
              </div>
            </div>

            <div class="mb-5">
              <label class="form-label fw-semibold text-dark small mb-2">Password</label>
              <div class="input-group input-group-lg login-input-group">
                <span class="input-group-text bg-transparent border-end-0 pe-2">
                  <i class="fas fa-lock text-muted"></i>
                </span>
                <input
                  v-model="form.password"
                  :type="showPass ? 'text' : 'password'"
                  class="form-control border-start-0 border-end-0 px-1"
                  placeholder="••••••••"
                  required
                />
                <button
                  class="input-group-text bg-transparent border-start-0 ps-1 pe-3 text-muted"
                  type="button"
                  @click="showPass = !showPass"
                >
                  <i :class="showPass ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-100 fw-bold d-flex align-items-center justify-content-center gap-2 login-btn text-white"
              :disabled="loading"
            >
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              <span v-else>Sign In</span>
              <i v-if="!loading" class="fas fa-arrow-right fs-6 ms-1"></i>
            </button>
          </form>

          <div class="separator position-relative text-center my-4">
            <hr class="text-muted opacity-25" />
            <span
              class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small fw-semibold"
              style="letter-spacing: 0.05em"
            >
              OR CONTINUE WITH
            </span>
          </div>

          <button
            type="button"
            @click="loginWithGoogle"
            class="btn btn-light w-100 fw-bold d-flex align-items-center justify-content-center gap-3 google-btn"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                fill="#4285F4"
              />
              <path
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                fill="#34A853"
              />
              <path
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                fill="#FBBC05"
              />
              <path
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                fill="#EA4335"
              />
            </svg>
            Google Account
          </button>
        </div>

        <div v-else class="fade-in">
          <form @submit.prevent="finalizeRegistration">
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold text-dark small mb-2">First Name</label>
                <div class="input-group login-input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                    <i class="fas fa-user text-muted small"></i>
                  </span>
                  <input
                    v-model="form.firstname"
                    type="text"
                    class="form-control border-start-0 ps-1"
                    placeholder="First Name"
                    required
                  />
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold text-dark small mb-2">Last Name</label>
                <div class="input-group login-input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                    <i class="fas fa-user text-muted small"></i>
                  </span>
                  <input
                    v-model="form.lastname"
                    type="text"
                    class="form-control border-start-0 ps-1"
                    placeholder="Last Name"
                    required
                  />
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold text-dark small mb-2">Middle Name (Optional)</label>
              <div class="input-group login-input-group">
                <span class="input-group-text bg-transparent border-end-0">
                  <i class="fas fa-user-tag text-muted small"></i>
                </span>
                <input
                  v-model="form.middlename"
                  type="text"
                  class="form-control border-start-0 ps-1"
                  placeholder="Middle Name"
                />
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-sm-6">
                <label class="form-label fw-semibold text-dark small mb-2">Course</label>
                <div class="input-group login-input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                    <i class="fas fa-graduation-cap text-muted small"></i>
                  </span>
                  <select v-model="form.course" class="form-select border-start-0 ps-1" required>
                    <option value="" disabled>Select Course</option>
                    <option v-for="c in availableCourses" :key="c.id" :value="c.name">
                      {{ c.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold text-dark small mb-2">Section</label>
                <div class="input-group login-input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                    <i class="fas fa-layer-group text-muted small"></i>
                  </span>
                  <select
                    v-model="form.section"
                    class="form-select border-start-0 ps-1"
                    required
                    :disabled="!form.course"
                  >
                    <option value="" disabled>Select Section</option>
                    <option v-for="s in availableSections" :key="s.id" :value="s.name">
                      {{ s.name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div class="d-flex flex-column gap-3">
              <button
                type="submit"
                class="btn btn-primary w-100 fw-bold d-flex align-items-center justify-content-center gap-2 login-btn text-white"
                :disabled="loading"
              >
                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                <span v-else>Complete Profile</span>
                <i v-if="!loading" class="fas fa-check-circle fs-6 ms-1"></i>
              </button>

              <button
                type="button"
                @click="isSettingUp = false"
                class="btn btn-link text-muted text-decoration-none small fw-semibold"
              >
                <i class="fas fa-arrow-left me-1"></i>
                Back to Login
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api.js";

const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";
const router = useRouter();
const form = ref({
  login: "",
  password: "",
  firstname: "",
  middlename: "",
  lastname: "",
  course: "",
  section: "",
});
const error = ref("");
const loading = ref(false);
const showPass = ref(false);
const isSettingUp = ref(false);
const googleData = ref(null);
const availableCourses = ref([]);

const availableSections = computed(() => {
  const selectedCourse = availableCourses.value.find((c) => c.name === form.value.course);
  return selectedCourse ? selectedCourse.academic_sections : [];
});

onMounted(() => {
  document.documentElement.removeAttribute("data-theme");
});

// Handle callback if redirected from Google
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has("token")) {
  localStorage.setItem("token", urlParams.get("token"));
  localStorage.setItem("user", urlParams.get("user"));
  localStorage.setItem("permissions", urlParams.get("permissions"));
  router.push("/dashboard");
} else if (urlParams.has("requires_setup")) {
  isSettingUp.value = true;
  try {
    googleData.value = JSON.parse(decodeURIComponent(urlParams.get("google_user")));
  } catch (e) {
    error.value = "Failed to parse Google information.";
  }

  api
    .get("/courses")
    .then((res) => {
      availableCourses.value = res.data;
    })
    .catch((err) => {
      error.value = "Failed to load courses.";
    });
} else if (urlParams.has("error")) {
  error.value = urlParams.get("error");
}

async function login() {
  loading.value = true;
  error.value = "";
  try {
    const res = await api.post("/login", form.value);
    localStorage.setItem("token", res.data.access_token);
    localStorage.setItem("user", JSON.stringify(res.data.user));
    localStorage.setItem("permissions", JSON.stringify(res.data.permissions));
    router.push("/dashboard");
  } catch (e) {
    error.value = e.response?.data?.message || "Login failed. Please try again.";
  } finally {
    loading.value = false;
  }
}

async function finalizeRegistration() {
  loading.value = true;
  error.value = "";
  try {
    const sectionObj = availableSections.value.find((s) => s.name === form.value.section);
    const payload = {
      firstname: form.value.firstname,
      lastname: form.value.lastname,
      middlename: form.value.middlename,
      course: form.value.course,
      section: form.value.section,
      section_id: sectionObj ? sectionObj.id : null,
      google_id: googleData.value.id,
      email: googleData.value.email,
    };
    const res = await api.post("/auth/google/register", payload);
    localStorage.setItem("token", res.data.token);
    localStorage.setItem("user", JSON.stringify(res.data.user));
    localStorage.setItem("permissions", JSON.stringify(res.data.permissions));
    router.push("/dashboard");
  } catch (e) {
    error.value = e.response?.data?.message || "Failed to complete registration. Please try again.";
  } finally {
    loading.value = false;
  }
}

function loginWithGoogle() {
  window.location.href = `${api.defaults.baseURL}/auth/google`;
}
</script>

<style scoped>
.login-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: #ffffff;
}

/* ── Hero / Brand Section ── */
.login-hero {
  flex: 0 0 55%;
  position: relative;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 4rem;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(10, 39, 138, 0.85) 0%, rgba(7, 28, 102, 0.95) 100%);
  z-index: 1;
}

.hero-content {
  position: relative;
  z-index: 2;
  max-width: 500px;
  margin: 0 auto;
  text-align: center;
}

.hero-logo {
  width: 90px;
  height: auto;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
}

.hero-title {
  font-size: 2.75rem;
  line-height: 1.2;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-subtitle {
  font-weight: 300;
}

.hero-footer {
  position: absolute;
  bottom: 2rem;
  left: 0;
  right: 0;
  text-align: center;
  z-index: 2;
  opacity: 0.7;
}

/* ── Form Section ── */
.login-form-section {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: #ffffff;
}

.login-form-container {
  width: 100%;
  max-width: 440px;
}

/* Enhanced Inputs */
.login-input-group {
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
  border-radius: 0.85rem;
  transition: all 0.3s ease;
}

.login-input-group:focus-within {
  box-shadow: 0 0 0 4px rgba(10, 39, 138, 0.1);
}

.login-input-group .form-control,
.login-input-group .input-group-text {
  border-color: #e2e8f0;
  background-color: #f8fafc;
  transition: all 0.3s ease;
}

.login-input-group:focus-within .form-control,
.login-input-group:focus-within .input-group-text {
  border-color: var(--primary);
  background-color: #ffffff;
}

.login-input-group .form-control:focus {
  box-shadow: none;
  border-color: var(--primary);
}

.login-input-group .form-control {
  font-size: 0.95rem;
  padding-top: 0.95rem;
  padding-bottom: 0.95rem;
  border-radius: 0 0.85rem 0.85rem 0;
}

.login-input-group .input-group-text:first-child {
  border-radius: 0.85rem 0 0 0.85rem;
}

/* Buttons */
.login-btn {
  padding: 0.95rem 1.75rem;
  font-size: 1.05rem;
  border-radius: 0.85rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(10, 39, 138, 0.2);
}

.login-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(10, 39, 138, 0.3);
}

.google-btn {
  border-color: #e2e8f0;
  color: #475569;
  padding: 0.95rem 1.5rem;
  font-size: 1.05rem;
  border-radius: 0.85rem;
  transition: all 0.3s ease;
  background-color: #ffffff;
}

.google-btn:hover {
  background-color: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

/* Animations */
.alert-slide {
  animation: slideDown 0.3s ease-out forwards;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 992px) {
  .login-form-section {
    background: #f8fafc; /* Subtle background for mobile forms */
    padding: 3rem 1.5rem;
    background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%230a278a" fill-opacity="0.03"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
  }
  .login-form-container {
    background: #ffffff;
    padding: 2.5rem 2rem;
    border-radius: var(--card-radius);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); /* Float effect on mobile */
  }
  .separator span.bg-white {
    background-color: #ffffff !important;
  }
}

@media (max-width: 576px) {
  .login-form-section {
    padding: 0;
  }
  .login-form-container {
    padding: 2.5rem 1.5rem;
    border-radius: 0;
    box-shadow: none;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .mobile-logo {
    width: 60px !important;
    margin-bottom: 1rem !important;
  }
  .form-header h2 {
    font-size: 1.5rem;
  }
  .form-header p {
    font-size: 0.85rem;
  }
  .login-btn {
    padding: 0.8rem 1.5rem;
    font-size: 1rem;
  }
  .google-btn {
    padding: 0.8rem 1.5rem;
    font-size: 0.95rem;
  }
}
</style>
