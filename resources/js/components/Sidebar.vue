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
          style="height: 32px; width: auto"
        />
        <div class="lh-1">
          <div class="text-white fw-bold small">NEUST</div>
          <div class="text-white fw-bold" style="font-size: 0.65rem; opacity: 0.8">CARRANGLAN</div>
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

      <!-- Course List (Direct Link) -->
      <li v-if="$can('manage_courses')">
        <router-link to="/courses" class="nav-link">
          <i class="fas fa-book"></i>
          <span>Course List</span>
          <span class="nav-tooltip">Course List</span>
        </router-link>
      </li>

      <!-- Accounts Management -->
      <li v-if="canSeeAccountsSection" class="sidebar-nav-section">
        <span class="sidebar-section-label">Accounts Management</span>
        <hr class="sidebar-section-divider" />
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

      <!-- Staff Management -->
      <li v-if="$can('manage_faculty') || $can('manage_users')" class="nav-item-dropdown" :class="{ open: isStaffOpen }">
        <div class="nav-link" @click="toggleStaff">
          <i class="fas fa-user-tie"></i>
          <span>Staff Management</span>
          <i class="fas fa-chevron-right ms-auto arrow"></i>
          <span class="nav-tooltip">Staff Management</span>
        </div>
        <ul class="sidebar-submenu">
          <li>
            <router-link to="/staff">
              <i class="fas fa-users"></i>
              <span>Staff Accounts</span>
            </router-link>
          </li>
          <li v-if="$can('manage_categories') || $can('manage_questions')">
            <router-link to="/questionnaire/staff">
              <i class="fas fa-list-alt"></i>
              <span>Staff Questionnaires</span>
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
          <span class="sidebar-section-label">Reports Management</span>
          <hr class="sidebar-section-divider" />
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

        <!-- Staff Reports -->
        <li v-if="canSeeStaffReports" class="nav-item-dropdown" :class="{ open: isStaffReportsOpen }">
          <div class="nav-link" @click="toggleStaffReports">
            <i class="fas fa-user-tie"></i>
            <span>Staff Reports</span>
            <i class="fas fa-chevron-right ms-auto arrow"></i>
            <span class="nav-tooltip">Staff Reports</span>
          </div>
          <ul class="sidebar-submenu">
            <li>
              <router-link :to="reportLink('/reports', 'staff')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/reports', 'staff') }">
                <i class="fas fa-chart-bar"></i>
                <span>{{ user.role === 'staff' ? 'My Ratings Overview' : 'Ratings Overview' }}</span>
              </router-link>
            </li>
            <li>
              <router-link :to="reportLink('/set-report', 'staff')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/set-report', 'staff') }">
                <i class="fas fa-file-invoice"></i>
                <span>{{ user.role === 'staff' ? 'My SET Report' : 'Detailed SET Report' }}</span>
              </router-link>
            </li>
            <li v-if="$can('view_reports')">
              <router-link :to="reportLink('/feedbacks', 'staff')" active-class="" exact-active-class="" :class="{ 'router-link-active': isReportNavActive('/feedbacks', 'staff') }">
                <i class="fas fa-comments"></i>
                <span>Feedback Management</span>
              </router-link>
            </li>
          </ul>
        </li>
      </template>

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
      <button class="btn-logout-sidebar d-none d-md-flex" @click="logout">
        <i class="fas fa-sign-out-alt"></i>
        <span v-show="!isCollapsed" class="ms-2">Logout</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch, getCurrentInstance } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../services/api.js";
import Swal from "sweetalert2";
import { syncThemeForUser } from "../helpers/theme.js";

const router = useRouter();
const route = useRoute();
const instance = getCurrentInstance();
const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const isCollapsed = ref(localStorage.getItem("sidebarCollapsed") === "true");
const basePath = window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public" : "";

const isFacultyOpen = ref(false);
const isStaffOpen = ref(false);
const isStudentsOpen = ref(false);
const isFacultyReportsOpen = ref(false);
const isStaffReportsOpen = ref(false);

const reportPaths = ["/reports", "/set-report", "/feedbacks"];

const canSeeAccountsSection = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("manage_faculty") || can?.("manage_users");
});

const canSeeReportsSection = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("view_reports") || user.value.role === "faculty" || user.value.role === "staff";
});

const canSeeFacultyReports = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("view_reports") || user.value.role === "faculty";
});

const canSeeStaffReports = computed(() => {
  const can = instance?.appContext.config.globalProperties.$can;
  return can?.("view_reports") || user.value.role === "staff";
});

function reportLink(path, type) {
  return { path, query: { type } };
}

function isReportNavActive(path, type) {
  if (route.path !== path) return false;
  const queryType = route.query.type === "staff" ? "staff" : "faculty";
  return queryType === type;
}

onMounted(() => {
  updateLayout();
  checkActiveDropdowns();
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
  const staffMgmtRoutes = ["/staff", "/questionnaire/staff"];
  const studentsRoutes = ["/students/regular", "/students/irregular"];

  if (facultyRoutes.some((path) => route.path.startsWith(path))) {
    isFacultyOpen.value = true;
  }
  if (staffMgmtRoutes.some((path) => route.path.startsWith(path))) {
    isStaffOpen.value = true;
  }
  if (studentsRoutes.some((path) => route.path.startsWith(path))) {
    isStudentsOpen.value = true;
  }
  if (reportPaths.includes(route.path)) {
    const queryType = route.query.type === "staff" ? "staff" : "faculty";
    if (queryType === "staff") {
      isStaffReportsOpen.value = true;
    } else {
      isFacultyReportsOpen.value = true;
    }
  }
}

function closeAllDropdowns() {
  isFacultyOpen.value = false;
  isStaffOpen.value = false;
  isStudentsOpen.value = false;
  isFacultyReportsOpen.value = false;
  isStaffReportsOpen.value = false;
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

function toggleStaff() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isStaffOpen.value = true;
  } else {
    const next = !isStaffOpen.value;
    closeAllDropdowns();
    isStaffOpen.value = next;
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

function toggleStaffReports() {
  if (isCollapsed.value) {
    toggleSidebar();
    closeAllDropdowns();
    isStaffReportsOpen.value = true;
  } else {
    const next = !isStaffReportsOpen.value;
    closeAllDropdowns();
    isStaffReportsOpen.value = next;
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
    confirmButtonColor: "#4f46e5",
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
