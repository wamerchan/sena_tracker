// assets/js/main.js - ADSO SENA Tracker - Global UI System

document.addEventListener('DOMContentLoaded', () => {
  initDarkMode();
  initMobileSidebar();
  initScrollAnimations();
  initButtonRipple();
});

// ============================================
// DARK MODE SYSTEM
// ============================================
function initDarkMode() {
  const themeToggleBtn = document.getElementById('theme-toggle');
  const htmlEl = document.documentElement;

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
      htmlEl.classList.toggle('dark');
      const isDark = htmlEl.classList.contains('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      updateThemeButton(themeToggleBtn, isDark);
    });
  }
}

function updateThemeButton(btn, isDark) {
  if (!btn) return;
  btn.innerHTML = isDark
    ? '<i class="fa-solid fa-sun text-yellow-400"></i> <span class="font-medium text-sm">Modo Claro</span>'
    : '<i class="fa-solid fa-moon text-indigo-500"></i> <span class="font-medium text-sm">Modo Oscuro</span>';
}

// ============================================
// MOBILE SIDEBAR
// ============================================
function initMobileSidebar() {
  const mobileBtn = document.getElementById('mobile-menu-btn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (mobileBtn && sidebar && overlay) {
    const toggleSidebar = () => {
      sidebar.classList.toggle('-translate-x-full');
      overlay.classList.toggle('hidden');
      document.body.classList.toggle('overflow-hidden');
    };

    mobileBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
  }
}

// ============================================
// TOAST NOTIFICATION SYSTEM
// ============================================
const ToastSystem = {
  container: null,

  init() {
    if (this.container) return;
    this.container = document.getElementById('toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(type, title, message, duration = 4000) {
    this.init();

    const icons = {
      success: 'fa-circle-check',
      error: 'fa-circle-xmark',
      warning: 'fa-triangle-exclamation',
      info: 'fa-circle-info'
    };

    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type} relative`;
    toast.innerHTML = `
      <i class="fa-solid ${icons[type] || icons.info} toast-icon"></i>
      <div class="toast-content">
        <div class="toast-title">${title}</div>
        ${message ? `<div class="toast-message">${message}</div>` : ''}
      </div>
      <button class="toast-close" onclick="ToastSystem.dismiss(this.parentElement)">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="toast-progress bg-current opacity-20" style="animation-duration: ${duration}ms;"></div>
    `;

    this.container.appendChild(toast);

    // Auto dismiss
    const timeout = setTimeout(() => this.dismiss(toast), duration);

    // Pause on hover
    toast.addEventListener('mouseenter', () => clearTimeout(timeout));
    toast.addEventListener('mouseleave', () => {
      setTimeout(() => this.dismiss(toast), 1500);
    });

    return toast;
  },

  dismiss(toast) {
    if (!toast || !toast.parentElement) return;
    toast.classList.add('toast-exit');
    setTimeout(() => {
      if (toast.parentElement) toast.remove();
    }, 300);
  },

  success(title, message) { return this.show('success', title, message); },
  error(title, message) { return this.show('error', title, message); },
  warning(title, message) { return this.show('warning', title, message); },
  info(title, message) { return this.show('info', title, message); }
};

// Make ToastSystem globally accessible
window.ToastSystem = ToastSystem;

// ============================================
// DELETE CONFIRMATION MODAL (replaces browser confirm())
// ============================================
function showDeleteConfirm(options = {}) {
  const {
    title = 'Confirmar Eliminacion',
    message = 'Esta accion no se puede deshacer.',
    confirmText = 'Si, Eliminar',
    cancelText = 'Cancelar',
    onConfirm = () => {},
    onCancel = () => {}
  } = options;

  // Remove existing delete modal if any
  const existing = document.getElementById('deleteConfirmModal');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'deleteConfirmModal';
  overlay.className = 'fixed inset-0 z-[60] flex items-center justify-center delete-modal-overlay transition-all duration-300';
  overlay.style.opacity = '0';

  overlay.innerHTML = `
    <div class="modal-container max-w-md mx-4" style="opacity: 0; transform: translateY(16px) scale(0.95);">
      <div class="bg-gradient-to-r from-red-500 to-rose-500 px-6 py-5 text-white rounded-t-2xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
            <i class="fa-solid fa-trash-can text-lg"></i>
          </div>
          <div>
            <h3 class="font-bold text-lg">${title}</h3>
            <p class="text-red-100 text-sm">Accion permanente</p>
          </div>
        </div>
      </div>
      <div class="p-6">
        <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-6">${message}</p>
        <div class="flex gap-3 justify-end">
          <button id="deleteCancelBtn" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg font-semibold text-sm transition-all duration-200">
            ${cancelText}
          </button>
          <button id="deleteConfirmBtn" class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600 text-white rounded-lg font-semibold text-sm shadow-md shadow-red-500/30 transition-all duration-200 hover:-translate-y-0.5">
            <i class="fa-solid fa-trash-can mr-1.5"></i> ${confirmText}
          </button>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);
  document.body.classList.add('overflow-hidden');

  // Animate in
  requestAnimationFrame(() => {
    overlay.style.opacity = '1';
    const container = overlay.querySelector('.modal-container');
    if (container) {
      container.style.opacity = '1';
      container.style.transform = 'translateY(0) scale(1)';
    }
  });

  const closeModal = () => {
    overlay.style.opacity = '0';
    const container = overlay.querySelector('.modal-container');
    if (container) {
      container.style.opacity = '0';
      container.style.transform = 'translateY(16px) scale(0.95)';
    }
    setTimeout(() => {
      overlay.remove();
      document.body.classList.remove('overflow-hidden');
    }, 300);
  };

  overlay.querySelector('#deleteConfirmBtn').addEventListener('click', () => {
    closeModal();
    onConfirm();
  });

  overlay.querySelector('#deleteCancelBtn').addEventListener('click', () => {
    closeModal();
    onCancel();
  });

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      closeModal();
      onCancel();
    }
  });
}

