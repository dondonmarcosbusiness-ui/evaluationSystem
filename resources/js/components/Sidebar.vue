<template>
  <div class="sidebar-overlay" @click="closeMobileSidebar"></div>
  <aside
    class="sidebar"
    :class="{ collapsed: isCollapsed }"
    v-bind="$attrs"
  >
    <!-- Mobile Header -->
    <div class="mobile-header d-md-none">
      <div class="d-flex align-items-center gap-2">
        <img
          :src="`${basePath}/assets/img/neust_logo.webp`"
          alt="NEUST Logo"
          class="brand-logo"
          style="height: 44px; width: auto"
        />
        <div class="lh-1">
          <div class="text-white fw-bold" style="font-size: 1.15rem">NEUST</div>
          <div class="text-white fw-bold" style="font-size: 0.75rem; opacity: 0.8">CARRANGLAN</div>
        </div>
      </div>
      <button
        class="btn btn-link text-white p-2 text-decoration-none"
        style="font-size: 1.5rem; opacity: 0.9"
        @click="closeMobileSidebar"
      >
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Desktop Brand Header -->
    <div class="sidebar-brand d-none d-md-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3 overflow-hidden">
        <img
          :src="`${basePath}/assets/img/neust_logo.webp`"
          alt="NEUST Logo"
          class="brand-logo"
          style="height: 40px; width: auto; flex-shrink: 0"
        />
        <div v-show="!isCollapsed" class="brand-text text-truncate">
          <h6 class="mb-0 fw-bold brand-title" style="line-height: 1.25">
            NEUST
            <br />
            CARRANGLAN
          </h6>
        </div>
      </div>
      <button class="sidebar-toggle d-none d-md-flex" @click="toggleSidebar" :title="isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'">
        <i class="fas fa-bars-staggered"></i>
      </button>
    </div>

    <!-- Sidebar Navigation List -->
    <ul class="sidebar-nav">
      <!-- Universal Dashboard -->
      <li>
        <router-link to="/dashboard" class="nav-link">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
          <span class="nav-tooltip">Dashboard</span>
        </router-link>
      </li>

      <!-- Student Direct Link -->
      <li v-if="$can('give_evaluations')">
        <router-link to="/evaluate" class="nav-link">
          <i class="fas fa-star"></i>
          <span>Evaluate Faculty</span>
          <span class="nav-tooltip">Evaluate Faculty</span>
        </router-link>
      </li>

      <!-- Accounts Section Label -->
      <li v-if="canSeeAccountsSection" class="sidebar-nav-section">
        <span class="sidebar-section-label">Accounts</span>
      </li>

      <!-- Faculty Management (Flyout Trigger) -->
      <li
        v-if="$can('manage_faculty')"
        class="nav-item-flyout"
        :class="{ active: isFacultyActive, 'flyout-open': activeFlyout === 'faculty' }"
        @mouseenter="openFlyout('faculty', $event)"
        @mouseleave="closeFlyout('faculty')"
      >
        <div class="nav-link" @click="toggleFlyout('faculty', $event)">
          <i class="fas fa-chalkboard-teacher"></i>
          <span>Faculty Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Faculty Management</span>
        </div>
      </li>

      <!-- Office Management (Flyout Trigger) -->
      <li
        v-if="$can('manage_offices') || $can('manage_faculty')"
        class="nav-item-flyout"
        :class="{ active: isOfficeActive, 'flyout-open': activeFlyout === 'office' }"
        @mouseenter="openFlyout('office', $event)"
        @mouseleave="closeFlyout('office')"
      >
        <div class="nav-link" @click="toggleFlyout('office', $event)">
          <i class="fas fa-building"></i>
          <span>Office Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Office Management</span>
        </div>
      </li>

      <!-- Students Management (Flyout Trigger) -->
      <li
        v-if="$can('manage_users')"
        class="nav-item-flyout"
        :class="{ active: isStudentsActive, 'flyout-open': activeFlyout === 'students' }"
        @mouseenter="openFlyout('students', $event)"
        @mouseleave="closeFlyout('students')"
      >
        <div class="nav-link" @click="toggleFlyout('students', $event)">
          <i class="fas fa-user-graduate"></i>
          <span>Students Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Students Management</span>
        </div>
      </li>

      <!-- Reports Section Label -->
      <template v-if="canSeeReportsSection">
        <li class="sidebar-nav-section">
          <span class="sidebar-section-label">Reports</span>
        </li>

        <!-- Faculty Reports (Flyout Trigger) -->
        <li
          v-if="canSeeFacultyReports"
          class="nav-item-flyout"
          :class="{ active: isFacultyReportsActive, 'flyout-open': activeFlyout === 'reports' }"
          @mouseenter="openFlyout('reports', $event)"
          @mouseleave="closeFlyout('reports')"
        >
          <div class="nav-link" @click="toggleFlyout('reports', $event)">
            <i class="fas fa-chart-line"></i>
            <span>Faculty Reports</span>
            <i class="fas fa-chevron-right ms-auto arrow"></i>
            <span class="nav-tooltip">Faculty Reports</span>
          </div>
        </li>
      </template>

      <!-- System Section Label -->
      <li v-if="$can('manage_courses')" class="sidebar-nav-section">
        <span class="sidebar-section-label">System</span>
      </li>

      <!-- Course List -->
      <li v-if="$can('manage_courses')">
        <router-link to="/courses" class="nav-link">
          <i class="fas fa-book"></i>
          <span>Course List</span>
          <span class="nav-tooltip">Course List</span>
        </router-link>
      </li>
    </ul>

    <!-- Footer User Profile Area -->
    <div class="sidebar-footer">
      <div
        class="nav-item-flyout user-flyout-container"
        :class="{ 'flyout-open': activeFlyout === 'user' }"
        @mouseenter="openFlyout('user', $event, true)"
        @mouseleave="closeFlyout('user')"
      >
        <div
          class="user-card-trigger d-flex align-items-center gap-3 p-2 rounded-3 cursor-pointer"
          @click="toggleFlyout('user', $event, true)"
        >
          <div class="sidebar-avatar flex-shrink-0 shadow-sm">
            {{ initials }}
          </div>
          <div v-show="!isCollapsed" class="user-details overflow-hidden flex-grow-1">
            <div class="user-name text-truncate fw-bold">
              {{ user.name }}
            </div>
            <div class="user-role text-capitalize text-truncate">
              {{ user.role }}
            </div>
          </div>
          <i v-show="!isCollapsed" class="fas fa-ellipsis-vertical user-menu-icon ms-auto"></i>
        </div>
      </div>
    </div>

    <!-- Password Modal -->
    <ChangePasswordModal :show="showChangePassword" @close="showChangePassword = false" />
  </aside>

  <!-- Teleported Glassmorphic Flyout Submenus (Appears outside sidebar clipping box) -->
  <Teleport to="body">
    <!-- Faculty Management Flyout -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'faculty'"
        class="flyout-menu"
        :style="flyoutStyle"
        @mouseenter="openFlyout('faculty')"
        @mouseleave="closeFlyout('faculty')"
      >
        <div class="flyout-header d-flex align-items-center gap-2">
          <i class="fas fa-chalkboard-teacher text-primary"></i>
          <span>Faculty Management</span>
        </div>
        <ul class="flyout-nav">
          <li>
            <router-link to="/faculty" @click="activeFlyout = null">
              <i class="fas fa-user-shield"></i>
              <span>Faculty Accounts</span>
            </router-link>
          </li>
          <li>
            <router-link to="/assignments" @click="activeFlyout = null">
              <i class="fas fa-link"></i>
              <span>Faculty Assignments</span>
            </router-link>
          </li>
          <li v-if="$can('manage_categories') || $can('manage_questions')">
            <router-link to="/questionnaire/faculty" @click="activeFlyout = null">
              <i class="fas fa-list-alt"></i>
              <span>Faculty Questionnaires</span>
            </router-link>
          </li>
        </ul>
      </div>
    </Transition>

    <!-- Office Management Flyout -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'office'"
        class="flyout-menu"
        :style="flyoutStyle"
        @mouseenter="openFlyout('office')"
        @mouseleave="closeFlyout('office')"
      >
        <div class="flyout-header d-flex align-items-center gap-2">
          <i class="fas fa-building text-primary"></i>
          <span>Office Management</span>
        </div>
        <ul class="flyout-nav">
          <li>
            <router-link to="/offices" @click="activeFlyout = null">
              <i class="fas fa-building"></i>
              <span>Office Directory</span>
            </router-link>
          </li>
          <li v-if="$can('view_reports') || $can('manage_offices')">
            <router-link to="/office-reports" @click="activeFlyout = null">
              <i class="fas fa-chart-bar"></i>
              <span>Office Reports</span>
            </router-link>
          </li>
          <li>
            <router-link to="/questionnaire/office" @click="activeFlyout = null">
              <i class="fas fa-list-check"></i>
              <span>Evaluation Questions</span>
            </router-link>
          </li>
        </ul>
      </div>
    </Transition>

    <!-- Students Management Flyout -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'students'"
        class="flyout-menu"
        :style="flyoutStyle"
        @mouseenter="openFlyout('students')"
        @mouseleave="closeFlyout('students')"
      >
        <div class="flyout-header d-flex align-items-center gap-2">
          <i class="fas fa-user-graduate text-primary"></i>
          <span>Students Management</span>
        </div>
        <ul class="flyout-nav">
          <li>
            <router-link to="/students/regular" @click="activeFlyout = null">
              <i class="fas fa-user"></i>
              <span>Regular Students</span>
            </router-link>
          </li>
          <li>
            <router-link to="/students/irregular" @click="activeFlyout = null">
              <i class="fas fa-user-minus"></i>
              <span>Irregular Students</span>
            </router-link>
          </li>
        </ul>
      </div>
    </Transition>

    <!-- Faculty Reports Flyout -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'reports'"
        class="flyout-menu"
        :style="flyoutStyle"
        @mouseenter="openFlyout('reports')"
        @mouseleave="closeFlyout('reports')"
      >
        <div class="flyout-header d-flex align-items-center gap-2">
          <i class="fas fa-chart-line text-primary"></i>
          <span>Faculty Reports</span>
        </div>
        <ul class="flyout-nav">
          <li>
            <router-link
              :to="reportLink('/reports', 'faculty')"
              active-class=""
              exact-active-class=""
              :class="{ 'router-link-active': isReportNavActive('/reports', 'faculty') }"
              @click="activeFlyout = null"
            >
              <i class="fas fa-chart-bar"></i>
              <span>{{ user.role === 'faculty' ? 'My Ratings Overview' : 'Ratings Overview' }}</span>
            </router-link>
          </li>
          <li>
            <router-link
              :to="reportLink('/set-report', 'faculty')"
              active-class=""
              exact-active-class=""
              :class="{ 'router-link-active': isReportNavActive('/set-report', 'faculty') }"
              @click="activeFlyout = null"
            >
              <i class="fas fa-file-invoice"></i>
              <span>{{ user.role === 'faculty' ? 'My SET Report' : 'Detailed SET Report' }}</span>
            </router-link>
          </li>
          <li v-if="$can('view_reports')">
            <router-link
              :to="reportLink('/feedbacks', 'faculty')"
              active-class=""
              exact-active-class=""
              :class="{ 'router-link-active': isReportNavActive('/feedbacks', 'faculty') }"
              @click="activeFlyout = null"
            >
              <i class="fas fa-comments"></i>
              <span>Feedback Management</span>
            </router-link>
          </li>
        </ul>
      </div>
    </Transition>

    <!-- User Profile Flyout Popover -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'user'"
        class="flyout-menu user-flyout-popover"
        :style="flyoutStyle"
        @mouseenter="openFlyout('user')"
        @mouseleave="closeFlyout('user')"
      >
        <div class="user-popover-header p-3 border-bottom border-secondary border-opacity-10">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="sidebar-avatar lg shadow-sm">
              {{ initials }}
            </div>
            <div class="overflow-hidden">
              <div class="fw-bold text-truncate user-popover-name" :title="user.name">
                {{ user.name }}
              </div>
              <div class="small text-truncate user-popover-email" :title="user.email">
                {{ user.email }}
              </div>
            </div>
          </div>
          <span class="badge role-pill text-capitalize">{{ user.role }}</span>
        </div>

        <div class="user-popover-actions p-2 d-flex flex-column gap-1">
          <!-- Change Password -->
          <button
            v-if="canChangePassword"
            type="button"
            class="user-action-btn d-flex align-items-center gap-3 w-100 px-3 py-2 rounded-2 border-0 bg-transparent"
            @click="openChangePassword"
          >
            <i class="fas fa-key text-info"></i>
            <span>Change Password</span>
          </button>

          <!-- Logout -->
          <button
            type="button"
            class="user-action-btn logout-btn d-flex align-items-center gap-3 w-100 px-3 py-2 rounded-2 border-0 bg-transparent text-danger"
            @click="logout"
          >
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, getCurrentInstance, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { confirmAction } from "../composables/useConfirm.js";
import ChangePasswordModal from "./ChangePasswordModal.vue";
import { syncThemeForUser } from "../helpers/theme.js";

