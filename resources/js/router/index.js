import { createRouter, createWebHistory } from "vue-router";
import { syncThemeForUser } from "../helpers/theme.js";
import Login from "../views/Login.vue";
import Dashboard from "../views/Dashboard.vue";
import FacultyManagement from "../views/FacultyManagement.vue";
import StudentManagement from "../views/StudentManagement.vue";
import EvaluationForm from "../views/EvaluationForm.vue";
import Reports from "../views/Reports.vue";
import SetReport from "../views/SetReport.vue";
import QuestionnaireManagement from "../views/QuestionnaireManagement.vue";
import Settings from "../views/Settings.vue";
import CourseManagement from "../views/CourseManagement.vue";
import AssignmentManagement from "../views/AssignmentManagement.vue";
import BackupManagement from "../views/BackupManagement.vue";
import FeedbackManagement from "../views/FeedbackManagement.vue";
import StaffManagement from "../views/StaffManagement.vue";

const routes = [
  { path: "/", redirect: "/login" },
  { path: "/login", name: "Login", component: Login },
  {
    path: "/dashboard",
    name: "Dashboard",
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: "/faculty",
    name: "FacultyManagement",
    component: FacultyManagement,
    meta: { requiresAuth: true, permission: "manage_faculty" },
  },
  {
    path: "/staff",
    name: "StaffManagement",
    component: StaffManagement,
    meta: { requiresAuth: true, permission: ["manage_faculty", "manage_users"] },
  },
  {
    path: "/students/regular",
    name: "RegularStudents",
    component: StudentManagement,
    props: { defaultType: "regular" },
    meta: { requiresAuth: true, permission: "manage_users" },
  },
  {
    path: "/students/irregular",
    name: "IrregularStudents",
    component: StudentManagement,
    props: { defaultType: "irregular" },
    meta: { requiresAuth: true, permission: "manage_users" },
  },
  {
    path: "/students",
    redirect: "/students/regular",
  },
  {
    path: "/courses",
    name: "CourseManagement",
    component: CourseManagement,
    meta: { requiresAuth: true, permission: "manage_courses" },
  },
  {
    path: "/assignments",
    name: "AssignmentManagement",
    component: AssignmentManagement,
    meta: { requiresAuth: true, permission: "manage_faculty" },
  },
  {
    path: "/questionnaire/faculty",
    name: "QuestionnaireManagementFaculty",
    component: QuestionnaireManagement,
    meta: { requiresAuth: true, permission: ["manage_categories", "manage_questions"] },
  },
  {
    path: "/questionnaire/staff",
    name: "QuestionnaireManagementStaff",
    component: QuestionnaireManagement,
    meta: { requiresAuth: true, permission: ["manage_categories", "manage_questions"] },
  },
  {
    path: "/questionnaire",
    redirect: "/questionnaire/faculty",
  },
  {
    path: "/settings",
    name: "Settings",
    component: Settings,
    meta: { requiresAuth: true, permission: "manage_rbac" },
  },
  {
    path: "/backups",
    name: "BackupManagement",
    component: BackupManagement,
    meta: { requiresAuth: true, permission: "manage_rbac" },
  },
  {
    path: "/evaluate",
    name: "EvaluationForm",
    component: EvaluationForm,
    meta: { requiresAuth: true, permission: "give_evaluations" },
  },
  {
    path: "/reports",
    name: "Reports",
    component: Reports,
    meta: { requiresAuth: true },
  },
  {
    path: "/set-report",
    name: "SetReport",
    component: SetReport,
    meta: { requiresAuth: true },
  },
  {
    path: "/feedbacks",
    name: "FeedbackManagement",
    component: FeedbackManagement,
    meta: { requiresAuth: true, permission: "view_reports" },
  },
];

const getBasePath = () => {
  return window.location.pathname.startsWith("/evaluation_system/public") ? "/evaluation_system/public/" : "/";
};

const router = createRouter({
  history: createWebHistory(getBasePath()),
  routes,
});

router.beforeEach((to, from, next) => {
  const isAuthenticated = !!localStorage.getItem("token");
  const user = JSON.parse(localStorage.getItem("user") || "{}");
  const userPermissions = JSON.parse(localStorage.getItem("permissions") || "[]");

  const can = (permission) => {
    if (user.role === "admin") return true;
    if (!permission) return true;
    if (Array.isArray(permission)) {
      return permission.some((p) => userPermissions.includes(p));
    }
    return userPermissions.includes(permission);
  };

  const reportPaths = ["/reports", "/set-report", "/feedbacks"];
  const isReportRoute = reportPaths.includes(to.path);
  const queryType = to.query.type === "staff" ? "staff" : "faculty";

  const applyTheme = () => {
    if (to.path === "/login" || !isAuthenticated) {
      syncThemeForUser(null);
    } else {
      syncThemeForUser(user);
    }
  };

  if (to.meta.requiresAuth && !isAuthenticated) {
    applyTheme();
    next("/login");
  } else if (to.meta.role && user.role !== to.meta.role) {
    applyTheme();
    next("/dashboard");
  } else if (to.meta.permission && !can(to.meta.permission)) {
    applyTheme();
    next("/dashboard");
  } else if (isReportRoute && user.role === "faculty" && queryType === "staff") {
    applyTheme();
    next({ path: to.path, query: { ...to.query, type: "faculty" } });
  } else if (isReportRoute && user.role === "staff" && queryType === "faculty") {
    applyTheme();
    next({ path: to.path, query: { ...to.query, type: "staff" } });
  } else {
    applyTheme();
    next();
  }
});

export default router;
