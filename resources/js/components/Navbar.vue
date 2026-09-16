<template>
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-link text-dark p-0 d-md-none" @click="toggleMobileSidebar">
        <i class="fas fa-bars fa-lg"></i>
      </button>
      <span class="topbar-title"><slot name="title">Dashboard</slot></span>
    </div>
    <div class="topbar-right">
      <!-- Language Switcher -->
      <LanguageSwitcher v-if="user.role === 'student'" />
      <!-- Theme Toggle (admin only — dark mode applies to admins) -->
      <button
        v-if="isAdmin"
        type="button"
        class="topbar-icon-btn"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        @click="toggleTheme"
      >
        <i v-if="isDark" class="fas fa-sun"></i>
        <i v-else class="fas fa-moon"></i>
      </button>
      <template v-if="$can('manage_rbac')">
        <div v-if="isAdmin" class="topbar-divider" aria-hidden="true"></div>
        <!-- Settings (icon only) -->
      <router-link
        v-if="$can('manage_rbac')"
        to="/settings"
        class="topbar-icon-btn"
        title="Settings"
        aria-label="Settings"
      >
        <i class="fas fa-cog"></i>
      </router-link>
      <!-- Backup & Recovery (icon only) -->
      <router-link
        v-if="$can('manage_rbac')"
        to="/backups"
        class="topbar-icon-btn"
        title="Backup & Recovery"
        aria-label="Backup & Recovery"
      >
        <i class="fas fa-database"></i>
      </router-link>
      </template>

      <div class="topbar-divider" aria-hidden="true"></div>

      <!-- User Profile -->
      <div class="navbar-profile-container" ref="profileContainerRef">
        <button class="navbar-profile-trigger" @click="profileOpen = !profileOpen">
          <div class="navbar-avatar">{{ initials }}</div>
          <span class="navbar-profile-name d-none d-lg-inline">{{ user.name }}</span>
          <i class="fas fa-chevron-down navbar-profile-chevron" :class="{ rotated: profileOpen }"></i>
        </button>

        <transition name="dropdown-fade">
          <div v-if="profileOpen" class="navbar-profile-dropdown">
            <div class="navbar-profile-header">
              <div class="navbar-avatar lg">{{ initials }}</div>
              <div class="overflow-hidden">
                <div class="navbar-dropdown-name text-truncate">{{ user.name }}</div>
                <div class="navbar-dropdown-email text-truncate">{{ user.email }}</div>
              </div>
            </div>
            <div class="navbar-dropdown-actions">
              <button
                v-if="canChangePassword"
                class="navbar-dropdown-item"
                @click="openChangePassword"
              >
                <i class="fas fa-key"></i>
                <span>Change Password</span>
              </button>
              <button class="navbar-dropdown-item logout" @click="logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </transition>
      </div>

      <ChangePasswordModal :show="showChangePassword" @close="showChangePassword = false" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import api from "../services/api.js";
import LanguageSwitcher from "./LanguageSwitcher.vue";
import ChangePasswordModal from "./ChangePasswordModal.vue";
import { isAdminUser, getAdminThemeIsDark, setAdminTheme, syncThemeForUser } from "../helpers/theme.js";
import { confirmAction } from "../composables/useConfirm.js";

const router = useRouter();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isAdmin = isAdminUser(user.value);
const isDark = ref(getAdminThemeIsDark());
const profileOpen = ref(false);
const showChangePassword = ref(false);
const profileContainerRef = ref(null);

let themeObserver = null;

const canChangePassword = computed(() => ["student", "faculty"].includes(user.value.role));

const initials = computed(() =>
  (user.value.name || "U")
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);

function handleProfileOutsideClick(event) {
  if (profileContainerRef.value && !profileContainerRef.value.contains(event.target)) {
    profileOpen.value = false;
  }
}

