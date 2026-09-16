<template>
  <div class="skeleton-loader" :class="`sk-${variant}`" role="status" aria-busy="true" aria-label="Loading content">
    <!-- Table: header row + avatar/text body rows with dividers -->
    <div v-if="variant === 'table'" class="sk-table-card">
      <div class="sk-table-wrap">
        <div class="sk-row sk-head-row" aria-hidden="true">
          <div v-for="c in cols" :key="`h-${c}`" class="sk-bone" :style="{ width: headWidth(c) }"></div>
        </div>
        <div v-for="r in rows" :key="r" class="sk-row" aria-hidden="true">
          <div class="sk-avatar sk-bone" style="width: 32px; height: 32px"></div>
          <div
            v-for="c in Math.max(cols - 1, 1)"
            :key="c"
            class="sk-bone"
            :style="{ width: cellWidth(r, c) }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Card grid: media block + heading + text + actions -->
    <div v-else-if="variant === 'cards'" class="row g-4" aria-hidden="true">
      <div v-for="r in rows" :key="r" class="col-md-6 col-lg-4 col-xl-3">
        <div class="sk-card h-100 overflow-hidden">
          <div class="sk-bone" style="width: 100%; height: 96px; border-radius: 0"></div>
          <div class="p-4 d-flex flex-column gap-2">
            <div class="d-flex align-items-center gap-3">
              <div class="sk-avatar sk-bone" style="width: 40px; height: 40px"></div>
              <div class="sk-bone" style="width: 55%; height: 16px"></div>
            </div>
            <div class="sk-bone" style="width: 90%; height: 13px"></div>
            <div class="sk-bone" style="width: 65%; height: 13px"></div>
            <div class="d-flex gap-2 mt-2">
              <div class="sk-bone" style="width: 72px; height: 28px; border-radius: 999px"></div>
              <div class="sk-bone" style="width: 72px; height: 28px; border-radius: 8px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- List rows: avatar + two-line text -->
    <div v-else-if="variant === 'list'" class="d-flex flex-column gap-3" aria-hidden="true">
      <div v-for="r in rows" :key="r" class="sk-list-item d-flex align-items-center gap-3">
        <div class="sk-avatar sk-bone" style="width: 40px; height: 40px"></div>
        <div class="d-flex flex-column gap-2 flex-fill">
          <div class="sk-bone" :style="{ width: r % 2 ? '70%' : '85%', height: '15px' }"></div>
          <div class="sk-bone" :style="{ width: r % 2 ? '45%' : '55%', height: '12px' }"></div>
        </div>
      </div>
    </div>

    <!-- Question form: prompt + text + likert options -->
    <div v-else class="d-flex flex-column gap-4" aria-hidden="true">
      <div v-for="r in rows" :key="r" class="sk-form-card">
        <div class="sk-bone" style="width: 55%; height: 16px; margin-bottom: 12px"></div>
        <div class="sk-bone" style="width: 85%; height: 14px"></div>
        <div class="sk-likert d-flex gap-3 mt-3">
          <div v-for="n in 5" :key="n" class="sk-circle sk-bone"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
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

function headWidth(c) {
  const w = widths[(c * 5 + 2) % widths.length];
  return Math.max(6, Math.min(16, (w / 10))).toFixed(1) + "%";
}
</script>
