import axios from "axios";
import router from "../router/index.js";

const getBaseURL = () => {
  const origin = window.location.origin;
  if (window.location.pathname.startsWith("/evaluation_system/public")) {
    return origin + "/evaluation_system/public/api";
  }
  return origin + "/api";
};

const api = axios.create({
  baseURL: getBaseURL(),
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// Add a request interceptor to include the auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);
// Add a response interceptor to handle auth failures globally
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    const status = error.response && error.response.status;
    const currentPath = window.location.pathname;
    const isPublicPage = currentPath.includes("/qr/") || currentPath.endsWith("/qr");

    if (status === 401) {
      // Do not redirect visitors on public QR pages to login
      if (!isPublicPage) {
        // Token expired or invalid
        localStorage.removeItem("token");
        localStorage.removeItem("user");
        localStorage.removeItem("permissions");
        const basePath = currentPath.startsWith("/evaluation_system/public")
          ? "/evaluation_system/public"
          : "";
        window.location.href = `${basePath}/login`;
      }
    } else if (status === 403 && !isPublicPage) {
      // Permission denied: surface the denial, then leave the forbidden page.
      // The server has already logged it in the audit trail; client-side we
      // just avoid stranding the user on a page they can no longer use.
      window.dispatchEvent(
        new CustomEvent("app:forbidden", {
          detail: {
            message: (error.response.data && error.response.data.message) || "Access denied.",
            path: error.config ? error.config.url : "",
          },
        }),
      );
      const target = router.currentRoute.value;
      if (target.path !== "/dashboard") {
        router.push({ path: "/dashboard" }).catch(() => {});
      }
    }
    return Promise.reject(error);
  },
);

export default api;
