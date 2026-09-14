<template>
  <router-view></router-view>
  <SessionTimeoutManager />
  <ConfirmDialog />
</template>

<script setup>
import SessionTimeoutManager from "./components/SessionTimeoutManager.vue";
import ConfirmDialog from "./components/ConfirmDialog.vue";
</script>
