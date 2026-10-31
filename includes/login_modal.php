<?php
// includes/login_modal.php - Universal Interactive Login & Registration Modal with CAPTCHA, Strong Passwords & Admin Approval Notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_subfolder = strpos($_SERVER['REQUEST_URI'], '/student/') !== false || strpos($_SERVER['REQUEST_URI'], '/leadership/') !== false;
$base_path = $is_subfolder ? '../' : './';
?>

<!-- Universal Authentication Modal -->
<div class="modal-backdrop" id="authLoginModal">
  <div class="modal-card" style="max-width: 520px; max-height: 90vh; display: flex; flex-direction: column; border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 20px 45px rgba(0,0,0,0.38);">
    
    <!-- Modal Header with Brand & Tabs -->
    <div style="background: linear-gradient(135deg, var(--primary, #641914), #450f0c); padding: 1.25rem 1.5rem 1rem; color: white; position: relative; border-bottom: 3px solid rgba(251, 243, 222, 0.25); flex-shrink: 0;">
      <button class="close-btn" data-modal-close style="position: absolute; right: 1rem; top: 1rem; background: rgba(255,255,255,0.18); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;">&times;</button>
      
      <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
        <img src="<?= $base_path ?>assets/uploads/logoswhite.jpg" alt="Logo" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--cream, #fbf3de); box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
        <div>
          <h3 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Portal Authentication</h3>
          <p style="font-size: 0.78rem; margin: 0; color: rgba(255,255,255,0.85);">Select your portal to authenticate</p>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div style="display: flex; background: rgba(0,0,0,0.28); padding: 4px; border-radius: 10px; gap: 4px;">
        <button type="button" class="auth-tab-btn" id="tabBtnStudent" onclick="switchAuthTab('student')" style="flex: 1; padding: 0.55rem 0.5rem; font-size: 0.85rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; background: #ffffff; color: var(--primary, #641914); box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
          <i class="fa-solid fa-graduation-cap"></i> Student
        </button>
        <button type="button" class="auth-tab-btn" id="tabBtnOfficer" onclick="switchAuthTab('officer')" style="flex: 1; padding: 0.55rem 0.5rem; font-size: 0.85rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.4rem; background: transparent; color: rgba(255,255,255,0.85);">
          <i class="fa-solid fa-shield-halved"></i> Officer / Leader
        </button>
      </div>
    </div>

    <!-- Scrollable Modal Body -->
    <div class="modal-body" style="padding: 1.25rem 1.5rem; overflow-y: auto;">
      
      <!-- Alert Error Box -->
      <div id="authModalAlert" style="display: none; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; background: var(--danger-light, #fee2e2); border: 1px solid var(--danger, #dc2626); color: var(--danger, #dc2626);">
        <i class="fa-solid fa-triangle-exclamation"></i> <span id="authModalAlertText"></span>
      </div>

      <!-- Alert Success Box (e.g. Registration Pending or Action Success) -->
      <div id="authModalSuccess" style="display: none; padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15);">
        <div style="display: flex; gap: 0.65rem; align-items: flex-start;">
          <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.15rem; margin-top: 2px;"></i>
          <div>
            <strong style="display: block; font-size: 0.9rem; margin-bottom: 0.2rem;" id="authModalSuccessTitle">Registration Submitted!</strong>
            <span id="authModalSuccessText">Your account is awaiting administrator approval.</span>
          </div>
        </div>
      </div>

      <!-- 1. STUDENT LOGIN TAB -->
      <div id="tabContentStudent">
        <div style="background: #eff6ff; border: 1px dashed #3b82f6; border-radius: 6px; padding: 0.55rem 0.85rem; font-size: 0.8rem; color: #1e40af; margin-bottom: 1.1rem;">
          <strong><i class="fa-solid fa-circle-info"></i> Demo Student:</strong> LRN: <code>2026-0001</code> | Pass: <code>student123</code>
        </div>

        <form method="POST" action="<?= $base_path ?>student/login.php">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="redirect_error" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Student LRN / ID or Email</label>
            <div style="position: relative;">
              <input type="text" name="identifier" id="studentLoginIdentifier" class="form-control" placeholder="e.g. 2026-0001" required style="padding-left: 2.3rem;">
              <i class="fa-solid fa-id-card" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
              <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin: 0;">Password</label>
              <button type="button" onclick="switchAuthTab('forgot', 'student')" style="background: none; border: none; color: var(--primary, #641914); font-size: 0.78rem; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">Forgot Password?</button>
            </div>
            <div style="position: relative;">
              <input type="password" name="password" id="studentLoginPassword" class="form-control" placeholder="Enter password" required style="padding-left: 2.3rem; padding-right: 2.4rem;">
              <i class="fa-solid fa-lock" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
              <button type="button" onclick="togglePassVisibility('studentLoginPassword', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0;">
                <i class="fa-solid fa-eye"></i>
              </button>
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
        <div style="background: #fdf4ff; border: 1px dashed #c084fc; border-radius: 6px; padding: 0.55rem 0.85rem; font-size: 0.8rem; color: #7e22ce; margin-bottom: 1.1rem;">
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
              <label class="form-label" style="font-weight: 600; font-size: 0.85rem; margin: 0;">Password</label>
              <button type="button" onclick="switchAuthTab('forgot', 'officer')" style="background: none; border: none; color: var(--primary, #641914); font-size: 0.78rem; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">Forgot Password?</button>
            </div>
            <div style="position: relative;">
              <input type="password" name="password" id="officerLoginPassword" class="form-control" placeholder="Enter password" required style="padding-left: 2.3rem; padding-right: 2.4rem;">
              <i class="fa-solid fa-lock" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
              <button type="button" onclick="togglePassVisibility('officerLoginPassword', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0;">
                <i class="fa-solid fa-eye"></i>
              </button>
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

      <!-- 3. STUDENT REGISTRATION TAB (With CAPTCHA, Strong Passwords & Admin Approval Notice) -->
      <div id="tabContentRegister" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
          <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--dark); margin: 0;">
            <i class="fa-solid fa-user-plus" style="color: var(--primary, #641914);"></i> Student Registration
          </h4>
          <span style="font-size: 0.75rem; color: var(--gray-500);"><span style="color: red;">*</span> Required fields</span>
        </div>

        <!-- Security & Admin Approval Policy Notice -->
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.78rem; color: #92400e; margin-bottom: 1rem; display: flex; align-items: flex-start; gap: 0.6rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
          <i class="fa-solid fa-shield-halved" style="color: #d97706; font-size: 1rem; margin-top: 2px;"></i>
          <div>
            <strong style="color: #78350f;">Administrator Verification Required:</strong>
            <p style="margin: 0.15rem 0 0; line-height: 1.35;">To protect student privacy, newly created accounts are submitted with <strong>Pending</strong> status and must be approved by an administrator before signing in.</p>
          </div>
        </div>
        
        <form method="POST" action="<?= $base_path ?>student/register.php" id="studentRegisterForm" onsubmit="return handleRegisterSubmit(event)">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="redirect_error" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 0.6rem; margin-bottom: 0.75rem;">
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Student LRN / ID <span style="color: red;">*</span></label>
              <div style="position: relative;">
                <input type="text" name="student_lrn" id="regStudentLrn" class="form-control" placeholder="e.g. 2026-0003" required value="<?= htmlspecialchars($_GET['lrn'] ?? '') ?>" style="padding-left: 2rem; font-size: 0.85rem;">
                <i class="fa-solid fa-id-badge" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
              </div>
            </div>

            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Grade & Section <span style="color: red;">*</span></label>
              <input type="text" name="grade_section" id="regGradeSection" class="form-control" placeholder="Grade 11 - STEM" required value="<?= htmlspecialchars($_GET['grade'] ?? '') ?>" style="font-size: 0.85rem;">
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0.75rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Full Name <span style="color: red;">*</span></label>
            <div style="position: relative;">
              <input type="text" name="full_name" id="regFullName" class="form-control" placeholder="e.g. Juan Dela Cruz" required value="<?= htmlspecialchars($_GET['name'] ?? '') ?>" style="padding-left: 2rem; font-size: 0.85rem;">
              <i class="fa-solid fa-user" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0.75rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Email Address <span style="font-weight: normal; color: var(--gray-500);">(Optional)</span></label>
            <div style="position: relative;">
              <input type="email" name="email" id="regEmail" class="form-control" placeholder="e.g. student@school.ph" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" style="padding-left: 2rem; font-size: 0.85rem;">
              <i class="fa-solid fa-envelope" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
            </div>
          </div>

          <!-- Password & Confirm Password with Visibility Toggles -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 0.5rem;">
            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Password <span style="color: red;">*</span></label>
              <div style="position: relative;">
                <input type="password" name="password" id="regPassword" class="form-control" placeholder="Min 8 characters" required oninput="evaluatePasswordPolicy()" style="padding-left: 2rem; padding-right: 2.2rem; font-size: 0.85rem;">
                <i class="fa-solid fa-lock" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
                <button type="button" onclick="togglePassVisibility('regPassword', this)" style="position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0;">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>

            <div class="form-group" style="margin: 0;">
              <label class="form-label" style="font-weight: 600; font-size: 0.8rem;">Confirm Password <span style="color: red;">*</span></label>
              <div style="position: relative;">
                <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Re-type password" required oninput="evaluatePasswordPolicy()" style="padding-left: 2rem; padding-right: 2.2rem; font-size: 0.85rem;">
                <i class="fa-solid fa-shield-check" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
                <button type="button" onclick="togglePassVisibility('regConfirmPassword', this)" style="position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0;">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Strong Password Policy Checklist & Strength Meter -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem 0.8rem; margin-bottom: 0.85rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
              <span style="font-size: 0.75rem; font-weight: 700; color: var(--gray-600);">Password Strength:</span>
              <span id="passStrengthLabel" style="font-size: 0.75rem; font-weight: 800; color: #dc2626;">Too Weak</span>
            </div>
            
            <!-- Visual Strength Meter Bar -->
            <div style="display: flex; gap: 4px; height: 5px; margin-bottom: 0.55rem;">
              <div id="strBar1" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
              <div id="strBar2" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
              <div id="strBar3" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
              <div id="strBar4" style="flex: 1; background: #e2e8f0; border-radius: 3px; transition: background 0.25s;"></div>
            </div>

            <!-- Dynamic Policy Requirement Items -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.25rem 0.5rem; font-size: 0.72rem;">
              <div id="ruleLen" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> 8+ characters</div>
              <div id="ruleUpper" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> Uppercase (A-Z)</div>
              <div id="ruleLower" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> Lowercase (a-z)</div>
              <div id="ruleNum" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> Number (0-9)</div>
              <div id="ruleSpecial" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> Symbol (!@#$%...)</div>
              <div id="ruleMatch" style="color: var(--gray-500);"><i class="fa-regular fa-circle"></i> Passwords match</div>
            </div>
          </div>

          <!-- CAPTCHA Verification Section -->
          <div class="form-group" style="margin-bottom: 1rem; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.75rem 0.85rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.45rem;">
              <span><i class="fa-solid fa-robot" style="color: var(--primary, #641914);"></i> Security Verification (CAPTCHA) <span style="color: red;">*</span></span>
              <span style="font-size: 0.7rem; font-weight: normal; color: var(--gray-500);">Case-insensitive</span>
            </label>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <!-- Captcha Image Display -->
              <div style="position: relative; flex-shrink: 0;">
                <img id="studentRegCaptchaImg" src="<?= $base_path ?>includes/captcha.php?t=<?= time() ?>" alt="Security CAPTCHA" style="height: 44px; width: 145px; border-radius: 6px; border: 1.5px solid #d97706; background: #fbf3de; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: block; object-fit: contain; cursor: pointer;" onclick="refreshStudentRegCaptcha()" title="Click image to refresh CAPTCHA">
              </div>

              <!-- Reload Button -->
              <button type="button" onclick="refreshStudentRegCaptcha()" class="btn btn-secondary" style="padding: 0.5rem 0.65rem; height: 44px; border-radius: 6px; flex-shrink: 0;" title="Get new CAPTCHA code">
                <i class="fa-solid fa-arrows-rotate" id="captchaReloadIcon"></i>
              </button>

              <!-- Captcha Code Input -->
              <div style="flex: 1;">
                <input type="text" name="captcha" id="regCaptchaInput" class="form-control" placeholder="Enter code" required maxlength="6" autocomplete="off" style="text-transform: uppercase; font-weight: 800; font-family: monospace; letter-spacing: 2px; font-size: 0.95rem; height: 44px;">
              </div>
            </div>
          </div>

          <button type="submit" id="btnSubmitRegister" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700; background: var(--primary, #641914); border: none; font-size: 0.92rem; box-shadow: 0 4px 12px rgba(100, 25, 20, 0.25);">
            <i class="fa-solid fa-user-plus"></i> Submit Registration for Approval
          </button>
        </form>

        <div style="text-align: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600);">
          <span>Already have an account?</span>
          <button type="button" onclick="switchAuthTab('student')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline; margin-left: 0.25rem;">Back to Sign In</button>
        </div>
      </div>

      <!-- 4. FORGOT PASSWORD TAB -->
      <div id="tabContentForgot" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
          <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--dark); margin: 0;">
            <i class="fa-solid fa-key" style="color: var(--primary, #641914);"></i> Forgot Password Request
          </h4>
          <span style="font-size: 0.75rem; color: var(--gray-500);"><span style="color: red;">*</span> Required</span>
        </div>

        <!-- Info Notice -->
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.78rem; color: #1e40af; margin-bottom: 1rem; display: flex; align-items: flex-start; gap: 0.6rem;">
          <i class="fa-solid fa-circle-info" style="color: #3b82f6; font-size: 1rem; margin-top: 2px;"></i>
          <div>
            <strong style="color: #1e3a8a;">Admin Password Reset Ticket:</strong>
            <p style="margin: 0.15rem 0 0; line-height: 1.35;">
              Submit your account details below. A secure reset ticket will be generated for the <strong>System Administrator</strong> to review and reset your password.
            </p>
          </div>
        </div>

        <!-- Account Type Selector (Student vs Leadership) -->
        <div style="margin-bottom: 1rem;">
          <label class="form-label" style="font-weight: 700; font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Select Account Type <span style="color: red;">*</span></label>
          <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 8px; gap: 4px;">
            <button type="button" id="forgotTypeStudentBtn" onclick="setForgotAccountType('student')" style="flex: 1; padding: 0.45rem 0.6rem; font-size: 0.82rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; transition: 0.2s; background: #ffffff; color: var(--primary, #641914); box-shadow: 0 1px 4px rgba(0,0,0,0.12);">
              <i class="fa-solid fa-graduation-cap"></i> Student
            </button>
            <button type="button" id="forgotTypeOfficerBtn" onclick="setForgotAccountType('leadership')" style="flex: 1; padding: 0.45rem 0.6rem; font-size: 0.82rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; transition: 0.2s; background: transparent; color: var(--gray-600, #475569);">
              <i class="fa-solid fa-shield-halved"></i> Officer / Leader
            </button>
          </div>
        </div>

        <form method="POST" action="<?= $base_path ?>includes/forgot_password_handler.php" id="forgotPasswordForm" onsubmit="return handleForgotPassSubmit(event)">
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="account_type" id="forgotAccountTypeInput" value="student">
          <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

          <div class="form-group" style="margin-bottom: 0.85rem;">
            <label class="form-label" id="forgotIdentifierLabel" style="font-weight: 600; font-size: 0.82rem; margin-bottom: 0.35rem; display: block;">
              Student LRN / ID or Registered Email <span style="color: red;">*</span>
            </label>
            <div style="position: relative;">
              <input type="text" name="identifier" id="forgotIdentifierInput" class="form-control" placeholder="e.g. 2026-0001 or student@school.ph" required style="padding-left: 2.2rem; font-size: 0.88rem;">
              <i id="forgotIdentifierIcon" class="fa-solid fa-id-card" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 0.85rem;"></i>
            </div>
            <small id="forgotIdentifierHelp" style="font-size: 0.72rem; color: var(--gray-500); margin-top: 0.25rem; display: block;">
              Enter the exact LRN or email you registered with your student account.
            </small>
          </div>

          <div class="form-group" style="margin-bottom: 0.85rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.82rem; margin-bottom: 0.35rem; display: block;">
              Reason / Additional Note <span style="font-weight: normal; color: var(--gray-500);">(Optional)</span>
            </label>
            <textarea name="reason" id="forgotReasonInput" class="form-control" rows="2" placeholder="e.g. Forgot password, please reset my account password." style="font-size: 0.85rem; resize: vertical;"></textarea>
          </div>

          <!-- CAPTCHA Verification -->
          <div class="form-group" style="margin-bottom: 1rem; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.65rem 0.85rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.78rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
              <span><i class="fa-solid fa-robot" style="color: var(--primary, #641914);"></i> Security CAPTCHA <span style="color: red;">*</span></span>
              <span style="font-size: 0.7rem; font-weight: normal; color: var(--gray-500);">Case-insensitive</span>
            </label>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <div style="position: relative; flex-shrink: 0;">
                <img id="forgotCaptchaImg" src="<?= $base_path ?>includes/captcha.php?t=<?= time() ?>" alt="Security CAPTCHA" style="height: 42px; width: 140px; border-radius: 6px; border: 1.5px solid #d97706; background: #fbf3de; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: block; object-fit: contain; cursor: pointer;" onclick="refreshForgotCaptcha()" title="Click image to refresh CAPTCHA">
              </div>
              <button type="button" onclick="refreshForgotCaptcha()" class="btn btn-secondary" style="padding: 0.5rem 0.65rem; height: 42px; border-radius: 6px; flex-shrink: 0;" title="Get new CAPTCHA code">
                <i class="fa-solid fa-arrows-rotate" id="forgotCaptchaReloadIcon"></i>
              </button>
              <div style="flex: 1;">
                <input type="text" name="captcha" id="forgotCaptchaInput" class="form-control" placeholder="Code" required maxlength="6" autocomplete="off" style="text-transform: uppercase; font-weight: 800; font-family: monospace; letter-spacing: 2px; font-size: 0.95rem; height: 42px;">
              </div>
            </div>
          </div>

          <button type="submit" id="btnSubmitForgotTicket" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700; background: var(--primary, #641914); border: none; font-size: 0.92rem; box-shadow: 0 4px 12px rgba(100, 25, 20, 0.25); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
            <i class="fa-solid fa-paper-plane"></i> Submit Password Reset Ticket
          </button>
        </form>

        <div style="text-align: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600); display: flex; justify-content: center; gap: 1rem;">
          <button type="button" onclick="switchAuthTab('student')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline;">Back to Student Login</button>
          <span>•</span>
          <button type="button" onclick="switchAuthTab('officer')" style="background: none; border: none; color: var(--primary, #641914); font-weight: 700; cursor: pointer; text-decoration: underline;">Back to Officer Login</button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Toggle Password Visibility Eye Icon
function togglePassVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) {
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    }
  } else {
    input.type = 'password';
    if (icon) {
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

// Refresh CAPTCHA Image
function refreshStudentRegCaptcha() {
  const img = document.getElementById('studentRegCaptchaImg');
  const icon = document.getElementById('captchaReloadIcon');
  if (img) {
    if (icon) icon.classList.add('fa-spin');
    const basePath = '<?= $base_path ?>';
    img.src = basePath + 'includes/captcha.php?t=' + new Date().getTime();
    img.onload = () => {
      if (icon) icon.classList.remove('fa-spin');
    };
  }
  const captchaInput = document.getElementById('regCaptchaInput');
  if (captchaInput) {
    captchaInput.value = '';
    captchaInput.focus();
  }
}

// Evaluate Password Policy in Real-Time
function evaluatePasswordPolicy() {
  const pass = document.getElementById('regPassword') ? document.getElementById('regPassword').value : '';
  const confirm = document.getElementById('regConfirmPassword') ? document.getElementById('regConfirmPassword').value : '';

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
      el.style.color = '#15803d'; // Green
      el.innerHTML = '<i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> ' + el.textContent.trim();
    } else {
      el.style.color = 'var(--gray-500)';
      el.innerHTML = '<i class="fa-regular fa-circle"></i> ' + el.textContent.trim();
    }
  };

  updateRule('ruleLen', hasLen);
  updateRule('ruleUpper', hasUpper);
  updateRule('ruleLower', hasLower);
  updateRule('ruleNum', hasNum);
  updateRule('ruleSpecial', hasSpecial);
  updateRule('ruleMatch', hasMatch);

  // Compute score out of 5 core rules
  let score = 0;
  if (hasLen) score++;
  if (hasUpper) score++;
  if (hasLower) score++;
  if (hasNum) score++;
  if (hasSpecial) score++;

  const strLabel = document.getElementById('passStrengthLabel');
  const b1 = document.getElementById('strBar1');
  const b2 = document.getElementById('strBar2');
  const b3 = document.getElementById('strBar3');
  const b4 = document.getElementById('strBar4');

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

// Client-side Form Validation Check on Submit
function handleRegisterSubmit(e) {
  const pass = document.getElementById('regPassword') ? document.getElementById('regPassword').value : '';
  const confirm = document.getElementById('regConfirmPassword') ? document.getElementById('regConfirmPassword').value : '';
  const captcha = document.getElementById('regCaptchaInput') ? document.getElementById('regCaptchaInput').value.trim() : '';
  const alertBox = document.getElementById('authModalAlert');
  const alertText = document.getElementById('authModalAlertText');

  const showError = (msg) => {
    if (alertBox && alertText) {
      alertText.textContent = msg;
      alertBox.style.display = 'block';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  if (pass.length < 8) {
    e.preventDefault();
    showError("Password must be at least 8 characters in length.");
    document.getElementById('regPassword').focus();
    return false;
  }
  if (!/[A-Z]/.test(pass)) {
    e.preventDefault();
    showError("Password must contain at least one uppercase letter (A-Z).");
    document.getElementById('regPassword').focus();
    return false;
  }
  if (!/[a-z]/.test(pass)) {
    e.preventDefault();
    showError("Password must contain at least one lowercase letter (a-z).");
    document.getElementById('regPassword').focus();
    return false;
  }
  if (!/[0-9]/.test(pass)) {
    e.preventDefault();
    showError("Password must contain at least one number (0-9).");
    document.getElementById('regPassword').focus();
    return false;
  }
  if (!/[^A-Za-z0-9]/.test(pass)) {
    e.preventDefault();
    showError("Password must contain at least one special symbol (!@#$%^&*...).");
    document.getElementById('regPassword').focus();
    return false;
  }
  if (pass !== confirm) {
    e.preventDefault();
    showError("Passwords do not match. Please verify your confirmation password.");
    document.getElementById('regConfirmPassword').focus();
    return false;
  }
  if (!captcha) {
    e.preventDefault();
    showError("Please enter the security CAPTCHA characters shown in the image.");
    document.getElementById('regCaptchaInput').focus();
    return false;
  }

  return true;
}

// Switch between modal tabs
function switchAuthTab(tab, subType = 'student') {
  const btnStudent = document.getElementById('tabBtnStudent');
  const btnOfficer = document.getElementById('tabBtnOfficer');
  const contentStudent = document.getElementById('tabContentStudent');
  const contentOfficer = document.getElementById('tabContentOfficer');
  const contentRegister = document.getElementById('tabContentRegister');
  const contentForgot = document.getElementById('tabContentForgot');

  if (!btnStudent || !btnOfficer) return;

  btnStudent.style.background = 'transparent';
  btnStudent.style.color = 'rgba(255,255,255,0.85)';
  btnStudent.style.boxShadow = 'none';

  btnOfficer.style.background = 'transparent';
  btnOfficer.style.color = 'rgba(255,255,255,0.85)';
  btnOfficer.style.boxShadow = 'none';

  if (contentStudent) contentStudent.style.display = 'none';
  if (contentOfficer) contentOfficer.style.display = 'none';
  if (contentRegister) contentRegister.style.display = 'none';
  if (contentForgot) contentForgot.style.display = 'none';

  const alertBox = document.getElementById('authModalAlert');
  if (alertBox) alertBox.style.display = 'none';

  if (tab === 'student') {
    btnStudent.style.background = '#ffffff';
    btnStudent.style.color = 'var(--primary, #641914)';
    btnStudent.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    if (contentStudent) contentStudent.style.display = 'block';
  } else if (tab === 'officer') {
    btnOfficer.style.background = '#ffffff';
    btnOfficer.style.color = 'var(--primary, #641914)';
    btnOfficer.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    if (contentOfficer) contentOfficer.style.display = 'block';
  } else if (tab === 'register') {
    btnStudent.style.background = '#ffffff';
    btnStudent.style.color = 'var(--primary, #641914)';
    btnStudent.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    if (contentRegister) {
      contentRegister.style.display = 'block';
      refreshStudentRegCaptcha();
    }
  } else if (tab === 'forgot') {
    if (contentForgot) {
      contentForgot.style.display = 'block';
      setForgotAccountType(subType === 'officer' || subType === 'leadership' ? 'leadership' : 'student');
      refreshForgotCaptcha();
    }
  }
}

// Toggle Account Type for Forgot Password (Student vs Leadership)
function setForgotAccountType(type) {
  const btnStu = document.getElementById('forgotTypeStudentBtn');
  const btnOff = document.getElementById('forgotTypeOfficerBtn');
  const typeInput = document.getElementById('forgotAccountTypeInput');
  const label = document.getElementById('forgotIdentifierLabel');
  const input = document.getElementById('forgotIdentifierInput');
  const icon = document.getElementById('forgotIdentifierIcon');
  const help = document.getElementById('forgotIdentifierHelp');

  if (type === 'leadership' || type === 'officer') {
    if (typeInput) typeInput.value = 'leadership';
    if (btnOff) {
      btnOff.style.background = '#ffffff';
      btnOff.style.color = 'var(--primary, #641914)';
      btnOff.style.boxShadow = '0 1px 4px rgba(0,0,0,0.12)';
    }
    if (btnStu) {
      btnStu.style.background = 'transparent';
      btnStu.style.color = 'var(--gray-600, #475569)';
      btnStu.style.boxShadow = 'none';
    }
    if (label) label.innerHTML = 'Officer Username or Registered Email <span style="color: red;">*</span>';
    if (input) input.placeholder = 'e.g. sslg_leader or leader@sslg.edu.ph';
    if (icon) icon.className = 'fa-solid fa-user-shield';
    if (help) help.textContent = 'Enter the username or email registered with your leadership officer account.';
  } else {
    if (typeInput) typeInput.value = 'student';
    if (btnStu) {
      btnStu.style.background = '#ffffff';
      btnStu.style.color = 'var(--primary, #641914)';
      btnStu.style.boxShadow = '0 1px 4px rgba(0,0,0,0.12)';
    }
    if (btnOff) {
      btnOff.style.background = 'transparent';
      btnOff.style.color = 'var(--gray-600, #475569)';
      btnOff.style.boxShadow = 'none';
    }
    if (label) label.innerHTML = 'Student LRN / ID or Registered Email <span style="color: red;">*</span>';
    if (input) input.placeholder = 'e.g. 2026-0001 or student@school.ph';
    if (icon) icon.className = 'fa-solid fa-id-card';
    if (help) help.textContent = 'Enter the exact LRN or email you registered with your student account.';
  }
}

// Refresh Forgot Password CAPTCHA
function refreshForgotCaptcha() {
  const img = document.getElementById('forgotCaptchaImg');
  const icon = document.getElementById('forgotCaptchaReloadIcon');
  if (img) {
    if (icon) icon.classList.add('fa-spin');
    const basePath = '<?= $base_path ?>';
    img.src = basePath + 'includes/captcha.php?t=' + new Date().getTime();
    img.onload = () => {
      if (icon) icon.classList.remove('fa-spin');
    };
  }
  const captchaInput = document.getElementById('forgotCaptchaInput');
  if (captchaInput) {
    captchaInput.value = '';
  }
}

// Handle Forgot Password Form Submission via AJAX
function handleForgotPassSubmit(e) {
  e.preventDefault();
  const alertBox = document.getElementById('authModalAlert');
  const alertText = document.getElementById('authModalAlertText');
  const submitBtn = document.getElementById('btnSubmitForgotTicket');
  const ident = document.getElementById('forgotIdentifierInput') ? document.getElementById('forgotIdentifierInput').value.trim() : '';
  const captcha = document.getElementById('forgotCaptchaInput') ? document.getElementById('forgotCaptchaInput').value.trim() : '';
  const accType = document.getElementById('forgotAccountTypeInput') ? document.getElementById('forgotAccountTypeInput').value : 'student';
  const reason = document.getElementById('forgotReasonInput') ? document.getElementById('forgotReasonInput').value.trim() : '';

  const showError = (msg) => {
    if (alertBox && alertText) {
      alertText.textContent = msg;
      alertBox.style.display = 'block';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  if (!ident) {
    showError("Please enter your account identifier (LRN, Username or Email).");
    return false;
  }
  if (!captcha) {
    showError("Please enter the security CAPTCHA code.");
    return false;
  }

  if (alertBox) alertBox.style.display = 'none';

  const origBtnText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting Ticket...';

  const formData = new FormData();
  formData.append('ajax', '1');
  formData.append('account_type', accType);
  formData.append('identifier', ident);
  formData.append('reason', reason);
  formData.append('captcha', captcha);

  const endpoint = '<?= $base_path ?>includes/forgot_password_handler.php';

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
    submitBtn.innerHTML = origBtnText;

    if (data.success) {
      const formEl = document.getElementById('forgotPasswordForm');
      if (formEl) formEl.reset();
      refreshForgotCaptcha();

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Reset Ticket Submitted!',
          html: `<div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">
                   <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                     <strong style="color: #166534; display: block; font-size: 1rem;"><i class="fa-solid fa-ticket"></i> Reset Ticket #${data.ticket_id}</strong>
                     <span style="color: #15803d; font-size: 0.85rem;">Account: <strong>${data.user_name}</strong></span><br>
                     <span style="color: #64748b; font-size: 0.8rem;">Details: ${data.account_details}</span>
                   </div>
                   <p style="margin: 0; color: #334155;">Your password reset request has been logged for <strong>Administrator review</strong>. The admin can verify and reset your password in the admin panel.</p>
                 </div>`,
          confirmButtonColor: '#641914',
          confirmButtonText: 'Return to Sign In'
        }).then(() => {
          switchAuthTab(accType === 'leadership' ? 'officer' : 'student');
        });
      } else {
        alert(data.message);
        switchAuthTab(accType === 'leadership' ? 'officer' : 'student');
      }
    } else {
      refreshForgotCaptcha();
      if (data.is_duplicate && typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: 'Active Ticket Pending',
          text: data.message,
          confirmButtonColor: '#641914'
        });
      } else {
        showError(data.message || 'Failed to submit reset ticket.');
      }
    }
  })
  .catch(err => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = origBtnText;
    refreshForgotCaptcha();
    showError("A network error occurred while submitting your ticket. Please try again.");
  });

  return false;
}

