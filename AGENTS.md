# PERFEX CRM - AI AGENT OPERATING GUIDELINES & ARCHITECTURAL STANDARDS
> **Universal Standards Document for AI Coding Assistants (Antigravity, Cursor, Windsurf, Claude Code, Copilot, Cline, Roo Code)**  
> **Applies to:** All Nexgeno Perfex CRM Projects

---

## 1. System Architecture & Tech Stack

| Component | Specification |
|---|---|
| **Platform** | Perfex CRM (Self-hosted PHP Business Application) |
| **Framework** | CodeIgniter 3 (MVC Architecture) |
| **Language** | PHP (7.4 to 8.2 compatible) |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Frontend** | Bootstrap (3.x / 4.x depending on core theme), jQuery, DataTables |
| **Extension Model** | Standalone Custom Modules (`/modules/<module_name>/`) |

---

## 2. The 7 Non-Negotiable AI Directives

### 🛡️ Directive 1: Core Protection (Zero Core Modifications)
- **NEVER** modify files inside:
  - `application/controllers/`
  - `application/models/`
  - `application/views/` *(unless using officially supported `my_<view>.php` overrides or explicitly directed by the project lead)*
  - `application/core/`
  - `system/`
- **Why:** Core file edits break when Perfex CRM updates, introduce regressions, and create untrackable merge conflicts.
- **The Correct Pattern:** Implement all features as **standalone custom modules** inside `/modules/<module_name>/` or hook into core events using Perfex's Hook System.

---

### 🧩 Directive 2: Module-First Development Architecture
Every new feature or customization must follow the standard Perfex module anatomy:

```text
modules/<module_name>/
├── <module_name>.php         # Main entry point (registers module, hooks, permissions, menu items)
├── install.php               # Database table/column creation (must be idempotent: IF NOT EXISTS)
├── uninstall.php             # Cleanup script on module deletion (optional/as needed)
├── controllers/              # Module controllers extending AdminController or ClientsController
├── models/                   # Module models extending App_Model
├── views/                    # Views loaded via $this->load->view('module_name/view_file')
│   ├── admin/                # Admin area views
│   └── client/               # Customer portal views
├── helpers/                  # Module helper functions (<module_name>_helper.php)
├── assets/                   # CSS, JS, images specific to this module
│   ├── css/
│   └── js/
└── language/                 # Multi-language translations
    └── english/
        └── <module_name>_lang.php
```

#### Key Module Hook Rules:
- **Registration**: Always register activation, deactivation, and language hooks in `<module_name>.php`.
- **Menu Items**: Add navigation items using `hooks()->add_action('admin_init', '...')` with `app_menu()->add_sidebar_children_item()` or `add_sidebar_menu_item()`.
- **Actions & Filters**: Use `hooks()->add_action()` and `hooks()->add_filter()` to attach logic to core events (e.g., `after_invoice_added`, `before_client_added`).

---

### 🗄️ Directive 3: Database & SQL Safety Standards
1. **Always Use Table Prefixes:**
   - **Correct:** `$this->db->get(db_prefix() . 'my_table');`
   - **Incorrect:** `$this->db->get('tblmy_table');` *(never hardcode prefixes)*
2. **Never Use Unescaped Raw SQL:**
   - **Forbidden:** `$this->db->query("SELECT * FROM " . db_prefix() . "orders WHERE id = " . $id);`
   - **Mandatory:** Use CodeIgniter Active Record / Query Builder:
     ```php
     $this->db->where('id', $id);
     $order = $this->db->get(db_prefix() . 'orders')->row();
     ```
     Or use positional bindings if raw SQL is strictly required:
     ```php
     $this->db->query("SELECT * FROM " . db_prefix() . "orders WHERE id = ? AND status = ?", [$id, $status]);
     ```
3. **Idempotent Installation (`install.php`):**
   - Tables must be created using `CREATE TABLE IF NOT EXISTS`.
   - If adding columns to existing tables, always check existence first:
     ```php
     if (!$CI->db->field_exists('custom_column', db_prefix() . 'invoices')) {
         $CI->db->query("ALTER TABLE `" . db_prefix() . "invoices` ADD `custom_column` VARCHAR(191) NULL;");
     }
     ```

---

### 🔒 Directive 4: Enterprise Security & Access Control
1. **Permission Enforcement:**
   - Define module permissions during initialization via `register_staff_capabilities()`.
   - Protect every controller method:
     ```php
     if (!has_permission('my_module', '', 'view')) {
         access_denied('my_module');
     }
     ```
   - Hide UI buttons/actions in views if user lacks permission:
     ```php
     <?php if (has_permission('my_module', '', 'create')) { ?>
         <a href="..." class="btn btn-info">Add New</a>
     <?php } ?>
     ```
