<template>
  <nav
    v-if="showPerPage || (pagination && pagination.last_page > 1)"
    class="pagination-container d-flex align-items-center justify-content-between gap-3 flex-wrap"
  >
    <!-- Rows per page -->
    <label v-if="showPerPage" class="per-page-wrap">
      <span class="per-page-label">Rows:</span>
      <select class="per-page-select" :value="perPage" @change="changePerPage($event.target.value)">
        <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
      </select>
    </label>
    <span v-else></span>

    <div v-if="pagination && pagination.last_page > 1" class="d-flex align-items-center gap-2">
      <!-- Previous -->
      <button
        class="pagination-btn"
        :class="{ disabled: pagination.current_page === 1 }"
        :disabled="pagination.current_page === 1"
        @click.prevent="changePage(pagination.current_page - 1)"
      >
        Previous
      </button>

      <!-- Page indicator -->
      <div class="pagination-info">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
      </div>

      <!-- Next -->
      <button
        class="pagination-btn"
        :class="{ disabled: pagination.current_page === pagination.last_page }"
        :disabled="pagination.current_page === pagination.last_page"
        @click.prevent="changePage(pagination.current_page + 1)"
      >
        Next
      </button>
    </div>
  </nav>
</template>

<script setup>
const props = defineProps({
  pagination: {
    type: Object,
    required: true,
  },
  perPage: {
    type: Number,
    default: 10,
  },
  perPageOptions: {
    type: Array,
    default: () => [10, 20, 50, 100],
  },
  showPerPage: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["change-page", "update:per-page"]);

const changePage = (page) => {
  if (page >= 1 && page <= props.pagination.last_page && page !== props.pagination.current_page) {
    emit("change-page", page);
  }
};

const changePerPage = (value) => {
  const n = parseInt(value, 10);
  if (Number.isFinite(n) && n !== props.perPage) {
    emit("update:per-page", n);
  }
};
</script>

<style scoped>
.pagination-container {
  user-select: none;
  padding: 1.25rem 1.75rem;
  border-top: 1px solid var(--border-light);
  background: transparent;
  width: 100%;
}

.pagination-btn {
  flex-shrink: 0;
  min-height: 32px;
  min-width: 32px;
  background: transparent;
  border: none;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--primary);
  padding: 6px 12px;
  border-radius: var(--radius-md);
  transition: all 0.2s ease-in-out;
  cursor: pointer;
  outline: none;
}

.pagination-btn:hover:not(:disabled):not(.disabled) {
  color: var(--primary-dark);
  background: rgba(25, 25, 112, 0.05);
}

.pagination-btn:disabled,
.pagination-btn.disabled {
  color: var(--text-muted);
  opacity: 0.4;
  cursor: not-allowed;
  background: transparent;
}

.pagination-info {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-main);
  letter-spacing: 0.01em;
}

/* Rows-per-page styles live globally in app.css (3e) so custom pagers match. */

[data-theme="dark"] .pagination-btn {
  color: #79c0ff;
}

[data-theme="dark"] .pagination-btn:hover:not(:disabled):not(.disabled) {
  color: #ffffff;
  background: rgba(31, 111, 235, 0.22);
}

[data-theme="dark"] .pagination-btn:disabled,
[data-theme="dark"] .pagination-btn.disabled {
  color: var(--text-muted);
  opacity: 0.35;
}

[data-theme="dark"] .pagination-info {
  color: var(--text-muted);
}
</style>