function openAuthModal(initialTab = 'student', subType = 'student') {
  const modal = document.getElementById('authLoginModal');
  if (modal) {
    switchAuthTab(initialTab, subType);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

// Auto open modal and handle URL parameters
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const modalParam = urlParams.get('modal');
  const errorParam = urlParams.get('error');
  const successParam = urlParams.get('success');
  const registeredPending = urlParams.get('registered_pending');
  const lrnParam = urlParams.get('lrn');

  if (registeredPending === '1') {
    openAuthModal('student');
    const successBox = document.getElementById('authModalSuccess');
    const successTitle = document.getElementById('authModalSuccessTitle');
    const successText = document.getElementById('authModalSuccessText');
    if (successBox && successText) {
      if (successTitle) successTitle.textContent = 'Registration Submitted!';
      successText.textContent = `Your student account (LRN: ${lrnParam || 'registered'}) was submitted successfully and is currently pending administrator approval. You will be able to sign in as soon as an admin activates your account.`;
      successBox.style.display = 'block';
    }
    const identInput = document.getElementById('studentLoginIdentifier');
    if (identInput && lrnParam) {
      identInput.value = lrnParam;
    }
  } else if (modalParam) {
    const tab = (modalParam === 'officer_login' || modalParam === 'officer') ? 'officer' : 
                (modalParam === 'register') ? 'register' : 
                (modalParam === 'forgot') ? 'forgot' : 'student';
    openAuthModal(tab);

    if (errorParam) {
      const alertBox = document.getElementById('authModalAlert');
      const alertText = document.getElementById('authModalAlertText');
      if (alertBox && alertText) {
        alertText.textContent = errorParam;
        alertBox.style.display = 'block';
      }
    }

    if (successParam) {
      const successBox = document.getElementById('authModalSuccess');
      const successText = document.getElementById('authModalSuccessText');
      if (successBox && successText) {
        successText.textContent = successParam;
        successBox.style.display = 'block';
      }
    }
  }
});
</script>
