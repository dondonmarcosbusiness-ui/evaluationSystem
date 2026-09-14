<template>
  <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold text-primary">Manage User Permissions</h5>
            <p class="text-muted small mb-0">{{ user?.name }} ({{ user?.email }})</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div v-if="loading" class="py-3">
            <SkeletonLoader variant="list" :rows="5" />
          </div>

          <div v-else>
            <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4">
              <div class="d-flex gap-3 align-items-center">
                <i class="fas fa-info-circle fa-lg"></i>
                <div>
                  <h6 class="fw-bold mb-1">Direct Permission Assignment</h6>
                  <p class="small mb-0 opacity-75">
                    Assign specific access rights directly to this user. These choices determine what they can see and
                    do in the system.
                  </p>
                </div>
              </div>
            </div>

            <div class="row g-2">
              <div v-for="perm in allPermissions" :key="perm.id" class="col-md-6">
                <div
                  class="p-3 border rounded-3 transition-all h-100 d-flex align-items-start gap-2"
                  :class="[
                    inheritedPermissions.includes(perm.name)
                      ? 'border-success bg-success bg-opacity-10'
                      : selectedPermissions.includes(perm.name)
                        ? 'border-primary bg-primary bg-opacity-10'
                        : 'bg-white',
                  ]"
                  :style="inheritedPermissions.includes(perm.name) ? 'cursor: not-allowed;' : 'cursor: pointer;'"
                  @click="inheritedPermissions.includes(perm.name) ? null : togglePermission(perm.name)"
                >
                  <input
                    class="form-check-input flex-shrink-0 m-0"
                    style="margin-top: 0.2rem !important"
                    type="checkbox"
                    :id="'up-' + perm.id"
                    :value="perm.name"
                    :checked="selectedPermissions.includes(perm.name) || inheritedPermissions.includes(perm.name)"
                    :disabled="inheritedPermissions.includes(perm.name)"
                    @change="inheritedPermissions.includes(perm.name) ? null : togglePermission(perm.name)"
                    @click.stop
                  />
                  <div>
                    <label
                      class="form-check-label small fw-semibold d-block mb-0"
                      :for="'up-' + perm.id"
                      :style="inheritedPermissions.includes(perm.name) ? 'cursor: not-allowed;' : 'cursor: pointer;'"
                    >
                      {{ formatPerm(perm.name) }}
                    </label>
                    <span
                      v-if="inheritedPermissions.includes(perm.name)"
                      class="badge bg-success mt-1 d-inline-block"
                      style="font-size: 0.65rem"
                    >
                      Inherited via Role
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal" :disabled="saving">
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary px-4 rounded-pill d-flex align-items-center gap-2"
            @click="save"
            :disabled="saving || loading"
          >
            <span v-if="saving">
              <i class="fas fa-spinner fa-spin"></i>
              Saving...
            </span>
            <span v-else>
              <i class="fas fa-check"></i>
              Save Changes
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Modal } from "bootstrap";
import SkeletonLoader from "./SkeletonLoader.vue";
import api from "../services/api";
import Swal from "sweetalert2";

const props = defineProps({
  show: Boolean,
  user: Object,
});

const emit = defineEmits(["close", "updated"]);

const modalEl = ref(null);
let modal = null;

const loading = ref(false);
const saving = ref(false);
const allPermissions = ref([]);
const selectedPermissions = ref([]);
const inheritedPermissions = ref([]);

onMounted(() => {
  modal = new Modal(modalEl.value, { focus: false });
  if (props.show) modal.show();
  modalEl.value.addEventListener("hidden.bs.modal", () => emit("close"));
});

watch(
  () => props.show,
  (newVal) => {
    if (newVal && props.user) {
      fetchUserData();
    }
    nextTick(() => (newVal ? modal?.show() : modal?.hide()));
  },
);

onBeforeUnmount(() => {
  modal?.dispose();
  modal = null;
});

async function fetchUserData() {
  loading.value = true;
  try {
    const [permsRes, userDetailsRes] = await Promise.all([
      api.get("/permissions"),
      api.get(`/users/${props.user?.id || 0}/rbac-details`),
    ]);

    allPermissions.value = permsRes.data.filter((p) => p.guard_name === "web");
    selectedPermissions.value = userDetailsRes.data.direct_permissions;
    inheritedPermissions.value = userDetailsRes.data.permissions.filter(
      (p) => !userDetailsRes.data.direct_permissions.includes(p),
    );
  } catch (error) {
    toast("Failed to load access details", "error");
  } finally {
    loading.value = false;
  }
}

function togglePermission(permName) {
  const index = selectedPermissions.value.indexOf(permName);
  if (index > -1) {
    selectedPermissions.value.splice(index, 1);
  } else {
    selectedPermissions.value.push(permName);
  }
}

async function save() {
  saving.value = true;
  try {
    await api.post(`/users/${props.user?.id || 0}/permissions`, {
      permissions: selectedPermissions.value,
    });

    toast("Permissions updated successfully");
    emit("updated");
    emit("close");
  } catch (error) {
    toast("Failed to update access", "error");
  } finally {
    saving.value = false;
  }
}

function formatPerm(name) {
  return name.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}

function toast(message, icon = "success") {
  Swal.fire({
    toast: true,
    position: "top-end",
    icon,
    title: message,
    showConfirmButton: false,
    timer: 3000,
  });
}
</script>

<style scoped>
.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
