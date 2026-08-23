<template>
  <div class="sidebar-overlay" @click="closeMobileSidebar"></div>
  <aside
    class="sidebar"
    :class="{ collapsed: isCollapsed }"
    v-bind="$attrs"
  >
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
    <div class="sidebar-brand d-none d-md-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <img
          :src="`${basePath}/assets/img/neust_logo.webp`"
          alt="NEUST Logo"
          class="brand-logo"
          style="height: 40px; width: auto"
        />
        <div v-show="!isCollapsed">
          <h6 class="mb-0 text-white fw-bold" style="line-height: 1.2">
            NEUST
            <br />
            CARRANGLAN
          </h6>
        </div>
      </div>
      <button class="sidebar-toggle d-none d-md-flex" @click="toggleSidebar">
        <i class="fas fa-bars-staggered"></i>
      </button>
    </div>

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

      <!-- Accounts Management -->
      <li v-if="canSeeAccountsSection" class="sidebar-nav-section">
        <span class="sidebar-section-label">Accounts</span>
      </li>

      <!-- Faculty Management -->
      <li v-if="$can('manage_faculty')" class="nav-item-dropdown" :class="{ open: isFacultyOpen }">
        <div class="nav-link" @click="toggleFaculty">
          <i class="fas fa-chalkboard-teacher"></i>
          <span>Faculty Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Faculty Management</span>
        </div>
        <ul class="sidebar-submenu">
          <li>
            <router-link to="/faculty">
              <i class="fas fa-user-shield"></i>
              <span>Faculty Accounts</span>
            </router-link>
          </li>
          <li>
            <router-link to="/assignments">
              <i class="fas fa-link"></i>
              <span>Faculty Assignments</span>
            </router-link>
          </li>
          <li v-if="$can('manage_categories') || $can('manage_questions')">
            <router-link to="/questionnaire/faculty">
              <i class="fas fa-list-alt"></i>
              <span>Faculty Questionnaires</span>
            </router-link>
          </li>
        </ul>
      </li>

      <!-- Office Management -->
      <li v-if="$can('manage_offices') || $can('manage_faculty')" class="nav-item-dropdown" :class="{ open: isOfficeOpen }">
        <div class="nav-link" @click="toggleOffice">
          <i class="fas fa-building"></i>
          <span>Office Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Office Management</span>
        </div>
        <ul class="sidebar-submenu">
          <li>
            <router-link to="/offices">
              <i class="fas fa-building"></i>
              <span>Office Directory</span>
            </router-link>
          </li>
          <li v-if="$can('view_reports') || $can('manage_offices')">
            <router-link to="/office-reports">
              <i class="fas fa-chart-bar"></i>
              <span>Office Reports</span>
            </router-link>
          </li>
          <li>
            <router-link to="/questionnaire/office">
              <i class="fas fa-list-check"></i>
              <span>Evaluation Questions</span>
            </router-link>
          </li>
        </ul>
      </li>

      <!-- Students Management -->
      <li v-if="$can('manage_users')" class="nav-item-dropdown" :class="{ open: isStudentsOpen }">
        <div class="nav-link" @click="toggleStudents">
          <i class="fas fa-user-graduate"></i>
          <span>Students Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Students Management</span>
        </div>
        <ul class="sidebar-submenu">
          <li>
            <router-link to="/students/regular">
              <i class="fas fa-user"></i>
              <span>Regular Students</span>
            </router-link>
          </li>
          <li>
            <router-link to="/students/irregular">
              <i class="fas fa-user-minus"></i>
              <span>Irregular Students</span>
            </router-link>
          </li>
        </ul>
      </li>

      <!-- Reports Management -->
      <template v-if="canSeeReportsSection">
        <li class="sidebar-nav-section">
          <span class="sidebar-section-label">Reports</span>
        </li>

        <!-- Faculty Reports -->
        <li v-if="canSeeFacultyReports" class="nav-item-dropdown" :class="{ open: isFacultyReportsOpen }">
          <div class="nav-link" @click="toggleFacultyReports">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>Faculty Reports</span>
            <i class="fas fa-chevron-right ms-auto arrow"></i>
            <span class="nav-tooltip">Faculty Reports</span>
          </div>
          <ul class="sidebar-submenu">
            <li>
              <router-link :to="reportLink('/reports', 'faculty')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/reports', 'faculty') }">
                <i class="fas fa-chart-bar"></i>
                <span>{{ user.role === 'faculty' ? 'My Ratings Overview' : 'Ratings Overview' }}</span>
              </router-link>
            </li>
            <li>
              <router-link :to="reportLink('/set-report', 'faculty')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/set-report', 'faculty') }">
                <i class="fas fa-file-invoice"></i>
                <span>{{ user.role === 'faculty' ? 'My SET Report' : 'Detailed SET Report' }}</span>
              </router-link>
            </li>
            <li v-if="$can('view_reports')">
              <router-link :to="reportLink('/feedbacks', 'faculty')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/feedbacks', 'faculty') }">
                <i class="fas fa-comments"></i>
                <span>Feedback Management</span>
              </router-link>
            </li>
          </ul>
        </li>
      </template>

      <!-- Course List -->
      <li v-if="$can('manage_courses')">
        <router-link to="/courses" class="nav-link">
          <i class="fas fa-book"></i>
          <span>Course List</span>
          <span class="nav-tooltip">Course List</span>
        </router-link>
      </li>

      <!-- System settings -->
      <li v-if="$can('manage_rbac')">
        <router-link to="/settings" class="nav-link">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
          <span class="nav-tooltip">Settings</span>
        </router-link>
      </li>
      <li v-if="$can('manage_rbac')">
        <router-link to="/backups" class="nav-link">
          <i class="fas fa-database"></i>
          <span>Backup & Recovery</span>
          <span class="nav-tooltip">Backup & Recovery</span>
        </router-link>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="dropup w-100 position-relative">
        <div class="d-flex align-items-center justify-content-between gap-2 p-1 rounded-3 sidebar-user-card">
          <!-- Theme Toggle Button -->
          <button
            v-if="user.role === 'admin'"
            type="button"
            class="btn-theme-toggle flex-shrink-0"
            @click.stop="toggleTheme"
            :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
          >
            <Transition name="theme-icon" mode="out-in">
              <i v-if="isDark" key="sun" class="fas fa-sun text-warning"></i>
              <i v-else key="moon" class="fas fa-moon text-white"></i>
            </Transition>
          </button>

          <!-- User Badge (Dropdown Trigger) -->
          <div
            class="user-badge-trigger d-flex align-items-center gap-2 flex-grow-1 cursor-pointer overflow-hidden rounded-3 p-1"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            title="Account Options"
          >
            <div class="sidebar-avatar shadow-sm flex-shrink-0">
              {{ initials }}
            </div>
            <div v-show="!isCollapsed" class="user-details text-start overflow-hidden flex-grow-1">
              <div class="user-name text-truncate d-flex align-items-center justify-content-between fw-bold">
                <span class="text-truncate me-1">{{ user.name }}</span>
                <i class="fas fa-caret-down ms-1 text-muted small flex-shrink-0"></i>
              </div>
              <div class="user-role text-capitalize text-truncate text-muted">
                {{ user.role }}
              </div>
            </div>
          </div>

          <!-- Dropup Menu -->
          <ul class="dropdown-menu shadow-lg rounded-4 border-0 mb-2 p-3 overflow-hidden" style="width: 250px">
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
                type="button"
                class="btn btn-light btn-sm w-100 py-2 rounded-3 border-0 d-flex align-items-center justify-content-center gap-2"
                @click="openChangePassword"
              >
                <i class="fas fa-key text-primary"></i>
                Change Password
              </button>
            </li>

            <li class="px-2">
              <button
                type="button"
                class="btn btn-light btn-sm w-100 py-2 rounded-3 text-danger border-0 d-flex align-items-center justify-content-center gap-2"
                @click="logout"
              >
                <i class="fas fa-sign-out-alt"></i>
                Logout
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <ChangePasswordModal :show="showChangePassword" @close="showChangePassword = false" />
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch, getCurrentInstance } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api.js";
import Swal from "sweetalert2";
import ChangePasswordModal from "./ChangePasswordModal.vue";
import { syncThemeForUser, setAdminTheme } from "../helpers/theme.js";

