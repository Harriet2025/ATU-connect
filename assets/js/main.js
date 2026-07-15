/**
 * ATU Connect — global vanilla JS.
 * No frameworks: small DOM helpers + progressive enhancement only.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initNavToggle();
    initSidebarToggle();
    initConfirmModals();
    initNotificationPolling();
    initFlashDismiss();
  });

  /** Mobile top-nav hamburger (public pages) */
  function initNavToggle() {
    var toggle = document.querySelector('.navbar-toggle');
    var links = document.querySelector('.navbar-links');
    if (!toggle || !links) return;
    toggle.addEventListener('click', function () {
      links.classList.toggle('open');
    });
  }

  /** Mobile sidebar drawer (authenticated app shell) */
  function initSidebarToggle() {
    var toggle = document.querySelector('[data-toggle="sidebar"]');
    var sidebar = document.querySelector('.sidebar');
    if (!toggle || !sidebar) return;
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('open');
      }
    });
  }

  /** Custom confirm modal for destructive actions instead of native confirm() */
  function initConfirmModals() {
    var triggers = document.querySelectorAll('[data-confirm]');
    var overlay = document.getElementById('confirm-modal');
    if (!overlay) return;

    var messageEl = overlay.querySelector('.confirm-message');
    var confirmBtn = overlay.querySelector('[data-action="confirm-yes"]');
    var cancelBtn = overlay.querySelector('[data-action="confirm-no"]');
    var pendingForm = null;

    triggers.forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        pendingForm = el.closest('form') || null;
        messageEl.textContent = el.getAttribute('data-confirm');
        overlay.classList.add('active');
      });
    });

    cancelBtn.addEventListener('click', function () {
      overlay.classList.remove('active');
      pendingForm = null;
    });

    confirmBtn.addEventListener('click', function () {
      overlay.classList.remove('active');
      if (pendingForm) {
        pendingForm.submit();
      }
    });
  }

  /** Poll unread notification/message counts every 30s and update header badges */
  function initNotificationPolling() {
    var notifBadge = document.querySelector('[data-badge="notifications"]');
    var msgBadge = document.querySelector('[data-badge="messages"]');
    if (!notifBadge && !msgBadge) return;

    var baseUrl = document.body.getAttribute('data-base-url') || '';

    function poll() {
      fetch(baseUrl + '/notifications/unread-count', { credentials: 'same-origin' })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
          if (!data) return;
          updateBadge(notifBadge, data.notifications);
          updateBadge(msgBadge, data.messages);
        })
        .catch(function () { /* silent: badge just stays stale until next tick */ });
    }

    function updateBadge(el, count) {
      if (!el) return;
      if (count > 0) {
        el.textContent = count > 99 ? '99+' : count;
        el.style.display = 'flex';
      } else {
        el.style.display = 'none';
      }
    }

    poll();
    setInterval(poll, 30000);
  }

  /** Auto-dismiss flash alerts after a few seconds */
  function initFlashDismiss() {
    document.querySelectorAll('.alert[data-autodismiss]').forEach(function (el) {
      setTimeout(function () {
        el.style.transition = 'opacity .3s ease';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 300);
      }, 4000);
    });
  }
})();
