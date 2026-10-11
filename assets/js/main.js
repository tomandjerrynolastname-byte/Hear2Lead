/* assets/js/main.js - Mobile Menu Toggle & Interactive Enhancements */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navbar Toggle
  const mobileToggleBtn = document.querySelector('.mobile-toggle-btn');
  const navLinks = document.querySelector('.nav-links');

  if (mobileToggleBtn && navLinks) {
    mobileToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      navLinks.classList.toggle('active');
      const icon = mobileToggleBtn.querySelector('i');
      if (icon) {
        if (navLinks.classList.contains('active')) {
          icon.className = 'fa-solid fa-xmark';
        } else {
          icon.className = 'fa-solid fa-bars';
        }
      }
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!navLinks.contains(e.target) && !mobileToggleBtn.contains(e.target)) {
        navLinks.classList.remove('active');
        const icon = mobileToggleBtn.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';
      }
    });
  }

  // Profile Dropdown Toggle Functionality
  const profileDropdowns = document.querySelectorAll('.profile-dropdown');
  profileDropdowns.forEach(dropdown => {
    const toggleBtn = dropdown.querySelector('.profile-dropdown-toggle');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        // Close any other open dropdowns
        profileDropdowns.forEach(other => {
          if (other !== dropdown) other.classList.remove('open');
        });
        dropdown.classList.toggle('open');
      });
    }
  });

  // Close profile dropdown when clicking outside
  document.addEventListener('click', (e) => {
    profileDropdowns.forEach(dropdown => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
      }
    });
  });

  // Close dropdowns on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      profileDropdowns.forEach(d => d.classList.remove('open'));
    }
  });

  // Retain user on dashboard/portal root when browser Back button is pressed
  (function () {
    const path = window.location.pathname.toLowerCase();
    const isPortalRoot = path.endsWith('/leadership/dashboard.php') ||
                         path.endsWith('/student/index.php') ||
                         path.endsWith('/leadership/') ||
                         path.endsWith('/student/');

    if (isPortalRoot && document.querySelector('.profile-dropdown')) {
      // Push state so back button action triggers popstate without leaving dashboard
      history.pushState('portalRoot', document.title, location.href);
      window.addEventListener('popstate', function () {
        history.pushState('portalRoot', document.title, location.href);
      });
    }
  })();


  // Modal toggle functionality
  const modalTriggers = document.querySelectorAll('[data-modal-target]');
  const modalCloses = document.querySelectorAll('[data-modal-close]');

  modalTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = trigger.getAttribute('data-modal-target');
      const targetModal = document.getElementById(targetId);
      if (targetModal) {
        targetModal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
      }
    });
  });

  modalCloses.forEach(closeBtn => {
    closeBtn.addEventListener('click', () => {
      const modal = closeBtn.closest('.modal-backdrop');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });

  // Close modal when clicking outside content
  window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
      e.target.classList.remove('active');
      document.body.style.overflow = '';
    }
  });

  // Initialize Global Toast Notification System (Bottom Right)
  initToastSystem();

  // Initialize Global Page Transition Preloader
  initPageLoader();
});

// --- Global Page Transition Loader & Progress Indicator ---
function initPageLoader() {
  let loader = document.getElementById('globalPageLoader');
  let progressBar = document.getElementById('globalProgressBar');

  if (!loader) {
    loader = document.createElement('div');
    loader.id = 'globalPageLoader';
    loader.className = 'page-loader-overlay';
    loader.innerHTML = `
      <div class="page-loader-content">
        <div class="loader-spinner-wrapper">
          <div class="loader-ring"></div>
          <div class="loader-ring"></div>
          <div class="loader-ring"></div>
          <div class="loader-logo-center">
            <i class="fa-solid fa-graduation-cap"></i>
          </div>
        </div>
        <div class="loader-text">Loading...</div>
        <div class="loader-subtext">Please wait a moment</div>
      </div>
    `;
    document.body.appendChild(loader);
  }

  if (!progressBar) {
    progressBar = document.createElement('div');
    progressBar.id = 'globalProgressBar';
    progressBar.className = 'page-progress-bar';
    document.body.appendChild(progressBar);
  }

  function showLoader() {
    if (loader) loader.classList.add('active');
    if (progressBar) progressBar.classList.add('active');
  }

  function hideLoader() {
    if (loader) loader.classList.remove('active');
    if (progressBar) progressBar.classList.remove('active');
  }

  // Expose global helper for programmatic navigation with loader
  window.navigateWithLoader = function (url, delay = 600) {
    showLoader();
    setTimeout(() => {
      window.location.href = url;
    }, delay);
  };

  // Intercept all internal navigation link clicks with smooth display delay
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;

    const href = link.getAttribute('href');
    const target = link.getAttribute('target');

    // Skip non-page transitions
    if (!href) return;
    if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    if (target && target === '_blank') return;
    if (link.hasAttribute('download')) return;
    if (link.hasAttribute('data-modal-target') || link.hasAttribute('data-modal-close')) return;
    if (link.getAttribute('role') === 'button' && href === '#') return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // Open in new tab/window

    // Prevent immediate navigation so the loader is clearly seen
    e.preventDefault();
    showLoader();

    // 600ms delay gives enough time to render and appreciate the smooth spinner
    setTimeout(() => {
      window.location.href = href;
    }, 600);

    // Auto-hide timeout safety after 6 seconds in case navigation is cancelled or slow
    setTimeout(hideLoader, 6000);
  });

  // Intercept form submissions with smooth display delay
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.getAttribute('target') === '_blank') return;
    if (form.dataset.submitting === 'true') return;

    // Check HTML5 validity before preventing submit
    if (form.checkValidity && !form.checkValidity()) {
      return;
    }

    e.preventDefault();
    showLoader();

    setTimeout(() => {
      form.dataset.submitting = 'true';
      form.submit();
    }, 600);
  });

  // Hide on bfcache restore or pageshow
  window.addEventListener('pageshow', () => {
    hideLoader();
  });

  // Hide if user hits Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideLoader();
  });

  // Ensure loader is hidden initially
  hideLoader();
}