const router = useRouter();
const route = useRoute();
const instance = getCurrentInstance();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isCollapsed = ref(localStorage.getItem("sidebarCollapsed") === "true");
const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";

const activeFlyout = ref(null);
const flyoutStyle = ref({ top: "0px", left: "0px", visibility: "visible" });
let flyoutTimeout = null;

const reportPaths = ["/reports", "/set-report", "/archive", "/feedbacks"];

// Computed Active States for Parent Nav Items
const isFacultyActive = computed(() => {
  const paths = ["/faculty", "/assignments", "/questionnaire/faculty"];
  return paths.some((p) => route.path.startsWith(p));
});

const isOfficeActive = computed(() => {
  const paths = ["/offices", "/office-reports", "/questionnaire/office"];
  return paths.some((p) => route.path.startsWith(p));
});

const isStudentsActive = computed(() => {
  const paths = ["/students/regular", "/students/irregular"];
  return paths.some((p) => route.path.startsWith(p));
});

const isFacultyReportsActive = computed(() => {
  return reportPaths.includes(route.path);
});

const canSeeAccountsSection = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("manage_faculty") || can?.("manage_users");
});

const canSeeReportsSection = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("view_reports") || user.value.role === "faculty";
});

const canSeeFacultyReports = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("view_reports") || user.value.role === "faculty";
});

