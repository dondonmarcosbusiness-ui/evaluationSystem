<template>
  <nav
    v-if="pagination && pagination.last_page > 1"
    class="pagination-container d-flex align-items-center justify-content-between"
  >
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
  </nav>
</template>

<script setup>
const props = defineProps({
  pagination: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["change-page"]);

const changePage = (page) => {
  if (page >= 1 && page <= props.pagination.last_page && page !== props.pagination.current_page) {
    emit("change-page", page);
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
  background: transparent;
  border: none;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--primary);
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
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

[data-theme="dark"] .pagination-btn {
  color: var(--primary-dark);
}

[data-theme="dark"] .pagination-btn:hover:not(:disabled):not(.disabled) {
  color: var(--text-white);
  background: rgba(255, 255, 255, 0.04);
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

