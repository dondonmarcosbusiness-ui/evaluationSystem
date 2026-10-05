import { createRouter, createWebHistory } from "vue-router";
import { syncThemeForUser } from "../helpers/theme.js";

const Login = () => import("../views/Login.vue");
const Dashboard = () => import("../views/Dashboard.vue");
const FacultyManagement = () => import("../views/FacultyManagement.vue");
const StudentManagement = () => import("../views/StudentManagement.vue");
const EvaluationForm = () => import("../views/EvaluationForm.vue");
const Reports = () => import("../views/Reports.vue");
const SetReport = () => import("../views/SetReport.vue");
const QuestionnaireManagement = () => import("../views/QuestionnaireManagement.vue");
const Settings = () => import("../views/Settings.vue");
const CourseManagement = () => import("../views/CourseManagement.vue");
const AssignmentManagement = () => import("../views/AssignmentManagement.vue");
const BackupManagement = () => import("../views/BackupManagement.vue");
const FeedbackManagement = () => import("../views/FeedbackManagement.vue");
const OfficeManagement = () => import("../views/OfficeManagement.vue");
const OfficeQuestionnaireManagement = () => import("../views/OfficeQuestionnaireManagement.vue");
const OfficeEvaluationForm = () => import("../views/OfficeEvaluationForm.vue");
const OfficeReports = () => import("../views/OfficeReports.vue");
const ArchiveReports = () => import("../views/ArchiveReports.vue");
const QrFeedback = () => import("../views/QrFeedback.vue");
const AuditLogs = () => import("../views/AuditLogs.vue");

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
    meta: { requiresAuth: true, permission: "faculty.view" },
  },
  {
    path: "/offices",
    name: "OfficeManagement",
    component: OfficeManagement,
    meta: { requiresAuth: true, permission: "office.view" },
  },
  {
    path: "/evaluate-office/:officeId",
    name: "OfficeEvaluationForm",
    component: OfficeEvaluationForm,
    meta: { requiresAuth: true, permission: "evaluation.create" },
  },
  {
    path: "/qr/:token",
    name: "QrFeedback",
    component: QrFeedback,
  },
  {
    path: "/office-reports",
    name: "OfficeReports",
    component: OfficeReports,
    meta: { requiresAuth: true, permission: "office.report.view" },
  },
  {
    path: "/questionnaire/office",
    name: "OfficeQuestionnaireManagement",
    component: OfficeQuestionnaireManagement,
    meta: { requiresAuth: true, permission: "office.view" },
  },
  {
    path: "/students/regular",
    name: "RegularStudents",
    component: StudentManagement,
    props: { defaultType: "regular" },
    meta: { requiresAuth: true, permission: "student.view" },
  },
  {
    path: "/students/irregular",
    name: "IrregularStudents",
    component: StudentManagement,
    props: { defaultType: "irregular" },
    meta: { requiresAuth: true, permission: "student.view" },
  },
  {
    path: "/students",
    redirect: "/students/regular",
  },
  {
    path: "/courses",
    name: "CourseManagement",
    component: CourseManagement,
    meta: { requiresAuth: true, permission: "course.view" },
  },
  {
    path: "/assignments",
    name: "AssignmentManagement",
    component: AssignmentManagement,
    meta: { requiresAuth: true, permission: "assignment.view" },
  },
  {
    path: "/questionnaire/faculty",
    name: "QuestionnaireManagementFaculty",
    component: QuestionnaireManagement,
    meta: { requiresAuth: true, permission: ["questionnaire.create", "questionnaire.edit", "questionnaire.delete"] },
  },
  {
    path: "/questionnaire",
    redirect: "/questionnaire/faculty",
  },
  {
    path: "/settings",
    name: "Settings",
    component: Settings,
    meta: { requiresAuth: true, permission: "settings.manage" },
  },
  {
    path: "/backups",
    name: "BackupManagement",
    component: BackupManagement,
    meta: { requiresAuth: true, permission: "backup.manage" },
  },
  {
    path: "/evaluate",
    name: "EvaluationForm",
    component: EvaluationForm,
    meta: { requiresAuth: true, permission: "evaluation.create" },
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
    path: "/archive",
    name: "ArchiveReports",
    component: ArchiveReports,
    meta: { requiresAuth: true },
  },
  {
    path: "/feedbacks",
    name: "FeedbackManagement",
    component: FeedbackManagement,
    meta: { requiresAuth: true, permission: "report.view" },
  },
  {
    path: "/audit-logs",
    name: "AuditLogs",
    component: AuditLogs,
    meta: { requiresAuth: true, permission: "permission.manage" },
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
    if (!permission) return true;
    if (Array.isArray(permission)) {
      return permission.some((p) => userPermissions.includes(p));
    }
    return userPermissions.includes(permission);
  };

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

  } else {
    applyTheme();
    next();
  }
});

export default router;
