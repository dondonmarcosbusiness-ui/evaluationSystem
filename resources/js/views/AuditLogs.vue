<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar>
        <template #title>Audit Log</template>
      </Navbar>

      <div class="content-area">
        <div class="card">
          <!-- Topbar already carries the page title — this header only holds context + filters -->
          <div class="card-header border-0 py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div class="d-flex align-items-center py-1 gap-3 flex-wrap">
                <span
                  class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-700"
                  style="font-size: 0.75rem"
                >
                  {{ pagination.total || 0 }} Records
                </span>
                <p class="mb-0 small text-muted">
                  Security-relevant events: permission changes, privilege assignments, and denied access.
                </p>
              </div>

              <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="premium-filter-group" style="width: 240px">
                  <span class="input-group-text"><i class="fas fa-filter"></i></span>
                  <CustomSelect
                    v-model="filters.action"
                    :options="actionOptions"
                    placeholder="All actions"
                    searchable
                    @change="applyFilters"
                  />
                </div>

                <div class="premium-filter-group" style="width: 170px">
                  <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                  <input v-model="filters.from" type="date" class="form-control" aria-label="From date" />
                </div>

                <div class="premium-filter-group" style="width: 170px">
                  <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                  <input v-model="filters.to" type="date" class="form-control" aria-label="To date" />
                </div>

                <button
                  class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-3"
                  :disabled="loading"
                  title="Apply filters"
                  @click="applyFilters"
                >
                  <i class="fas fa-search"></i>
                  <span class="d-none d-xl-inline">Apply</span>
                </button>
                <button
                  class="btn btn-light btn-sm d-flex align-items-center gap-2 px-3"
                  :disabled="loading"
                  title="Clear filters"
                  @click="clearFilters"
                >
                  <i class="fas fa-times"></i>
                  <span class="d-none d-xl-inline">Clear</span>
                </button>
              </div>
            </div>
          </div>

          <div class="card-body p-0">
            <div v-if="loading" class="p-4"><SkeletonLoader /></div>

            <div v-else-if="logs.length === 0" class="audit-empty">
              <i class="fas fa-clipboard-list"></i>
              <p class="mb-0">No audit entries match the current filters.</p>
            </div>

            <div v-else class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th class="audit-th">Timestamp</th>
                    <th class="audit-th">User</th>
                    <th class="audit-th">Action</th>
                    <th class="audit-th">Target</th>
                    <th class="audit-th">IP</th>
                    <th class="audit-th text-center">Details</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="log in logs" :key="log.id">
                    <tr :class="{ 'audit-row-open': expanded === log.id }">
                      <td>
                        <div class="audit-time">{{ formatDate(log.created_at) }}</div>
                        <div class="audit-sub">{{ formatClock(log.created_at) }}</div>
                      </td>
                      <td>
                        <div class="audit-user">{{ log.user?.name || "—" }}</div>
                        <div class="audit-sub">{{ log.user?.email || "" }}</div>
                      </td>
                      <td>
                        <span class="audit-tone" :class="actionTone(log.action)">{{ log.action }}</span>
                      </td>
                      <td>
                        <template v-if="log.auditable_type">
                          <span class="audit-target-type">{{ shortType(log.auditable_type) }}</span>
                          <code v-if="log.auditable_id" class="audit-target-id" :title="log.auditable_id">
                            {{ shortId(log.auditable_id) }}
                          </code>
                        </template>
                        <span v-else class="audit-sub">—</span>
                      </td>
                      <td class="audit-ip">{{ log.ip_address || "—" }}</td>
                      <td class="text-center">
                        <button
                          class="audit-toggle"
                          :title="expanded === log.id ? 'Hide details' : 'Show details'"
                          @click="expanded = expanded === log.id ? null : log.id"
                        >
                          <i class="fas" :class="expanded === log.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expanded === log.id" class="audit-detail-row">
                      <td colspan="6">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <div class="audit-detail-label">Previous values</div>
                            <pre class="audit-json mb-0">{{ pretty(log.old_values) }}</pre>
                          </div>
                          <div class="col-md-6">
                            <div class="audit-detail-label">New values</div>
                            <pre class="audit-json mb-0">{{ pretty(log.new_values) }}</pre>
                          </div>
                          <div class="col-12" v-if="log.user_agent">
                            <div class="audit-detail-label">User agent</div>
                            <div class="audit-sub">{{ log.user_agent }}</div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <Pagination
            v-if="!loading && logs.length"
            :pagination="pagination"
            :per-page="perPage"
            :per-page-options="[25, 50, 100]"
            @change-page="fetchLogs"
            @update:per-page="changePerPage"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import Pagination from "../components/Pagination.vue";
import CustomSelect from "../components/CustomSelect.vue";
import api from "../services/api";
import { format } from "date-fns";

const logs = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
const loading = ref(true);
const expanded = ref(null);
const perPage = ref(25);

