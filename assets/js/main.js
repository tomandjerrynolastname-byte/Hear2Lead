/* assets/js/main.js - Mobile Menu Toggle & Interactive Enhancements */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navbar Toggle
  const mobileToggleBtn = document.querySelector('.mobile-toggle-btn');
  const navLinks = document.querySelector('.nav-links');

  if (mobileToggleBtn && navLinks) {
    mobileToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = navLinks.classList.toggle('active');
      mobileToggleBtn.classList.toggle('active', isOpen);
      mobileToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      const icon = mobileToggleBtn.querySelector('i');
      if (icon) {
        icon.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
      }
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!navLinks.contains(e.target) && !mobileToggleBtn.contains(e.target)) {
        navLinks.classList.remove('active');
        mobileToggleBtn.classList.remove('active');
        mobileToggleBtn.setAttribute('aria-expanded', 'false');
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

  // Global helper for SweetAlert2 delete confirmation on forms
  window.confirmDeleteSwal = function (e, message, title = 'Confirm Deletion') {
    if (e && e.target) {
      const form = e.target.closest('form') || e.target;
      if (form.dataset.swalConfirmed === 'true') {
        return true;
      }
      e.preventDefault();
      e.stopPropagation();

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: title,
          text: message || 'Are you sure you want to delete this item? This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#64748b',
          confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Yes, Delete',
          cancelButtonText: 'Cancel',
          reverseButtons: true,
          focusCancel: true
        }).then((result) => {
          if (result.isConfirmed) {
            form.dataset.swalConfirmed = 'true';
            form.dataset.submitting = 'true';
            showLoader();
            setTimeout(() => {
              form.submit();
            }, 400);
          }
        });
      } else {
        if (window.confirm(message || 'Are you sure you want to delete this item?')) {
          form.dataset.swalConfirmed = 'true';
          form.dataset.submitting = 'true';
          showLoader();
          form.submit();
        }
      }
      return false;
    }
    return false;
  };

  // Intercept form submissions with smooth display delay & SweetAlert2 delete guard
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented) return;
    if (form.getAttribute('target') === '_blank') return;
    if (form.dataset.submitting === 'true') return;

    // Automatically intercept any delete form if not yet confirmed via SweetAlert2
    const actionInput = form.querySelector('input[name="action"]');
    const isDeleteAction = actionInput && actionInput.value && actionInput.value.indexOf('delete_') === 0;
    if ((form.hasAttribute('data-confirm-delete') || isDeleteAction) && form.dataset.swalConfirmed !== 'true') {
      const msg = form.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item? This action cannot be undone.';
      const title = form.getAttribute('data-confirm-title') || 'Confirm Deletion';
      window.confirmDeleteSwal(e, msg, title);
      return;
    }

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


