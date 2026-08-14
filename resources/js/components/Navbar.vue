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
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import LanguageSwitcher from "./LanguageSwitcher.vue";

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});

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
</style>
