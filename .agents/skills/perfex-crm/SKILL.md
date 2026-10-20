---
name: perfex-crm
description: End-to-end development, planning, and QA testing workflow for Perfex CRM modules and customizations. Use whenever planning, building, modifying, reviewing, or QA testing any Perfex CRM feature, CodeIgniter PHP code, database schema, or custom module.
---

# Unified Perfex CRM Engineering Workflow (PM ➔ Dev ➔ QA)

This skill provides an all-in-one, structured engineering lifecycle for Perfex CRM features and custom modules. Every task must pass through this 3-phase flow.

---

## 🛑 Zero-Deviation Core Directives
1. **Zero Core Modifications:** Never modify `application/controllers`, `application/models`, `application/views` (except safe theme overrides `my_<view>.php`), `application/core`, or `system/`. Everything must live in `/modules/<module_name>/`.
2. **Database Integrity:** Always use table prefix `db_prefix() . 'table_name'`. Never write unescaped raw SQL. Use CodeIgniter Active Record / Query Builder. Migrations must be idempotent with `IF NOT EXISTS` and `$CI->db->field_exists()` checks.
3. **Strict Git Workflow:**
   - **Always develop in a new task branch** (`feat/...`, `fix/...`, `chore/...`).
   - **NEVER push automatically.** The user reviews and pushes.
   - **NEVER merge automatically.** Merges must go through a Pull Request or be explicitly instructed by the user, and **must always use `--no-ff`** (`git merge --no-ff <branch_name>`). Fast-forward merges are strictly forbidden.

---

## Phase 1: Planning & Technical Architecture (The PM Phase)

Before writing any code, produce an implementation plan:

### 1. Requirements & User Roles
- What business problem does this solve?
- Which roles are affected? (Admin, Staff, Department Manager, Client)
- What permissions are required?

### 2. Architecture & Hook Discovery
- Can this feature be 100% encapsulated inside `/modules/<module_name>/`?
- Which Perfex hooks (`hooks()->add_action()`, `hooks()->add_filter()`) will be used?
  - Core event examples: `admin_init`, `after_invoice_added`, `before_lead_created`, `app_admin_head`
  - Menu hooks: `admin_init` ➔ `app_menu()->add_sidebar_children_item()`

### 3. Database Schema Design
- New tables needed? (Must have primary key `id`, indexes on foreign keys, `utf8mb4_unicode_ci` charset).
- New columns needed on existing tables?
- Idempotent migration design for `install.php`.

### 4. Security & Access Boundaries
- Define capabilities: `register_staff_capabilities('my_module', $capabilities, _l('my_module'))`
- CSRF protection plan on all form/AJAX endpoints.
- IDOR mitigation strategy: ensure tenant/ownership check on every ID-based query.

---

## Phase 2: Implementation (The Senior Developer Phase)

### Standard Module Anatomy
```text
modules/<module_name>/
├── <module_name>.php         # Module entry point (registers module, hooks, menu, permissions)
├── install.php               # Idempotent database migrations
├── uninstall.php             # Optional cleanup
├── controllers/              # Controllers extending AdminController / ClientsController
├── models/                   # Models extending App_Model
├── views/                    # Views (admin/ and client/)
├── helpers/                  # Helper functions (<module_name>_helper.php)
├── assets/                   # CSS & JS
└── language/                 # Multi-language translations
    └── english/
        └── <module_name>_lang.php
```

### Critical Code Patterns

#### 1. Controller Authentication & Permissions
```php
class My_module extends AdminController {
    public function __construct() {
        parent::__construct();
        if (!has_permission('my_module', '', 'view')) {
            access_denied('my_module');
        }
        $this->load->model('my_module_model');
    }

    public function manage() {
        if (!has_permission('my_module', '', 'view')) {
            access_denied('my_module');
        }
        $data['title'] = _l('my_module_title');
        $this->load->view('my_module/manage', $data);
    }
}
```

#### 2. Safe Database Operations (Active Record Only)
```php
// GOOD - Active Record with automatic escaping
$this->db->where('id', (int) $id);
$record = $this->db->get(db_prefix() . 'my_custom_table')->row();

// FORBIDDEN - Raw unescaped string interpolation
// $this->db->query("SELECT * FROM " . db_prefix() . "my_custom_table WHERE id = " . $id);
```

#### 3. Idempotent `install.php`
```php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'my_custom_table')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'my_custom_table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `datecreated` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('custom_field', db_prefix() . 'invoices')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'invoices` ADD `custom_field` VARCHAR(191) NULL;');
}
```

#### 4. UI, DataTables & Forms
- Lists > 50 items must use server-side DataTables with `initDataTable()`.
- Use Perfex helpers: `render_input()`, `render_select()`, `render_textarea()`, `render_date_input()`.
- Never hardcode user-facing English strings; use `_l('my_string_key')`.

---

## Phase 3: Security & Regression Audit (The QA Phase)

Before presenting work as complete, audit the solution against this checklist:

### 1. Security Probes
- **SQL Injection:** Are all database queries using Active Record or bindings? No `$this->db->query("...$var")`?
- **XSS (Cross-Site Scripting):** Is all user-generated content sanitized with `clean_html()` or `htmlspecialchars()`?
- **CSRF:** Do all form submissions use `form_open()` or include CSRF tokens? Do AJAX POSTs pass `csrf_token_name` and `csrf_hash`?
- **IDOR (Direct Object References):** Does editing/deleting record `id=5` verify that the user actually owns or has permission to edit record `5`?

### 2. Permission Boundary Testing
- Can an unauthorized staff member view or manipulate the module by directly navigating to the URL?
- Are action buttons (Add, Edit, Delete) hidden in the view if `!has_permission(...)`?

### 3. Core Regression Verification
Confirm that primary Perfex CRM workflows remain 100% operational:
- Invoices & Estimates
- Leads & Customers
- Projects & Tasks
- Staff management & Authentication
