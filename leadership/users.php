<?php
// leadership/users.php - User Management Module (Leaders & Officers, Student Accounts & Dynamic Role Library)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id']);
$current_org_id = intval($_SESSION['org_id']);
$current_role = $_SESSION['user_role'] ?? 'Officer';
$current_user_name = $_SESSION['user_name'] ?? 'Administrator';

$role_perms = getRolePermissions($current_role, $pdo);
$is_admin = !empty($role_perms['is_admin']);
$is_pres_or_vp = !empty($role_perms['is_pres_or_vp']);
$can_manage_users = !empty($role_perms['can_manage_users']);

// Strict RBAC: Only Admin, President, Vice President, or Custom Roles with User Management permission can access this page
if (!$can_manage_users) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. You do not have permission to access User Management."));
    exit;
}

$success_msg = '';
$error_msg = '';

$req_tab = $_GET['tab'] ?? 'leaders';
if ($req_tab === 'students') {
    $active_tab = 'students';
} elseif ($req_tab === 'roles' && $is_admin) {
    $active_tab = 'roles';
} else {
    $active_tab = 'leaders';
}

// ----------------------------------------------------
// Handle CRUD Actions
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // 1. LEADER / OFFICER ACTIONS
    // ==========================================
    if ($action === 'create_user') {
        $active_tab = 'leaders';
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'Officer');
        
        // Scope org_id
        $org_id = $is_admin ? intval($_POST['org_id'] ?? 0) : $current_org_id;

        // Prevent non-admin from creating Admin or Admin-equivalent accounts
        $target_role_perms = getRolePermissions($role, $pdo);
        if (!$is_admin && !empty($target_role_perms['is_admin'])) {
            $role = 'Officer';
        }

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($org_id)) {
            $error_msg = "All required fields must be filled out.";
        } else {
            // Check if username or email already exists in users table
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Username or Email address is already taken by another officer account.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (org_id, full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$org_id, $full_name, $username, $email, $hashed_password, $role])) {
                    $success_msg = "Officer account '$username' ($role) created successfully!";
                } else {
                    $error_msg = "Failed to create officer account.";
                }
            }
        }
    }

    if ($action === 'update_user') {
        $active_tab = 'leaders';
        $user_id = intval($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'Officer');
        $new_password = trim($_POST['new_password'] ?? '');

        // Fetch target user for security verification
        $target_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $target_stmt->execute([$user_id]);
        $target_user = $target_stmt->fetch();
        $existing_target_perms = $target_user ? getRolePermissions($target_user['role'], $pdo) : [];

        if (!$target_user) {
            $error_msg = "The specified user account was not found.";
        } elseif (!$is_admin && $target_user['org_id'] != $current_org_id) {
            $error_msg = "Permission denied: You can only manage officers in your own organization.";
        } elseif (!$is_admin && !empty($existing_target_perms['is_admin'])) {
            $error_msg = "Permission denied: You cannot modify an Administrator account.";
        } else {
            // Scope org_id
            $org_id = $is_admin ? intval($_POST['org_id'] ?? $target_user['org_id']) : $current_org_id;

            // Non-admin cannot escalate anyone to Admin or Admin-equivalent role
            $new_role_perms = getRolePermissions($role, $pdo);
            if (!$is_admin && !empty($new_role_perms['is_admin'])) {
                $role = $target_user['role'];
            }

            if (empty($full_name) || empty($username) || empty($email) || empty($org_id)) {
                $error_msg = "Full Name, Username, Email, and Organization are required.";
            } else {
                // Check duplicate username for other users
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check->execute([$username, $email, $user_id]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "Username or Email is already in use by another account.";
                } else {
                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET org_id = ?, full_name = ?, username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                        $stmt->execute([$org_id, $full_name, $username, $email, $hashed_password, $role, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET org_id = ?, full_name = ?, username = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$org_id, $full_name, $username, $email, $role, $user_id]);
                    }

                    // Update active session name if editing self
                    if ($user_id === $current_user_id) {
                        $_SESSION['user_name'] = $full_name;
                        $_SESSION['user_role'] = $role;
                    }

                    $success_msg = "Officer account updated successfully!";
                }
            }
        }
    }

    if ($action === 'delete_user') {
        $active_tab = 'leaders';
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id === $current_user_id) {
            $error_msg = "You cannot delete your own currently logged-in account!";
        } else {
            $target_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $target_stmt->execute([$user_id]);
            $target_user = $target_stmt->fetch();
            $existing_target_perms = $target_user ? getRolePermissions($target_user['role'], $pdo) : [];

            if (!$target_user) {
                $error_msg = "Target account not found.";
            } elseif (!$is_admin && $target_user['org_id'] != $current_org_id) {
                $error_msg = "Permission denied: You can only delete accounts in your own organization.";
            } elseif (!$is_admin && !empty($existing_target_perms['is_admin'])) {
                $error_msg = "Permission denied: You cannot delete an Administrator account.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success_msg = "Officer account deleted successfully.";
                } else {
                    $error_msg = "Failed to delete user account.";
                }
            }
        }
    }

    // ==========================================
    // 2. STUDENT ACCOUNT ACTIONS (Admin Only)
    // ==========================================
    if (in_array($action, ['create_student', 'update_student', 'delete_student', 'approve_student', 'reject_student'], true)) {
        $active_tab = 'students';
        if (!$is_admin) {
            $error_msg = "Permission denied: Only Administrators can create, edit, approve, or delete student accounts.";
        } elseif ($action === 'approve_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $target_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $target_stmt->execute([$student_id]);
            $target_student = $target_stmt->fetch();
            if (!$target_student) {
                $error_msg = "Target student account was not found.";
            } else {
                $prev_status = $target_student['status'] ?? 'Pending';
                $upd = $pdo->prepare("UPDATE students SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                if ($upd->execute([$current_user_id, $student_id])) {
                    if (function_exists('recordStudentAccountLog')) {
                        $action_lbl = ($prev_status === 'Rejected') ? 'Re-Approved' : 'Approved';
                        $log_msg = "Account approval status changed from '{$prev_status}' to 'Approved' by {$current_user_name} ({$current_role}). Student is now permitted to sign in to the Student Portal.";
                        recordStudentAccountLog($student_id, $target_student['student_lrn'], $target_student['full_name'], $current_user_id, $current_user_name, $current_role, $action_lbl, $prev_status, 'Approved', $log_msg, $pdo);
                    }
                    $success_msg = "Student account for '" . htmlspecialchars($target_student['full_name']) . "' (LRN: " . htmlspecialchars($target_student['student_lrn']) . ") has been APPROVED and logged successfully!";
                } else {
                    $error_msg = "Failed to approve student account.";
                }
            }
        } elseif ($action === 'reject_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $target_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $target_stmt->execute([$student_id]);
            $target_student = $target_stmt->fetch();
            if (!$target_student) {
                $error_msg = "Target student account was not found.";
            } else {
                $prev_status = $target_student['status'] ?? 'Pending';
                $upd = $pdo->prepare("UPDATE students SET status = 'Rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
                if ($upd->execute([$current_user_id, $student_id])) {
                    if (function_exists('recordStudentAccountLog')) {
                        $action_lbl = ($prev_status === 'Approved') ? 'Revoked / Rejected' : 'Rejected';
                        $log_msg = "Account approval status changed from '{$prev_status}' to 'Rejected' by {$current_user_name} ({$current_role}). Student portal access blocked.";
                        recordStudentAccountLog($student_id, $target_student['student_lrn'], $target_student['full_name'], $current_user_id, $current_user_name, $current_role, $action_lbl, $prev_status, 'Rejected', $log_msg, $pdo);
                    }
                    $success_msg = "Student account for '" . htmlspecialchars($target_student['full_name']) . "' has been marked as REJECTED and logged.";
                } else {
                    $error_msg = "Failed to update student account status.";
                }
            }
        } elseif ($action === 'create_student') {
            $student_lrn = trim($_POST['student_lrn'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $grade_section = trim($_POST['grade_section'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($student_lrn) || empty($full_name) || empty($grade_section) || empty($password)) {
                $error_msg = "LRN, Full Name, Grade & Section, and Password are required.";
            } else {
                // Check duplicate LRN or email
                $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_lrn = ? OR (email = ? AND email != '')");
                $check->execute([$student_lrn, $email]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "A student with this LRN or Email already exists.";
                } else {
                    $status = in_array($_POST['status'] ?? '', ['Pending', 'Approved', 'Rejected'], true) ? $_POST['status'] : 'Approved';
                    $approved_by = ($status === 'Approved') ? $current_user_id : null;
                    $approved_at = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO students (student_lrn, full_name, grade_section, email, password, status, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed_password, $status, $approved_by, $approved_at])) {
                        $new_stu_id = intval($pdo->lastInsertId());
                        if (function_exists('recordStudentAccountLog')) {
                            $log_msg = "Student account manually created by {$current_user_name} ({$current_role}) with LRN '{$student_lrn}' ({$grade_section}) and initial status '{$status}'.";
                            recordStudentAccountLog($new_stu_id, $student_lrn, $full_name, $current_user_id, $current_user_name, $current_role, "Created ({$status})", null, $status, $log_msg, $pdo);
                        }
                        $success_msg = "Student account for '$full_name' (LRN: $student_lrn) created and logged with status '$status'!";
                    } else {
                        $error_msg = "Failed to create student account.";
                    }
                }
            }
        } elseif ($action === 'update_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $student_lrn = trim($_POST['student_lrn'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $grade_section = trim($_POST['grade_section'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $new_password = trim($_POST['new_password'] ?? '');

            if (empty($student_lrn) || empty($full_name) || empty($grade_section)) {
                $error_msg = "LRN, Full Name, and Grade & Section are required.";
            } else {
                // Check duplicate LRN or email for other students
                $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE (student_lrn = ? OR (email = ? AND email != '')) AND id != ?");
                $check->execute([$student_lrn, $email, $student_id]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "Another student is already using this LRN or Email address.";
                } else {
                    $old_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $old_stmt->execute([$student_id]);
                    $old_stu = $old_stmt->fetch();
                    $old_status = $old_stu ? ($old_stu['status'] ?? 'Approved') : 'Approved';

                    $status = in_array($_POST['status'] ?? '', ['Pending', 'Approved', 'Rejected'], true) ? $_POST['status'] : 'Approved';
                    $approved_by_val = ($status === 'Approved') ? $current_user_id : null;
                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE students SET student_lrn = ?, full_name = ?, grade_section = ?, email = ?, password = ?, status = ?, approved_by = IF(? = 'Approved' AND approved_by IS NULL, ?, approved_by), approved_at = IF(? = 'Approved' AND approved_at IS NULL, NOW(), approved_at) WHERE id = ?");
                        $stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed_password, $status, $status, $approved_by_val, $status, $student_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE students SET student_lrn = ?, full_name = ?, grade_section = ?, email = ?, status = ?, approved_by = IF(? = 'Approved' AND approved_by IS NULL, ?, approved_by), approved_at = IF(? = 'Approved' AND approved_at IS NULL, NOW(), approved_at) WHERE id = ?");
                        $stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $status, $status, $approved_by_val, $status, $student_id]);
                    }

                    if (function_exists('recordStudentAccountLog')) {
                        if ($old_status !== $status) {
                            $action_lbl = "Status Changed to {$status}";
                            $log_msg = "Account approval status changed from '{$old_status}' to '{$status}' by {$current_user_name} ({$current_role}).";
                        } else {
                            $action_lbl = "Account Updated";
                            $log_msg = "Student profile details updated by {$current_user_name} ({$current_role}). Status remains '{$status}'.";
                        }
                        recordStudentAccountLog($student_id, $student_lrn, $full_name, $current_user_id, $current_user_name, $current_role, $action_lbl, $old_status, $status, $log_msg, $pdo);
                    }
                    $success_msg = "Student account updated and logged successfully!";
                }
            }
        } elseif ($action === 'delete_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $old_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $old_stmt->execute([$student_id]);
            $old_stu = $old_stmt->fetch();

            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            if ($stmt->execute([$student_id])) {
                if ($old_stu && function_exists('recordStudentAccountLog')) {
                    $prev_status = $old_stu['status'] ?? 'Approved';
                    $log_msg = "Student account '{$old_stu['full_name']}' (LRN: {$old_stu['student_lrn']}) was deleted by {$current_user_name} ({$current_role}). Previous status was '{$prev_status}'.";
                    recordStudentAccountLog(null, $old_stu['student_lrn'], $old_stu['full_name'], $current_user_id, $current_user_name, $current_role, 'Account Deleted', $prev_status, 'Deleted', $log_msg, $pdo);
                }
                $success_msg = "Student account removed and logged successfully.";
            } else {
                $error_msg = "Failed to delete student account.";
            }
        }
    }

    // ==========================================
    // 3. DYNAMIC / CUSTOM ROLE LIBRARY ACTIONS (Admin Only)
    // ==========================================
    if (in_array($action, ['create_role', 'update_role', 'delete_role'], true)) {
        $active_tab = 'roles';
        if (!$is_admin) {
            $error_msg = "Permission denied: Only Administrators can manage the User Role Library.";
        } elseif ($action === 'create_role') {
            $role_name = trim($_POST['role_name'] ?? '');
            $base_role = trim($_POST['base_role'] ?? 'Officer');
            $description = trim($_POST['description'] ?? '');
            $badge_style = trim($_POST['badge_style'] ?? '');

            $allowed_bases = ['President', 'Vice President', 'Secretary', 'Treasurer', 'Officer', 'Admin'];
            if (!in_array($base_role, $allowed_bases, true)) {
                $base_role = 'Officer';
            }

            if (empty($badge_style)) {
                switch ($base_role) {
                    case 'Admin': $badge_style = 'badge-role-admin'; break;
                    case 'President': $badge_style = 'badge-role-president'; break;
                    case 'Vice President': $badge_style = 'badge-role-vp'; break;
                    case 'Secretary': $badge_style = 'badge-role-secretary'; break;
                    case 'Treasurer': $badge_style = 'badge-role-treasurer'; break;
                    default: $badge_style = 'badge-role-officer'; break;
                }
            }

            $can_manage_orgs = ($base_role === 'Admin' || !empty($_POST['can_manage_orgs'])) ? 1 : 0;
            $can_manage_users_flag = ($can_manage_orgs || !empty($_POST['can_manage_users'])) ? 1 : 0;
            $can_manage_tasks_flag = ($can_manage_orgs || !empty($_POST['can_manage_tasks'])) ? 1 : 0;
            $can_manage_projects_flag = ($can_manage_orgs || !empty($_POST['can_manage_projects'])) ? 1 : 0;
            $can_manage_acc_flag = ($can_manage_orgs || !empty($_POST['can_manage_accomplishments'])) ? 1 : 0;
            $can_manage_fb_flag = ($can_manage_orgs || !empty($_POST['can_manage_feedbacks'])) ? 1 : 0;
            $can_view_reports_flag = ($can_manage_orgs || !empty($_POST['can_view_reports'])) ? 1 : 0;

            if (empty($role_name)) {
                $error_msg = "Role Name is required (e.g., CEO, Auditor, P.I.O.).";
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE LOWER(role_name) = LOWER(?)");
                $chk->execute([$role_name]);
                if ($chk->fetchColumn() > 0) {
                    $error_msg = "A role named '$role_name' already exists in the Role Library.";
                } else {
                    if (empty($description)) {
                        $description = "Custom role with system accessibility based on $base_role.";
                    }
                    $stmt = $pdo->prepare("INSERT INTO user_roles (role_name, base_role, description, badge_style, can_manage_users, can_manage_tasks, can_manage_projects, can_manage_accomplishments, can_manage_feedbacks, can_view_reports, can_manage_orgs, is_system) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                    if ($stmt->execute([$role_name, $base_role, $description, $badge_style, $can_manage_users_flag, $can_manage_tasks_flag, $can_manage_projects_flag, $can_manage_acc_flag, $can_manage_fb_flag, $can_view_reports_flag, $can_manage_orgs])) {
                        $success_msg = "Custom role '$role_name' (accessibility like $base_role) added to the Role Library!";
                    } else {
                        $error_msg = "Failed to add custom role.";
                    }
                }
            }
        } elseif ($action === 'update_role') {
            $role_id = intval($_POST['role_id'] ?? 0);
            $role_name = trim($_POST['role_name'] ?? '');
            $base_role = trim($_POST['base_role'] ?? 'Officer');
            $description = trim($_POST['description'] ?? '');
            $badge_style = trim($_POST['badge_style'] ?? 'badge-role-officer');

            $stmt_old = $pdo->prepare("SELECT * FROM user_roles WHERE id = ?");
            $stmt_old->execute([$role_id]);
            $old_role = $stmt_old->fetch();

            if (!$old_role) {
                $error_msg = "Role record not found.";
            } else {
                $is_sys = intval($old_role['is_system']) === 1;
                if ($is_sys) {
                    // Preserve core role_name and base_role for built-in system roles
                    $role_name = $old_role['role_name'];
                    $base_role = $old_role['base_role'];
                }

                $is_admin_role = ($base_role === 'Admin' || $role_name === 'Admin');
                $can_manage_orgs = ($is_admin_role || !empty($_POST['can_manage_orgs'])) ? 1 : 0;
                $can_manage_users_flag = ($can_manage_orgs || !empty($_POST['can_manage_users'])) ? 1 : 0;
                $can_manage_tasks_flag = ($can_manage_orgs || !empty($_POST['can_manage_tasks'])) ? 1 : 0;
                $can_manage_projects_flag = ($can_manage_orgs || !empty($_POST['can_manage_projects'])) ? 1 : 0;
                $can_manage_acc_flag = ($can_manage_orgs || !empty($_POST['can_manage_accomplishments'])) ? 1 : 0;
                $can_manage_fb_flag = ($can_manage_orgs || !empty($_POST['can_manage_feedbacks'])) ? 1 : 0;
                $can_view_reports_flag = ($can_manage_orgs || !empty($_POST['can_view_reports'])) ? 1 : 0;

                if (empty($role_name)) {
                    $error_msg = "Role Name cannot be empty.";
                } else {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE LOWER(role_name) = LOWER(?) AND id != ?");
                    $chk->execute([$role_name, $role_id]);
                    if ($chk->fetchColumn() > 0) {
                        $error_msg = "Another role is already using the name '$role_name'.";
                    } else {
                        $stmt_up = $pdo->prepare("UPDATE user_roles SET role_name = ?, base_role = ?, description = ?, badge_style = ?, can_manage_users = ?, can_manage_tasks = ?, can_manage_projects = ?, can_manage_accomplishments = ?, can_manage_feedbacks = ?, can_view_reports = ?, can_manage_orgs = ? WHERE id = ?");
                        if ($stmt_up->execute([$role_name, $base_role, $description, $badge_style, $can_manage_users_flag, $can_manage_tasks_flag, $can_manage_projects_flag, $can_manage_acc_flag, $can_manage_fb_flag, $can_view_reports_flag, $can_manage_orgs, $role_id])) {
                            // If custom role name changed, propagate to users assigned to this role
                            if ($old_role['role_name'] !== $role_name) {
                                $upd_users = $pdo->prepare("UPDATE users SET role = ? WHERE role = ?");
                                $upd_users->execute([$role_name, $old_role['role_name']]);
                            }
                            $success_msg = "Role '$role_name' accessibility & settings updated!";
                        } else {
                            $error_msg = "Failed to update role.";
                        }
                    }
                }
            }
        } elseif ($action === 'delete_role') {
            $role_id = intval($_POST['role_id'] ?? 0);
            $stmt_old = $pdo->prepare("SELECT * FROM user_roles WHERE id = ?");
            $stmt_old->execute([$role_id]);
            $old_role = $stmt_old->fetch();

            if (!$old_role) {
                $error_msg = "Role not found.";
            } elseif (intval($old_role['is_system']) === 1) {
                $error_msg = "Built-in system roles cannot be deleted.";
            } else {
                $fallback_role = $old_role['base_role'] ?: 'Officer';
                $reassign = $pdo->prepare("UPDATE users SET role = ? WHERE role = ?");
                $reassign->execute([$fallback_role, $old_role['role_name']]);
                $affected = $reassign->rowCount();

                $del = $pdo->prepare("DELETE FROM user_roles WHERE id = ?");
                if ($del->execute([$role_id])) {
                    $extra = $affected > 0 ? " ($affected assigned account(s) were moved to '$fallback_role')." : "";
                    $success_msg = "Custom role '{$old_role['role_name']}' deleted from Role Library.$extra";
                } else {
                    $error_msg = "Failed to delete custom role.";
                }
            }
        }
    }
}

