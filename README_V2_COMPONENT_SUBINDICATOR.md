# ISLANDS GEB Portal — Component/Sub-Indicator Extension

This build preserves the supplied portal MVP and extends it with:

- Four M&R Framework components and their project-level sub-indicators/outcomes/outputs.
- Explicit child-project ↔ sub-indicator ↔ GEB linkage.
- A new component/sub-indicator reporting form.
- The same Draft → Review → Approval → Completed workflow and audit trail for sub-indicator records.
- Sub-indicator workflow board and register.
- Sub-indicator CSV/XLSX export.
- API dataset support for `geb`, `subindicator`, or `all`.
- Structured GEF #11 beneficiary-category table with male/female fields and automatic total.
- Automatic derivation of GEF #11.1 (male) and GEF #11.2 (female) from the GEF #11 category table.
- Structured GEF #10.1 policy/regulatory instrument table with the three framework categories and advancement stages: consultation, drafted, adopted, under implementation.
- New schema is additive and designed to preserve existing GEB submissions.

## Important

The four component names and sub-indicator catalogue follow the supplied M&R Framework. The framework also contains project contribution mappings and unresolved mapping/numbering issues; the portal stores the project logframe reference and a reviewable GEB linkage rather than silently replacing the framework.

## Local paths

Existing application: `C:\xampp\htdocs\islands_geb_portal`

Recommended new build folder: `C:\xampp\htdocs\islands_geb_portal_v2`

Local URL: `http://localhost/islands_geb_portal_v2/`

The new build does **not** overwrite the existing folder.
