<template>
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-link text-dark p-0 d-md-none" @click="toggleMobileSidebar">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      <span class="topbar-title"><slot name="title">Dashboard</slot></span>
    </div>
    <div class="topbar-right">
      <!-- Theme Toggle -->
      <button
        v-if="user.role === 'admin'"
        class="btn-theme-toggle"
        @click="toggleTheme"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
      >
        <Transition name="theme-icon" mode="out-in">
          <i v-if="isDark" key="sun" class="fas fa-sun"></i>
          <i v-else key="moon" class="fas fa-moon"></i>
        </Transition>
      </button>
      
      <!-- Language Switcher -->
      <LanguageSwitcher v-if="user.role === 'student'" />

      <!-- User Dropdown (Avatar) -->
      <div class="dropdown">
        <div
          class="user-badge dropdown-toggle border-0 bg-transparent p-0 d-flex align-items-center gap-2"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          style="cursor: pointer"
        >
          <div class="avatar shadow-sm">{{ initials }}</div>
          <div class="d-none d-sm-block text-start">
            <div class="fw-semibold text-main lh-1" style="font-size: 0.85rem">
              {{ user.name }}
            </div>
            <div class="text-muted text-capitalize" style="font-size: 0.7rem">
              {{ user.role }}
            </div>
          </div>
        </div>

        <ul
          class="dropdown-menu dropdown-menu-end shadow-lg rounded-4 border-0 mt-2 p-3 overflow-hidden"
          style="width: 300px"
        >
          <li class="px-2 py-1">
            <div class="fw-bold text-dark text-truncate" :title="user.name">
              {{ user.name }}
            </div>
            <div class="text-muted small mb-2 text-truncate" :title="user.email">
              {{ user.email }}
            </div>
          </li>
          <li><hr class="dropdown-divider" /></li>

          <li v-if="canChangePassword" class="px-2 mb-2">
            <button
              class="btn btn-light btn-sm w-100 py-2 rounded-3 border-0 d-flex align-items-center justify-content-center gap-2"
              @click="openChangePassword"
            >
              <i class="fas fa-key text-primary"></i>
              {{ t.change_password }}
            </button>
          </li>

          <li class="px-2">
            <button
              class="btn btn-light btn-sm w-100 py-2 rounded-3 text-danger border-0 d-flex align-items-center justify-content-center gap-2"
              @click="logout"
            >
              <i class="fas fa-sign-out-alt"></i>
              {{ t.logout }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <ChangePasswordModal :show="showChangePassword" @close="showChangePassword = false" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api.js";
import Swal from "sweetalert2";
import LanguageSwitcher from "./LanguageSwitcher.vue";
import ChangePasswordModal from "./ChangePasswordModal.vue";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";
import { syncThemeForUser, setAdminTheme } from "../helpers/theme.js";

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value] || translations.en);

const router = useRouter();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const showChangePassword = ref(false);
const canChangePassword = computed(() => ["student", "faculty", "staff"].includes(user.value.role));
const initials = computed(() =>
  (user.value.name || "U")
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);
const isDark = ref(false);

onMounted(() => {
  isDark.value = syncThemeForUser(user.value) === "dark";
});

function toggleTheme() {
  isDark.value = !isDark.value;
  setAdminTheme(isDark.value);
}

function openChangePassword() {
  showChangePassword.value = true;
}

async function logout() {
  const res = await Swal.fire({
    title: t.value.confirm_logout_title,
    text: t.value.confirm_logout_text,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#4f46e5",
    cancelButtonColor: "#6b7280",
    confirmButtonText: `<span class="text-white"><i class="fas fa-sign-out-alt me-2"></i> ${t.value.yes_logout}</span>`,
    cancelButtonText: `<span class="text-white">${t.value.no_stay}</span>`,
    reverseButtons: true,
    customClass: {
      popup: "rounded-4 border-0 shadow-lg",
      confirmButton: "px-4 py-2 rounded-3 fw-bold",
      cancelButton: "px-4 py-2 rounded-3 fw-bold",
    },
  });

  if (res.isConfirmed) {
    try {
      await api.post("/logout");
    } catch {}
    syncThemeForUser(null);
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    router.push("/login");
  }
}

function toggleMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (sidebar) sidebar.classList.toggle("mobile-show");
  if (overlay) overlay.classList.toggle("show");
}
</script>

<style scoped>
.text-main {
  color: var(--text-dark);
}

.btn-theme-toggle {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--text-muted);
  font-size: 1.1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-theme-toggle:hover {
  background: var(--border-color);
  color: var(--text-dark);
  transform: rotate(15deg);
}

/* Theme icon transition */
.theme-icon-enter-active,
.theme-icon-leave-active {
  transition: all 0.2s ease;
}
.theme-icon-enter-from {
  opacity: 0;
  transform: rotate(-90deg) scale(0.5);
}
.theme-icon-leave-to {
  opacity: 0;
  transform: rotate(90deg) scale(0.5);
}
</style>