2. **Authentication Checks:**
   - Admin area: Ensure `is_staff_logged_in()` is true (standard `AdminController` checks this).
   - Client portal: Ensure `is_client_logged_in()` is true when viewing client-specific records.
3. **IDOR (Insecure Direct Object Reference) Prevention:**
   - Never query solely by URL ID without verifying tenant or ownership rights:
     ```php
     // Check that the invoice belongs to the logged-in client
     $this->db->where('id', $id);
     $this->db->where('clientid', get_client_user_id());
     ```
4. **CSRF Protection:**
   - Form views must use `echo form_open(...)` or include the CSRF token input.
   - AJAX POST requests must send the CSRF cookie/token (`csrf_token_name` and `csrf_hash`).
5. **Output Sanitization (Anti-XSS):**
   - Escape output: `htmlspecialchars()`, or Perfex's `clean_html()` for rich text/HTML fields.

---

### 📋 Directive 5: Workflow - Plan First, Code Second
For any feature request, task, or bug fix:
1. **Analyze Requirements:** Understand user roles, affected areas, and workflows.
2. **Formulate a Plan:**
   - Hook opportunities vs core override needs
   - Database schema changes (tables, indexes, foreign keys)
   - Permissions to register
   - UI views & DataTable implementations
3. **Review & Confirm:** Share the implementation plan before generating massive code changes.
4. **Implement Cleanly:** Build following modular standards.
5. **QA & Regression Check:** Verify that core functions (Leads, Invoices, Projects, Staff) remain unaffected.

---

### 🎨 Directive 6: UI / UX & Perfex Standards
1. **Admin Theme Alignment:**
   - Use standard Bootstrap markup compatible with Perfex's current theme.
   - Use standard Perfex UI panels: `<div class="panel_s"><div class="panel-body">...</div></div>`.
2. **DataTables:**
   - For lists likely to exceed 50 items, use server-side DataTables via Perfex's `initDataTable()`.
   - Create table definition controllers in `controllers/table_<name>.php` or views following Perfex conventions.
3. **Form Helpers:**
   - Use standard helper functions: `render_input()`, `render_select()`, `render_textarea()`, `render_date_input()`.
4. **Language & i18n:**
   - **Never hardcode English text** in views or controller flash messages.
   - Use `_l('my_module_string')` and maintain translations in `modules/<module_name>/language/english/<module_name>_lang.php`.

---

### 🧹 Directive 7: Git & Workspace Hygiene
1. **Never Commit Secrets or Local Environment Data:**
   - Do **NOT** commit `application/config/app-config.php` or `.env`.
   - Do **NOT** commit `application/logs/`, `application/cache/`, `ci_sessions/`, or customer `uploads/`.
   - Do **NOT** commit raw database backups (`.sql`) or spreadsheets containing client data.
2. **Vendor Directory Rules:**
   - `application/vendor/` is ignored by Git. Do not attempt to track or force-push third-party libraries into core vendor.
   - Custom module vendor folders (`modules/<module_name>/vendor/`) may be tracked if self-contained and small.
3. **Track Team AI Skills:**
   - Keep `.agents/` and `AGENTS.md` **tracked** in Git so all pair developers and AI systems share identical context and quality standards.
4. **Clean Worktrees:**
   - Never leave detached Git worktrees (e.g., `.kilo/worktrees/*`). Clean up scratch scripts after task completion.

---

## 3. Specialized Sub-Roles (Skills Available in `.agents/skills/`)

When working on specific phases of a task, adopt the appropriate persona:

1. **`perfex-crm-project-manager`**
   - **When to use:** In the initial phase of any feature request or architectural overhaul.
   - **Goal:** Produces detailed implementation plans, identifies risks, defines database requirements, and maps out dependencies.
2. **`perfex-crm-senior-developer`**
   - **When to use:** During code implementation and refactoring.
   - **Goal:** Delivers modular, PSR-compliant, secure, and hook-driven CodeIgniter/PHP code with installation/migration SQL.
3. **`perfex-crm-qa-tester`**
   - **When to use:** After code changes or before releases.
   - **Goal:** Actively attempts to break custom modules, tests permission boundaries, probes for SQLi/XSS/CSRF/IDOR, and checks for regression.

---
*Document Version: 1.0.0 — Maintained by Nexgeno Engineering Team*