// Helper function to dynamically populate edit modal fields
function openTaskEditModal(task) {
  const modal = document.getElementById('editTaskModal');
  if (!modal) return;

  document.getElementById('edit_task_id').value = task.id;
  document.getElementById('edit_task_title').value = task.title;
  document.getElementById('edit_task_description').value = task.description || '';
  document.getElementById('edit_task_status').value = task.status;
  document.getElementById('edit_task_priority').value = task.priority;
  document.getElementById('edit_task_deadline').value = task.deadline;
  document.getElementById('edit_task_assigned_to').value = task.assigned_to || '';
  if (document.getElementById('edit_task_project_id')) {
    document.getElementById('edit_task_project_id').value = task.project_id || '';
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

// Helper to open respond modal for feedbacks
function openFeedbackRespondModal(feedback) {
  const modal = document.getElementById('respondFeedbackModal');
  if (!modal) return;

  document.getElementById('respond_feedback_id').value = feedback.id;
  document.getElementById('respond_student_name').textContent = feedback.student_name;
  document.getElementById('respond_subject').textContent = feedback.subject;
  document.getElementById('respond_message').textContent = feedback.message;
  document.getElementById('respond_status').value = feedback.status;
  document.getElementById('respond_officer_response').value = feedback.officer_response || '';

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

// ==========================================================================
// Interactive Toast Notification System (Bottom Right)
// ==========================================================================
function initToastSystem() {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  // Attach interactive behaviors (countdown timer, pause on hover, close action)
  window.attachToastBehavior = function(toastEl, duration = 4500) {
    if (!toastEl) return;
    
    // Ensure element resides inside the bottom-right toast container
    if (toastEl.parentElement !== container) {
      container.appendChild(toastEl);
    }

    const isDanger = toastEl.classList.contains('alert-danger') || toastEl.classList.contains('toast-danger');
    const isSuccess = !isDanger;

    // Transform plain alert markup into structured toast if needed
    if (!toastEl.querySelector('.toast-body')) {
      const clone = toastEl.cloneNode(true);
      clone.querySelectorAll('i, svg, .close-btn, button').forEach(el => el.remove());
      const messageText = clone.textContent.trim();
      const iconClass = isDanger ? 'fa-circle-exclamation' : 'fa-circle-check';
      const titleText = isDanger ? 'Notice' : 'Success';

      toastEl.innerHTML = `
        <div class="toast-icon"><i class="fa-solid ${iconClass}"></i></div>
        <div class="toast-body">
          <div class="toast-title">${titleText}</div>
          <div class="toast-message">${messageText}</div>
        </div>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
      `;
    } else if (!toastEl.querySelector('.toast-progress')) {
      const p = document.createElement('div');
      p.className = 'toast-progress';
      p.innerHTML = '<div class="toast-progress-bar"></div>';
      toastEl.appendChild(p);
    }

    // Set duration on progress bar
    const pBar = toastEl.querySelector('.toast-progress-bar');
    if (pBar) {
      pBar.style.animationDuration = `${duration}ms`;
    }

    let timerId = null;
    let isDismissed = false;

    function dismissToast() {
      if (isDismissed) return;
      isDismissed = true;
      if (timerId) clearTimeout(timerId);
      toastEl.classList.add('toast-hiding');
      setTimeout(() => {
        toastEl.remove();
      }, 350);
    }

    // Close button event
    const closeBtn = toastEl.querySelector('.toast-close');
    if (closeBtn) {
      closeBtn.onclick = function(e) {
        e.stopPropagation();
        dismissToast();
      };
    }

    // Countdown timer with hover pause
    let startTime = Date.now();
    let remainingTime = duration;

    function startTimer() {
      startTime = Date.now();
      timerId = setTimeout(dismissToast, remainingTime);
    }

    function pauseTimer() {
      if (timerId) clearTimeout(timerId);
      remainingTime -= (Date.now() - startTime);
      if (remainingTime < 500) remainingTime = 500;
      toastEl.classList.add('toast-paused');
    }

    function resumeTimer() {
      toastEl.classList.remove('toast-paused');
      startTimer();
    }

    toastEl.addEventListener('mouseenter', pauseTimer);
    toastEl.addEventListener('mouseleave', resumeTimer);

    startTimer();
  };

  // Convert and mount all existing alert/toast elements on page load
  const existingAlerts = document.querySelectorAll('.alert-auto-dismiss, .toast');
  existingAlerts.forEach(el => window.attachToastBehavior(el));
}

// Global programmatic showToast helper
window.showToast = function(message, type = 'success', duration = 4500) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  const isDanger = type === 'danger' || type === 'error';
  const typeClass = isDanger ? 'toast-danger' : 'toast-success';
  const iconClass = isDanger ? 'fa-circle-exclamation' : 'fa-circle-check';
  const title = isDanger ? 'Notice' : 'Success';

  toast.className = `toast ${typeClass} alert-auto-dismiss`;
  toast.setAttribute('role', 'alert');
  toast.innerHTML = `
    <div class="toast-icon"><i class="fa-solid ${iconClass}"></i></div>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  `;

  container.appendChild(toast);
  window.attachToastBehavior(toast, duration);
};