// ----------------------------------------------------
// Data Fetching & Scoping
// ----------------------------------------------------
// 0. Dynamic Role Library List & Map
$role_library = getAllUserRoles($pdo);
$role_library_map = [];
foreach ($role_library as $rItem) {
    $role_library_map[strtolower($rItem['role_name'])] = $rItem;
}

// Usage count per role
$role_usage_counts = [];
$stmt_rc = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
foreach ($stmt_rc->fetchAll() as $rcRow) {
    $role_usage_counts[strtolower($rcRow['role'])] = intval($rcRow['cnt']);
}

// 1. Organizations List (for filters & modals)
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

// 2. Leaders Query Scoping
$search_leaders = trim($_GET['q_leaders'] ?? ($_GET['q'] ?? ''));
$filter_org = $is_admin ? intval($_GET['org_id'] ?? 0) : $current_org_id;

$leader_where = ["1=1"];
$leader_params = [];

if (!$is_admin) {
    // President / Vice President / Custom Leader strictly scoped to their own organization
    $leader_where[] = "u.org_id = ?";
    $leader_params[] = $current_org_id;
} elseif ($filter_org > 0) {
    // Admin filtering by specific organization
    $leader_where[] = "u.org_id = ?";
    $leader_params[] = $filter_org;
}

if (!empty($search_leaders)) {
    $leader_where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.role LIKE ?)";
    $leader_params[] = "%$search_leaders%";
    $leader_params[] = "%$search_leaders%";
    $leader_params[] = "%$search_leaders%";
    $leader_params[] = "%$search_leaders%";
}

