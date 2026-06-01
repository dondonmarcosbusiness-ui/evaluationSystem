<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar><template #title>Role Management</template></Navbar>

      <div class="content-area py-4 px-4">
        <div class="row mb-4 align-items-center">
          <div class="col">
            <h4 class="mb-1 fw-bold text-primary">Role & Permission Management</h4>
            <p class="text-muted small mb-0">Define what each role can view and perform in the system.</p>
          </div>
          <div class="col-auto">
            <button class="btn btn-primary d-flex align-items-center gap-2" @click="openRoleModal()">
              <i class="fas fa-plus"></i>
              New Role
            </button>
          </div>
        </div>

        <div class="row">
          <!-- Roles List -->
          <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold">Available Roles</h6>
              </div>
              <div class="list-group list-group-flush">
                <div
                  v-for="role in roles"
                  :key="role.id"
                  class="list-group-item list-group-item-action border-0 py-3 d-flex align-items-center justify-content-between"
                  :class="{ 'bg-light text-primary fw-bold': selectedRole?.id === role.id }"
                  style="cursor: pointer"
                  @click="selectRole(role)"
                >
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon-box" :class="selectedRole?.id === role.id ? 'bg-primary' : 'bg-light'">
                      <i
                        class="fas fa-user-shield"
                        :class="selectedRole?.id === role.id ? 'text-white' : 'text-primary'"
                      ></i>
                    </div>
                    <span>{{ role.name }}</span>
                  </div>
                  <div class="dropdown" @click.stop>
                    <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                      <li>
                        <a class="dropdown-item" href="#" @click.prevent="openRoleModal(role)">
                          <i class="fas fa-edit me-2 small"></i>
                          Rename
                        </a>
                      </li>
                      <li v-if="role.name !== 'Admin'"><hr class="dropdown-divider" /></li>
                      <li v-if="role.name !== 'Admin'">
                        <a class="dropdown-item text-danger" href="#" @click.prevent="deleteRole(role)">
                          <i class="fas fa-trash me-2 small"></i>
                          Delete
                        </a>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Permissions for selected role -->
          <div class="col-md-8">
            <div v-if="selectedRole" class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold">
                  Permissions for
                  <span class="text-primary">{{ selectedRole.name }}</span>
                </h6>
                <button
                  class="btn btn-sm btn-success px-3 d-flex align-items-center gap-2"
                  @click="savePermissions"
                  :disabled="saving"
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
              <div class="card-body">
                <div class="row g-3">
                  <div v-for="(perms, group) in groupedPermissions" :key="group" class="col-md-6">
                    <div class="p-3 bg-light rounded-3 h-100">
                      <h6 class="fw-bold small text-uppercase text-muted mb-3 border-bottom pb-2">{{ group }}</h6>
                      <div v-for="permission in perms" :key="permission.id" class="form-check mb-2">
                        <input
                          class="form-check-input mt-1"
                          type="checkbox"
                          :id="'perm-' + permission.id"
                          :value="permission.name"
                          v-model="selectedPermissions"
                          :disabled="selectedRole.name === 'Admin'"
                        />
                        <label
                          class="form-check-label ms-2 small"
                          :for="'perm-' + permission.id"
                          style="cursor: pointer"
                        >
                          {{ formatPermissionName(permission.name) }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div
              v-else
              class="h-100 d-flex flex-column align-items-center justify-content-center text-center p-5 bg-white rounded-3 shadow-sm border"
            >
              <div class="icon-box bg-light mb-3" style="width: 80px; height: 80px">
                <i class="fas fa-user-lock text-muted fa-2x"></i>
              </div>
              <h5 class="text-muted">Select a role to manage its permissions</h5>
            </div>
          </div>
        </div>

        <!-- Role Modal -->
        <div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
              <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold">{{ roleForm.id ? "Edit Role" : "Create New Role" }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <form @submit.prevent="saveRole">
                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Role Name</label>
                    <input
                      v-model="roleForm.name"
                      type="text"
                      class="form-control"
                      placeholder="e.g. Coordinator, Proctor"
                      required
                    />
                  </div>
                </div>
                <div class="modal-footer border-0">
                  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary px-4">Save Role</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import api from "../services/api";
import Swal from "sweetalert2";

const roles = ref([]);
const permissions = ref([]);
const selectedRole = ref(null);
const selectedPermissions = ref([]);
const saving = ref(false);
const roleForm = ref({ id: null, name: "" });

onMounted(() => {
  fetchData();
});

async function fetchData() {
  try {
    const [rolesRes, permsRes] = await Promise.all([api.get("/roles"), api.get("/permissions")]);
    roles.value = rolesRes.data;
    permissions.value = permsRes.data;

    if (roles.value.length > 0 && !selectedRole.value) {
      selectRole(roles.value[0]);
    }
  } catch (error) {
    toast("Failed to load data", "error");
  }
}

const groupedPermissions = computed(() => {
  // Group permissions by prefix or simple mapping
  const groups = {
    Management: ["manage_rbac", "manage_users", "manage_faculty", "manage_courses"],
    Evaluations: ["manage_categories", "manage_questions", "give_evaluations", "view_evaluations"],
    General: ["view_dashboard", "view_reports"],
  };

  const result = {};
  permissions.value.forEach((p) => {
    let found = false;
    for (const [group, patterns] of Object.entries(groups)) {
      if (patterns.includes(p.name)) {
        if (!result[group]) result[group] = [];
        result[group].push(p);
        found = true;
        break;
      }
    }
    if (!found) {
      if (!result["Other"]) result["Other"] = [];
      result["Other"].push(p);
    }
  });
  return result;
});

function selectRole(role) {
  selectedRole.value = role;
  selectedPermissions.value = role.permissions.map((p) => p.name);
}

async function saveRole() {
  try {
    if (roleForm.value.id) {
      await api.put(`/roles/${roleForm.value.id}`, roleForm.value);
      toast("Role updated successfully");
    } else {
      await api.post("/roles", roleForm.value);
      toast("Role created successfully");
    }
    fetchData();
    bootstrap.Modal.getInstance(document.getElementById("roleModal")).hide();
  } catch (error) {
    toast(error.response?.data?.message || "Failed to save role", "error");
  }
}

async function deleteRole(role) {
  const result = await Swal.fire({
    title: "Delete Role?",
    text: `Are you sure you want to delete ${role.name}? This cannot be undone.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it",
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/roles/${role.id}`);
      toast("Role deleted");
      selectedRole.value = null;
      fetchData();
    } catch (error) {
      toast("Failed to delete role", "error");
    }
  }
}

async function savePermissions() {
  if (!selectedRole.value) return;

  saving.value = true;
  try {
    await api.post(`/roles/${selectedRole.value.id}/permissions`, {
      permissions: selectedPermissions.value,
    });
    toast("Permissions updated successfully");
    fetchData();
  } catch (error) {
    toast("Failed to update permissions", "error");
  } finally {
    saving.value = false;
  }
}

function openRoleModal(role = null) {
  if (role) {
    roleForm.value = { id: role.id, name: role.name };
  } else {
    roleForm.value = { id: null, name: "" };
  }
  const modal = new bootstrap.Modal(document.getElementById("roleModal"));
  modal.show();
}

function formatPermissionName(name) {
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
.icon-box {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
}
.list-group-item {
  transition: all 0.2s;
}
.list-group-item:hover:not(.active) {
  background-color: #f8f9fa;
}
</style>
