# UI/UX Refinement Plan - TODO

## Pages to Refine: Roles, Users, Audit Logs, Profile

### Step 1: Update `assets/css/user_roles.css` ✅

- [x] Improve Add/Edit Role form with 2-column grid layout
- [x] Style permissions checkboxes as a grid of tag-like cards
- [x] Add empty state styling for table
- [x] Add hover/transition effects on form elements
- [x] Improve responsive table behavior

### Step 2: Update `pages/user_roles.php` ✅

- [x] Wrap form fields in `.form-grid` div for 2-column layout
- [x] Replace inline checkbox labels with `.permissions-grid` and `.permission-checkbox`
- [x] Wrap buttons in `.form-actions` container

### Step 3: Update `assets/css/user_management.css` ✅

- [x] Convert Add/Edit User form to 2-column grid (like profile)
- [x] Style filter section (search + dropdowns) as a card-like bar
- [x] Add empty state styling for table
- [x] Add hover effects on form cards and table rows
- [x] Improve responsive table column hiding

### Step 4: Update `pages/user_management.php` ✅

- [x] Wrap form fields in `.form-grid` div for 2-column layout
- [x] Wrap buttons in `.form-actions` container

### Step 5: Create `assets/css/audit_logs.css` ✅

- [x] Create dedicated stylesheet for audit logs page
- [x] Style table with better visual hierarchy (action icons, badges)
- [x] Add section header with icon
- [x] Add empty state styling
- [x] Make responsive

### Step 6: Update `assets/css/profile.css` ✅

- [x] Add visual distinction for readonly input fields (dashed border, muted color)
- [x] Improve card hover effects (border highlight, shadow)
- [x] Add avatar glow on header hover

### Step 7: Update `assets/css/style.css` ✅

- [x] Add `.form-grid` shared utility for 2-column form layouts
- [x] Add `.form-actions` shared utility for form button rows
- [x] Add `.table-empty-state` utility for empty table messages
