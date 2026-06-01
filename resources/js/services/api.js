import axios from "axios";

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
// Add a response interceptor to handle 401 Unauthorized globally
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      // Token expired or invalid
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      const basePath = window.location.pathname.startsWith("/evaluation_system/public")
        ? "/evaluation_system/public"
        : "";
      window.location.href = `${basePath}/login`;
    }
    return Promise.reject(error);
  },
);

export default api;
