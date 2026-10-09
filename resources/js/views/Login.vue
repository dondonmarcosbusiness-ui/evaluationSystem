<template>
  <div
    class="login-shell"
    :style="{
      backgroundImage: `linear-gradient(180deg, rgba(12, 17, 76, 0.55) 0%, rgba(10, 14, 62, 0.82) 45%, rgba(6, 9, 40, 0.94) 100%), url(${basePath}/assets/img/modern_login_hero.png)`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
      backgroundRepeat: 'no-repeat',
    }"
  >
    <!-- ── Brand / Hero panel ──────────────────────────────── -->
    <div class="login-brand">
      <div
        class="login-brand__image"
        :style="{ backgroundImage: `url(${basePath}/assets/img/modern_login_hero.png)` }"
        aria-hidden="true"
      ></div>
      <div class="login-brand__overlay" aria-hidden="true"></div>

      <div class="login-brand__content">
        <img
          :src="`${basePath}/assets/img/neust_logo.webp`"
          alt="NEUST Carranglan logo"
          class="login-brand__logo"
        />
        <h1 class="login-brand__title">Faculty &amp; Staff<br />Evaluation System</h1>
        <p class="login-brand__tagline">Your Voice. Better Education.</p>
        <p class="login-brand__description">
          Help us improve teaching and learning through meaningful feedback.
        </p>
      </div>

      <p class="login-brand__footer">&copy; {{ currentYear }} NEUST Carranglan. All rights reserved.</p>
    </div>

    <!-- ── Login panel ──────────────────────────────────────── -->
    <div class="login-panel">
      <div class="login-panel__inner">
        <header class="login-panel__header">
          <h2 class="login-panel__heading">
            {{ isSettingUp ? "Complete Your Profile" : "Welcome!" }}
          </h2>
          <p class="login-panel__subheading">
            {{
              isSettingUp
                ? "Please provide your details to finish setting up your account."
                : "Sign in using your institutional account to continue."
            }}
          </p>
        </header>

        <div v-if="error" class="login-alert" role="alert" aria-live="assertive" aria-atomic="true">
          <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
          <span>{{ error }}</span>
        </div>

        <!-- ── Sign in ───────────────────────────────────────── -->
        <div v-if="!isSettingUp">
          <button type="button" class="login-google" @click="loginWithGoogle">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
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
            <span
              >Continue with Google<span class="visually-hidden">
                using your institutional Google account</span
              ></span
            >
          </button>

          <p class="login-google__hint">
            Use your <span class="login-google__domain">@neustcarranglan.ph.education</span> account
          </p>

          <div class="login-divider">
            <span>OR</span>
          </div>

          <form class="login-form" @submit.prevent="login">
            <div class="login-field-group">
              <label class="login-label" for="login-identifier">ID Number or Email</label>
              <div class="login-field">
                <span class="login-field__icon" aria-hidden="true">
                  <i class="fas fa-id-card"></i>
                </span>
                <input
                  id="login-identifier"
                  v-model="form.login"
                  type="text"
                  class="login-input"
                  name="login"
                  autocomplete="username"
                  autocapitalize="none"
                  spellcheck="false"
                  placeholder="Enter your ID or Email"
                  required
                />
              </div>
            </div>

            <div class="login-field-group">
              <label class="login-label" for="login-password">Password</label>
              <div class="login-field">
                <span class="login-field__icon" aria-hidden="true">
                  <i class="fas fa-lock"></i>
                </span>
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPass ? 'text' : 'password'"
                  class="login-input login-input--password"
                  name="password"
                  autocomplete="current-password"
                  placeholder="Enter your password"
                  required
                />
                <button
                  type="button"
                  class="login-field__toggle"
                  :aria-label="showPass ? 'Hide password' : 'Show password'"
                  :aria-pressed="showPass"
                  aria-controls="login-password"
                  @click="showPass = !showPass"
                >
                  <i :class="showPass ? 'fas fa-eye-slash' : 'fas fa-eye'" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="login-submit" :disabled="loading">
              <i v-if="loading" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
              <span>{{ loading ? "Signing In…" : "Sign In" }}</span>
              <i v-if="!loading" class="fas fa-arrow-right login-submit__arrow" aria-hidden="true"></i>
            </button>
          </form>
        </div>

        <!-- ── Complete profile ───────────────────────────────── -->
        <div v-else>
          <form class="login-form" @submit.prevent="finalizeRegistration">
            <div class="login-grid-2">
              <div class="login-field-group">
                <label class="login-label" for="setup-firstname">First Name</label>
                <div class="login-field">
                  <span class="login-field__icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                  <input
                    id="setup-firstname"
                    v-model="form.firstname"
                    type="text"
                    class="login-input"
                    name="firstname"
                    autocomplete="given-name"
                    placeholder="First Name"
                    required
                  />
                </div>
              </div>
              <div class="login-field-group">
                <label class="login-label" for="setup-lastname">Last Name</label>
                <div class="login-field">
                  <span class="login-field__icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                  <input
                    id="setup-lastname"
                    v-model="form.lastname"
                    type="text"
                    class="login-input"
                    name="lastname"
                    autocomplete="family-name"
                    placeholder="Last Name"
                    required
                  />
                </div>
              </div>
            </div>

            <div class="login-field-group">
              <label class="login-label" for="setup-middlename">
                Middle Name <span class="login-label__optional">(Optional)</span>
              </label>
              <div class="login-field">
                <span class="login-field__icon" aria-hidden="true"><i class="fas fa-user-tag"></i></span>
                <input
                  id="setup-middlename"
                  v-model="form.middlename"
                  type="text"
                  class="login-input"
                  name="middlename"
                  autocomplete="additional-name"
                  placeholder="Middle Name"
                />
              </div>
            </div>

            <div class="login-grid-2">
              <div class="login-field-group">
                <label class="login-label" for="setup-course">Course</label>
                <div class="login-field">
                  <span class="login-field__icon" aria-hidden="true"
                    ><i class="fas fa-graduation-cap"></i
                  ></span>
                  <select
                    id="setup-course"
                    v-model="form.course"
                    class="login-input login-select"
                    name="course"
                    required
                  >
                    <option value="" disabled>Select Course</option>
                    <option v-for="c in availableCourses" :key="c.id" :value="c.name">
                      {{ c.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="login-field-group">
                <label class="login-label" for="setup-section">Section</label>
                <div class="login-field">
                  <span class="login-field__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                  <select
                    id="setup-section"
                    v-model="form.section"
                    class="login-input login-select"
                    name="section"
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

            <p v-if="inferredYearLevel" class="login-note">
              <i class="fas fa-info-circle" aria-hidden="true"></i>
              Detected year: {{ inferredYearLabel }} (auto-set from section)
            </p>

            <div class="login-actions">
              <button type="submit" class="login-submit" :disabled="loading">
                <i v-if="loading" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span>{{ loading ? "Saving…" : "Complete Profile" }}</span>
                <i v-if="!loading" class="fas fa-check-circle login-submit__arrow" aria-hidden="true"></i>
              </button>
              <button type="button" class="login-back" @click="isSettingUp = false">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
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

const basePath = window.location.pathname.toLowerCase().startsWith("/evaluation_system/public")
  ? window.location.pathname.substring(0, "/evaluation_system/public".length)
  : "";
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
const currentYear = new Date().getFullYear();

const availableSections = computed(() => {
  const selectedCourse = availableCourses.value.find((c) => c.name === form.value.course);
  return selectedCourse ? selectedCourse.academic_sections : [];
});

const selectedSectionObj = computed(() => {
  return availableSections.value.find((s) => s.name === form.value.section) || null;
});

function inferYearFromSectionName(name) {
  if (!name) return null;
  const map = { 1: "1st", 2: "2nd", 3: "3rd", 4: "4th" };
  const s = String(name);
  let m = s.match(/([1-4])\s*(st|nd|rd|th)\b/i);
  if (m) return map[m[1]];
  m = s.match(/^\s*([1-4])\b/);
  if (m) return map[m[1]];
  m = s.match(/\b([1-4])[A-Za-z]\b/);
  if (m) return map[m[1]];
  m = s.match(/\byears?\s+([1-4])\b/i);
  if (m) return map[m[1]];
  m = s.match(/\b([1-4])\s*[-_]\s*[A-Za-z]\b/);
  if (m) return map[m[1]];
  return null;
}

// Section tag wins; otherwise infer from names like "4A"/"3A" => "4th"/"3rd".
const inferredYearLevel = computed(() => {
  return selectedSectionObj.value?.year_level || inferYearFromSectionName(form.value.section);
});

const inferredYearLabel = computed(() => {
  const labels = { "1st": "1st Year", "2nd": "2nd Year", "3rd": "3rd Year", "4th": "4th Year" };
  return labels[inferredYearLevel.value] || inferredYearLevel.value;
});

onMounted(() => {
  document.documentElement.removeAttribute("data-theme");
  document.title = "Faculty & Staff Evaluation System";
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
      year_level: inferredYearLevel.value || undefined,
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
/* ══════════════════════════════════════════════════════════
   Design tokens — mobile-first, re-declared per breakpoint.
   Spacing uses the 4/8/12/16/20/24/28/32/40/48/64 scale.
   ══════════════════════════════════════════════════════════ */
.login-shell {
  --login-navy: #191970;
  --login-ink: #1f2328;
  --login-muted: #59636e;
  --login-muted-soft: #5c6672;
  --login-border: #d8dee6;
  --login-border-soft: #e6eaef;
  --login-surface: #f8fafc;

  /* Type */
  --fs-title: 20px;
  --lh-title: 1.2;
  --fs-tagline: 14px;
  --fs-desc: 12px;
  --lh-desc: 1.4;
  --fs-heading: 24px;
  --fs-sub: 14px;
  --fs-label: 14px;
  --fs-control: 16px;
  --fs-button: 15px;

  /* Sizing */
  --logo-size: 64px;
  --h-control: 50px;
  --r-control: 8px;
  --form-max: 480px;
  --panel-overlap: 20px;
  --panel-radius: 20px;

  /* Hero spacing */
  --gap-logo-title: 12px;
  --gap-title-tagline: 12px;
  --gap-tagline-desc: 8px;
  --hero-min: 230px;
  --hero-pad-y: 16px;
  --hero-pad-x: 24px;

  /* Form spacing */
  --gap-heading-desc: 8px;
  --gap-label: 8px;
  --gap-google: 24px;
  --gap-hint: 12px;
  --gap-divider: 20px;
  --gap-group: 20px;
  --gap-submit: 24px;
  --form-pad-top: 32px;
  --form-pad-x: 24px;
  --form-pad-bottom: 40px;
  --panel-pad-x: 0px;

  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  width: 100%;
  position: relative;
  overflow-x: hidden;
  background-color: #ffffff;
}

/* ── Brand / hero panel ────────────────────────────────────── */
.login-brand {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: var(--hero-min);
  padding: var(--hero-pad-y) var(--hero-pad-x) calc(var(--hero-pad-y) + var(--panel-overlap));
  overflow: hidden;
  text-align: center;
}

.login-brand__image {
  position: absolute;
  inset: 0;
  z-index: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.login-brand__overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  background-color: rgba(12, 17, 76, 0.8);
}

.login-brand__content {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 520px;
}

.login-brand__logo {
  display: block;
  width: var(--logo-size);
  height: var(--logo-size);
  object-fit: contain;
  margin: 0 0 var(--gap-logo-title);
}

.login-brand__title {
  margin: 0 0 var(--gap-title-tagline);
  max-width: 520px;
  /* app.css forces h1 colour / size with !important — override explicitly. */
  color: #ffffff !important;
  font-family: "Chakra Petch", var(--font-sans);
  font-size: var(--fs-title) !important;
  font-weight: 600 !important;
  line-height: var(--lh-title);
  letter-spacing: 0;
}

.login-brand__tagline {
  margin: 0 0 var(--gap-tagline-desc);
  color: rgba(255, 255, 255, 0.92);
  font-size: var(--fs-tagline);
  font-weight: 500;
  line-height: 1.5;
}

.login-brand__description {
  margin: 0;
  max-width: 500px;
  color: rgba(255, 255, 255, 0.78);
  font-size: var(--fs-desc);
  font-weight: 400;
  line-height: var(--lh-desc);
}

.login-brand__footer {
  display: none;
}

/* ── Login panel ───────────────────────────────────────────── */
.login-panel {
  position: relative;
  z-index: 1;
  display: flex;
  margin-top: calc(var(--panel-overlap) * -1);
  background-color: #ffffff;
  border-radius: var(--panel-radius) var(--panel-radius) 0 0;
  box-shadow: 0 -6px 24px rgba(12, 17, 76, 0.18);
  padding: 0 var(--panel-pad-x);
  overflow-y: auto;
}

.login-panel__inner {
  width: 100%;
  max-width: var(--form-max);
  margin: auto;
  box-sizing: border-box;
  padding: var(--form-pad-top) var(--form-pad-x) var(--form-pad-bottom);
}

.login-panel__header {
  margin: 0;
}

.login-panel__heading {
  margin: 0 0 var(--gap-heading-desc);
  /* app.css forces h2 colour / size with !important — override explicitly. */
  color: var(--login-ink) !important;
  font-family: "Chakra Petch", var(--font-sans);
  font-size: var(--fs-heading) !important;
  font-weight: 600 !important;
  line-height: 1.2;
  letter-spacing: -0.015em;
}

.login-panel__subheading {
  margin: 0;
  color: var(--login-muted);
  font-size: var(--fs-sub);
  line-height: 1.5;
}

/* ── Alert ─────────────────────────────────────────────────── */
.login-alert {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 24px 0 0;
  padding: 12px;
  border: 1px solid #f3c2c2;
  border-radius: var(--r-control);
  background-color: #fdf2f2;
  color: #8a1c1c;
  font-size: 13px;
  line-height: 1.5;
}

.login-alert i {
  margin-top: 2px;
}

/* ── Google button ─────────────────────────────────────────── */
.login-google {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: var(--h-control);
  margin-top: var(--gap-google);
  padding: 0 16px;
  box-sizing: border-box;
  border: 1px solid transparent;
  border-radius: var(--r-control);
  background:
    linear-gradient(#ffffff, #ffffff) padding-box,
    linear-gradient(90deg, #4285f4 0%, #34a853 33%, #fbbc05 66%, #ea4335 100%) border-box;
  color: #3c4043;
  font-family: inherit;
  font-size: var(--fs-button);
  font-weight: 500;
  line-height: 1.2;
  cursor: pointer;
  transition:
    box-shadow 0.15s ease,
    transform 0.15s ease;
}

.login-google:hover {
  background:
    linear-gradient(#f7f8f9, #f7f8f9) padding-box,
    linear-gradient(90deg, #4285f4 0%, #34a853 33%, #fbbc05 66%, #ea4335 100%) border-box;
  box-shadow: 0 2px 8px rgba(66, 133, 244, 0.22);
}

.login-google:active {
  transform: translateY(1px);
}

.login-google svg {
  flex: 0 0 auto;
}

.login-google__hint {
  margin: var(--gap-hint) 0 0;
  text-align: center;
  color: var(--login-muted);
  font-size: 12px;
  line-height: 1.5;
}

.login-google__domain {
  color: #3f4652;
  font-weight: 500;
  white-space: nowrap;
}

/* ── Divider ───────────────────────────────────────────────── */
.login-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: var(--gap-divider) 0;
  color: var(--login-muted-soft);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.08em;
}

.login-divider::before,
.login-divider::after {
  content: "";
  flex: 1 1 auto;
  height: 1px;
  background-color: var(--login-border-soft);
}

/* ── Fields ────────────────────────────────────────────────── */
.login-form > .login-field-group + .login-field-group,
.login-form > .login-grid-2 + .login-field-group,
.login-form > .login-field-group + .login-grid-2 {
  margin-top: var(--gap-group);
}

.login-grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--gap-group);
}

.login-label {
  display: block;
  margin-bottom: var(--gap-label);
  color: var(--login-ink);
  font-size: var(--fs-label);
  font-weight: 500;
  line-height: 1.4;
}

.login-label__optional {
  color: var(--login-muted);
  font-weight: 400;
}

.login-field {
  display: flex;
  align-items: stretch;
  min-height: var(--h-control);
  box-sizing: border-box;
  background-color: var(--login-surface);
  border: 1px solid var(--login-border);
  border-radius: var(--r-control);
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease,
    box-shadow 0.15s ease;
}

.login-field:focus-within {
  background-color: #ffffff;
  border-color: var(--login-navy);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

.login-field__icon {
  display: flex;
  align-items: center;
  padding-left: 16px;
  color: var(--login-muted-soft);
  font-size: 14px;
  pointer-events: none;
}

.login-field:focus-within .login-field__icon {
  color: var(--login-navy);
}

.login-input {
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
  padding: 0 16px 0 8px;
  border: 0;
  outline: none;
  background-color: transparent;
  color: var(--login-ink);
  font-family: inherit;
  font-size: var(--fs-control);
  line-height: 1.4;
  appearance: none;
}

.login-input::placeholder {
  color: var(--login-muted-soft);
  opacity: 1;
}

.login-input:focus {
  border: 0;
  outline: none;
  box-shadow: none;
  background-color: transparent;
}

.login-input--password {
  padding-right: 4px;
}

.login-field__toggle {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 48px;
  margin-right: 4px;
  padding: 0;
  border: 0;
  border-radius: var(--r-control);
  background-color: transparent;
  color: var(--login-muted-soft);
  font-size: 16px;
  cursor: pointer;
  transition:
    color 0.15s ease,
    background-color 0.15s ease;
}

.login-field__toggle:hover {
  color: var(--login-navy);
  background-color: rgba(25, 25, 112, 0.05);
}

.login-select {
  padding-right: 40px;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 16px center;
  background-size: 16px 12px;
  cursor: pointer;
}

.login-select:disabled {
  color: var(--login-muted-soft);
  cursor: not-allowed;
}

.login-note {
  margin: 12px 0 0;
  color: var(--login-muted);
  font-size: 12px;
  line-height: 1.5;
}

/* ── Buttons ───────────────────────────────────────────────── */
.login-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: var(--h-control);
  margin-top: var(--gap-submit);
  padding: 0 24px;
  box-sizing: border-box;
  border: 1px solid var(--login-navy);
  border-radius: var(--r-control);
  background-color: var(--login-navy);
  color: #ffffff;
  font-family: inherit;
  font-size: var(--fs-button);
  font-weight: 500;
  line-height: 1.2;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease;
}

.login-submit:hover:not(:disabled) {
  background-color: #12125e;
  border-color: #12125e;
}

.login-submit:active:not(:disabled) {
  background-color: #0e0e52;
  border-color: #0e0e52;
}

.login-submit:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.login-submit__arrow {
  font-size: 14px;
}

.login-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 44px;
  margin-top: 8px;
  padding: 8px;
  border: 0;
  background-color: transparent;
  color: var(--login-muted);
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.login-back:hover {
  color: var(--login-navy);
}

/* ── Focus visibility ──────────────────────────────────────── */
.login-google:focus-visible,
.login-submit:focus-visible,
.login-field__toggle:focus-visible,
.login-back:focus-visible {
  outline: 2px solid var(--login-navy);
  outline-offset: 2px;
}

.login-field:focus-within {
  outline: none;
}

@media (forced-colors: active) {
  .login-field {
    border: 1px solid CanvasText;
  }

  .login-field:focus-within {
    outline: 2px solid Highlight;
    outline-offset: 1px;
  }
}

/* ══════════════════════════════════════════════════════════
   Tablet — 768px to 1023px: still stacked (a 50/50 split at
   this width would compress the 420px form), larger scale.
   ══════════════════════════════════════════════════════════ */
@media (min-width: 768px) {
  .login-shell {
    --logo-size: 80px;
    --fs-title: 28px;
    --fs-tagline: 16px;
    --fs-desc: 14px;
    --fs-heading: 28px;
    --fs-sub: 16px;
    --h-control: 52px;

    --gap-logo-title: 20px;
    --gap-title-tagline: 16px;
    --gap-tagline-desc: 12px;
    --hero-min: 260px;
    --hero-pad-y: 24px;
    --hero-pad-x: 40px;

    --gap-google: 28px;
    --gap-divider: 24px;
    --gap-group: 28px;
    --form-pad-top: 40px;
    --form-pad-x: 0;
    --form-pad-bottom: 48px;
  }

  .login-grid-2 {
    grid-template-columns: 1fr 1fr;
    gap: var(--gap-group) 24px;
  }
}

/* ══════════════════════════════════════════════════════════
   Desktop — 1024px and above: exact 50/50 split, both panels
   100dvh, hero content centred as one block.
   ══════════════════════════════════════════════════════════ */
@media (min-width: 1024px) {
  .login-shell {
    flex-direction: row;
    height: 100dvh;

    --logo-size: 96px;
    --fs-title: 32px;
    --lh-title: 1.15;
    --fs-tagline: 18px;
    --fs-desc: 16px;
    --lh-desc: 1.5;
    --fs-heading: 28px;
    --fs-sub: 16px;
    --h-control: 52px;

    --gap-logo-title: 24px;
    --gap-title-tagline: 20px;
    --gap-tagline-desc: 12px;
    --hero-min: 100dvh;
    --hero-pad-y: 64px;
    --hero-pad-x: 64px;

    --gap-google: 28px;
    --gap-divider: 24px;
    --gap-group: 28px;
    --form-pad-top: 0;
    --form-pad-x: 0;
    --form-pad-bottom: 0;
    --panel-pad-x: 40px;
    --panel-overlap: 0px;
    --panel-radius: 0px;
  }

  .login-brand {
    flex: 0 0 50%;
    height: 100dvh;
  }

  .login-panel {
    flex: 1 1 50%;
    height: 100dvh;
    margin-top: 0;
    border-radius: 0;
    box-shadow: none;
  }

  .login-brand__footer {
    display: block;
    position: absolute;
    z-index: 2;
    left: 64px;
    right: 64px;
    bottom: 40px;
    margin: 0;
    text-align: center;
    color: rgba(255, 255, 255, 0.68);
    font-size: 12px;
    line-height: 1.5;
  }
}

/* ── Short viewports (e.g. 360×800) ───────────────────────── */
@media (max-width: 767px) and (max-height: 820px) {
  .login-shell {
    --logo-size: 56px;
    --hero-min: 200px;
    --hero-pad-y: 12px;
    --form-pad-top: 24px;
    --form-pad-bottom: 32px;
    --panel-overlap: 16px;
    --panel-radius: 16px;
  }
}

/* ══════════════════════════════════════════════════════════
   Small screens — full-screen photo background with a
   full-height frosted-glass form card floating over it.
   Type/spacing are compacted so the sign-in form fits the
   viewport; the shell scrolls only for taller content.
   ══════════════════════════════════════════════════════════ */
@media (max-width: 1023px) {
  .login-shell {
    --logo-size: 48px;
    --fs-title: 17px;
    --fs-tagline: 13px;
    --fs-heading: 21px;
    --fs-sub: 13px;
    --fs-label: 13px;
    --fs-control: 15px;
    --h-control: 46px;
    --gap-logo-title: 10px;
    --gap-title-tagline: 8px;
    --gap-heading-desc: 6px;
    --gap-google: 16px;
    --gap-hint: 8px;
    --gap-divider: 14px;
    --gap-group: 14px;
    --gap-submit: 18px;
    --form-pad-top: 20px;
    --form-pad-x: 20px;
    --form-pad-bottom: 22px;

    /* Light-on-dark palette for form controls on the photo */
    --login-ink: #ffffff;
    --login-muted: rgba(255, 255, 255, 0.78);
    --login-muted-soft: rgba(255, 255, 255, 0.65);
    --login-border: rgba(255, 255, 255, 0.35);
    --login-border-soft: rgba(255, 255, 255, 0.28);
    --login-surface: rgba(255, 255, 255, 0.1);

    height: 100dvh;
    overflow-y: auto;
    padding: 0 0 16px;
    background-color: #0c114c;
  }

  /* Photo comes from the shell background now — drop the local copy */
  .login-brand__image,
  .login-brand__overlay {
    display: none;
  }

  .login-brand {
    min-height: 0;
    padding: 24px 20px 16px;
  }

  .login-brand__title,
  .login-brand__tagline,
  .login-brand__description {
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.45);
  }

  /* Panel is layout only — the photo is the form's background */
  .login-panel {
    flex: 1 0 auto;
    margin-top: 0;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    overflow: visible;
  }

  .login-field:focus-within {
    background-color: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.9);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.22);
  }

  .login-field:focus-within .login-field__icon {
    color: #ffffff;
  }

  .login-field__toggle:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.12);
  }

  .login-google__domain {
    color: #ffffff;
  }

  .login-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  }

  .login-select option {
    color: #1f2328;
    background-color: #ffffff;
  }

  /* White CTA reads better on the dark photo than navy */
  .login-submit {
    background-color: #ffffff;
    border-color: #ffffff;
    color: var(--login-navy);
  }

  .login-submit:hover:not(:disabled) {
    background-color: #eef1f8;
    border-color: #eef1f8;
  }

  .login-submit:active:not(:disabled) {
    background-color: #dfe4f0;
    border-color: #dfe4f0;
  }

  .login-back:hover {
    color: #ffffff;
  }

  .login-alert {
    background-color: rgba(248, 81, 73, 0.16);
    border-color: rgba(248, 81, 73, 0.55);
    color: #ffd7d3;
  }

  .login-google:focus-visible,
  .login-submit:focus-visible,
  .login-field__toggle:focus-visible,
  .login-back:focus-visible {
    outline-color: #ffffff;
  }
}

@media (min-width: 768px) and (max-width: 1023px) {
  .login-shell {
    --form-pad-x: 32px;
    padding: 0 0 24px;
  }
}

/* Height-constrained phones — trim the hero so nothing scrolls */
@media (max-width: 1023px) and (max-height: 900px) {
  .login-brand__description {
    display: none;
  }
}

@media (max-width: 1023px) and (max-height: 700px) {
  .login-shell {
    --logo-size: 40px;
    --fs-title: 16px;
    --fs-heading: 19px;
    --h-control: 44px;
    --gap-google: 12px;
    --gap-divider: 10px;
    --gap-group: 12px;
    --gap-submit: 14px;
    --form-pad-top: 16px;
    --form-pad-bottom: 18px;
  }

  .login-brand {
    padding-top: 16px;
    padding-bottom: 12px;
  }

  .login-brand__tagline {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .login-google,
  .login-submit,
  .login-field,
  .login-field__toggle,
  .login-field__icon {
    transition: none;
  }
}
</style>
