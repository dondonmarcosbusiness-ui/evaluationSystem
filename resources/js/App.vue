<template>
  <router-view></router-view>
  <SessionTimeoutManager />
</template>

<script setup>
import SessionTimeoutManager from "./components/SessionTimeoutManager.vue";
</script>