$leader_where_sql = implode(" AND ", $leader_where);
$sql_leaders = "SELECT u.*, o.name as org_name, o.acronym 
                FROM users u 
                JOIN organizations o ON u.org_id = o.id 
                WHERE $leader_where_sql 
                ORDER BY o.acronym ASC, u.full_name ASC";
$stmt_leaders = $pdo->prepare($sql_leaders);
$stmt_leaders->execute($leader_params);
$users = $stmt_leaders->fetchAll();

// Count badges
if ($is_admin) {
    $leader_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} else {
    $stmt_lc = $pdo->prepare("SELECT COUNT(*) FROM users WHERE org_id = ?");
    $stmt_lc->execute([$current_org_id]);
    $leader_count = $stmt_lc->fetchColumn();
}

// 3. Students Query Scoping with Status Filters
$search_students = trim($_GET['q_students'] ?? '');
$filter_student_status = trim($_GET['student_status'] ?? 'all');
$student_where = ["1=1"];
$student_params = [];

if (!empty($search_students)) {
    $student_where[] = "(s.full_name LIKE ? OR s.student_lrn LIKE ? OR s.grade_section LIKE ? OR s.email LIKE ?)";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
}

if ($filter_student_status === 'pending') {
    $student_where[] = "s.status = 'Pending'";
} elseif ($filter_student_status === 'approved') {
    $student_where[] = "s.status = 'Approved'";
} elseif ($filter_student_status === 'rejected') {
    $student_where[] = "s.status = 'Rejected'";
}

$student_where_sql = implode(" AND ", $student_where);
$sql_students = "SELECT s.*, u.full_name as approver_name FROM students s LEFT JOIN users u ON s.approved_by = u.id WHERE $student_where_sql ORDER BY CASE s.status WHEN 'Pending' THEN 1 WHEN 'Approved' THEN 2 ELSE 3 END ASC, s.created_at DESC";
$stmt_students = $pdo->prepare($sql_students);
$stmt_students->execute($student_params);
$students = $stmt_students->fetchAll();