const router = useRouter();
const route = useRoute();
const instance = getCurrentInstance();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isCollapsed = ref(localStorage.getItem("sidebarCollapsed") === "true");
const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";

const isFacultyOpen = ref(false);
const isOfficeOpen = ref(false);
const isStudentsOpen = ref(false);
const isFacultyReportsOpen = ref(false);

const reportPaths = ["/reports", "/set-report", "/feedbacks"];

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
const isDark = ref(false);

const canChangePassword = computed(() => ["student", "faculty"].includes(user.value.role));
const initials = computed(() =>
  (user.value.name || "U")
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);

function toggleTheme() {
  isDark.value = !isDark.value;
  setAdminTheme(isDark.value);
}

function openChangePassword() {
  showChangePassword.value = true;
}

onMounted(() => {
  updateLayout();
  checkActiveDropdowns();
  isDark.value = syncThemeForUser(user.value) === "dark";
});

// Watch for route changes to keep dropdowns open if needed
watch(
  () => [route.path, route.query.type],
  () => {
    checkActiveDropdowns();
  },
);

function checkActiveDropdowns() {
  const facultyRoutes = ["/faculty", "/assignments", "/questionnaire/faculty"];
  const officeMgmtRoutes = ["/offices", "/office-reports", "/questionnaire/office"];
  const studentsRoutes = ["/students/regular", "/students/irregular"];

  if (facultyRoutes.some((path) => route.path.startsWith(path))) {
    isFacultyOpen.value = true;
  }
  if (officeMgmtRoutes.some((path) => route.path.startsWith(path))) {
    isOfficeOpen.value = true;
  }
  if (studentsRoutes.some((path) => route.path.startsWith(path))) {
    isStudentsOpen.value = true;
  }
  if (reportPaths.includes(route.path)) {
    // Only faculty reports are supported now
    isFacultyReportsOpen.value = true;
  }
}

