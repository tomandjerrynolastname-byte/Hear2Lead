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

  // Auto hide alerts after 4 seconds
  const autoAlerts = document.querySelectorAll('.alert-auto-dismiss');
  autoAlerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.5s ease';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  });
});

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