$student_count = intval($pdo->query("SELECT COUNT(*) FROM students")->fetchColumn());
$pending_students_count = intval($pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'")->fetchColumn());
$approved_students_count = intval($pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Approved'")->fetchColumn());
$rejected_students_count = intval($pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Rejected'")->fetchColumn());

// Fetch Student Account Approval & Status Logs
$all_student_logs = [];
$student_logs_map = [];
$students_map_by_id = [];
foreach ($students as $st_item) {
    $students_map_by_id[intval($st_item['id'])] = $st_item;
}
try {
    $stmt_slogs = $pdo->query("SELECT sl.*, DATE_FORMAT(sl.created_at, '%b %d, %Y • %h:%i %p') as formatted_time FROM student_logs sl ORDER BY sl.created_at DESC, sl.id DESC");
    $all_student_logs = $stmt_slogs->fetchAll();
    foreach ($all_student_logs as $sl) {
        $sid = intval($sl['student_id'] ?? 0);
        if ($sid > 0) {
            $student_logs_map[$sid][] = $sl;
        }
    }
} catch (PDOException $e) {
    $all_student_logs = [];
    $student_logs_map = [];
}
$role_count = count($role_library);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-users-gear" style="color: var(--primary);"></i> User Management Module</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Manage leader and officer accounts across all organizations, configure verified student accounts, and customize the <strong>User Role Library & System Accessibility</strong>.
      <?php else: ?>
        Manage leader and officer accounts for <strong><?= htmlspecialchars($_SESSION['org_name']) ?> (<?= htmlspecialchars($_SESSION['org_acronym']) ?>)</strong>, and view student portal accounts.
      <?php endif; ?>
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <button class="btn btn-primary" data-modal-target="createUserModal">
      <i class="fa-solid fa-user-plus"></i> Add New Officer
    </button>
    <?php if ($is_admin): ?>
      <button class="btn btn-secondary" data-modal-target="createRoleModal" style="border-color: #c7d2fe; background: #eef2ff; color: var(--primary);">
        <i class="fa-solid fa-id-badge"></i> Add Custom Role
      </button>
      <button class="btn btn-secondary" data-modal-target="createStudentModal">
        <i class="fa-solid fa-user-graduate"></i> Add Student Account
      </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="toast toast-success alert-auto-dismiss" role="alert">
    <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="toast-body">
      <div class="toast-title">Success</div>
      <div class="toast-message"><?= htmlspecialchars($success_msg) ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="toast toast-danger alert-auto-dismiss" role="alert">
    <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="toast-body">
      <div class="toast-title">Notice</div>
      <div class="toast-message"><?= htmlspecialchars($error_msg) ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  </div>
<?php endif; ?>

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'leaders' ? 'active' : '' ?>" id="tab-btn-leaders" onclick="switchUserTab('leaders')">
    <i class="fa-solid fa-user-shield"></i>
    <span>Leaders & Officers Accounts</span>
    <span class="user-tab-badge"><?= $leader_count ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'students' ? 'active' : '' ?>" id="tab-btn-students" onclick="switchUserTab('students')">
    <i class="fa-solid fa-graduation-cap"></i>
    <span>Student Accounts</span>
    <span class="user-tab-badge"><?= $student_count ?></span>
    <?php if (!empty($pending_students_count)): ?>
      <span class="badge" style="background: #dc2626; color: #ffffff; font-size: 0.7rem; padding: 2px 7px; border-radius: 12px; margin-left: 4px; font-weight: 800; display: inline-flex; align-items: center; gap: 3px;" title="<?= $pending_students_count ?> student registration(s) pending approval">
        <i class="fa-solid fa-clock"></i> <?= $pending_students_count ?> Pending
      </span>
    <?php endif; ?>
  </button>
  <?php if ($is_admin): ?>
    <button type="button" class="user-tab-btn <?= $active_tab === 'roles' ? 'active' : '' ?>" id="tab-btn-roles" onclick="switchUserTab('roles')">
      <i class="fa-solid fa-id-badge"></i>
      <span>Role Library & Accessibility</span>
      <span class="user-tab-badge"><?= $role_count ?></span>
    </button>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- TAB 1: LEADERS & OFFICERS ACCOUNTS PANE                  -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'leaders' ? 'active' : '' ?>" id="tab-leaders">

  <!-- Leaders Filter & Search Bar -->
  <div class="filter-bar">
    <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="leaders">

      <div style="flex: 2; min-width: 240px; position: relative;">
        <input type="text" name="q_leaders" class="form-control" placeholder="Search officers by name, username, or email..." value="<?= htmlspecialchars($search_leaders) ?>" style="padding-left: 2.25rem;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>

      <?php if ($is_admin): ?>
        <div style="flex: 1; min-width: 220px;">
          <select name="org_id" class="form-control" onchange="this.form.submit()">
            <option value="0">All Organizations</option>
            <?php foreach ($orgs as $org): ?>
              <option value="<?= $org['id'] ?>" <?= $filter_org == $org['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <div style="padding: 0.5rem 0.75rem; background: var(--gray-100); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--gray-700); font-weight: 600;">
          <i class="fa-solid fa-shield-halved" style="color: var(--accent);"></i> Org: <?= htmlspecialchars($_SESSION['org_acronym']) ?>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
      
      <?php if (!empty($search_leaders) || ($is_admin && $filter_org > 0)): ?>
        <a href="users.php?tab=leaders" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Leaders Table -->
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Username</th>
          <th>Email Address</th>
          <th>Organization</th>
          <th>Role</th>
          <th>Date Created</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
              <i class="fa-solid fa-user-xmark" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
              No officer or leader accounts found matching your criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $user): 
            $is_self = ($user['id'] === $current_user_id);
            $rKey = strtolower($user['role'] ?? 'officer');
            $rInfo = $role_library_map[$rKey] ?? null;
            $role_badge_class = $rInfo['badge_style'] ?? 'badge-role-officer';
            $base_equiv = $rInfo['base_role'] ?? $user['role'];
            $is_custom_role = ($rInfo && strcasecmp($rInfo['role_name'], $rInfo['base_role']) !== 0);
            $is_target_admin = ($user['role'] === 'Admin' || strcasecmp($base_equiv, 'Admin') === 0);
          ?>
            <tr style="<?= $is_self ? 'background-color: #f0f9ff;' : '' ?>">
              <td>
                <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($user['full_name']) ?></strong>
                <?php if ($is_self): ?>
                  <span class="badge badge-in-progress" style="font-size: 0.65rem; margin-left: 0.25rem;">YOU</span>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($user['username']) ?></code></td>
              <td><a href="mailto:<?= htmlspecialchars($user['email']) ?>" style="font-size: 0.85rem; color: var(--primary);"><?= htmlspecialchars($user['email']) ?></a></td>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($user['acronym']) ?></strong></span></td>
              <td>
                <span class="badge <?= htmlspecialchars($role_badge_class) ?>"><?= htmlspecialchars($user['role']) ?></span>
                <?php if ($is_custom_role): ?>
                  <div style="font-size: 0.7rem; color: var(--gray-500); margin-top: 0.18rem; font-weight: 600;">
                    <i class="fa-solid fa-key" style="font-size: 0.65rem; color: var(--primary);"></i> Access: like <?= htmlspecialchars($base_equiv) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><span style="font-size: 0.8rem; color: var(--gray-600);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($user['created_at'])) ?></span></td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                  <button class="btn btn-secondary btn-sm" onclick='openUserEditModal(<?= json_encode($user) ?>)' title="Edit Officer">
                    <i class="fa-solid fa-user-pen"></i> Edit
                  </button>
                  <?php if (!$is_self && ($is_admin || !$is_target_admin)): ?>
                    <form method="POST" action="users.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete officer account \'<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>\'?', 'Delete Officer Account?');" style="display: inline;">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete User"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 2: STUDENT ACCOUNTS PANE                             -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'students' ? 'active' : '' ?>" id="tab-students">

  <!-- Students Filter & Status Quick Tabs -->
  <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.85rem; padding: 0.6rem 0.85rem; background: #ffffff; border: 1px solid var(--gray-200); border-radius: var(--radius-md); box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <span style="font-size: 0.82rem; font-weight: 700; color: var(--gray-600); margin-right: 0.25rem;"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Status Filter:</span>
    
    <a href="users.php?tab=students&student_status=all<?= !empty($search_students) ? '&q_students=' . urlencode($search_students) : '' ?>" class="btn btn-sm <?= $filter_student_status === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
      All (<?= $student_count ?>)
    </a>
    
    <a href="users.php?tab=students&student_status=pending<?= !empty($search_students) ? '&q_students=' . urlencode($search_students) : '' ?>" class="btn btn-sm <?= $filter_student_status === 'pending' ? 'btn-warning' : 'btn-secondary' ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; <?= $pending_students_count > 0 && $filter_student_status !== 'pending' ? 'border-color: #f59e0b; color: #b45309; background: #fffbeb;' : '' ?>">
      <i class="fa-solid fa-clock"></i> Pending Approval (<?= $pending_students_count ?>)
    </a>
    
    <a href="users.php?tab=students&student_status=approved<?= !empty($search_students) ? '&q_students=' . urlencode($search_students) : '' ?>" class="btn btn-sm <?= $filter_student_status === 'approved' ? 'btn-success' : 'btn-secondary' ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
      <i class="fa-solid fa-circle-check"></i> Approved (<?= $approved_students_count ?>)
    </a>
    
    <a href="users.php?tab=students&student_status=rejected<?= !empty($search_students) ? '&q_students=' . urlencode($search_students) : '' ?>" class="btn btn-sm <?= $filter_student_status === 'rejected' ? 'btn-danger' : 'btn-secondary' ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
      <i class="fa-solid fa-circle-xmark"></i> Rejected (<?= $rejected_students_count ?>)
    </a>
  </div>

  <!-- Students Filter & Search Bar -->
  <div class="filter-bar">
    <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="students">
      <input type="hidden" name="student_status" value="<?= htmlspecialchars($filter_student_status) ?>">

      <div style="flex: 2; min-width: 260px; position: relative;">
        <input type="text" name="q_students" class="form-control" placeholder="Search students by name, LRN, grade/section, or email..." value="<?= htmlspecialchars($search_students) ?>" style="padding-left: 2.25rem;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
      
      <?php if (!empty($search_students) || $filter_student_status !== 'all'): ?>
        <a href="users.php?tab=students" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Students Table -->
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Student LRN</th>
          <th>Grade & Section</th>
          <th>Email Address</th>
          <th>Account Status</th>
          <th>Date Registered</th>
          <th style="text-align: right;">Actions & Logs</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="<?= $is_admin ? '7' : '6' ?>" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
              <i class="fa-solid fa-graduation-cap" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
              No student accounts found matching your filter criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $student): ?>
            <?php $stu_status = $student['status'] ?? 'Approved'; ?>
            <tr style="<?= $stu_status === 'Pending' ? 'background: rgba(254, 243, 199, 0.25);' : '' ?>">
              <td>
                <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($student['full_name']) ?></strong>
              </td>
              <td><code><?= htmlspecialchars($student['student_lrn']) ?></code></td>
              <td><span class="badge badge-planning"><i class="fa-solid fa-school"></i> <?= htmlspecialchars($student['grade_section']) ?></span></td>
              <td>
                <?php if (!empty($student['email'])): ?>
                  <a href="mailto:<?= htmlspecialchars($student['email']) ?>" style="font-size: 0.85rem; color: var(--primary);"><?= htmlspecialchars($student['email']) ?></a>
                <?php else: ?>
                  <span style="color: var(--gray-400); font-size: 0.85rem;">None</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($stu_status === 'Pending'): ?>
                  <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; font-weight: 700; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-clock"></i> Pending Approval
                  </span>
                <?php elseif ($stu_status === 'Approved'): ?>
                  <span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #22c55e; font-weight: 700; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;" title="Approved<?= !empty($student['approved_at']) ? ' on ' . date('M d, Y', strtotime($student['approved_at'])) : '' ?><?= !empty($student['approver_name']) ? ' by ' . htmlspecialchars($student['approver_name']) : '' ?>">
                    <i class="fa-solid fa-circle-check"></i> Approved
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; font-weight: 700; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-circle-xmark"></i> Rejected
                  </span>
                <?php endif; ?>
              </td>
              <td><span style="font-size: 0.8rem; color: var(--gray-600);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($student['created_at'])) ?></span></td>
              <td style="text-align: right;">
                <?php $stu_log_count = isset($student_logs_map[$student['id']]) ? count($student_logs_map[$student['id']]) : 0; ?>
                <div style="display: inline-flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick="openStudentLogsModal(<?= intval($student['id']) ?>)" title="View Status & Approval History Logs">
                    <i class="fa-solid fa-clock-rotate-left"></i> Logs (<?= $stu_log_count ?>)
                  </button>
                  <?php if ($is_admin): ?>
                    <?php if ($stu_status === 'Pending'): ?>
                      <form method="POST" action="users.php" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="approve_student">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background: #16a34a; color: white; border: none; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px; box-shadow: 0 1px 3px rgba(22, 163, 74, 0.3);" title="Approve student account">
                          <i class="fa-solid fa-check"></i> Approve
                        </button>
                      </form>
                      <form method="POST" action="users.php" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="reject_student">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; font-weight: 600; padding: 0.35rem 0.65rem; border-radius: 6px;" title="Reject student account">
                          <i class="fa-solid fa-ban"></i> Reject
                        </button>
                      </form>
                    <?php elseif ($stu_status === 'Approved'): ?>
                      <form method="POST" action="users.php" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="reject_student">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #b91c1c; padding: 0.35rem 0.6rem;" title="Revoke Approval">
                          <i class="fa-solid fa-ban"></i> Revoke
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" action="users.php" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="approve_student">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background: #16a34a; color: white; border: none; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;" title="Re-activate / Approve Student">
                          <i class="fa-solid fa-check"></i> Approve
                        </button>
                      </form>
                    <?php endif; ?>

                    <button class="btn btn-secondary btn-sm" onclick='openStudentEditModal(<?= json_encode($student) ?>)' title="Edit Student">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <form method="POST" action="users.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete student account \'<?= htmlspecialchars($student['full_name'], ENT_QUOTES) ?>\' (LRN: <?= htmlspecialchars($student['student_lrn'], ENT_QUOTES) ?>)?', 'Delete Student Account?');" style="display: inline; margin: 0;">
                      <input type="hidden" name="action" value="delete_student">
                      <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Student"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Student Account Approval & Status Activity Logs Panel -->
  <div class="panel-card" style="margin-top: 2rem;">
    <div class="panel-header">
      <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Student Account Approval & Status Activity Logs</span>
      <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($all_student_logs) ?> Total Entries</span>
    </div>

    <?php if (empty($all_student_logs)): ?>
      <div style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 2.2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No student account status logs recorded yet.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <th>Student Account</th>
              <th>Action</th>
              <th>Status Transition</th>
              <th>Logged By</th>
              <th>Activity & Change Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_student_logs as $slog):
              $act_lower = strtolower($slog['action_type'] ?? '');
              if (strpos($act_lower, 'approv') !== false) {
                  $slog_badge = 'badge-accomplished';
              } elseif (strpos($act_lower, 'reject') !== false || strpos($act_lower, 'revok') !== false || strpos($act_lower, 'delet') !== false) {
                  $slog_badge = 'badge-overdue';
              } elseif (strpos($act_lower, 'pend') !== false || strpos($act_lower, 'regist') !== false) {
                  $slog_badge = 'badge-todo';
              } else {
                  $slog_badge = 'badge-in-progress';
              }
            ?>
              <tr>
                <td>
                  <strong style="color: var(--dark); display: block;"><?= htmlspecialchars($slog['student_name']) ?></strong>
                  <code style="font-size: 0.78rem;"><?= htmlspecialchars($slog['student_lrn']) ?></code>
                </td>
                <td>
                  <span class="badge <?= $slog_badge ?>"><?= htmlspecialchars($slog['action_type']) ?></span>
                </td>
                <td style="white-space: nowrap; font-size: 0.82rem;">
                  <?php if (!empty($slog['previous_status'])): ?>
                    <span class="badge badge-planning" style="font-size: 0.72rem;"><?= htmlspecialchars($slog['previous_status']) ?></span>
                    <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem; color: var(--gray-400); margin: 0 0.25rem;"></i>
                  <?php endif; ?>
                  <span class="badge <?= $slog['new_status'] === 'Approved' ? 'badge-accomplished' : ($slog['new_status'] === 'Pending' ? 'badge-todo' : 'badge-overdue') ?>" style="font-size: 0.72rem;">
                    <?= htmlspecialchars($slog['new_status']) ?>
                  </span>
                </td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($slog['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($slog['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($slog['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($slog['formatted_time']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_admin): ?>
<!-- ======================================================== -->
<!-- TAB 3: ROLE LIBRARY & SYSTEM ACCESSIBILITY PANE          -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'roles' ? 'active' : '' ?>" id="tab-roles">

  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Role Title</th>
          <th>Role Equivalent (Access Template)</th>
          <th>System Accessibility & Permitted Modules</th>
          <th>Description</th>
          <th>Assigned Users</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($role_library as $rRow):
          $is_sys_role = intval($rRow['is_system']) === 1;
          $used_cnt = $role_usage_counts[strtolower($rRow['role_name'])] ?? 0;
        ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span class="badge <?= htmlspecialchars($rRow['badge_style']) ?>" style="font-size: 0.8rem;"><?= htmlspecialchars($rRow['role_name']) ?></span>
                <?php if ($is_sys_role): ?>
                  <span style="font-size: 0.68rem; background: var(--gray-100); color: var(--gray-600); padding: 0.15rem 0.45rem; border-radius: 999px; font-weight: 700;">System Default</span>
                <?php else: ?>
                  <span style="font-size: 0.68rem; background: #eef2ff; color: var(--primary); padding: 0.15rem 0.45rem; border-radius: 999px; font-weight: 700;">Custom Role</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <span style="font-size: 0.84rem; font-weight: 700; color: var(--dark);">
                <i class="fa-solid fa-user-check" style="color: var(--primary); margin-right: 0.25rem;"></i>
                Like <?= htmlspecialchars($rRow['base_role']) ?>
              </span>
            </td>
            <td>
              <div style="display: flex; flex-wrap: wrap; gap: 0.3rem; max-width: 380px;">
                <span class="badge badge-planning" style="font-size: 0.68rem;"><i class="fa-solid fa-chart-line"></i> Dashboard</span>
                <?php if (!empty($rRow['can_manage_orgs'])): ?>
                  <span class="badge badge-role-admin" style="font-size: 0.68rem;"><i class="fa-solid fa-sitemap"></i> All Orgs & Admin</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_manage_users'])): ?>
                  <span class="badge badge-role-president" style="font-size: 0.68rem;"><i class="fa-solid fa-users-gear"></i> Manage Users</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_manage_tasks'])): ?>
                  <span class="badge badge-in-progress" style="font-size: 0.68rem;"><i class="fa-solid fa-list-check"></i> Create/Assign Tasks</span>
                <?php else: ?>
                  <span class="badge badge-todo" style="font-size: 0.68rem;"><i class="fa-solid fa-list-check"></i> Assigned Tasks Only</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_manage_projects'])): ?>
                  <span class="badge badge-accomplished" style="font-size: 0.68rem;"><i class="fa-solid fa-diagram-project"></i> Projects</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_manage_accomplishments'])): ?>
                  <span class="badge badge-accomplished" style="font-size: 0.68rem;"><i class="fa-solid fa-trophy"></i> Accomplishments</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_manage_feedbacks'])): ?>
                  <span class="badge badge-accomplished" style="font-size: 0.68rem;"><i class="fa-solid fa-comments"></i> Feedbacks</span>
                <?php endif; ?>
                <?php if (!empty($rRow['can_view_reports'])): ?>
                  <span class="badge badge-accomplished" style="font-size: 0.68rem;"><i class="fa-solid fa-chart-pie"></i> Reports</span>
                <?php endif; ?>
              </div>
            </td>
            <td style="max-width: 240px; font-size: 0.82rem; color: var(--gray-600);">
              <?= htmlspecialchars($rRow['description'] ?: '—') ?>
            </td>
            <td>
              <span class="badge badge-todo" style="font-weight: 700;"><?= $used_cnt ?> account<?= $used_cnt === 1 ? '' : 's' ?></span>
            </td>
            <td style="text-align: right;">
              <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary btn-sm" onclick='openRoleEditModal(<?= json_encode($rRow, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Role & Accessibility">
                  <i class="fa-solid fa-sliders"></i> Edit Access
                </button>
                <?php if (!$is_sys_role): ?>
                  <form method="POST" action="users.php" onsubmit="return confirmDeleteSwal(event, 'Delete custom role \'<?= htmlspecialchars($rRow['role_name'], ENT_QUOTES) ?>\'? Any users assigned to it will be reassigned to <?= htmlspecialchars($rRow['base_role'], ENT_QUOTES) ?>.', 'Delete Custom Role?');" style="display: inline;">
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="<?= $rRow['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Custom Role"><i class="fa-solid fa-trash"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: ADD NEW OFFICER / USER                           -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="createUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add New Officer Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="create_user">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span style="color: red;">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Username <span style="color: red;">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="e.g. juan_sslg" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span style="color: red;">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="juan@school.edu.ph" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <?php if ($is_admin): ?>
              <select name="org_id" class="form-control" required>
                <option value="">-- Select Org --</option>
                <?php foreach ($orgs as $org): ?>
                  <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['org_acronym'] . ' - ' . $_SESSION['org_name']) ?>" disabled style="background: var(--gray-100);">
            <?php endif; ?>
          </div>

          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="form-label" style="margin-bottom: 0.35rem;">Role <span style="color: red;">*</span></label>
              <?php if ($is_admin): ?>
                <button type="button" onclick="document.getElementById('createUserModal').classList.remove('active'); document.getElementById('createRoleModal').classList.add('active');" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; font-weight: 700; cursor: pointer; padding: 0;">
                  <i class="fa-solid fa-plus"></i> New Role
                </button>
              <?php endif; ?>
            </div>
            <select name="role" class="form-control" required>
              <?php foreach ($role_library as $rOpt):
                $opt_is_admin = ($rOpt['role_name'] === 'Admin' || $rOpt['base_role'] === 'Admin' || !empty($rOpt['can_manage_orgs']));
                if (!$is_admin && $opt_is_admin) continue;
                $opt_label = $rOpt['role_name'];
                if (strcasecmp($rOpt['role_name'], $rOpt['base_role']) !== 0) {
                    $opt_label .= ' (like ' . $rOpt['base_role'] . ')';
                }
              ?>
                <option value="<?= htmlspecialchars($rOpt['role_name']) ?>" <?= $rOpt['role_name'] === 'Officer' ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt_label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password <span style="color: red;">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT OFFICER / USER                               -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="editUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Edit Officer Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span style="color: red;">*</span></label>
          <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Username <span style="color: red;">*</span></label>
            <input type="text" name="username" id="edit_username" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span style="color: red;">*</span></label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <?php if ($is_admin): ?>
              <select name="org_id" id="edit_org_id" class="form-control" required>
                <?php foreach ($orgs as $org): ?>
                  <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['org_acronym'] . ' - ' . $_SESSION['org_name']) ?>" disabled style="background: var(--gray-100);">
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span style="color: red;">*</span></label>
            <select name="role" id="edit_role" class="form-control" required>
              <?php foreach ($role_library as $rOpt):
                $opt_is_admin = ($rOpt['role_name'] === 'Admin' || $rOpt['base_role'] === 'Admin' || !empty($rOpt['can_manage_orgs']));
                if (!$is_admin && $opt_is_admin) continue;
                $opt_label = $rOpt['role_name'];
                if (strcasecmp($rOpt['role_name'], $rOpt['base_role']) !== 0) {
                    $opt_label .= ' (like ' . $rOpt['base_role'] . ')';
                }
              ?>
                <option value="<?= htmlspecialchars($rOpt['role_name']) ?>">
                  <?= htmlspecialchars($opt_label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">New Password (Leave blank to keep existing password)</label>
          <input type="password" name="new_password" class="form-control" placeholder="••••••••">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php if ($is_admin): ?>
<!-- ======================================================== -->
<!-- MODAL: ADD CUSTOM ROLE TO ROLE LIBRARY                   -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="createRoleModal">
  <div class="modal-card" style="max-width: 600px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-id-badge" style="color: var(--primary);"></i> Add Custom Role to Library</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="create_role">

      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Custom Role Title <span style="color: red;">*</span></label>
            <input type="text" name="role_name" class="form-control" placeholder="e.g. CEO, Auditor, P.I.O." required>
          </div>

          <div class="form-group">
            <label class="form-label">Role Equivalent (Like...) <span style="color: red;">*</span></label>
            <select name="base_role" id="create_base_role" class="form-control" onchange="applyBaseRoleTemplate('create', this.value)" required>
              <option value="President" selected>President (Executive Leader)</option>
              <option value="Vice President">Vice President (Co-Executive)</option>
              <option value="Secretary">Secretary (Officer Access)</option>
              <option value="Treasurer">Treasurer (Officer Access)</option>
              <option value="Officer">Officer (Standard Access)</option>
              <option value="Admin">Admin (System-wide Access)</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Color Style</label>
            <select name="badge_style" id="create_badge_style" class="form-control">
              <option value="badge-role-president" selected>Crimson Red (President Style)</option>
              <option value="badge-role-vp">Sky Blue (Vice President Style)</option>
              <option value="badge-role-secretary">Emerald Green (Secretary Style)</option>
              <option value="badge-role-treasurer">Amber Gold (Treasurer Style)</option>
              <option value="badge-role-officer">Slate Gray (Officer Style)</option>
              <option value="badge-role-admin">Royal Purple (Admin Style)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Description (Optional)</label>
            <input type="text" name="description" class="form-control" placeholder="e.g. Chief Executive Officer with President access">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="margin-bottom: 0.5rem;"><i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> System Accessibility & Module Permissions</label>
          <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.85rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_users" id="create_perm_users" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>User Management</strong><br><small style="color: var(--gray-500);">Add/Edit Organization Officers</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_tasks" id="create_perm_tasks" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>Task Organizer & Assign</strong><br><small style="color: var(--gray-500);">Create, Edit & Assign Tasks</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_projects" id="create_perm_projects" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>Programs & Projects</strong><br><small style="color: var(--gray-500);">Create & Update Projects</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_accomplishments" id="create_perm_acc" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>Accomplishments</strong><br><small style="color: var(--gray-500);">Publish & Edit Posts</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_feedbacks" id="create_perm_fb" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>Student Feedbacks</strong><br><small style="color: var(--gray-500);">View & Reply to Concerns</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_view_reports" id="create_perm_reports" value="1" checked style="margin-top: 0.2rem;">
              <span><strong>Performance Reports</strong><br><small style="color: var(--gray-500);">View Reports & Audit Logs</small></span>
            </label>
            <label style="grid-column: 1 / -1; display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer; padding-top: 0.4rem; border-top: 1px dashed var(--gray-200);">
              <input type="checkbox" name="can_manage_orgs" id="create_perm_orgs" value="1" style="margin-top: 0.2rem;">
              <span><strong>System-wide Administrator Access</strong><br><small style="color: var(--gray-500);">Manage All Organizations, Student Accounts & Role Library</small></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Role to Library</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT ROLE & SYSTEM ACCESSIBILITY                  -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="editRoleModal">
  <div class="modal-card" style="max-width: 600px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-sliders" style="color: var(--primary);"></i> Edit Role & System Accessibility</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="role_id" id="edit_role_id">

      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Role Title <span style="color: red;">*</span></label>
            <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Role Equivalent (Like...) <span style="color: red;">*</span></label>
            <select name="base_role" id="edit_base_role" class="form-control" onchange="applyBaseRoleTemplate('edit', this.value)" required>
              <option value="President">President (Executive Leader)</option>
              <option value="Vice President">Vice President (Co-Executive)</option>
              <option value="Secretary">Secretary (Officer Access)</option>
              <option value="Treasurer">Treasurer (Officer Access)</option>
              <option value="Officer">Officer (Standard Access)</option>
              <option value="Admin">Admin (System-wide Access)</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Color Style</label>
            <select name="badge_style" id="edit_badge_style" class="form-control">
              <option value="badge-role-president">Crimson Red (President Style)</option>
              <option value="badge-role-vp">Sky Blue (Vice President Style)</option>
              <option value="badge-role-secretary">Emerald Green (Secretary Style)</option>
              <option value="badge-role-treasurer">Amber Gold (Treasurer Style)</option>
              <option value="badge-role-officer">Slate Gray (Officer Style)</option>
              <option value="badge-role-admin">Royal Purple (Admin Style)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <input type="text" name="description" id="edit_role_description" class="form-control">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" style="margin-bottom: 0.5rem;"><i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> System Accessibility & Module Permissions</label>
          <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.85rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_users" id="edit_perm_users" value="1" style="margin-top: 0.2rem;">
              <span><strong>User Management</strong><br><small style="color: var(--gray-500);">Add/Edit Organization Officers</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_tasks" id="edit_perm_tasks" value="1" style="margin-top: 0.2rem;">
              <span><strong>Task Organizer & Assign</strong><br><small style="color: var(--gray-500);">Create, Edit & Assign Tasks</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_projects" id="edit_perm_projects" value="1" style="margin-top: 0.2rem;">
              <span><strong>Programs & Projects</strong><br><small style="color: var(--gray-500);">Create & Update Projects</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_accomplishments" id="edit_perm_acc" value="1" style="margin-top: 0.2rem;">
              <span><strong>Accomplishments</strong><br><small style="color: var(--gray-500);">Publish & Edit Posts</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_manage_feedbacks" id="edit_perm_fb" value="1" style="margin-top: 0.2rem;">
              <span><strong>Student Feedbacks</strong><br><small style="color: var(--gray-500);">View & Reply to Concerns</small></span>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer;">
              <input type="checkbox" name="can_view_reports" id="edit_perm_reports" value="1" style="margin-top: 0.2rem;">
              <span><strong>Performance Reports</strong><br><small style="color: var(--gray-500);">View Reports & Audit Logs</small></span>
            </label>
            <label style="grid-column: 1 / -1; display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; cursor: pointer; padding-top: 0.4rem; border-top: 1px dashed var(--gray-200);">
              <input type="checkbox" name="can_manage_orgs" id="edit_perm_orgs" value="1" style="margin-top: 0.2rem;">
              <span><strong>System-wide Administrator Access</strong><br><small style="color: var(--gray-500);">Manage All Organizations, Student Accounts & Role Library</small></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Update Role & Access</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD NEW STUDENT ACCOUNT                           -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="createStudentModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-graduate" style="color: var(--primary);"></i> Add Student Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="create_student">
      
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Student LRN <span style="color: red;">*</span></label>
            <input type="text" name="student_lrn" class="form-control" placeholder="e.g. 2026-0004" required>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name <span style="color: red;">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Clara" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Grade & Section <span style="color: red;">*</span></label>
            <input type="text" name="grade_section" class="form-control" placeholder="e.g. Grade 11 - STEM" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address (Optional)</label>
            <input type="email" name="email" class="form-control" placeholder="maria@student.edu.ph">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Password <span style="color: red;">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min 8 chars" required>
          </div>

          <div class="form-group">
            <label class="form-label">Account Approval Status <span style="color: red;">*</span></label>
            <select name="status" class="form-control" required>
              <option value="Approved" selected>Approved (Allowed to sign in)</option>
              <option value="Pending">Pending (Awaiting approval)</option>
              <option value="Rejected">Rejected (Login blocked)</option>
            </select>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Student Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT STUDENT ACCOUNT                             -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="editStudentModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Student Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="student_id" id="edit_student_id">
      
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Student LRN <span style="color: red;">*</span></label>
            <input type="text" name="student_lrn" id="edit_student_lrn" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name <span style="color: red;">*</span></label>
            <input type="text" name="full_name" id="edit_student_fullname" class="form-control" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Grade & Section <span style="color: red;">*</span></label>
            <input type="text" name="grade_section" id="edit_student_grade" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address (Optional)</label>
            <input type="email" name="email" id="edit_student_email" class="form-control">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">New Password (Leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 8 chars if changing">
          </div>

          <div class="form-group">
            <label class="form-label">Account Approval Status <span style="color: red;">*</span></label>
            <select name="status" id="edit_student_status" class="form-control" required>
              <option value="Approved">Approved (Allowed to sign in)</option>
              <option value="Pending">Pending (Awaiting approval)</option>
              <option value="Rejected">Rejected (Login blocked)</option>
            </select>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: VIEW STUDENT ACCOUNT STATUS & APPROVAL LOGS       -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="viewStudentLogsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Student Status & Approval Logs
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: var(--gray-100); padding: 0.85rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; border: 1px solid var(--gray-200);">
        <div style="font-size: 0.78rem; color: var(--gray-500); text-transform: uppercase; font-weight: 700;">Student Account</div>
        <div id="log_modal_student_name" style="font-size: 1.05rem; font-weight: 800; color: var(--dark); margin-top: 0.1rem;"></div>
        <div id="log_modal_student_meta" style="font-size: 0.82rem; color: var(--gray-600); margin-top: 0.25rem;"></div>
      </div>

      <div id="studentLogsTimelineContainer" class="task-log-timeline"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
// Switch between Leaders, Students, and Role Library tabs
function switchUserTab(tabName) {
  var btns = document.querySelectorAll('.user-tab-btn');
  var panes = document.querySelectorAll('.user-tab-pane');
  
  btns.forEach(function(btn) { btn.classList.remove('active'); });
  panes.forEach(function(pane) { pane.classList.remove('active'); });

  if (tabName === 'students') {
    var btnSt = document.getElementById('tab-btn-students');
    if (btnSt) btnSt.classList.add('active');
    var stPane = document.getElementById('tab-students');
    if (stPane) stPane.classList.add('active');
    history.replaceState(null, '', 'users.php?tab=students');
  } else if (tabName === 'roles') {
    var btnRl = document.getElementById('tab-btn-roles');
    if (btnRl) btnRl.classList.add('active');
    var rlPane = document.getElementById('tab-roles');
    if (rlPane) rlPane.classList.add('active');
    history.replaceState(null, '', 'users.php?tab=roles');
  } else {
    var btnLd = document.getElementById('tab-btn-leaders');
    if (btnLd) btnLd.classList.add('active');
    var ldPane = document.getElementById('tab-leaders');
    if (ldPane) ldPane.classList.add('active');
    history.replaceState(null, '', 'users.php?tab=leaders');
  }
}

// Open Officer Edit Modal
function openUserEditModal(user) {
  document.getElementById('edit_user_id').value = user.id;
  document.getElementById('edit_full_name').value = user.full_name;
  document.getElementById('edit_username').value = user.username;
  document.getElementById('edit_email').value = user.email;
  var orgField = document.getElementById('edit_org_id');
  if (orgField) {
    orgField.value = user.org_id;
  }
  var roleSelect = document.getElementById('edit_role');
  if (roleSelect) {
    // Ensure option exists if legacy custom role
    var exists = false;
    for (var i = 0; i < roleSelect.options.length; i++) {
      if (roleSelect.options[i].value === user.role) {
        exists = true;
        break;
      }
    }
    if (!exists && user.role) {
      var opt = document.createElement('option');
      opt.value = user.role;
      opt.textContent = user.role;
      roleSelect.appendChild(opt);
    }
    roleSelect.value = user.role;
  }
  
  document.getElementById('editUserModal').classList.add('active');
}

<?php if ($is_admin): ?>
// Auto-fill accessibility checkboxes & badge style when selecting a Base Role Template ("Like President", etc.)
function applyBaseRoleTemplate(prefix, baseRole) {
  var isAdmin = (baseRole === 'Admin');
  var isExec = (baseRole === 'President' || baseRole === 'Vice President' || isAdmin);

  var badgeMap = {
    'Admin': 'badge-role-admin',
    'President': 'badge-role-president',
    'Vice President': 'badge-role-vp',
    'Secretary': 'badge-role-secretary',
    'Treasurer': 'badge-role-treasurer',
    'Officer': 'badge-role-officer'
  };

  var badgeSel = document.getElementById(prefix + '_badge_style');
  if (badgeSel && badgeMap[baseRole]) {
    badgeSel.value = badgeMap[baseRole];
  }

  var chkUsers = document.getElementById(prefix + '_perm_users');
  var chkTasks = document.getElementById(prefix + '_perm_tasks');
  var chkProj = document.getElementById(prefix + '_perm_projects');
  var chkAcc = document.getElementById(prefix + '_perm_acc');
  var chkFb = document.getElementById(prefix + '_perm_fb');
  var chkRep = document.getElementById(prefix + '_perm_reports');
  var chkOrgs = document.getElementById(prefix + '_perm_orgs');

  if (chkUsers) chkUsers.checked = isExec;
  if (chkTasks) chkTasks.checked = isExec;
  if (chkProj) chkProj.checked = true;
  if (chkAcc) chkAcc.checked = true;
  if (chkFb) chkFb.checked = true;
  if (chkRep) chkRep.checked = true;
  if (chkOrgs) chkOrgs.checked = isAdmin;
}

// Open Role Edit Modal
function openRoleEditModal(role) {
  document.getElementById('edit_role_id').value = role.id;
  var nameInput = document.getElementById('edit_role_name');
  var baseSelect = document.getElementById('edit_base_role');
  nameInput.value = role.role_name;
  baseSelect.value = role.base_role || 'Officer';

  var isSystem = parseInt(role.is_system, 10) === 1;
  nameInput.readOnly = isSystem;
  nameInput.style.backgroundColor = isSystem ? 'var(--gray-100)' : '';

  document.getElementById('edit_badge_style').value = role.badge_style || 'badge-role-officer';
  document.getElementById('edit_role_description').value = role.description || '';

  document.getElementById('edit_perm_users').checked = parseInt(role.can_manage_users, 10) === 1;
  document.getElementById('edit_perm_tasks').checked = parseInt(role.can_manage_tasks, 10) === 1;
  document.getElementById('edit_perm_projects').checked = parseInt(role.can_manage_projects, 10) === 1;
  document.getElementById('edit_perm_acc').checked = parseInt(role.can_manage_accomplishments, 10) === 1;
  document.getElementById('edit_perm_fb').checked = parseInt(role.can_manage_feedbacks, 10) === 1;
  document.getElementById('edit_perm_reports').checked = parseInt(role.can_view_reports, 10) === 1;
  document.getElementById('edit_perm_orgs').checked = parseInt(role.can_manage_orgs, 10) === 1;

  document.getElementById('editRoleModal').classList.add('active');
}

// Open Student Edit Modal
function openStudentEditModal(student) {
  document.getElementById('edit_student_id').value = student.id;
  document.getElementById('edit_student_lrn').value = student.student_lrn;
  document.getElementById('edit_student_fullname').value = student.full_name;
  document.getElementById('edit_student_grade').value = student.grade_section;
  document.getElementById('edit_student_email').value = student.email || '';
  if (document.getElementById('edit_student_status')) {
    document.getElementById('edit_student_status').value = student.status || 'Approved';
  }
  
  document.getElementById('editStudentModal').classList.add('active');
}
<?php endif; ?>

const STUDENT_LOGS_MAP = <?= json_encode($student_logs_map) ?>;
const STUDENTS_BY_ID = <?= json_encode($students_map_by_id) ?>;

function escapeStudentLogHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function toggleStudentStepperLogs(btn) {
  const wrap = btn.closest('.log-stepper-container');
  if (!wrap) return;
  const stepper = wrap.querySelector('.log-stepper');
  const limit = parseInt(stepper.getAttribute('data-initial-limit') || '2', 10);
  const steps = stepper.querySelectorAll('.log-step');
  const expanded = btn.getAttribute('data-expanded') === 'true';

  if (!expanded) {
    steps.forEach(s => {
      s.classList.remove('log-step-hidden');
      s.classList.remove('log-step-last-visible');
    });
    btn.setAttribute('data-expanded', 'true');
    btn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Show Less';
  } else {
    steps.forEach((s, idx) => {
      if (idx >= limit) {
        s.classList.add('log-step-hidden');
      }
      if (idx === limit - 1 && steps.length > limit) {
        s.classList.add('log-step-last-visible');
      }
    });
    btn.setAttribute('data-expanded', 'false');
    const hiddenCount = steps.length - limit;
    btn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> Show More (' + hiddenCount + ' more ' + (hiddenCount === 1 ? 'log' : 'logs') + ')';
  }
}

function openStudentLogsModal(studentId) {
  const modal = document.getElementById('viewStudentLogsModal');
  const titleEl = document.getElementById('log_modal_student_name');
  const metaEl = document.getElementById('log_modal_student_meta');
  const container = document.getElementById('studentLogsTimelineContainer');
  if (!modal || !container) return;

  const stu = STUDENTS_BY_ID[studentId];
  if (stu) {
    titleEl.textContent = stu.full_name + ' (LRN: ' + stu.student_lrn + ')';
    metaEl.textContent = 'Grade & Section: ' + (stu.grade_section || 'N/A') + ' • Current Status: ' + (stu.status || 'Approved');
  } else {
    titleEl.textContent = 'Student Account #' + studentId;
    metaEl.textContent = '';
  }

  const logs = STUDENT_LOGS_MAP[studentId] || [];
  if (logs.length === 0) {
    container.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: var(--gray-500);">No status or approval logs recorded for this student account yet.</div>';
  } else {
    const initialLimit = logs.length === 2 ? 1 : 2;
    const totalLogs = logs.length;
    const hiddenCount = Math.max(0, totalLogs - initialLimit);

    let html = '<div class="log-stepper-container"><div class="log-stepper" data-initial-limit="' + initialLimit + '">';
    logs.forEach((log, index) => {
      const actLower = String(log.action_type || '').toLowerCase();
      let stepClass = 'step-info';
      let badgeClass = 'badge-in-progress';
      let iconClass = 'fa-arrows-rotate';

      if (actLower.indexOf('approv') !== -1) {
        stepClass = 'step-success';
        badgeClass = 'badge-accomplished';
        iconClass = 'fa-check';
      } else if (actLower.indexOf('reject') !== -1 || actLower.indexOf('revok') !== -1) {
        stepClass = 'step-danger';
        badgeClass = 'badge-overdue';
        iconClass = 'fa-ban';
      } else if (actLower.indexOf('regist') !== -1 || actLower.indexOf('creat') !== -1) {
        stepClass = 'step-info';
        badgeClass = 'badge-todo';
        iconClass = 'fa-user-plus';
      }

      const hiddenClass = index >= initialLimit ? 'log-step-hidden' : '';
      const lastVisibleClass = (index === initialLimit - 1 && totalLogs > initialLimit) ? 'log-step-last-visible' : '';

      const transitionHtml = log.previous_status
        ? '<span style="font-size: 0.73rem; color: var(--gray-600); margin-left: 0.25rem;">[' + escapeStudentLogHtml(log.previous_status) + ' &rarr; ' + escapeStudentLogHtml(log.new_status) + ']</span>'
        : '<span style="font-size: 0.73rem; color: var(--gray-600); margin-left: 0.25rem;">[' + escapeStudentLogHtml(log.new_status) + ']</span>';

      html += `
        <div class="log-step ${stepClass} ${hiddenClass} ${lastVisibleClass}">
          <div class="log-step-node">
            <i class="fa-solid ${iconClass}"></i>
          </div>
          <div class="log-step-content">
            <div class="log-step-header">
              <div class="log-step-title">
                <span class="badge ${badgeClass}" style="font-size: 0.7rem;">${escapeStudentLogHtml(log.action_type)}</span>
                ${transitionHtml}
                <span>${escapeStudentLogHtml(log.user_name)}</span>
                <span class="log-step-role">(${escapeStudentLogHtml(log.user_role)})</span>
              </div>
              <div class="log-step-time">
                <i class="fa-regular fa-clock"></i> ${escapeStudentLogHtml(log.formatted_time || log.created_at)}
              </div>
            </div>
            <div class="log-step-body">${escapeStudentLogHtml(log.log_details)}</div>
          </div>
        </div>
      `;
    });
    html += '</div>';
    if (hiddenCount > 0) {
      html += `
        <div class="log-stepper-toggle-wrap">
          <button type="button" class="log-stepper-toggle-btn" data-expanded="false" onclick="toggleStudentStepperLogs(this)">
            <i class="fa-solid fa-chevron-down"></i> Show More (${hiddenCount} more ${hiddenCount === 1 ? 'log' : 'logs'})
          </button>
        </div>
      `;
    }
    html += '</div>';
    container.innerHTML = html;
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

// Sync active tab on initial load if hash is set
document.addEventListener('DOMContentLoaded', function() {
  var hash = window.location.hash;
  if (hash === '#tab-students') {
    switchUserTab('students');
  } else if (hash === '#tab-roles') {
    switchUserTab('roles');
  } else if (hash === '#tab-leaders') {
    switchUserTab('leaders');
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
