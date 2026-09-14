<template>
  <div ref="timeoutModalEl" class="modal fade timeout-dialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content timeout-content overflow-hidden position-relative">
        <div class="timeout-progress" :style="{ width: (countdown / 30) * 100 + '%' }"></div>
        <div class="modal-body timeout-body">
          <div class="timeout-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h5 class="timeout-title">Session expiring soon</h5>
          <div class="timeout-count">
            <span class="timeout-number">{{ countdown }}</span>
            <span class="timeout-label">seconds remaining</span>
          </div>
          <p class="timeout-message mb-0">Still active? You'll be signed out due to inactivity.</p>
        </div>
        <div class="modal-footer timeout-footer">
          <button type="button" class="timeout-btn timeout-logout" @click="logoutNow">Logout</button>
          <button type="button" class="timeout-btn timeout-stay" @click="stayLoggedIn">Stay Logged In</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api.js";
import { syncThemeForUser } from "../helpers/theme.js";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";

const router = useRouter();
const route = useRoute();

// Configurable via System Settings → Session Timeout.
// Inactivity limit comes from `session_timeout_minutes` (default 60);
// a fixed 30-second warning is shown before signing out.
const WARNING_LIMIT = 30;
const DEFAULT_MINUTES = 60;

const showModal = ref(false);
const { modalEl: timeoutModalEl } = useBootstrapModal(showModal, { backdrop: "static", keyboard: false });
const countdown = ref(WARNING_LIMIT);
const timeoutEnabled = ref(false);
const inactivityMs = ref(DEFAULT_MINUTES * 60 * 1000);

let inactivityTimer = null;
let countdownInterval = null;
let lastReset = 0;

function isAuthenticated() {
  return !!localStorage.getItem("token");
}

function isAdmin() {
  try {
    return JSON.parse(localStorage.getItem("user") || "{}")?.role === "admin";
  } catch {
    return false;
  }
}

async function loadTimeoutConfig() {
  try {
    const res = await api.get("/settings");
    const d = res.data || {};
    const enabled =
      d.session_timeout_enabled === true ||
      d.session_timeout_enabled === 1 ||
      d.session_timeout_enabled === "1" ||
      d.session_timeout_enabled === "true";
    const minutes = Number(d.session_timeout_minutes) || DEFAULT_MINUTES;
    return { enabled, minutes };
  } catch {
    return { enabled: false, minutes: DEFAULT_MINUTES };
  }
}

const resetInactivityTimer = () => {
  if (!timeoutEnabled.value || !isAuthenticated() || !isAdmin() || showModal.value) return;

  const now = Date.now();
  // Throttle resets to once every 2 seconds to improve performance
  if (now - lastReset < 2000) return;
  lastReset = now;

  if (inactivityTimer) clearTimeout(inactivityTimer);

  inactivityTimer = setTimeout(() => {
    startWarning();
  }, inactivityMs.value);
};

const startWarning = () => {
  showModal.value = true;
  countdown.value = WARNING_LIMIT;
  
  countdownInterval = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      clearInterval(countdownInterval);
      logoutNow();
    }
  }, 1000);
};

const stayLoggedIn = () => {
  showModal.value = false;
  clearInterval(countdownInterval);
  lastReset = 0; // Force immediate reset
  resetInactivityTimer();
};

const logoutNow = async () => {
  try {
    await api.post("/logout");
  } catch (err) {
    console.error("Logout failed during timeout", err);
  } finally {
    syncThemeForUser(null);
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    localStorage.removeItem("permissions");
    showModal.value = false;
    clearInterval(countdownInterval);
    router.push("/login");
  }
};

const activityEvents = ["mousemove", "keydown", "click", "scroll", "touchstart", "visibilitychange"];

// (Re)loads config from the server and re-arms. Called on mount and whenever
// settings are saved (same-tab live update — no refresh needed to test).
async function reloadConfig() {
  if (inactivityTimer) clearTimeout(inactivityTimer);
  if (!isAuthenticated() || !isAdmin()) {
    timeoutEnabled.value = false;
    return;
  }
  const cfg = await loadTimeoutConfig();
  timeoutEnabled.value = cfg.enabled;
  inactivityMs.value = cfg.minutes * 60 * 1000;
  if (cfg.enabled) {
    lastReset = 0;
    resetInactivityTimer();
  } else {
    // Timeout turned off while armed/warning → stand down immediately.
    if (countdownInterval) clearInterval(countdownInterval);
    showModal.value = false;
  }
}

onMounted(async () => {
  activityEvents.forEach((event) => {
    window.addEventListener(event, resetInactivityTimer, { passive: true });
  });
  window.addEventListener("session-timeout:reload", reloadConfig);
  await reloadConfig();
});

onUnmounted(() => {
  window.removeEventListener("session-timeout:reload", reloadConfig);
  activityEvents.forEach((event) => {
    window.removeEventListener(event, resetInactivityTimer);
  });
  if (inactivityTimer) clearTimeout(inactivityTimer);
  if (countdownInterval) clearInterval(countdownInterval);
});

watch(() => route.path, () => {
  if (timeoutEnabled.value && isAuthenticated() && isAdmin()) {
    resetInactivityTimer();
  } else {
    if (inactivityTimer) clearTimeout(inactivityTimer);
    if (countdownInterval) clearInterval(countdownInterval);
    showModal.value = false;
  }
});
</script>

<style scoped>
.timeout-content {
  border-radius: 1rem !important;
  overflow: hidden;
}

.timeout-progress {
  position: absolute;
  top: 0;
  left: 0;
  height: 3px;
  background: var(--danger);
  transition: width 1s linear;
  z-index: 1;
}

.timeout-body {
  text-align: center;
  padding: 1.5rem !important;
}

.timeout-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto 0.9rem;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  background: rgba(240, 82, 82, 0.1);
  color: var(--danger);
}

.timeout-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-dark);
  margin: 0 0 0.35rem;
}

.timeout-count {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}

.timeout-number {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  color: var(--danger);
  font-variant-numeric: tabular-nums;
}

.timeout-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
}

.timeout-message {
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--text-muted);
}

.timeout-dialog .modal-footer.timeout-footer {
  display: flex;
  align-items: stretch;
  padding: 0 !important;
  border-top: 1px solid var(--border-light);
}

.timeout-dialog .modal-footer.timeout-footer > * {
  margin: 0 !important;
}

.timeout-btn {
  flex: 1 1 0%;
  min-width: 0;
  border: 0;
  background: transparent;
  padding: 0.9rem 0.5rem;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background-color 0.15s ease, filter 0.15s ease, color 0.15s ease;
}

.timeout-logout {
  background: transparent;
  color: var(--text-muted);
  font-weight: 600;
  border-right: 1px solid var(--border-light);
}

.timeout-logout:hover {
  background: var(--bg-light);
  color: var(--danger);
}

.timeout-stay {
  background: var(--primary);
  color: #fff;
  font-weight: 800;
}

.timeout-stay:hover {
  filter: brightness(1.12);
}
</style>
