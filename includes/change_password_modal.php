<?php
// includes/change_password_modal.php - Global Change Password Modal for Authenticated Users
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cp_is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['student_id']);
if (!$cp_is_logged_in) {
    return; // Don't render modal for guest/unauthenticated users
}

$cp_is_officer = isset($_SESSION['user_id']);
$cp_display_name = $cp_is_officer ? ($_SESSION['user_name'] ?? 'Officer') : ($_SESSION['student_name'] ?? 'Student');
$cp_display_sub = $cp_is_officer 
    ? (($_SESSION['user_role'] ?? 'Officer') . ' • ' . ($_SESSION['org_acronym'] ?? 'SSLG'))
    : ('LRN: ' . ($_SESSION['student_lrn'] ?? '') . ' • ' . ($_SESSION['student_grade_section'] ?? 'Student'));

$cp_is_subfolder = strpos($_SERVER['REQUEST_URI'], '/student/') !== false || strpos($_SERVER['REQUEST_URI'], '/leadership/') !== false;
$cp_base_path = $cp_is_subfolder ? '../' : './';
?>

<!-- Change Password Modal -->
<div class="modal-backdrop" id="changePasswordModal" style="display: none; z-index: 10000;">
  <div class="modal-card" style="max-width: 480px; border-radius: var(--radius-lg, 14px); overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.35);">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, var(--primary, #641914), #450f0c); padding: 1.2rem 1.5rem; color: white; position: relative;">
      <button type="button" onclick="closeChangePasswordModal()" style="position: absolute; right: 1rem; top: 1rem; background: rgba(255,255,255,0.18); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;">&times;</button>
      
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1.5px solid rgba(255,255,255,0.25);">
          <i class="fa-solid fa-key" style="color: #fbf3de;"></i>
        </div>
        <div>
          <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: white;">Change Password</h3>
          <p style="font-size: 0.78rem; margin: 0.15rem 0 0; color: rgba(255,255,255,0.85);">Update your account login credentials</p>
        </div>
      </div>
    </div>

    <!-- Modal Body -->
    <div class="modal-body" style="padding: 1.25rem 1.5rem; max-height: calc(85vh - 80px); overflow-y: auto;">
      
      <!-- Current User Context Card -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.65rem 0.9rem; margin-bottom: 1.1rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
          <div style="width: 34px; height: 34px; border-radius: 50%; background: <?= $cp_is_officer ? 'var(--accent, #e11d48)' : 'var(--primary, #641914)' ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem;">
            <?= strtoupper(substr($cp_display_name, 0, 1)) ?>
          </div>
          <div>
            <strong style="display: block; font-size: 0.88rem; color: var(--dark, #0f172a);"><?= htmlspecialchars($cp_display_name) ?></strong>
            <span style="font-size: 0.74rem; color: var(--gray-600, #64748b);"><?= htmlspecialchars($cp_display_sub) ?></span>
          </div>
        </div>
        <span class="badge <?= $cp_is_officer ? 'badge-role-officer' : 'badge-planning' ?>" style="font-size: 0.7rem;">
          <?= $cp_is_officer ? 'Officer' : 'Student' ?>
        </span>
      </div>

      <!-- Alert Error Box -->
      <div id="changePassAlertError" style="display: none; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; background: #fee2e2; border: 1px solid #dc2626; color: #dc2626;">
        <div style="display: flex; gap: 0.5rem; align-items: center;">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span id="changePassAlertErrorText"></span>
        </div>
      </div>

      <form id="changePasswordForm" onsubmit="handleChangePasswordSubmit(event)">
        <!-- Current Password -->
        <div class="form-group" style="margin-bottom: 1rem;">
          <label class="form-label" style="font-weight: 600; font-size: 0.82rem; margin-bottom: 0.35rem; display: block;">
            Current Password <span style="color: red;">*</span>
          </label>
          <div style="position: relative;">
            <input type="password" id="cpCurrentPass" name="current_password" class="form-control" placeholder="Enter your current password" required style="padding-left: 2.2rem; padding-right: 2.3rem;">
            <i class="fa-solid fa-lock" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400, #94a3b8); font-size: 0.85rem;"></i>
            <button type="button" onclick="togglePassVisibility('cpCurrentPass', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400, #94a3b8); cursor: pointer; padding: 0;">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- New Password -->
        <div class="form-group" style="margin-bottom: 0.75rem;">
          <label class="form-label" style="font-weight: 600; font-size: 0.82rem; margin-bottom: 0.35rem; display: block;">
            New Password <span style="color: red;">*</span>
          </label>
          <div style="position: relative;">
            <input type="password" id="cpNewPass" name="new_password" class="form-control" placeholder="Enter at least 8 characters" required oninput="evaluateChangePasswordPolicy()" style="padding-left: 2.2rem; padding-right: 2.3rem;">
            <i class="fa-solid fa-key" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400, #94a3b8); font-size: 0.85rem;"></i>
            <button type="button" onclick="togglePassVisibility('cpNewPass', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400, #94a3b8); cursor: pointer; padding: 0;">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Confirm New Password -->
        <div class="form-group" style="margin-bottom: 0.85rem;">
          <label class="form-label" style="font-weight: 600; font-size: 0.82rem; margin-bottom: 0.35rem; display: block;">
            Confirm New Password <span style="color: red;">*</span>
          </label>
          <div style="position: relative;">
            <input type="password" id="cpConfirmPass" name="confirm_password" class="form-control" placeholder="Re-type new password" required oninput="evaluateChangePasswordPolicy()" style="padding-left: 2.2rem; padding-right: 2.3rem;">
            <i class="fa-solid fa-shield-check" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400, #94a3b8); font-size: 0.85rem;"></i>
            <button type="button" onclick="togglePassVisibility('cpConfirmPass', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400, #94a3b8); cursor: pointer; padding: 0;">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Strong Password Policy Checklist & Strength Meter -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem 0.8rem; margin-bottom: 1.1rem;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--gray-600, #475569);">Password Strength:</span>
            <span id="cpPassStrengthLabel" style="font-size: 0.75rem; font-weight: 800; color: #dc2626;">Too Weak</span>
          </div>
          
          <!-- Strength Meter Bar -->
          <div style="display: flex; gap: 4px; height: 5px; margin-bottom: 0.55rem;">
            <div id="cpStrBar1" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
            <div id="cpStrBar2" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
            <div id="cpStrBar3" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
            <div id="cpStrBar4" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
          </div>

          <!-- Policy Requirements -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.25rem 0.5rem; font-size: 0.72rem;">
            <div id="cpRuleLen" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> 8+ characters</div>
            <div id="cpRuleUpper" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> Uppercase (A-Z)</div>
            <div id="cpRuleLower" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> Lowercase (a-z)</div>
            <div id="cpRuleNum" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> Number (0-9)</div>
            <div id="cpRuleSpecial" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> Symbol (!@#$%...)</div>
            <div id="cpRuleMatch" style="color: var(--gray-500, #64748b);"><i class="fa-regular fa-circle"></i> Passwords match</div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 0.6rem; justify-content: flex-end;">
          <button type="button" onclick="closeChangePasswordModal()" class="btn btn-secondary" style="padding: 0.6rem 1rem; font-size: 0.85rem;">
            Cancel
          </button>
          <button type="submit" id="cpSubmitBtn" class="btn btn-primary" style="padding: 0.6rem 1.25rem; font-weight: 700; font-size: 0.85rem; background: var(--primary, #641914); border: none; display: flex; align-items: center; gap: 0.4rem;">
            <i class="fa-solid fa-floppy-disk"></i> Save New Password
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
function openChangePasswordModal() {
  const modal = document.getElementById('changePasswordModal');
  if (modal) {
    modal.style.display = 'flex';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    const curr = document.getElementById('cpCurrentPass');
    if (curr) {
      curr.value = '';
      curr.focus();
    }
    const newP = document.getElementById('cpNewPass');
    if (newP) newP.value = '';
    const confP = document.getElementById('cpConfirmPass');
    if (confP) confP.value = '';
    const alertBox = document.getElementById('changePassAlertError');
    if (alertBox) alertBox.style.display = 'none';
    evaluateChangePasswordPolicy();
  }
}

function closeChangePasswordModal() {
  const modal = document.getElementById('changePasswordModal');
  if (modal) {
    modal.style.display = 'none';
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Close when clicking on backdrop
document.addEventListener('click', function(e) {
  const modal = document.getElementById('changePasswordModal');
  if (modal && e.target === modal) {
    closeChangePasswordModal();
  }
});

function evaluateChangePasswordPolicy() {
  const pass = document.getElementById('cpNewPass') ? document.getElementById('cpNewPass').value : '';
  const confirm = document.getElementById('cpConfirmPass') ? document.getElementById('cpConfirmPass').value : '';

  const hasLen = pass.length >= 8;
  const hasUpper = /[A-Z]/.test(pass);
  const hasLower = /[a-z]/.test(pass);
  const hasNum = /[0-9]/.test(pass);
  const hasSpecial = /[^A-Za-z0-9]/.test(pass);
  const hasMatch = pass.length > 0 && pass === confirm;

  const updateRule = (elemId, satisfied) => {
    const el = document.getElementById(elemId);
    if (!el) return;
    if (satisfied) {
      el.style.color = '#15803d';
      el.innerHTML = '<i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> ' + el.textContent.trim();
    } else {
      el.style.color = 'var(--gray-500, #64748b)';
      el.innerHTML = '<i class="fa-regular fa-circle"></i> ' + el.textContent.trim();
    }
  };

  updateRule('cpRuleLen', hasLen);
  updateRule('cpRuleUpper', hasUpper);
  updateRule('cpRuleLower', hasLower);
  updateRule('cpRuleNum', hasNum);
  updateRule('cpRuleSpecial', hasSpecial);
  updateRule('cpRuleMatch', hasMatch);

  let score = 0;
  if (hasLen) score++;
  if (hasUpper) score++;
  if (hasLower) score++;
  if (hasNum) score++;
  if (hasSpecial) score++;

  const strLabel = document.getElementById('cpPassStrengthLabel');
  const b1 = document.getElementById('cpStrBar1');
  const b2 = document.getElementById('cpStrBar2');
  const b3 = document.getElementById('cpStrBar3');
  const b4 = document.getElementById('cpStrBar4');

  [b1, b2, b3, b4].forEach(b => { if (b) b.style.background = '#e2e8f0'; });

  if (score <= 1) {
    if (strLabel) { strLabel.textContent = 'Too Weak'; strLabel.style.color = '#dc2626'; }
    if (b1) b1.style.background = '#dc2626';
  } else if (score === 2) {
    if (strLabel) { strLabel.textContent = 'Fair'; strLabel.style.color = '#ea580c'; }
    if (b1) b1.style.background = '#ea580c';
    if (b2) b2.style.background = '#ea580c';
  } else if (score === 3 || score === 4) {
    if (strLabel) { strLabel.textContent = 'Good'; strLabel.style.color = '#0284c7'; }
    if (b1) b1.style.background = '#0284c7';
    if (b2) b2.style.background = '#0284c7';
    if (b3) b3.style.background = '#0284c7';
  } else if (score === 5) {
    if (strLabel) { strLabel.textContent = 'Strong ✓'; strLabel.style.color = '#16a34a'; }
    if (b1) b1.style.background = '#16a34a';
    if (b2) b2.style.background = '#16a34a';
    if (b3) b3.style.background = '#16a34a';
    if (b4) b4.style.background = '#16a34a';
  }

  return hasLen && hasUpper && hasLower && hasNum && hasSpecial && hasMatch;
}

function handleChangePasswordSubmit(e) {
  e.preventDefault();
  const alertBox = document.getElementById('changePassAlertError');
  const alertText = document.getElementById('changePassAlertErrorText');
  const submitBtn = document.getElementById('cpSubmitBtn');

  const curr = document.getElementById('cpCurrentPass') ? document.getElementById('cpCurrentPass').value : '';
  const pass = document.getElementById('cpNewPass') ? document.getElementById('cpNewPass').value : '';
  const confirm = document.getElementById('cpConfirmPass') ? document.getElementById('cpConfirmPass').value : '';

  const showError = (msg) => {
    if (alertBox && alertText) {
      alertText.textContent = msg;
      alertBox.style.display = 'block';
    }
  };

  if (!curr) {
    showError("Please enter your current password.");
    return false;
  }
  if (pass.length < 8) {
    showError("New password must be at least 8 characters in length.");
    return false;
  }
  if (!/[A-Z]/.test(pass)) {
    showError("New password must contain at least one uppercase letter (A-Z).");
    return false;
  }
  if (!/[a-z]/.test(pass)) {
    showError("New password must contain at least one lowercase letter (a-z).");
    return false;
  }
  if (!/[0-9]/.test(pass)) {
    showError("New password must contain at least one number (0-9).");
    return false;
  }
  if (!/[^A-Za-z0-9]/.test(pass)) {
    showError("New password must contain at least one special symbol (!@#$%...).");
    return false;
  }
  if (pass !== confirm) {
    showError("New password and confirmation password do not match.");
    return false;
  }

  if (alertBox) alertBox.style.display = 'none';

  // Submit via AJAX
  const originalBtnHtml = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

  const formData = new FormData();
  formData.append('ajax', '1');
  formData.append('current_password', curr);
  formData.append('new_password', pass);
  formData.append('confirm_password', confirm);

  const endpoint = '<?= $cp_base_path ?>includes/change_password_handler.php';

  fetch(endpoint, {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnHtml;

    if (data.success) {
      closeChangePasswordModal();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Password Updated!',
          text: data.message,
          confirmButtonColor: '#641914'
        });
      } else {
        alert(data.message);
      }
    } else {
      showError(data.message || 'Failed to update password.');
    }
  })
  .catch(err => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnHtml;
    showError("Network or server error while updating password. Please try again.");
  });

  return false;
}
</script>
