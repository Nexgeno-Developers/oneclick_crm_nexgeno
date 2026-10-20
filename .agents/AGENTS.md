# PERFEX CRM - AI AGENT WORKSPACE RULES
> This file is loaded automatically by the IDE and AI agents from `.agents/AGENTS.md`.  
> For the complete architectural guide, refer to the root [AGENTS.md](file:///c:/xampp/1/htdocs/oneclick/AGENTS.md).

## Core Rules for This Workspace

1. **Zero Core Modifications:**
   - Never modify `application/controllers`, `application/models`, `application/views` (unless officially supported `my_<view>.php` overrides), `application/core`, or `system/`.
   - All customizations MUST be encapsulated as standalone modules inside `/modules/<module_name>/`.

2. **Database Integrity:**
   - Always use `db_prefix() . 'table_name'`. Never hardcode `tbl...`.
   - Never write raw unescaped SQL with variables. Always use CodeIgniter Active Record (`$this->db->where()`, `$this->db->get()`, etc.).
   - Migrations in `install.php` must be idempotent (`CREATE TABLE IF NOT EXISTS` and `$CI->db->field_exists()` checks).

3. **Enterprise Security:**
   - Always check permissions with `has_permission()` before allowing view/edit/delete actions.
   - Always authenticate staff (`is_staff_logged_in()`) and clients (`is_client_logged_in()`).
   - Validate ownership to prevent IDOR vulnerabilities.
   - Always sanitize output (`clean_html()`, `htmlspecialchars()`) and enforce CSRF tokens on forms/AJAX.

4. **Workflow Execution:**
   - Analyze requirements and produce an implementation plan first before writing code.
   - Use available skills in `.agents/skills/`:
     - `perfex-crm-project-manager`: Planning & requirements analysis.
     - `perfex-crm-senior-developer`: Module architecture & code standards.
     - `perfex-crm-qa-tester`: Security, permissions, and regression testing.

5. **Clean Workspace & Version Control:**
   - Never commit `application/config/app-config.php`, session files, uploads, database dumps, or vendor caches.
   - Keep `.agents/` and `AGENTS.md` tracked in Git.
