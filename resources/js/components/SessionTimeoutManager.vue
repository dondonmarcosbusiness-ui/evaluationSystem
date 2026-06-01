<template>
  <Transition name="slide-fade">
    <div v-if="showModal" class="minimal-timeout-overlay">
      <div class="minimal-timeout-card shadow-lg animate__animated animate__fadeInUp">
        <div class="card-progress" :style="{ width: (countdown / 30) * 100 + '%' }"></div>
        
        <div class="d-flex align-items-center gap-4 px-4 py-3">
          <!-- Countdown Section -->
          <div class="minimal-countdown">
            <div class="number">{{ countdown }}</div>
            <div class="label">SEC</div>
          </div>

          <!-- Message Section -->
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="fas fa-shield-alt text-primary small"></i>
              <span class="fw-bold small text-uppercase tracking-wider">Security Alert</span>
            </div>
            <h6 class="mb-0 fw-600">Still active? Session will expire shortly.</h6>
          </div>

          <!-- Actions Section -->
          <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-4 rounded-pill fw-bold text-nowrap" @click="stayLoggedIn">
              Stay Logged In
            </button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill text-nowrap" @click="logoutNow">
              Logout
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api.js";
import { syncThemeForUser } from "../helpers/theme.js";

const router = useRouter();
const route = useRoute();

// Config (3 minutes inactivity + 30 seconds warning)
const INACTIVITY_LIMIT = 50 * 60 * 1000;
const WARNING_LIMIT = 30;

const showModal = ref(false);
const countdown = ref(WARNING_LIMIT);
const isAdmin = ref(false);

let inactivityTimer = null;
let countdownInterval = null;
let lastReset = 0;

const checkAdminStatus = () => {
  const user = JSON.parse(localStorage.getItem("user") || "{}");
  isAdmin.value = user.role === "admin";
};

const resetInactivityTimer = () => {
  if (!isAdmin.value || showModal.value) return;

  const now = Date.now();
  // Throttle resets to once every 2 seconds to improve performance
  if (now - lastReset < 2000) return;
  lastReset = now;

  if (inactivityTimer) clearTimeout(inactivityTimer);
  
  inactivityTimer = setTimeout(() => {
    startWarning();
  }, INACTIVITY_LIMIT);
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

onMounted(() => {
  checkAdminStatus();
  activityEvents.forEach((event) => {
    window.addEventListener(event, resetInactivityTimer, { passive: true });
  });
  if (isAdmin.value) {
    resetInactivityTimer();
  }
});

onUnmounted(() => {
  activityEvents.forEach((event) => {
    window.removeEventListener(event, resetInactivityTimer);
  });
  if (inactivityTimer) clearTimeout(inactivityTimer);
  if (countdownInterval) clearInterval(countdownInterval);
});

watch(() => route.path, () => {
  checkAdminStatus();
  if (isAdmin.value) {
    resetInactivityTimer();
  } else {
    if (inactivityTimer) clearTimeout(inactivityTimer);
    if (countdownInterval) clearInterval(countdownInterval);
    showModal.value = false;
  }
});
</script>

<style scoped>
.minimal-timeout-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 3rem;
}

.minimal-timeout-card {
  width: 100%;
  max-width: 700px;
  background: white;
  border-radius: 1rem;
  overflow: hidden;
  position: relative;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .minimal-timeout-card {
  background: #1e293b;
  border-color: rgba(255, 255, 255, 0.1);
  color: white;
}

.card-progress {
  position: absolute;
  top: 0;
  left: 0;
  height: 3px;
  background: #3b82f6;
  transition: width 1s linear;
}

.minimal-countdown {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 0.5rem 1rem;
  border-radius: 0.75rem;
  min-width: 70px;
}

[data-theme="dark"] .minimal-countdown {
  background: rgba(255, 255, 255, 0.05);
}

.minimal-countdown .number {
  font-size: 1.5rem;
  font-weight: 800;
  color: #ef4444;
  line-height: 1;
}

.minimal-countdown .label {
  font-size: 0.6rem;
  font-weight: 700;
  color: #64748b;
}

.slide-fade-enter-active {
  transition: all 0.4s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.3s ease-in;
}
.slide-fade-enter-from {
  transform: translateY(20px);
  opacity: 0;
}
.slide-fade-leave-to {
  transform: translateY(10px);
  opacity: 0;
}

@media (max-width: 768px) {
  .minimal-timeout-card {
    max-width: 90%;
  }
  .minimal-timeout-card .d-flex {
    flex-direction: column;
    text-align: center;
    padding: 2rem !important;
  }
  .d-flex.gap-2 {
    width: 100%;
    margin-top: 1rem;
  }
  .d-flex.gap-2 button {
    flex: 1;
  }
}
</style>