function closeAllDropdowns() {
  isFacultyOpen.value = false;
  isOfficeOpen.value = false;
  isStudentsOpen.value = false;
  isFacultyReportsOpen.value = false;
}

function toggleFaculty() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isFacultyOpen.value = true;
  } else {
    const next = !isFacultyOpen.value;
    closeAllDropdowns();
    isFacultyOpen.value = next;
  }
}

function toggleOffice() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isOfficeOpen.value = true;
  } else {
    const next = !isOfficeOpen.value;
    closeAllDropdowns();
    isOfficeOpen.value = next;
  }
}

function toggleStudents() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isStudentsOpen.value = true;
  } else {
    const next = !isStudentsOpen.value;
    closeAllDropdowns();
    isStudentsOpen.value = next;
  }
}

function toggleFacultyReports() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isFacultyReportsOpen.value = true;
  } else {
    const next = !isFacultyReportsOpen.value;
    closeAllDropdowns();
    isFacultyReportsOpen.value = next;
  }
}

function toggleSidebar() {
  isCollapsed.value = !isCollapsed.value;
  localStorage.setItem("sidebarCollapsed", isCollapsed.value);
  updateLayout();
}

function closeMobileSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const overlay = document.querySelector(".sidebar-overlay");
  if (sidebar) sidebar.classList.remove("mobile-show");
  if (overlay) overlay.classList.remove("show");
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
  const result = await Swal.fire({
    title: "Ready to Leave?",
    text: "Are you sure you want to end your current session?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#191970",
    cancelButtonColor: "#6b7280",
    confirmButtonText: '<span class="text-white"><i class="fas fa-sign-out-alt me-2"></i> Yes, Logout</span>',
    cancelButtonText: '<span class="text-white">No, Stay Here</span>',
    reverseButtons: true,
    background: "#ffffff",
    color: "#1e293b",
    customClass: {
      popup: "rounded-4 border-0 shadow-lg",
      confirmButton: "px-4 py-2 rounded-3 fw-bold",
      cancelButton: "px-4 py-2 rounded-3 fw-bold",
    },
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
.sidebar-footer {
  padding: 0.85rem;
  margin-top: auto;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-user-card {
  transition: background 0.2s ease;
}

.user-badge-trigger {
  user-select: none;
  transition: background 0.2s ease;
}

.user-badge-trigger:hover {
  background: rgba(255, 255, 255, 0.06);
}

.sidebar-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: #eceff1;
  color: #191970;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.9rem;
  letter-spacing: 0.02em;
}

.user-name {
  font-size: 0.88rem;
  color: #f8fafc;
  line-height: 1.25;
}

[data-theme="dark"] .user-name {
  color: #f8fafc;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.65);
}

.btn-theme-toggle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.7);
  font-size: 1.15rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-theme-toggle:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  transform: rotate(15deg);
}

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

.sidebar.collapsed .sidebar-footer {
  padding: 0.75rem 0.25rem;
}

.sidebar.collapsed .sidebar-user-card {
  flex-direction: column;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.5rem !important;
}
</style>
