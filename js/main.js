// ============================================
// FitPulse Gym Management System
// Main JavaScript - Responsiveness & UI Logic
// ============================================

document.addEventListener('DOMContentLoaded', () => {

  // ---------- NAVBAR SCROLL EFFECT ----------
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
  }

  // ---------- HAMBURGER MENU ----------
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  const navAuth = document.getElementById('navAuth');

  if (hamburger) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('active');
      if (navLinks) navLinks.classList.toggle('active');
      if (navAuth) navAuth.classList.toggle('active');
    });

    // Close menu on link click
    document.querySelectorAll('.nav-links a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        if (navLinks) navLinks.classList.remove('active');
        if (navAuth) navAuth.classList.remove('active');
      });
    });
  }

  // ---------- SMOOTH SCROLL ----------
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ---------- SCROLL ANIMATIONS ----------
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const animateObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.style.animationDelay = `${index * 0.1}s`;
          entry.target.classList.add('visible');
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, index * 100);
        animateObserver.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.animate-in').forEach(el => {
    animateObserver.observe(el);
  });

  // ---------- SIDEBAR TOGGLE (Dashboard Pages) ----------
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
    });

    // Close sidebar on outside click (mobile)
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
          sidebar.classList.remove('active');
        }
      }
    });
  }

  // ---------- MODAL SYSTEM ----------
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  };

  // Close modal on overlay click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });

  // ---------- ALERT AUTO-DISMISS ----------
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s, transform 0.5s';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });

  // ---------- FORM VALIDATION HELPER ----------
  window.validateForm = function(formEl) {
    let isValid = true;
    const requiredFields = formEl.querySelectorAll('[required]');

    requiredFields.forEach(field => {
      removeError(field);

      if (!field.value.trim()) {
        showError(field, 'This field is required');
        isValid = false;
      } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
        showError(field, 'Please enter a valid email');
        isValid = false;
      } else if (field.name === 'confirm_password') {
        const password = formEl.querySelector('[name="password"]');
        if (password && field.value !== password.value) {
          showError(field, 'Passwords do not match');
          isValid = false;
        }
      }
    });

    return isValid;
  };

  function showError(field, message) {
    field.style.borderColor = '#ef4444';
    const errorEl = document.createElement('small');
    errorEl.className = 'field-error';
    errorEl.style.color = '#ef4444';
    errorEl.style.fontSize = '0.8rem';
    errorEl.style.marginTop = '4px';
    errorEl.style.display = 'block';
    errorEl.textContent = message;
    field.parentNode.appendChild(errorEl);
  }

  function removeError(field) {
    field.style.borderColor = '';
    const existing = field.parentNode.querySelector('.field-error');
    if (existing) existing.remove();
  }

  // Clear errors on input
  document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('input', function() {
      removeError(this);
    });
  });

  // ---------- PASSWORD TOGGLE ----------
  document.querySelectorAll('.toggle-password').forEach(toggle => {
    toggle.addEventListener('click', function() {
      const input = this.previousElementSibling;
      if (input.type === 'password') {
        input.type = 'text';
        this.innerHTML = '<i class="fas fa-eye-slash"></i>';
      } else {
        input.type = 'password';
        this.innerHTML = '<i class="fas fa-eye"></i>';
      }
    });
  });

  // ---------- RESPONSIVE TABLE SCROLL ----------
  document.querySelectorAll('.data-table').forEach(table => {
    const wrapper = table.parentElement;
    if (wrapper && window.innerWidth <= 768) {
      wrapper.style.overflowX = 'auto';
    }
  });

  // ---------- COUNTER ANIMATION ----------
  function animateCounters() {
    const counters = document.querySelectorAll('.hero-stat .number');
    counters.forEach(counter => {
      const target = parseInt(counter.textContent);
      if (isNaN(target)) return;

      let current = 0;
      const increment = target / 50;
      const suffix = counter.textContent.includes('+') ? '+' : counter.textContent.includes('%') ? '%' : '';

      const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
          current = target;
          clearInterval(timer);
        }
        counter.textContent = Math.floor(current) + suffix;
      }, 30);
    });
  }

  // Trigger counter animation when hero is visible
  const heroSection = document.querySelector('.hero');
  if (heroSection) {
    const heroObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounters();
          heroObserver.unobserve(entry.target);
        }
      });
    });
    heroObserver.observe(heroSection);
  }

  // ---------- ACTIVE NAV LINK HIGHLIGHT ----------
  const sections = document.querySelectorAll('section[id]');
  if (sections.length > 0) {
    window.addEventListener('scroll', () => {
      const scrollY = window.scrollY + 100;
      sections.forEach(section => {
        const top = section.offsetTop;
        const height = section.offsetHeight;
        const id = section.getAttribute('id');
        const link = document.querySelector(`.nav-links a[href="#${id}"]`);

        if (link) {
          if (scrollY >= top && scrollY < top + height) {
            link.style.color = '#ff6b35';
          } else {
            link.style.color = '';
          }
        }
      });
    });
  }

  // ---------- DELETE CONFIRMATION ----------
  window.confirmDelete = function(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
  };

  // ---------- FORMAT CURRENCY ----------
  window.formatCurrency = function(amount) {
    return 'KSh ' + parseFloat(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 });
  };

  // ---------- FORMAT DATE ----------
  window.formatDate = function(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-KE', { year: 'numeric', month: 'short', day: 'numeric' });
  };

});