// Helper function to dynamically populate edit modal fields (if not overridden by page)
if (typeof window.openTaskEditModal !== 'function') {
  window.openTaskEditModal = function(task) {
    const modal = document.getElementById('editTaskModal');
    if (!modal) return;

    document.getElementById('edit_task_id').value = task.id;
    document.getElementById('edit_task_title').value = task.title;
    document.getElementById('edit_task_description').value = task.description || '';
    document.getElementById('edit_task_status').value = task.status;
    document.getElementById('edit_task_priority').value = task.priority;
    document.getElementById('edit_task_deadline').value = task.deadline;
    if (document.getElementById('edit_task_assigned_to')) {
      document.getElementById('edit_task_assigned_to').value = task.assigned_to || '';
    }
    if (document.getElementById('edit_task_project_id')) {
      document.getElementById('edit_task_project_id').value = task.project_id || '';
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  };
}

// Helper to open respond modal for feedbacks (if not overridden by page)
if (typeof window.openFeedbackRespondModal !== 'function') {
  window.openFeedbackRespondModal = function(feedback) {
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
  };
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

// Global helper for Show More / Show Less on Vertical Stepper Logs
window.toggleStepperLogs = function(btn) {
  if (!btn) return;
  const wrapper = btn.closest('.log-stepper-container');
  if (!wrapper) return;
  const stepper = wrapper.querySelector('.log-stepper');
  if (!stepper) return;

  const limit = parseInt(stepper.getAttribute('data-initial-limit') || '1', 10);
  const isExpanded = btn.getAttribute('data-expanded') === 'true';
  const steps = Array.from(stepper.querySelectorAll('.log-step'));
  const hiddenCount = Math.max(0, steps.length - limit);

  if (isExpanded) {
    steps.forEach((step, idx) => {
      if (idx >= limit) {
        step.classList.add('log-step-hidden');
      }
      if (idx === limit - 1 && steps.length > limit) {
        step.classList.add('log-step-last-visible');
      } else {
        step.classList.remove('log-step-last-visible');
      }
    });
    btn.setAttribute('data-expanded', 'false');
    btn.innerHTML = `<i class="fa-solid fa-chevron-down"></i> Show More (${hiddenCount} more ${hiddenCount === 1 ? 'log' : 'logs'})`;
  } else {
    steps.forEach(step => {
      step.classList.remove('log-step-hidden');
      step.classList.remove('log-step-last-visible');
    });
    btn.setAttribute('data-expanded', 'true');
    btn.innerHTML = `<i class="fa-solid fa-chevron-up"></i> Show Less`;
  }
};

// Header Notification Bell Tabs & Mark-As-Read Logic
window.switchHeaderNotifTab = function(tab) {
  const btnTasks = document.getElementById('notifTabBtnTasks');
  const btnLogins = document.getElementById('notifTabBtnLogins');
  const btnAct = document.getElementById('notifTabBtnActivity');
  const paneTasks = document.getElementById('notifPaneTasks');
  const paneLogins = document.getElementById('notifPaneLogins');
  const paneAct = document.getElementById('notifPaneActivity');

  [btnTasks, btnLogins, btnAct].forEach(b => { if (b) b.classList.remove('active'); });
  [paneTasks, paneLogins, paneAct].forEach(p => { if (p) p.style.display = 'none'; });

  if (tab === 'activity') {
    if (btnAct) btnAct.classList.add('active');
    if (paneAct) paneAct.style.display = 'block';
  } else if (tab === 'logins') {
    if (btnLogins) btnLogins.classList.add('active');
    if (paneLogins) paneLogins.style.display = 'block';
  } else {
    if (btnTasks) btnTasks.classList.add('active');
    if (paneTasks) paneTasks.style.display = 'block';
  }
};

window.markHeaderNotifsRead = function(latestId) {
  const badge = document.getElementById('headerNotifBadge');
  if (badge) {
    badge.style.display = 'none';
  }
  try {
    localStorage.setItem('hear2lead_read_notif_id', String(latestId || 0));
  } catch (e) {}
};

document.addEventListener('DOMContentLoaded', () => {
  const notifDrop = document.getElementById('headerNotifDropdown');
  const badge = document.getElementById('headerNotifBadge');
  if (notifDrop && badge) {
    const latestId = parseInt(notifDrop.getAttribute('data-latest-id') || '0', 10);
    try {
      const readId = parseInt(localStorage.getItem('hear2lead_read_notif_id') || '0', 10);
      if (latestId > 0 && readId >= latestId) {
        badge.style.display = 'none';
      }
    } catch (e) {}
  }
});



// ==========================================================================
// Universal Table Pagination (5 Rows Per Page)
// ==========================================================================
window.initTablePagination = function(defaultPageSize = 5) {
  const tables = document.querySelectorAll('table.table');
  tables.forEach((table) => {
    if (table.dataset.noPagination === 'true') return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const allRows = Array.from(tbody.querySelectorAll(':scope > tr'));
    // Exclude "empty state" rows that span across all columns
    const dataRows = allRows.filter((row) => {
      const cells = row.querySelectorAll(':scope > td');
      if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
        return false;
      }
      return true;
    });

    const container = table.closest('.table-container') || table.parentElement;
    if (!container) return;

    // Remove existing pagination bar if re-initializing
    const existingBar = container.querySelector(':scope > .table-pagination-bar');
    if (existingBar) {
      existingBar.remove();
    }

    if (dataRows.length === 0) {
      return;
    }

    const pageSize = parseInt(table.getAttribute('data-page-size') || String(defaultPageSize), 10) || 5;
    const totalRows = dataRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
    let currentPage = 1;

    const paginationBar = document.createElement('div');
    paginationBar.className = 'table-pagination-bar';
    container.appendChild(paginationBar);

    function getPageNumbers(current, total) {
      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }
      const pages = [1];
      if (current > 3) pages.push('...');
      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      if (current < total - 2) pages.push('...');
      pages.push(total);
      return pages;
    }

    function renderPage(page) {
      currentPage = Math.min(Math.max(1, page), totalPages);
      table.dataset.currentPage = String(currentPage);
      const startIdx = (currentPage - 1) * pageSize;
      const endIdx = Math.min(startIdx + pageSize, totalRows);

      dataRows.forEach((row, idx) => {
        if (idx >= startIdx && idx < endIdx) {
          row.classList.remove('table-paginated-hidden');
        } else {
          row.classList.add('table-paginated-hidden');
        }
      });

      const pageNums = getPageNumbers(currentPage, totalPages);
      let buttonsHtml = `
        <button type="button" class="table-page-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
          <i class="fa-solid fa-chevron-left" style="font-size: 0.68rem;"></i> Prev
        </button>
      `;

      pageNums.forEach((p) => {
        if (p === '...') {
          buttonsHtml += `<span class="table-page-ellipsis">&hellip;</span>`;
        } else {
          const activeClass = p === currentPage ? 'active' : '';
          buttonsHtml += `<button type="button" class="table-page-btn ${activeClass}" data-page="${p}">${p}</button>`;
        }
      });

      buttonsHtml += `
        <button type="button" class="table-page-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
          Next <i class="fa-solid fa-chevron-right" style="font-size: 0.68rem;"></i>
        </button>
      `;

      paginationBar.innerHTML = `
        <div class="table-pagination-info">
          Showing <strong>${startIdx + 1}</strong> to <strong>${endIdx}</strong> of <strong>${totalRows}</strong> ${totalRows === 1 ? 'entry' : 'entries'}
          <span style="color: var(--gray-400); margin-left: 0.25rem;">(${pageSize} per page)</span>
        </div>
        <div class="table-pagination-controls">
          ${buttonsHtml}
        </div>
      `;

      paginationBar.querySelectorAll('.table-page-btn:not(:disabled)').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const targetPage = parseInt(btn.getAttribute('data-page') || '1', 10);
          if (targetPage && targetPage !== currentPage) {
            renderPage(targetPage);
          }
        });
      });
    }

    const initialPage = parseInt(table.dataset.currentPage || '1', 10) || 1;
    renderPage(initialPage);
  });
};

