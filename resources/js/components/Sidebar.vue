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
  </aside>

  <!-- Teleported Glassmorphic Flyout Submenus (Appears outside sidebar clipping box) -->
  <Teleport to="body">
    <!-- Faculty Management Flyout -->
    <Transition name="flyout">
      <div
        v-if="activeFlyout === 'faculty'"
        class="flyout-menu"
        :style="flyoutStyle"
      >
        <button class="flyout-close-btn" @click="activeFlyout = null" title="Close">
          <i class="fas fa-times"></i>
        </button>
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
      >
        <button class="flyout-close-btn" @click="activeFlyout = null" title="Close">
          <i class="fas fa-times"></i>
        </button>
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
      >
        <button class="flyout-close-btn" @click="activeFlyout = null" title="Close">
          <i class="fas fa-times"></i>
        </button>
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
      >
        <button class="flyout-close-btn" @click="activeFlyout = null" title="Close">
          <i class="fas fa-times"></i>
        </button>
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
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, getCurrentInstance, nextTick } from "vue";
import { useRoute } from "vue-router";
import { syncThemeForUser } from "../helpers/theme.js";

const route = useRoute();
const instance = getCurrentInstance();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isCollapsed = ref(localStorage.getItem("sidebarCollapsed") === "true");
const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";

const activeFlyout = ref(null);
const flyoutStyle = ref({ top: "0px", left: "0px", visibility: "visible" });

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
  const targetEl = event?.currentTarget || null;
  activeFlyout.value = name;
  if (targetEl) {
    await updateFlyoutPosition(targetEl, isUser);
  }
}

async function toggleFlyout(name, event = null, isUser = false) {
  if (activeFlyout.value === name) {
    activeFlyout.value = null;
  } else {
    const targetEl = event?.currentTarget || null;
    activeFlyout.value = name;
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

/* ── Flyout Close Button ──────── */
.flyout-close-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 0.7rem;
  transition: all 0.15s ease;
  z-index: 1;
}

.flyout-close-btn:hover {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}
</style>