function reportLink(path, type) {
  return { path, query: { type } };
}

function isReportNavActive(path, type) {
  if (route.path !== path) return false;
  return type === "faculty";
}

const showChangePassword = ref(false);

const canChangePassword = computed(() => ["student", "faculty"].includes(user.value.role));
const initials = computed(() =>
  (user.value.name || "U")
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);

async function updateFlyoutPosition(targetEl, isUser = false) {
  if (!targetEl) return;
  const rect = targetEl.getBoundingClientRect();
  const viewportHeight = window.innerHeight;

  // Temporarily set initial position to allow Vue to mount & render DOM for exact height calculation
  flyoutStyle.value = {
    top: `${Math.max(12, rect.top)}px`,
    left: `${rect.right + 8}px`,
    visibility: "hidden",
  };

  await nextTick();

  const flyoutEl = document.querySelector(".flyout-menu");
  const actualHeight = flyoutEl ? flyoutEl.offsetHeight : (isUser ? 280 : 220);

  let topPos;
  if (isUser) {
    // Position user popover so its bottom edge aligns with trigger card bottom
    topPos = rect.bottom - actualHeight;
  } else {
    // Position flyout top aligned with trigger item top
    topPos = rect.top - 4;
  }

  // Prevent overflowing bottom of screen (keep 16px safety padding above taskbar/screen bottom)
  if (topPos + actualHeight > viewportHeight - 16) {
    topPos = viewportHeight - actualHeight - 16;
  }

  // Prevent overflowing top of screen
  if (topPos < 12) {
    topPos = 12;
  }

  flyoutStyle.value = {
    top: `${topPos}px`,
    left: `${rect.right + 8}px`,
    visibility: "visible",
  };
}