// ==========================================================================
// Real-Time Data Tunneling & Live Table/View Auto-Reload Engine
// ==========================================================================
(function() {
  let lastKnownStateHash = null;
  let isSyncInProgress = false;
  let hasPendingSync = false;
  let syncIntervalId = null;
  let indicatorTimerId = null;

  function getLiveSyncEndpointUrl() {
    const path = window.location.pathname.toLowerCase();
    if (path.includes('/leadership/') || path.includes('/student/')) {
      return '../includes/live_sync.php';
    }
    return 'includes/live_sync.php';
  }

  function isUserBusyWithUI() {
    // 1. Check if any modal is open
    if (document.querySelector('.modal-backdrop.active')) return true;
    // 2. Check if SweetAlert2 dialog is open
    if (document.querySelector('.swal2-container')) return true;
    // 3. Check if any calendar popover or multi-select or profile/notif dropdown is open
    if (document.querySelector('.calendar-popover.active, .multi-select-container.open, .profile-dropdown.open')) return true;
    // 4. Check if user is focused inside an interactive form field or contenteditable
    const active = document.activeElement;
    if (active && active !== document.body) {
      const tag = (active.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || active.isContentEditable) {
        return true;
      }
    }
    // 5. Check if any inline student reply box is open
    const openReplyBoxes = document.querySelectorAll('[id^="studentReplyBox_"]');
    for (let i = 0; i < openReplyBoxes.length; i++) {
      if (openReplyBoxes[i].style.display === 'block') return true;
    }
    return false;
  }

  function showLiveSyncIndicator(text = 'Live synced to latest data') {
    let pill = document.getElementById('liveSyncStatusPill');
    if (!pill) {
      pill = document.createElement('div');
      pill.id = 'liveSyncStatusPill';
      pill.className = 'live-sync-indicator';
      pill.innerHTML = `<span class="live-sync-dot"></span><span id="liveSyncStatusText"></span>`;
      document.body.appendChild(pill);
    }
    const textEl = document.getElementById('liveSyncStatusText');
    if (textEl) textEl.textContent = text;
    pill.classList.add('visible');
    if (indicatorTimerId) clearTimeout(indicatorTimerId);
    indicatorTimerId = setTimeout(() => {
      pill.classList.remove('visible');
    }, 2400);
  }

  function normalizeHtmlForComparison(el) {
    if (!el) return '';
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.table-pagination-bar').forEach(b => b.remove());
    clone.querySelectorAll('.table-paginated-hidden').forEach(r => r.classList.remove('table-paginated-hidden'));
    clone.querySelectorAll('.live-sync-updated').forEach(u => u.classList.remove('live-sync-updated'));
    clone.querySelectorAll('[data-current-page]').forEach(t => t.removeAttribute('data-current-page'));
    return clone.innerHTML.replace(/\s+/g, ' ').trim();
  }

  function syncScriptDataConstants(newDoc) {
    const scripts = newDoc.querySelectorAll('script:not([src])');
    const targetVars = [
      'ORG_USERS',
      'ORG_PROJECTS',
      'ALL_TASKS',
      'TASK_LOGS_MAP',
      'ALL_PROJECTS',
      'PROJECT_TASKS_MAP',
      'PROJECT_LOGS_MAP',
      'ALL_ACCOMPLISHMENTS',
      'ACC_LOGS_MAP',
      'ALL_FEEDBACKS',
      'FEEDBACK_LOGS_MAP',
      'LEADERSHIP_FB_DATES',
      'REPORT_ACTIVITY_DATES',
      'STUDENT_LOGS_MAP',
      'STUDENT_FB_DATES'
    ];

    scripts.forEach(scr => {
      const code = scr.textContent || '';
      targetVars.forEach(varName => {
        const regex = new RegExp('const\\s+' + varName + '\\s*=\\s*([\\s\\S]*?);\\s*(?:\\r?\\n|$)');
        const match = code.match(regex);
        if (match && match[1]) {
          try {
            const parsed = JSON.parse(match[1]);
            // Mutate global lexical constant in-place using Function evaluator
            const updater = new Function('newVal', `
              if (typeof ${varName} !== 'undefined' && ${varName} !== null) {
                if (Array.isArray(${varName}) && Array.isArray(newVal)) {
                  ${varName}.splice(0, ${varName}.length, ...newVal);
                } else if (typeof ${varName} === 'object' && typeof newVal === 'object') {
                  Object.keys(${varName}).forEach(k => delete ${varName}[k]);
                  Object.assign(${varName}, newVal);
                }
              }
            `);
            updater(parsed);
          } catch (e) {}
        }
      });
    });
  }

  function syncHeaderAndNotifications(newDoc) {
    let changed = false;

    // 1. Update Users nav link pending student badge
    const oldUsersLink = document.querySelector('.nav-links a[href*="users.php"]');
    const newUsersLink = newDoc.querySelector('.nav-links a[href*="users.php"]');
    if (oldUsersLink && newUsersLink && oldUsersLink.innerHTML.trim() !== newUsersLink.innerHTML.trim()) {
      oldUsersLink.innerHTML = newUsersLink.innerHTML;
      changed = true;
    }

    // 2. Update Header Notification Bell & Dropdown
    const oldNotifDrop = document.getElementById('headerNotifDropdown');
    const newNotifDrop = newDoc.getElementById('headerNotifDropdown');
    if (oldNotifDrop && newNotifDrop && !oldNotifDrop.classList.contains('open')) {
      const oldLatestId = oldNotifDrop.getAttribute('data-latest-id') || '0';
      const newLatestId = newNotifDrop.getAttribute('data-latest-id') || '0';
      if (oldLatestId !== newLatestId || normalizeHtmlForComparison(oldNotifDrop) !== normalizeHtmlForComparison(newNotifDrop)) {
        oldNotifDrop.setAttribute('data-latest-id', newLatestId);
        const oldMenu = oldNotifDrop.querySelector('.profile-dropdown-menu');
        const newMenu = newNotifDrop.querySelector('.profile-dropdown-menu');
        if (oldMenu && newMenu) {
          oldMenu.innerHTML = newMenu.innerHTML;
        }
        const oldBtn = oldNotifDrop.querySelector('.notif-bell-btn');
        const newBtn = newNotifDrop.querySelector('.notif-bell-btn');
        if (oldBtn && newBtn) {
          oldBtn.innerHTML = newBtn.innerHTML;
        }
        const badge = document.getElementById('headerNotifBadge');
        if (badge) {
          try {
            const readId = parseInt(localStorage.getItem('hear2lead_read_notif_id') || '0', 10);
            const latestNum = parseInt(newLatestId, 10);
            if (latestNum > 0 && readId >= latestNum) {
              badge.style.display = 'none';
            }
          } catch (e) {}
        }
        changed = true;
      }
    }

    return changed;
  }

  window.syncPageContentToLatest = async function() {
    if (isSyncInProgress) return false;
    isSyncInProgress = true;
    let anyUpdated = false;

    try {
      const response = await fetch(window.location.href, {
        method: 'GET',
        cache: 'no-store',
        headers: {
          'X-Live-Sync': '1',
          'Cache-Control': 'no-cache'
        }
      });
      if (!response.ok) return false;

      const htmlText = await response.text();
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(htmlText, 'text/html');

      // Save current pagination page for every table on screen
      const savedTablePages = Array.from(document.querySelectorAll('table.table')).map(
        t => t.dataset.currentPage || '1'
      );

      // 1. Update in-memory JS constants for modals & logs
      syncScriptDataConstants(newDoc);

      // 2. Sync Header Notifications & Nav Badges
      if (syncHeaderAndNotifications(newDoc)) {
        anyUpdated = true;
      }

      // 3. Sync Tab Counter Badges (.user-tabs-nav .user-tab-badge)
      const oldTabNavs = document.querySelectorAll('.user-tabs-nav');
      const newTabNavs = newDoc.querySelectorAll('.user-tabs-nav');
      oldTabNavs.forEach((oldNav, navIdx) => {
        const newNav = newTabNavs[navIdx];
        if (!newNav) return;
        const oldBadges = oldNav.querySelectorAll('.user-tab-badge');
        const newBadges = newNav.querySelectorAll('.user-tab-badge');
        oldBadges.forEach((oldB, bIdx) => {
          const newB = newBadges[bIdx];
          if (newB && oldB.textContent.trim() !== newB.textContent.trim()) {
            oldB.textContent = newB.textContent;
            oldB.style.background = newB.style.background;
            oldB.style.color = newB.style.color;
            anyUpdated = true;
          }
        });
      });

      // 4. Sync Tab Panes (.user-tab-pane) while preserving active tab
      const oldPanes = document.querySelectorAll('.user-tab-pane');
      oldPanes.forEach((oldPane, idx) => {
        const paneId = oldPane.id;
        const newPane = paneId ? newDoc.getElementById(paneId) : newDoc.querySelectorAll('.user-tab-pane')[idx];
        if (newPane && normalizeHtmlForComparison(oldPane) !== normalizeHtmlForComparison(newPane)) {
          oldPane.innerHTML = newPane.innerHTML;
          oldPane.classList.add('live-sync-updated');
          anyUpdated = true;
        }
      });

      // 5. Sync KPI Stat Grids (.stats-grid)
      const oldStatGrids = document.querySelectorAll('.stats-grid');
      const newStatGrids = newDoc.querySelectorAll('.stats-grid');
      oldStatGrids.forEach((oldGrid, idx) => {
        const newGrid = newStatGrids[idx];
        if (newGrid && normalizeHtmlForComparison(oldGrid) !== normalizeHtmlForComparison(newGrid)) {
          oldGrid.innerHTML = newGrid.innerHTML;
          oldGrid.classList.add('live-sync-updated');
          anyUpdated = true;
        }
      });

      // 6. Sync Tables outside .user-tab-pane (e.g., reports.php, notifications.php, dashboard.php)
      const oldStandaloneTables = Array.from(document.querySelectorAll('table.table')).filter(t => !t.closest('.user-tab-pane'));
      const newStandaloneTables = Array.from(newDoc.querySelectorAll('table.table')).filter(t => !t.closest('.user-tab-pane'));
      oldStandaloneTables.forEach((oldTable, idx) => {
        const newTable = newStandaloneTables[idx];
        if (newTable && normalizeHtmlForComparison(oldTable) !== normalizeHtmlForComparison(newTable)) {
          oldTable.innerHTML = newTable.innerHTML;
          oldTable.classList.add('live-sync-updated');
          anyUpdated = true;
        }
      });

      // 7. Sync Panel Headers outside .user-tab-pane
      const oldPanelHeaders = Array.from(document.querySelectorAll('.panel-header')).filter(h => !h.closest('.user-tab-pane'));
      const newPanelHeaders = Array.from(newDoc.querySelectorAll('.panel-header')).filter(h => !h.closest('.user-tab-pane'));
      oldPanelHeaders.forEach((oldH, idx) => {
        const newH = newPanelHeaders[idx];
        if (newH && normalizeHtmlForComparison(oldH) !== normalizeHtmlForComparison(newH)) {
          oldH.innerHTML = newH.innerHTML;
          anyUpdated = true;
        }
      });

      // 8. Sync Dynamic Non-Table Containers inside <main class="main-content"> (e.g., .layout-split, .card-grid, feedbacks list)
      const oldMain = document.querySelector('main.main-content');
      const newMain = newDoc.querySelector('main.main-content');
      if (oldMain && newMain) {
        const skipSelector = '.page-header, .hero-card, .filter-bar, .stats-grid, .user-tabs-nav, .user-tab-pane, .modal-backdrop, script, style, .toast';
        const oldBlocks = Array.from(oldMain.children).filter(el => !el.matches(skipSelector) && !el.querySelector('.user-tab-pane'));
        const newBlocks = Array.from(newMain.children).filter(el => !el.matches(skipSelector) && !el.querySelector('.user-tab-pane'));
        oldBlocks.forEach((oldBlock, idx) => {
          const newBlock = newBlocks[idx];
          if (!newBlock) return;
          // If this block contains a standalone table, we already synced the table above unless transitioning from empty state
          const hadTable = !!oldBlock.querySelector('table.table');
          const hasTableNow = !!newBlock.querySelector('table.table');
          if ((!hadTable || !hasTableNow) && normalizeHtmlForComparison(oldBlock) !== normalizeHtmlForComparison(newBlock)) {
            oldBlock.innerHTML = newBlock.innerHTML;
            oldBlock.classList.add('live-sync-updated');
            anyUpdated = true;
          }
        });
      }

      // 9. Sync Official Print Report Sheet (leadership/print_report.php) if present
      const printSectionIds = ['sec-kpis', 'sec-officers', 'sec-tasks', 'sec-projects', 'sec-accomplishments', 'sec-logs'];
      printSectionIds.forEach(secId => {
        const oldSec = document.getElementById(secId);
        const newSec = newDoc.getElementById(secId);
        if (oldSec && newSec && normalizeHtmlForComparison(oldSec) !== normalizeHtmlForComparison(newSec)) {
          oldSec.innerHTML = newSec.innerHTML;
          anyUpdated = true;
        }
      });

      // 10. Restore saved table pagination pages and re-initialize 5-per-page paginator
      const updatedTables = document.querySelectorAll('table.table');
      updatedTables.forEach((t, idx) => {
        if (savedTablePages[idx]) {
          t.dataset.currentPage = savedTablePages[idx];
        }
      });
      window.initTablePagination(5);

      if (anyUpdated) {
        showLiveSyncIndicator('Updated to latest changes');
      }
      return anyUpdated;
    } catch (err) {
      return false;
    } finally {
      isSyncInProgress = false;
    }
  };

  async function pollLiveSyncTunnel() {
    if (document.visibilityState === 'hidden') return;

    // If a sync was deferred while the user was busy in a modal/input, run it as soon as they finish
    if (hasPendingSync && !isUserBusyWithUI()) {
      hasPendingSync = false;
      await window.syncPageContentToLatest();
      return;
    }

    try {
      const endpoint = getLiveSyncEndpointUrl();
      const res = await fetch(`${endpoint}?_t=${Date.now()}`, {
        method: 'GET',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || data.status !== 'ok' || !data.state_hash) return;

      if (lastKnownStateHash === null) {
        lastKnownStateHash = data.state_hash;
        return;
      }

      if (data.state_hash !== lastKnownStateHash) {
        lastKnownStateHash = data.state_hash;
        if (isUserBusyWithUI()) {
          hasPendingSync = true;
        } else {
          await window.syncPageContentToLatest();
        }
      }
    } catch (e) {
      // Ignore transient network errors during background polling
    }
  }

  window.initLiveSyncTunnel = function(intervalMs = 3000) {
    if (syncIntervalId) clearInterval(syncIntervalId);
    pollLiveSyncTunnel();
    syncIntervalId = setInterval(pollLiveSyncTunnel, intervalMs);

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        pollLiveSyncTunnel();
      }
    });

    // Trigger deferred sync immediately when user closes a modal or leaves an input field
    document.addEventListener('focusout', () => {
      if (hasPendingSync && !isUserBusyWithUI()) {
        hasPendingSync = false;
        window.syncPageContentToLatest();
      }
    });
    document.addEventListener('click', () => {
      if (hasPendingSync && !isUserBusyWithUI()) {
        setTimeout(() => {
          if (hasPendingSync && !isUserBusyWithUI()) {
            hasPendingSync = false;
            window.syncPageContentToLatest();
          }
        }, 150);
      }
    });
  };
})();

document.addEventListener('DOMContentLoaded', () => {
  window.initTablePagination(5);
  window.initLiveSyncTunnel(3000);
});
