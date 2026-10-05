<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Catalog (single source of truth)
    |--------------------------------------------------------------------------
    | Capability-based permissions. UI visibility is DERIVED from these
    | (e.g. evaluation.delete = false  =>  Delete button hidden AND the
    | backend rejects DELETE requests). Never add UI-specific permissions
    | such as "hide_delete_button".
    |
    | To add a new permission later: add an entry here and run
    | `php artisan permissions:sync`. No architectural changes required.
    |
    | Entry keys:
    |   module     - grouping used by the admin UI / audit log
    |   action     - the capability verb
    |   label      - human label for the admin permission matrix
    |   scope_for  - marks a data-scope permission (own/team/all) for a base
    |                permission; scope resolves as all > team > own and a
    |                bare base permission defaults to "own" (default deny)
    |   reserved   - catalog entry with no endpoint/button yet (inert)
    */

    'permissions' => [

        'dashboard.view' => [
            'module' => 'dashboard',
            'action' => 'view',
            'label' => 'View dashboard',
        ],

        // --- Evaluations ---------------------------------------------------
        'evaluation.view' => [
            'module' => 'evaluation',
            'action' => 'view',
            'label' => 'View evaluations',
            'scope_for' => 'evaluation.view',
        ],
        'evaluation.view.own' => [
            'module' => 'evaluation',
            'action' => 'view',
            'label' => 'Own evaluations only',
            'scope_for' => 'evaluation.view',
            'scope' => 'own',
        ],
        'evaluation.view.team' => [
            'module' => 'evaluation',
            'action' => 'view',
            'label' => 'Own department evaluations',
            'scope_for' => 'evaluation.view',
            'scope' => 'team',
        ],
        'evaluation.view.all' => [
            'module' => 'evaluation',
            'action' => 'view',
            'label' => 'All evaluations',
            'scope_for' => 'evaluation.view',
            'scope' => 'all',
        ],
        'evaluation.create' => [
            'module' => 'evaluation',
            'action' => 'create',
            'label' => 'Start evaluation',
        ],
        'evaluation.submit' => [
            'module' => 'evaluation',
            'action' => 'submit',
            'label' => 'Submit evaluation',
        ],
        'evaluation.export' => [
            'module' => 'evaluation',
            'action' => 'export',
            'label' => 'Export evaluations',
        ],
        'evaluation.edit' => [
            'module' => 'evaluation',
            'action' => 'edit',
            'label' => 'Edit evaluation',
            'reserved' => true,
        ],
        'evaluation.delete' => [
            'module' => 'evaluation',
            'action' => 'delete',
            'label' => 'Delete evaluation',
            'reserved' => true,
        ],

        // --- Faculty / Employee -------------------------------------------
        'faculty.view' => ['module' => 'faculty', 'action' => 'view', 'label' => 'View'],
        'faculty.create' => ['module' => 'faculty', 'action' => 'create', 'label' => 'Create'],
        'faculty.edit' => ['module' => 'faculty', 'action' => 'edit', 'label' => 'Edit'],
        'faculty.delete' => ['module' => 'faculty', 'action' => 'delete', 'label' => 'Delete'],
        'faculty.import' => ['module' => 'faculty', 'action' => 'import', 'label' => 'Import'],

        'assignment.view' => ['module' => 'assignment', 'action' => 'view', 'label' => 'View'],
        'assignment.create' => ['module' => 'assignment', 'action' => 'create', 'label' => 'Create'],
        'assignment.delete' => ['module' => 'assignment', 'action' => 'delete', 'label' => 'Delete'],

        // --- Students ------------------------------------------------------
        'student.view' => ['module' => 'student', 'action' => 'view', 'label' => 'View'],
        'student.create' => ['module' => 'student', 'action' => 'create', 'label' => 'Create'],
        'student.edit' => ['module' => 'student', 'action' => 'edit', 'label' => 'Edit'],
        'student.delete' => ['module' => 'student', 'action' => 'delete', 'label' => 'Delete'],
        'student.import' => ['module' => 'student', 'action' => 'import', 'label' => 'Import'],

        // --- Courses -------------------------------------------------------
        'course.view' => ['module' => 'course', 'action' => 'view', 'label' => 'View'],
        'course.create' => ['module' => 'course', 'action' => 'create', 'label' => 'Create'],
        'course.edit' => ['module' => 'course', 'action' => 'edit', 'label' => 'Edit'],
        'course.delete' => ['module' => 'course', 'action' => 'delete', 'label' => 'Delete'],
        'course.import' => ['module' => 'course', 'action' => 'import', 'label' => 'Import'],

        // --- Questionnaires ------------------------------------------------
        'questionnaire.create' => ['module' => 'questionnaire', 'action' => 'create', 'label' => 'Create'],
        'questionnaire.edit' => ['module' => 'questionnaire', 'action' => 'edit', 'label' => 'Edit'],
        'questionnaire.delete' => ['module' => 'questionnaire', 'action' => 'delete', 'label' => 'Delete'],

        // --- Reports -------------------------------------------------------
        'report.view' => [
            'module' => 'report',
            'action' => 'view',
            'label' => 'View reports',
            'scope_for' => 'report.view',
        ],
        'report.view.own' => [
            'module' => 'report',
            'action' => 'view',
            'label' => 'Own reports only',
            'scope_for' => 'report.view',
            'scope' => 'own',
        ],
        'report.view.team' => [
            'module' => 'report',
            'action' => 'view',
            'label' => 'Own department reports',
            'scope_for' => 'report.view',
            'scope' => 'team',
        ],
        'report.view.all' => [
            'module' => 'report',
            'action' => 'view',
            'label' => 'All reports',
            'scope_for' => 'report.view',
            'scope' => 'all',
        ],
        'report.export' => ['module' => 'report', 'action' => 'export', 'label' => 'Export'],

        // --- User management ----------------------------------------------
        'user.view' => ['module' => 'user', 'action' => 'view', 'label' => 'View'],
        'user.create' => ['module' => 'user', 'action' => 'create', 'label' => 'Create'],
        'user.edit' => ['module' => 'user', 'action' => 'edit', 'label' => 'Edit'],
        'user.disable' => ['module' => 'user', 'action' => 'disable', 'label' => 'Disable accounts'],

        // --- Role management ----------------------------------------------
        'role.view' => ['module' => 'role', 'action' => 'view', 'label' => 'View'],
        'role.create' => ['module' => 'role', 'action' => 'create', 'label' => 'Create'],
        'role.edit' => ['module' => 'role', 'action' => 'edit', 'label' => 'Edit'],
        'role.delete' => ['module' => 'role', 'action' => 'delete', 'label' => 'Delete'],

        // --- Permission management ----------------------------------------
        'permission.view' => ['module' => 'permission', 'action' => 'view', 'label' => 'View permissions'],
        'permission.manage' => ['module' => 'permission', 'action' => 'manage', 'label' => 'Manage permissions'],

        // --- System --------------------------------------------------------
        'settings.manage' => ['module' => 'settings', 'action' => 'manage', 'label' => 'Change system settings'],
        'backup.manage' => ['module' => 'backup', 'action' => 'manage', 'label' => 'Manage backups'],

        // --- Offices -------------------------------------------------------
        'office.view' => ['module' => 'office', 'action' => 'view', 'label' => 'View'],
        'office.create' => ['module' => 'office', 'action' => 'create', 'label' => 'Create'],
        'office.edit' => ['module' => 'office', 'action' => 'edit', 'label' => 'Edit'],
        'office.delete' => ['module' => 'office', 'action' => 'delete', 'label' => 'Delete'],
        'office.qr.manage' => ['module' => 'office', 'action' => 'qr.manage', 'label' => 'Manage QR codes'],
        'office.feedback.view' => ['module' => 'office', 'action' => 'feedback.view', 'label' => 'View feedback'],
        'office.feedback.delete' => ['module' => 'office', 'action' => 'feedback.delete', 'label' => 'Delete feedback'],
        'office.report.view' => ['module' => 'office', 'action' => 'report.view', 'label' => 'View reports'],
        'office.report.export' => ['module' => 'office', 'action' => 'report.export', 'label' => 'Export reports'],
        'office.questionnaire.create' => ['module' => 'office', 'action' => 'questionnaire.create', 'label' => 'Create questionnaire'],
        'office.questionnaire.edit' => ['module' => 'office', 'action' => 'questionnaire.edit', 'label' => 'Edit questionnaire'],
        'office.questionnaire.delete' => ['module' => 'office', 'action' => 'questionnaire.delete', 'label' => 'Delete questionnaire'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module ordering / labels for the admin permission matrix
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'dashboard' => 'Dashboard',
        'evaluation' => 'Evaluation',
        'report' => 'Reports',
        'faculty' => 'Faculty / Employee',
        'assignment' => 'Faculty Assignments',
        'student' => 'Students',
        'course' => 'Courses',
        'questionnaire' => 'Questionnaires',
        'user' => 'User Management',
        'role' => 'Role Management',
        'permission' => 'Permission Management',
        'settings' => 'System Settings',
        'backup' => 'Backups',
        'office' => 'Offices',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy permission => namespaced expansion
    |--------------------------------------------------------------------------
    | Used by the one-time data migration (and kept for reference/tests) so
    | existing roles/users keep their exact effective access after the rename.
    | One legacy permission may expand into several granular ones.
    */

    'legacy_map' => [
        'manage_rbac' => [
            'permission.view', 'permission.manage',
            'role.view', 'role.create', 'role.edit', 'role.delete',
            'settings.manage', 'backup.manage',
        ],
        'manage_users' => [
            'user.view', 'user.create', 'user.edit', 'user.disable',
            'student.view', 'student.create', 'student.edit', 'student.delete', 'student.import',
        ],
        'manage_faculty' => [
            'faculty.view', 'faculty.create', 'faculty.edit', 'faculty.delete', 'faculty.import',
            'assignment.view', 'assignment.create', 'assignment.delete',
        ],
        'manage_courses' => [
            'course.view', 'course.create', 'course.edit', 'course.delete', 'course.import',
        ],
        'manage_categories' => ['questionnaire.create', 'questionnaire.edit', 'questionnaire.delete'],
        'manage_questions' => ['questionnaire.create', 'questionnaire.edit', 'questionnaire.delete'],
        'view_dashboard' => ['dashboard.view'],
        'view_reports' => ['report.view', 'report.view.all', 'report.export'],
        'give_evaluations' => ['evaluation.create', 'evaluation.submit'],
        'view_evaluations' => ['evaluation.view', 'evaluation.view.own', 'evaluation.export', 'report.view', 'report.view.own'],
        'manage_offices' => [
            'office.view', 'office.create', 'office.edit', 'office.delete', 'office.qr.manage',
            'office.feedback.view', 'office.feedback.delete',
            'office.report.view', 'office.report.export',
            'office.questionnaire.create', 'office.questionnaire.edit', 'office.questionnaire.delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default role grants (backward compatibility)
    |--------------------------------------------------------------------------
    | Mirrors the effective access of the previous RolePermissionSeeder:
    |   Admin   = every permission EXCEPT giving/submitting evaluations
    |   Faculty = view own evaluations + own reports + dashboard
    |   Student = give evaluations
    */

    'role_defaults' => [
        'Admin' => [
            'except' => ['evaluation.create', 'evaluation.submit'],
        ],
        'Faculty' => [
            'only' => [
                'evaluation.view', 'evaluation.view.own', 'evaluation.export',
                'report.view', 'report.view.own',
            ],
        ],
        'Student' => [
            'only' => ['evaluation.create', 'evaluation.submit'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Student permission allowlist
    |--------------------------------------------------------------------------
    | System-control permissions are granted to faculty/staff only. Students
    | may never hold anything outside this list — enforced server-side in
    | RoleController / UserRoleController (422) and reflected as disabled
    | checkboxes in the role matrix and Manage Access modal.
    */
    'student_permission_allowlist' => [
        'evaluation.create',
        'evaluation.submit',
    ],

];