async function openFlyout(name, event = null, isUser = false) {
  if (flyoutTimeout) clearTimeout(flyoutTimeout);
  const targetEl = event?.currentTarget || (name === 'user' ? document.querySelector('.user-card-trigger') : null);
  activeFlyout.value = name;
  if (targetEl) {
    await updateFlyoutPosition(targetEl, isUser);
  }
}

function closeFlyout(name) {
  if (flyoutTimeout) clearTimeout(flyoutTimeout);
  flyoutTimeout = setTimeout(() => {
    if (activeFlyout.value === name) {
      activeFlyout.value = null;
    }
  }, 200);
}

async function toggleFlyout(name, event = null, isUser = false) {
  if (flyoutTimeout) clearTimeout(flyoutTimeout);
  if (activeFlyout.value === name) {
    activeFlyout.value = null;
  } else {
    activeFlyout.value = name;
    const targetEl = event?.currentTarget || (name === 'user' ? document.querySelector('.user-card-trigger') : null);
    if (targetEl) {
      await updateFlyoutPosition(targetEl, isUser);
    }
  }
}

function handleOutsideClick(event) {
  const sidebar = document.querySelector(".sidebar");
  const openFlyoutEl = document.querySelector(".flyout-menu");
  if (
    sidebar &&
    !sidebar.contains(event.target) &&
    openFlyoutEl &&
    !openFlyoutEl.contains(event.target)
  ) {
    activeFlyout.value = null;
  }
}

