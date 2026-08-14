<template>
  <div class="skeleton-loader" :class="`sk-${variant}`" role="status" aria-busy="true">
    <!-- Table -->
    <div v-if="variant === 'table'" class="sk-table-wrap">
      <div v-for="r in rows" :key="r" class="sk-row">
        <div
          v-for="c in cols"
          :key="c"
          class="sk-shimmer"
          :style="{ width: cellWidth(r, c) }"
        ></div>
      </div>
    </div>

    <!-- Card grid -->
    <div v-else-if="variant === 'cards'" class="row g-4">
      <div v-for="r in rows" :key="r" class="col-md-6 col-lg-4 col-xl-3">
        <div class="sk-card h-100 p-4 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-4">
            <div class="sk-icon-box sk-shimmer"></div>
            <div class="sk-shimmer" style="width: 76px; height: 18px; border-radius: 999px"></div>
          </div>
          <div class="sk-shimmer mb-2" style="width: 70%; height: 18px"></div>
          <div class="sk-shimmer" style="width: 45%; height: 14px"></div>
          <div class="mt-auto pt-4 border-top border-light">
            <div class="d-flex justify-content-between align-items-center">
              <div class="sk-shimmer" style="width: 60px; height: 14px"></div>
              <div class="sk-shimmer" style="width: 92px; height: 14px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- List rows -->
    <div v-else-if="variant === 'list'" class="d-flex flex-column gap-3">
      <div v-for="r in rows" :key="r" class="sk-list-item">
        <div class="sk-shimmer" style="width: 90%; height: 16px"></div>
        <div class="sk-shimmer" style="width: 55%; height: 13px"></div>
      </div>
    </div>

    <!-- Question form -->
    <div v-else class="d-flex flex-column gap-4">
      <div v-for="r in rows" :key="r" class="sk-form-card">
        <div class="sk-shimmer" style="width: 55%; height: 16px; margin-bottom: 12px"></div>
        <div class="sk-shimmer" style="width: 85%; height: 14px"></div>
        <div class="sk-likert d-flex gap-3 mt-3">
          <div v-for="n in 5" :key="n" class="sk-circle sk-shimmer"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps } from "vue";

const props = defineProps({
  variant: { type: String, default: "table" },
  rows: { type: Number, default: 5 },
  cols: { type: Number, default: 5 },
});

const widths = [52, 68, 40, 75, 58, 70, 46, 62];

function rowTotal(r) {
  let sum = 0;
  for (let i = 0; i < props.cols; i++) {
    sum += widths[(r * 3 + i) % widths.length];
  }
  return sum || 1;
}

function cellWidth(r, c) {
  const w = widths[(r * 3 + c) % widths.length];
  return ((w / rowTotal(r)) * 100).toFixed(2) + "%";
}
</script>
