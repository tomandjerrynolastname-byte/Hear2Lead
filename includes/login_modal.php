<?php
// includes/login_modal.php - Universal Interactive Login & Registration Modal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_subfolder = strpos($_SERVER['REQUEST_URI'], '/student/') !== false || strpos($_SERVER['REQUEST_URI'], '/leadership/') !== false;
$base_path = $is_subfolder ? '../' : './';
?>

<!-- Universal Authentication Modal -->
<div class="modal-backdrop" id="authLoginModal">
  <div class="modal-card" style="max-width: 480px; border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.35);">
    
    <!-- Modal Header with Brand & Tabs -->
    <div style="background: linear-gradient(135deg, var(--primary, #641914), #450f0c); padding: 1.5rem 1.5rem 1rem; color: white; position: relative; border-bottom: 3px solid rgba(251, 243, 222, 0.25);">
      <button class="close-btn" data-modal-close style="position: absolute; right: 1rem; top: 1rem; background: rgba(255,255,255,0.18); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;">&times;</button>
      
      <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
        <img src="<?= $base_path ?>assets/uploads/logoswhite.jpg" alt="Logo" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--cream, #fbf3de); box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
        <div>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Portal Sign In</h3>
          <p style="font-size: 0.8rem; margin: 0; color: rgba(255,255,255,0.85);">Select your portal to authenticate</p>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div style="display: flex; background: rgba(0,0,0,0.28); padding: 4px; border-radius: 10px; gap: 4px;">
        <button type="button" class="auth-tab-btn" id="tabBtnStudent" onclick="switchAuthTab('student')" style="flex: 1; padding: 0.6rem 0.5rem; font-size: 0.85rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; background: #ffffff; color: var(--primary, #641914); box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
          <i class="fa-solid fa-graduation-cap"></i> Student
        </button>
        <button type="button" class="auth-tab-btn" id="tabBtnOfficer" onclick="switchAuthTab('officer')" style="flex: 1; padding: 0.6rem 0.5rem; font-size: 0.85rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; background: transparent; color: rgba(255,255,255,0.85);">
          <i class="fa-solid fa-shield-halved"></i> Officer / Leader
        </button>
      </div>
    </div>

    <div class="modal-body" style="padding: 1.5rem;">
      <div id="authModalAlert" style="display: none; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; background: var(--danger-light, #fee2e2); border: 1px solid var(--danger, #dc2626); color: var(--danger, #dc2626);">
        <i class="fa-solid fa-triangle-exclamation"></i> <span id="authModalAlertText"></span>
      </div>

      <!-- 1. STUDENT LOGIN TAB -->
      <div id="tabContentStudent">
        <div style="background: #eff6ff; border: 1px dashed #3b82f6; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.8rem; color: #1e40af; margin-bottom: 1.25rem;">
          <strong><i class="fa-solid fa-circle-info"></i> Demo Student:</strong> LRN: <code>2026-0001</code> | Pass: <code>student123</code>
        </div>

        <form method="POST" action="<?= $base_path ?>student/login.php">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="redirect_error" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Student LRN / ID or Email</label>
            <div style="position: relative;">
              <input type="text" name="identifier" class="form-control" placeholder="e.g. 2026-0001" required style="padding-left: 2.3rem;">
              <i class="fa-solid fa-id-card" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Password</label>
            <div style="position: relative;">
              <input type="password" name="password" class="form-control" placeholder="Enter password" required style="padding-left: 2.3rem;">
              <i class="fa-solid fa-lock" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700; background: var(--primary, #641914); border: none;">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In to Student Portal
          </button>
        </form>

        <div style="text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600);">
          <span>Don't have a student account?</span>
          <button type="button" onclick="switchAuthTab('register')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline; margin-left: 0.25rem;">Register Here</button>
        </div>
      </div>

      <!-- 2. OFFICER LOGIN TAB -->
      <div id="tabContentOfficer" style="display: none;">
        <div style="background: #fdf4ff; border: 1px dashed #c084fc; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.8rem; color: #7e22ce; margin-bottom: 1.25rem;">
          <strong><i class="fa-solid fa-circle-info"></i> Demo Officer:</strong> User: <code>sslg_leader</code> | Pass: <code>admin123</code>
        </div>

        <form method="POST" action="<?= $base_path ?>leadership/login.php">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="redirect_error" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Officer Username or Email</label>
            <div style="position: relative;">
              <input type="text" name="username" class="form-control" placeholder="e.g. sslg_leader" required style="padding-left: 2.3rem;">
              <i class="fa-solid fa-user-shield" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Password</label>
            <div style="position: relative;">
              <input type="password" name="password" class="form-control" placeholder="Enter password" required style="padding-left: 2.3rem;">
              <i class="fa-solid fa-lock" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700; background: var(--primary, #641914); border: none;">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In to Leadership Portal
          </button>
        </form>

        <div style="text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600);">
          <span>Are you a student?</span>
          <button type="button" onclick="switchAuthTab('student')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline; margin-left: 0.25rem;">Switch to Student Login</button>
        </div>
      </div>

      <!-- 3. STUDENT REGISTRATION TAB -->
      <div id="tabContentRegister" style="display: none;">
        <h4 style="font-size: 1rem; font-weight: 700; color: var(--dark); margin-bottom: 0.75rem;"><i class="fa-solid fa-user-plus" style="color: var(--primary, #641914);"></i> Create Student Account</h4>
        
        <form method="POST" action="<?= $base_path ?>student/register.php">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="redirect_error" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div class="form-group" style="margin-bottom: 0.75rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Student LRN / ID <span style="color: red;">*</span></label>
            <input type="text" name="student_lrn" class="form-control" placeholder="e.g. 2026-0003" required>
          </div>

          <div class="form-group" style="margin-bottom: 0.75rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Full Name <span style="color: red;">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Santos" required>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Grade & Section <span style="color: red;">*</span></label>
              <input type="text" name="grade_section" class="form-control" placeholder="Grade 11 - STEM" required>
            </div>
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Email</label>
              <input type="email" name="email" class="form-control" placeholder="student@school.ph">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Password <span style="color: red;">*</span></label>
              <input type="password" name="password" class="form-control" placeholder="Min 6 chars" required>
            </div>
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Confirm Password <span style="color: red;">*</span></label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Confirm" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.7rem; font-weight: 700; background: var(--primary, #641914); border: none;">
            <i class="fa-solid fa-user-plus"></i> Register & Enter Student Portal
          </button>
        </form>

        <div style="text-align: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600);">
          <span>Already have an account?</span>
          <button type="button" onclick="switchAuthTab('student')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline; margin-left: 0.25rem;">Back to Sign In</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function switchAuthTab(tab) {
  const btnStudent = document.getElementById('tabBtnStudent');
  const btnOfficer = document.getElementById('tabBtnOfficer');
  const contentStudent = document.getElementById('tabContentStudent');
  const contentOfficer = document.getElementById('tabContentOfficer');
  const contentRegister = document.getElementById('tabContentRegister');

  if (!btnStudent || !btnOfficer) return;

  btnStudent.style.background = 'transparent';
  btnStudent.style.color = 'rgba(255,255,255,0.85)';
  btnStudent.style.boxShadow = 'none';

  btnOfficer.style.background = 'transparent';
  btnOfficer.style.color = 'rgba(255,255,255,0.85)';
  btnOfficer.style.boxShadow = 'none';

  contentStudent.style.display = 'none';
  contentOfficer.style.display = 'none';
  contentRegister.style.display = 'none';

  if (tab === 'student') {
    btnStudent.style.background = '#ffffff';
    btnStudent.style.color = 'var(--primary, #641914)';
    btnStudent.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    contentStudent.style.display = 'block';
  } else if (tab === 'officer') {
    btnOfficer.style.background = '#ffffff';
    btnOfficer.style.color = 'var(--primary, #641914)';
    btnOfficer.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    contentOfficer.style.display = 'block';
  } else if (tab === 'register') {
    btnStudent.style.background = '#ffffff';
    btnStudent.style.color = 'var(--primary, #641914)';
    btnStudent.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    contentRegister.style.display = 'block';
  }
}

function openAuthModal(initialTab = 'student') {
  const modal = document.getElementById('authLoginModal');
  if (modal) {
    switchAuthTab(initialTab);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

// Auto open modal if URL params request it
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const modalParam = urlParams.get('modal');
  const errorParam = urlParams.get('error');

  if (modalParam) {
    const tab = (modalParam === 'officer_login' || modalParam === 'officer') ? 'officer' : 
                (modalParam === 'register') ? 'register' : 'student';
    openAuthModal(tab);

    if (errorParam) {
      const alertBox = document.getElementById('authModalAlert');
      const alertText = document.getElementById('authModalAlertText');
      if (alertBox && alertText) {
        alertText.textContent = errorParam;
        alertBox.style.display = 'block';
      }
    }
  }
});
</script>