function openChangePassword() {
  activeFlyout.value = null;
  showChangePassword.value = true;
}

onMounted(() => {
  updateLayout();
  syncThemeForUser(user.value);
  document.addEventListener("click", handleOutsideClick);
  window.addEventListener("scroll", handleScroll, true);
});

onUnmounted(() => {
  document.removeEventListener("click", handleOutsideClick);
  window.removeEventListener("scroll", handleScroll, true);
});

function handleScroll() {
  activeFlyout.value = null;
}

// Close flyout automatically when route changes
watch(
  () => route.fullPath,
  () => {
    activeFlyout.value = null;
  },
);

function toggleSidebar() {
  isCollapsed.value = !isCollapsed.value;
  localStorage.setItem("sidebarCollapsed", isCollapsed.value);
  activeFlyout.value = null;
  updateLayout();
}

function closeMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (sidebar) sidebar.classList.remove("mobile-show");
  if (overlay) overlay.classList.remove("show");
  activeFlyout.value = null;
}

function updateLayout() {
  const wrapper = document.querySelector(".main-wrapper");
  if (wrapper) {
    if (isCollapsed.value) {
      wrapper.classList.add("collapsed");
    } else {
      wrapper.classList.remove("collapsed");
    }
  }
}

async function logout() {
  activeFlyout.value = null;
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
/* ── Flyout Transition ─────────── */
.flyout-enter-active,
.flyout-leave-active {
  transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

.flyout-enter-from,
.flyout-leave-to {
  opacity: 0;
  transform: translateX(-6px) scale(0.97);
}

/* ── User Avatar ────────────────── */
.sidebar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #191970;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  letter-spacing: 0.03em;
  flex-shrink: 0;
}

.sidebar-avatar.lg {
  width: 40px;
  height: 40px;
  font-size: 0.9rem;
}

/* ── Footer & User Card ─────────── */
.sidebar-footer {
  padding: 12px 14px;
  margin-top: auto;
  border-top: 1px solid var(--border-color);
}

.user-card-trigger {
  transition: background 0.15s ease;
  user-select: none;
}

.user-card-trigger:hover {
  background: var(--bg-light);
}

.user-name {
  font-size: 0.9rem;
  color: var(--text-main);
  line-height: 1.3;
}

.user-role {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.user-menu-icon {
  font-size: 0.85rem;
  color: var(--text-muted);
  opacity: 0.7;
}

/* User Popover Specifics */
.user-flyout-popover {
  min-width: 250px;
}

.user-popover-name {
  color: #ffffff;
  font-size: 0.95rem;
}

.user-popover-email {
  color: rgba(255, 255, 255, 0.65);
}

.role-pill {
  background: var(--badge-info-bg);
  color: var(--badge-info-text);
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 3px 10px;
  border-radius: 999px;
  margin-top: 8px;
  display: inline-block;
}

.user-action-btn {
  font-size: 0.85rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.85);
  border-radius: 4px;
  transition: background 0.15s ease, color 0.15s ease;
}

.user-action-btn:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}

.user-action-btn.logout-btn {
  color: #ff8f8f !important;
}

.user-action-btn.logout-btn:hover {
  background: rgba(240, 82, 82, 0.18);
  color: #ff6b6b !important;
}

/* Collapsed footer adjustments */
.sidebar.collapsed .sidebar-footer {
  padding: 10px 8px;
}

.sidebar.collapsed .user-card-trigger {
  justify-content: center;
  padding: 8px 0;
}
</style>