window.showDeleteConfirm = showDeleteConfirm;

// ============================================
// SCROLL ANIMATIONS (Intersection Observer)
// ============================================
function initScrollAnimations() {
  const observerOptions = {
    root: null,
    rootMargin: '0px 0px -50px 0px',
    threshold: 0.1
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-fade-in-up');
        entry.target.style.opacity = '1';
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Observe elements with data-animate attribute
  document.querySelectorAll('[data-animate]').forEach(el => {
    el.style.opacity = '0';
    observer.observe(el);
  });
}

// ============================================
// BUTTON RIPPLE EFFECT
// ============================================
function initButtonRipple() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-ripple');
    if (!btn) return;

    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const size = Math.max(rect.width, rect.height);

    const ripple = document.createElement('span');
    ripple.className = 'ripple-circle';
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (x - size / 2) + 'px';
    ripple.style.top = (y - size / 2) + 'px';

    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  });
}

// ============================================
// COUNTER ANIMATION (for dashboard metrics)
// ============================================
function animateCounter(element, target, duration = 1000) {
  if (!element) return;
  const start = 0;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
    const current = Math.round(start + (target - start) * eased);

    element.textContent = current;

    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }

  requestAnimationFrame(update);
}

window.animateCounter = animateCounter;

// ============================================
// MODAL UTILITIES (shared across views)
// ============================================
function createModalUtils(overlayId, contentId) {
  const overlay = document.getElementById(overlayId);
  const content = document.getElementById(contentId);

  if (!overlay || !content) return null;

  return {
    open() {
      overlay.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
      requestAnimationFrame(() => {
        overlay.style.opacity = '1';
        content.style.opacity = '1';
        content.style.transform = 'translateY(0) scale(1)';
      });
    },

    close() {
      overlay.style.opacity = '0';
      content.style.opacity = '0';
      content.style.transform = 'translateY(16px) scale(0.95)';
      setTimeout(() => {
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }, 300);
    },

    initClickOutside() {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) this.close();
      });
    },

    initEscapeClose() {
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
          this.close();
        }
      });
    }
  };
}

window.createModalUtils = createModalUtils;