onMounted(() => {
  isDark.value = document.documentElement.getAttribute("data-theme") === "dark";
  themeObserver = new MutationObserver(() => {
    isDark.value = document.documentElement.getAttribute("data-theme") === "dark";
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
  document.addEventListener("click", handleProfileOutsideClick);
});

onUnmounted(() => {
  themeObserver?.disconnect();
  document.removeEventListener("click", handleProfileOutsideClick);
});

function toggleTheme() {
  isDark.value = !isDark.value;
  setAdminTheme(isDark.value);
}

function toggleMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (sidebar) sidebar.classList.toggle("mobile-show");
  if (overlay) overlay.classList.toggle("show");
}

function openChangePassword() {
  profileOpen.value = false;
  showChangePassword.value = true;
}

async function logout() {
  profileOpen.value = false;
  const result = await confirmAction({
    title: "Ready to Leave?",
    message: "Are you sure you want to end your current session?",
  });

  if (result.isConfirmed) {
    try {
      await api.post("/logout");
    } catch {}
    syncThemeForUser(null);
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    router.push("/login");
  }
}
</script>

<style scoped>
.text-main {
  color: var(--text-dark);
}

.topbar-icon-btn {
  flex-shrink: 0;
  flex-grow: 0;
  min-height: 32px;
  min-width: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s ease;
}

.topbar-icon-btn:hover {
  background: var(--bg-light);
  border-color: var(--border-color);
  color: var(--text-dark);
}

.topbar-icon-btn.router-link-active {
  background: rgba(25, 25, 112, 0.08);
  border-color: var(--primary);
  color: var(--primary);
}

.topbar-divider {
  width: 1px;
  height: 24px;
  background: var(--border-color);
  flex-shrink: 0;
}

/* ── Profile Section ── */
.navbar-profile-container {
  position: relative;
}

.navbar-profile-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 10px 4px 4px;
  border: 1px solid var(--border-color);
  border-radius: 50px;
  background: var(--bg-card);
  cursor: pointer;
  transition: all 0.2s ease;
  color: var(--text-dark);
}

.navbar-profile-trigger:hover {
  background: var(--bg-light);
  border-color: var(--border-color);
}

.navbar-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #191970;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  flex-shrink: 0;
}

.navbar-avatar.lg {
  width: 38px;
  height: 38px;
  font-size: 0.85rem;
}

.navbar-profile-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.navbar-profile-chevron {
  font-size: 0.6rem;
  color: var(--text-muted);
  transition: transform 0.2s ease;
}

.navbar-profile-chevron.rotated {
  transform: rotate(180deg);
}

/* ── Profile Dropdown ── */
.navbar-profile-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 240px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  z-index: 2000;
  overflow: hidden;
}

.navbar-profile-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-light);
}

.navbar-dropdown-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-main);
}

.navbar-dropdown-email {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.navbar-dropdown-actions {
  padding: 6px;
}

.navbar-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 12px;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text-main);
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}

.navbar-dropdown-item:hover {
  background: var(--bg-light);
}

.navbar-dropdown-item i {
  font-size: 0.85rem;
  color: var(--text-muted);
  width: 18px;
  text-align: center;
}

.navbar-dropdown-item.logout {
  color: #ef4444;
}

.navbar-dropdown-item.logout i {
  color: #ef4444;
}

.navbar-dropdown-item.logout:hover {
  background: rgba(239, 68, 68, 0.08);
}

/* ── Dropdown Transition ── */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ── Dark mode ── */
[data-theme="dark"] .topbar-icon-btn {
  color: #b1bac4 !important;
  background: rgba(255, 255, 255, 0.04) !important;
  border-color: #30363d !important;
}

[data-theme="dark"] .topbar-icon-btn:hover {
  background: #1f6feb !important;
  border-color: #1f6feb !important;
  color: #ffffff !important;
}

[data-theme="dark"] .topbar-icon-btn.router-link-active {
  background: rgba(31, 111, 235, 0.18) !important;
  border-color: #1f6feb !important;
  color: #79c0ff !important;
}

[data-theme="dark"] .navbar-profile-trigger {
  background: rgba(255, 255, 255, 0.04) !important;
  border-color: #30363d !important;
}

[data-theme="dark"] .navbar-profile-trigger:hover {
  background: #1f6feb !important;
  border-color: #1f6feb !important;
}

[data-theme="dark"] .navbar-profile-name {
  color: #e6edf3 !important;
}

[data-theme="dark"] .navbar-profile-dropdown {
  background: #161b22 !important;
  border-color: #30363d !important;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5) !important;
}

[data-theme="dark"] .navbar-dropdown-name {
  color: #e6edf3 !important;
}

[data-theme="dark"] .navbar-dropdown-email {
  color: #8b949e !important;
}

[data-theme="dark"] .navbar-dropdown-item {
  color: #e6edf3 !important;
}

[data-theme="dark"] .navbar-dropdown-item:hover {
  background: rgba(31, 111, 235, 0.18) !important;
}

[data-theme="dark"] .navbar-dropdown-item i {
  color: #8b949e !important;
}
</style>