const filters = ref({
  action: "",
  from: "",
  to: "",
});

const actionList = ref([]);
const actionOptions = computed(() => [
  { label: "All actions", value: "" },
  ...actionList.value.map((action) => ({ label: action, value: action })),
]);

async function fetchActions() {
  try {
    const res = await api.get("/audit-logs/actions");
    actionList.value = Array.isArray(res.data) ? res.data : [];
  } catch (err) {
    console.error("Failed to load audit actions:", err);
  }
}

function parseDate(value) {
  return new Date(String(value).replace(" ", "T"));
}

function formatDate(value) {
  if (!value) return "—";
  try {
    return format(parseDate(value), "MMM dd, yyyy");
  } catch {
    return value;
  }
}

function formatClock(value) {
  if (!value) return "";
  try {
    return format(parseDate(value), "hh:mm:ss a");
  } catch {
    return "";
  }
}

function shortType(type) {
  if (!type) return "";
  return type.split("\\").pop();
}

function shortId(id) {
  return String(id).slice(0, 8) + "…";
}

function pretty(value) {
  if (!value || (typeof value === "object" && Object.keys(value).length === 0)) {
    return "—";
  }
  return JSON.stringify(value, null, 2);
}

function actionTone(action = "") {
  if (action.includes("denied") || action.includes("blocked")) return "audit-tone-danger";
  if (action.includes("deleted") || action.includes("removed")) return "audit-tone-danger";
  if (action.includes("created") || action.includes("synced") || action.includes("assigned"))
    return "audit-tone-success";
  if (action.includes("updated") || action.includes("changed")) return "audit-tone-warning";
  if (action.includes("submitted")) return "audit-tone-info";
  return "audit-tone-neutral";
}

async function fetchLogs(page = 1) {
  loading.value = true;
  expanded.value = null;
  try {
    const params = {
      page,
      per_page: perPage.value,
    };
    if (filters.value.action) params.action = filters.value.action;
    if (filters.value.from) params.from = filters.value.from;
    if (filters.value.to) params.to = filters.value.to;

    const res = await api.get("/audit-logs", { params });
    logs.value = res.data.data || [];
    pagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      from: res.data.from || 0,
      to: res.data.to || 0,
      total: res.data.total || 0,
      per_page: res.data.per_page || perPage.value,
    };
  } catch (err) {
    console.error("Failed to load audit logs:", err);
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchLogs(1);
}

function clearFilters() {
  filters.value = { action: "", from: "", to: "" };
  fetchLogs(1);
}

function changePerPage(value) {
  perPage.value = value;
  fetchLogs(1);
}

onMounted(() => {
  fetchActions();
  fetchLogs(1);
});
</script>

<style scoped>
.audit-th {
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  background: transparent;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-light);
  white-space: nowrap;
}

.table td {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid var(--border-light);
}

.audit-time {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-main);
  white-space: nowrap;
}

.audit-user {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-main);
}

.audit-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.audit-tone {
  display: inline-block;
  padding: 0.3rem 0.6rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.audit-tone-danger {
  background: var(--badge-danger-bg);
  color: var(--badge-danger-text);
}

.audit-tone-success {
  background: var(--badge-success-bg);
  color: var(--badge-success-text);
}

.audit-tone-info {
  background: var(--badge-info-bg);
  color: var(--badge-info-text);
}

.audit-tone-warning {
  background: var(--badge-warning-bg);
  color: var(--badge-warning-text);
}

.audit-tone-neutral {
  background: var(--bg-light);
  color: var(--text-muted);
}

.audit-target-type {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-right: 0.4rem;
}

.audit-target-id {
  font-size: 0.75rem;
  background: var(--bg-light);
  color: var(--text-main);
  padding: 0.15rem 0.45rem;
  border-radius: 6px;
}

.audit-ip {
  font-size: 0.78rem;
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.audit-toggle {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.75rem;
  transition: all 0.2s ease;
}

.audit-toggle:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #ffffff;
}

.audit-row-open {
  background: rgba(25, 25, 112, 0.04);
}

.audit-detail-row td {
  background: var(--bg-light);
  padding: 1rem;
  border-bottom: 1px solid var(--border-light);
}

.audit-detail-label {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 0.4rem;
}

.audit-json {
  max-height: 240px;
  overflow: auto;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.6rem 0.75rem;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.75rem;
  color: var(--text-main);
}

.audit-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: var(--text-muted);
  text-align: center;
}

.audit-empty i {
  font-size: 2rem;
  opacity: 0.35;
}

[data-theme="dark"] .audit-row-open {
  background: rgba(88, 166, 255, 0.06);
}

/* Deep override for CustomSelect when used in the filter group */
.premium-filter-group :deep(.custom-select-trigger) {
  border: none !important;
  background: transparent !important;
  padding: 0 12px 0 0 !important;
  font-size: 14px !important;
  min-height: 0 !important;
  box-shadow: none !important;
}
</style>
