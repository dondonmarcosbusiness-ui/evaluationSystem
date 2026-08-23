<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar>
        <template #title>Office Questionnaire</template>
      </Navbar>

      <div class="content-area">
        <OfficeQuestionnaireManager />
      </div>
    </div>
  </div>
</template>

<script setup>
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import OfficeQuestionnaireManager from "../components/OfficeQuestionnaireManager.vue";
</script>