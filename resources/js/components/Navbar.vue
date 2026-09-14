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
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import LanguageSwitcher from "./LanguageSwitcher.vue";
import { isAdminUser, getAdminThemeIsDark, setAdminTheme } from "../helpers/theme.js";

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isAdmin = isAdminUser(user.value);
const isDark = ref(getAdminThemeIsDark());

let themeObserver = null;

onMounted(() => {
  // Stay in sync when the theme is toggled elsewhere (e.g. sidebar popover).
  isDark.value = document.documentElement.getAttribute("data-theme") === "dark";
  themeObserver = new MutationObserver(() => {
    isDark.value = document.documentElement.getAttribute("data-theme") === "dark";
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
});

onUnmounted(() => {
  themeObserver?.disconnect();
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
</style>
