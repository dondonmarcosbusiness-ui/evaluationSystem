import "./bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "../css/app.css";

import { createApp } from "vue";
import App from "./App.vue";
import router from "./router/index.js";
import permissions from "./helpers/permissions.js";

const app = createApp(App);
app.use(router);
app.use(permissions);
app.mount("#app");
