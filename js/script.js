// =========================================================
// ATU CONNECT — GLOBAL SCRIPT
// =========================================================

// =========================================================
// API HELPER — thin fetch wrapper: same-origin cookies + CSRF
// =========================================================
let _csrfToken = null;

async function getCsrfToken(forceRefresh) {
  if (_csrfToken && !forceRefresh) return _csrfToken;
  try {
    const res = await fetch('api/csrf-token', { credentials: 'same-origin' });
    const json = await res.json();
    _csrfToken = json?.data?.csrf_token || null;
  } catch {
    _csrfToken = null;
  }
  return _csrfToken;
}

// path: e.g. 'login', 'profile', 'profile/picture'
// options.method defaults to GET; options.body for JSON calls (plain object,
// auto-stringified) or a FormData instance for multipart uploads.
async function apiFetch(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const isMutating = method !== 'GET';
  const isFormData = options.body instanceof FormData;

  async function doFetch() {
    const headers = Object.assign({}, options.headers);
    if (!isFormData) headers['Content-Type'] = 'application/json';
    if (isMutating) headers['X-CSRF-Token'] = await getCsrfToken();

    const fetchOpts = {
      method,
      credentials: 'same-origin',
      headers,
    };
    if (options.body !== undefined) {
      fetchOpts.body = isFormData ? options.body : JSON.stringify(options.body);
    }
    const res = await fetch('api/' + path, fetchOpts);
    let data = null;
    try { data = await res.json(); } catch { data = null; }
    return { ok: res.ok, status: res.status, data };
  }

  let result = await doFetch();

  // CSRF tokens are tied to the PHP session, which regenerates its id on
  // login (Core\Auth::login() -> session_regenerate_id(true)), so a token
  // fetched before login can be rejected right after. Retry once with a
  // freshly-fetched token before giving up.
  if (isMutating && result.status === 403 && result.data?.message === 'Invalid CSRF token.') {
    await getCsrfToken(true);
    result = await doFetch();
  }

  return result;
}

// =========================================================
// ROLE MAPPING — frontend hyphenated <select> values <-> backend slugs
// =========================================================
const ROLE_HYPHEN_TO_SLUG = {
  student: 'student',
  lecturer: 'lecturer',
  'dept-exec': 'dept_executive',
  'src-exec': 'src_executive',
  'jcrc-exec': 'jcrc_executive',
  'faculty-exec': 'faculty_executive',
  hod: 'hod',
  'faculty-dean': 'faculty_dean',
  registrar: 'registrar_office',
  'student-affairs': 'student_affairs',
  'university-management': 'university_management',
  admin: 'administrator'
};

const ROLE_SLUG_DASHBOARDS = {
  student: 'student.html',
  lecturer: 'lecturer.html',
  dept_executive: 'dashboard.html',
  src_executive: 'dashboard.html',
  jcrc_executive: 'dashboard.html',
  faculty_executive: 'dashboard.html',
  hod: 'dashboard.html',
  faculty_dean: 'dashboard.html',
  registrar_office: 'dashboard.html',
  student_affairs: 'dashboard.html',
  university_management: 'dashboard.html',
  administrator: 'admin.html'
};

const ROLE_SLUG_LABELS = {
  student: 'Student',
  lecturer: 'Lecturer',
  dept_executive: 'Departmental Executive',
  src_executive: 'SRC Executive',
  jcrc_executive: 'JCRC (Hall) Executive',
  faculty_executive: 'Faculty Executive',
  hod: 'Head of Department (HOD)',
  faculty_dean: 'Faculty Dean',
  registrar_office: "The Registrar's Office",
  student_affairs: 'Student Affairs',
  university_management: 'University Management',
  administrator: 'Administrator'
};

// Maps a server user row (snake_case, from login/register/me/profile) into
// the local "atuConnectUser" shape the existing UI-binding code expects.
// roleLabelFallback/roleSlugFallback cover the register response, which
// (unlike login/profile) doesn't join role_name onto the user row.
function mapServerUserToLocal(serverUser, roleLabelFallback, roleSlugFallback) {
  const roleSlug = serverUser.role_name || roleSlugFallback || null;
  const profile = serverUser.profile || {};
  const mapped = {
    name: serverUser.full_name,
    identifier: serverUser.identifier,
    email: serverUser.email,
    phone: serverUser.phone,
    role: roleSlug,
    roleLabel: roleLabelFallback || ROLE_SLUG_LABELS[roleSlug] || roleSlug,
    notifyChannels: [
      serverUser.notify_email ? 'email' : null,
      serverUser.notify_sms ? 'sms' : null,
      serverUser.notify_whatsapp ? 'whatsapp' : null
    ].filter(Boolean)
  };
  if (serverUser.profile_picture) mapped.photo = 'uploads/' + serverUser.profile_picture;
  if (serverUser.department_name) mapped.department = serverUser.department_name;
  if (profile.programme) mapped.programme = profile.programme;
  if (profile.level) mapped.level = profile.level;
  if (profile.emergency_contact_phone) mapped.emergencyContact = profile.emergency_contact_phone;
  if (profile.advisor_name) mapped.advisor = profile.advisor_name;
  return mapped;
}

// Shallow-merges patch onto base, skipping undefined values so we never
// clobber a cached field with something the latest response simply omitted.
function mergeUser(base, patch) {
  const merged = Object.assign({}, base || {});
  Object.entries(patch || {}).forEach(([key, value]) => {
    if (value !== undefined) merged[key] = value;
  });
  return merged;
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      navToggle.classList.toggle('active');
    });
  }

  initPasswordToggles();
  initAuthPage();
  initAppShell();
  initSessionUser();
  initAnnouncementsPage();
  initAssignmentsTabs();
  initNotificationsPage();
  initCalendarPage();
  initMessagesPage();
  initProfilePage();
  initCoursesPage();
  renderAnnouncementsFeed();
  renderAssignmentsFeed();
  renderCoursesFeed();
  renderMessagesFeed();
  renderCalendarFeed();
  renderNoticeBoardFeed();
  renderNotificationsFeed();
  renderHodManagement();
  updateMessageBadges();
  initSmsTest();

  // Server is the source of truth when reachable; falls back silently to
  // the localStorage cache above (already rendered) if there's no session
  // (e.g. on login.html, or offline/PWA-installed with no connectivity).
  refreshSessionFromServer();
});

// Re-hydrates the cached local user from GET /api/profile and re-renders
// every bound element via initSessionUser(). Safe to call on any page,
// including ones with no logged-in session (apiFetch just resolves ok:false).
async function refreshSessionFromServer() {
  try {
    const res = await apiFetch('profile');
    if (!res.ok || !res.data?.success) {
      // A 401 here specifically means "no valid session" (this is the one
      // endpoint whose whole job is checking identity) — bounce to login
      // instead of leaving every panel stuck on a misleading "could not
      // load" message. login.html/index.html call this same check to see
      // whether a session already exists, so skip the redirect there to
      // avoid a loop.
      if (res.status === 401) {
        const path = location.pathname.split('/').pop();
        const isPublicPage = path === '' || path === 'login.html' || path === 'index.html';
        if (!isPublicPage) location.href = 'login.html';
      }
      return;
    }
    const existing = getCurrentUser() || {};
    const merged = mergeUser(existing, mapServerUserToLocal(res.data.data, existing.roleLabel, existing.role));
    setCurrentUser(merged);
    initSessionUser();
    renderHodManagement(); // role may only be known after this refresh
  } catch {
    // offline or unreachable — keep whatever was already rendered from cache
  }
}

// =========================================================
// PASSWORD SHOW/HIDE TOGGLE (login.html)
// =========================================================
function initPasswordToggles() {
  document.querySelectorAll('.password-toggle').forEach(btn => {
    const input = document.getElementById(btn.dataset.target);
    if (!input) return;
    btn.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? '👁' : '🙈';
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  });
}

// =========================================================
// APP SHELL (dashboard.html + all logged-in pages)
// =========================================================
function initAppShell() {
  const logoutLink = document.getElementById('logoutLink');
  if (logoutLink) {
    logoutLink.addEventListener('click', e => {
      e.preventDefault();
      apiFetch('logout', { method: 'POST' }).catch(() => {}).finally(() => {
        localStorage.removeItem('atuConnectUser');
        localStorage.removeItem('atuConnectUsers');
        window.location.href = 'index.html';
      });
    });
  }

  const notifBtn = document.getElementById('notifBtn');
  const notifDropdown = document.getElementById('notifDropdown');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const appSidebar = document.getElementById('appSidebar');

  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', e => {
      e.stopPropagation();
      notifDropdown.classList.toggle('open');
    });
    document.addEventListener('click', e => {
      if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
        notifDropdown.classList.remove('open');
      }
    });
  }

  if (sidebarToggle && appSidebar) {
    sidebarToggle.addEventListener('click', () => {
      appSidebar.classList.toggle('open');
    });
  }
}

// =========================================================
// AUTH PAGE (login.html)
// =========================================================
function initAuthPage() {
  const tabs = document.querySelectorAll('.auth-tab');
  const panels = document.querySelectorAll('.auth-form');
  if (!tabs.length) return;

  function showPanel(name) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    panels.forEach(p => p.style.display = p.dataset.panel === name ? '' : 'none');
    history.replaceState(null, '', name === 'register' ? '#register' : '#login');
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => showPanel(tab.dataset.tab));
  });

  document.querySelectorAll('.switch-to-register').forEach(a =>
    a.addEventListener('click', e => { e.preventDefault(); showPanel('register'); })
  );
  document.querySelectorAll('.switch-to-login').forEach(a =>
    a.addEventListener('click', e => { e.preventDefault(); showPanel('login'); })
  );

  if (window.location.hash === '#register') showPanel('register');

  // quick-pick chips <-> full role <select> stay in sync
  function wireRolePicker(gridId, selectId) {
    const grid = document.getElementById(gridId);
    const select = document.getElementById(selectId);
    if (!grid || !select) return;

    grid.addEventListener('click', e => {
      const chip = e.target.closest('.role-chip');
      if (!chip) return;
      grid.querySelectorAll('.role-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      select.value = chip.dataset.role;
      select.dispatchEvent(new Event('change'));
    });

    select.addEventListener('change', () => {
      const match = grid.querySelector(`.role-chip[data-role="${select.value}"]`);
      grid.querySelectorAll('.role-chip').forEach(c => c.classList.remove('active'));
      if (match) match.classList.add('active');
    });
  }
  wireRolePicker('loginRoleGrid', 'loginRoleSelect');
  wireRolePicker('registerRoleGrid', 'registerRoleSelect');

  // Level only applies to students
  const registerRoleSelect = document.getElementById('registerRoleSelect');
  const regLevelGroup = document.getElementById('regLevelGroup');
  const regEmail = document.getElementById('regEmail');
  const regEmailLabel = document.getElementById('regEmailLabel');
  const STUDENT_EMAIL_PATTERN = '^[A-Za-z0-9]+@st\\.atu\\.edu\\.gh$';
  const STAFF_EMAIL_PATTERN = '^[A-Za-z0-9._-]+@atu\\.edu\\.gh$';
  if (registerRoleSelect && regEmail && regEmailLabel) {
    const toggleEmailField = () => {
      const isStudent = registerRoleSelect.value === 'student';
      regEmailLabel.textContent = isStudent ? 'Student Email' : 'Staff Email';
      regEmail.placeholder = isStudent ? 'e.g. 00934522B@st.atu.edu.gh' : 'e.g. k.owusu@atu.edu.gh';
      regEmail.pattern = isStudent ? STUDENT_EMAIL_PATTERN : STAFF_EMAIL_PATTERN;
      regEmail.title = isStudent
        ? 'Format: yourStudentID@st.atu.edu.gh (e.g. 00934522B@st.atu.edu.gh)'
        : 'Format: name@atu.edu.gh (staff email, not a student @st.atu.edu.gh address)';
    };
    toggleEmailField();
    registerRoleSelect.addEventListener('change', toggleEmailField);
  }
  if (registerRoleSelect && regLevelGroup) {
    const toggleLevelField = () => {
      regLevelGroup.style.display = registerRoleSelect.value === 'student' ? '' : 'none';
    };
    toggleLevelField();
    registerRoleSelect.addEventListener('change', toggleLevelField);
  }

  // Field-level error helpers shared by both forms.
  function clearFieldErrors(fieldMap) {
    Object.values(fieldMap).forEach(inputId => {
      const input = document.getElementById(inputId);
      if (!input) return;
      input.classList.remove('field-input-error');
      input.parentElement?.querySelector('.field-error-msg')?.remove();
    });
  }
  function showFieldErrors(errors, fieldMap) {
    if (!errors) return;
    Object.entries(errors).forEach(([serverField, msgs]) => {
      const inputId = fieldMap[serverField];
      const input = inputId && document.getElementById(inputId);
      if (!input) return;
      input.classList.add('field-input-error');
      const msg = document.createElement('p');
      msg.className = 'field-error-msg';
      msg.textContent = Array.isArray(msgs) ? msgs[0] : String(msgs);
      input.insertAdjacentElement('afterend', msg);
    });
  }
  function showAuthError(elId, message) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = message;
    el.style.display = '';
  }
  function hideAuthError(elId) {
    const el = document.getElementById(elId);
    if (el) el.style.display = 'none';
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async e => {
      e.preventDefault();
      hideAuthError('loginError');
      const roleLabel = roleLabelFor('loginRoleSelect');
      const identifier = document.getElementById('loginId')?.value.trim() || '';
      const password = document.getElementById('loginPass')?.value || '';
      const submitBtn = loginForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const res = await apiFetch('login', { method: 'POST', body: { identifier, password } });
        if (!res.ok || !res.data?.success) {
          showAuthError('loginError', res.data?.message || 'Invalid credentials. Please try again.');
          return;
        }

        // Login regenerates the session id server-side, so any pre-login
        // CSRF token is now stale — refresh it before the next mutating call.
        await getCsrfToken(true);

        const serverUser = res.data.data;
        let mapped = mapServerUserToLocal(serverUser, roleLabel);
        setCurrentUser(mapped);
        if (mapped.identifier) saveRegisteredUser(mapped);

        // Enrich with department/advisor/programme etc from /api/profile.
        const profileRes = await apiFetch('profile');
        if (profileRes.ok && profileRes.data?.success) {
          mapped = mergeUser(mapped, mapServerUserToLocal(profileRes.data.data, roleLabel));
          setCurrentUser(mapped);
          if (mapped.identifier) saveRegisteredUser(mapped);
        }

        window.location.href = ROLE_SLUG_DASHBOARDS[serverUser.role_name] || 'dashboard.html';
      } catch (err) {
        showAuthError('loginError', 'Network error — please check your connection and try again.');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  const registerFieldMap = {
    full_name: 'regName', identifier: 'regId', email: 'regEmail',
    phone: 'regPhone', password: 'regPass'
  };

  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', async e => {
      e.preventDefault();
      hideAuthError('registerError');
      clearFieldErrors(registerFieldMap);

      const roleHyphen = document.getElementById('registerRoleSelect')?.value || 'student';
      const role = ROLE_HYPHEN_TO_SLUG[roleHyphen] || 'student';
      const roleLabel = roleLabelFor('registerRoleSelect');
      const notifyChannels = [];
      if (document.getElementById('notifyEmail')?.checked) notifyChannels.push('email');
      if (document.getElementById('notifySms')?.checked) notifyChannels.push('sms');
      if (document.getElementById('notifyWhatsapp')?.checked) notifyChannels.push('whatsapp');

      const payload = {
        full_name: document.getElementById('regName')?.value.trim() || '',
        identifier: document.getElementById('regId')?.value.trim() || '',
        email: document.getElementById('regEmail')?.value.trim() || '',
        password: document.getElementById('regPass')?.value || '',
        phone: document.getElementById('regPhone')?.value.trim() || '',
        role,
        notify_channels: notifyChannels
      };
      if (role === 'student') {
        payload.level = document.getElementById('regLevel')?.value || '';
      }

      const submitBtn = registerForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const res = await apiFetch('register', { method: 'POST', body: payload });
        if (!res.ok || !res.data?.success) {
          if (res.data?.errors) showFieldErrors(res.data.errors, registerFieldMap);
          showAuthError('registerError', res.data?.message || 'Registration failed. Please check the highlighted fields.');
          return;
        }

        await getCsrfToken(true);

        const serverUser = res.data.data;
        let mapped = mapServerUserToLocal(serverUser, roleLabel, role);
        mapped.notifyChannels = notifyChannels;
        setCurrentUser(mapped);
        if (mapped.identifier) saveRegisteredUser(mapped);

        const profileRes = await apiFetch('profile');
        if (profileRes.ok && profileRes.data?.success) {
          mapped = mergeUser(mapped, mapServerUserToLocal(profileRes.data.data, roleLabel, role));
          setCurrentUser(mapped);
          if (mapped.identifier) saveRegisteredUser(mapped);
        }

        window.location.href = ROLE_SLUG_DASHBOARDS[role] || 'dashboard.html';
      } catch (err) {
        showAuthError('registerError', 'Network error — please check your connection and try again.');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
}

function roleLabelFor(selectId) {
  const select = document.getElementById(selectId);
  const option = select?.selectedOptions?.[0];
  if (!option) return '';
  // strip the leading emoji glyph, keep the readable role name
  return option.textContent.replace(/^\S+\s/, '').trim();
}

// =========================================================
// SESSION USER (persisted client-side only — no backend wired yet)
// =========================================================
function getRegisteredUsers() {
  try { return JSON.parse(localStorage.getItem('atuConnectUsers')) || []; }
  catch { return []; }
}

function findRegisteredUser(identifier) {
  if (!identifier) return null;
  return getRegisteredUsers().find(u => u.identifier === identifier || u.email === identifier) || null;
}

function saveRegisteredUser(user) {
  const users = getRegisteredUsers().filter(u => u.identifier !== user.identifier);
  users.push(user);
  localStorage.setItem('atuConnectUsers', JSON.stringify(users));
}

function setCurrentUser(user) {
  localStorage.setItem('atuConnectUser', JSON.stringify(user));
}

function getCurrentUser() {
  try { return JSON.parse(localStorage.getItem('atuConnectUser')); }
  catch { return null; }
}

function initSessionUser() {
  const user = getCurrentUser();
  if (!user) return;

  if (user.photo) applyAvatarPhoto(user.photo);

  const firstName = (user.name || user.identifier || '').split(' ')[0];

  const nameEl = document.getElementById('topbarUserName');
  if (nameEl) nameEl.textContent = user.name || user.identifier;

  const roleEl = document.getElementById('topbarUserRole');
  if (roleEl && user.roleLabel) roleEl.textContent = user.roleLabel;

  const welcomeEl = document.getElementById('welcomeName');
  if (welcomeEl && firstName) welcomeEl.textContent = firstName;

  const profileName = document.getElementById('profileName');
  if (profileName) profileName.textContent = user.name || user.identifier;

  const profileRole = document.getElementById('profileRole');
  if (profileRole && user.roleLabel) profileRole.textContent = user.roleLabel;

  // Personal Information form fields (profile.html)
  const infoFieldMap = {
    infoName: user.name,
    infoIdentifier: user.identifier,
    infoEmail: user.email,
    infoPhone: user.phone,
    infoLevel: user.level,
    infoEmergencyContact: user.emergencyContact,
    infoAdvisor: user.advisor,
    infoProgramme: user.programme,
    infoDepartment: user.department
  };
  Object.entries(infoFieldMap).forEach(([id, value]) => {
    if (!value) return;
    const el = document.getElementById(id);
    if (el) el.value = value;
  });

  if (user.notifyChannels) {
    const map = { email: 'notifyEmailToggle', sms: 'notifySmsToggle', whatsapp: 'notifyWhatsappToggle' };
    Object.entries(map).forEach(([channel, id]) => {
      const toggle = document.getElementById(id);
      if (toggle) toggle.checked = user.notifyChannels.includes(channel);
    });
  }
}

// =========================================================
// ANNOUNCEMENTS PAGE
// =========================================================
function initAnnouncementsPage() {
  const chips = document.querySelectorAll('.filter-bar .chip');
  if (!chips.length) return;
  chips.forEach(chip => chip.addEventListener('click', () => chip.classList.toggle('active')));

  const newBtn = document.getElementById('newAnnouncementBtn');
  const composer = document.getElementById('composerCard');
  if (newBtn && composer) {
    newBtn.addEventListener('click', () => composer.classList.toggle('open'));
  }
  const cancelBtn = document.getElementById('composerCancel');
  if (cancelBtn && composer) {
    cancelBtn.addEventListener('click', () => composer.classList.remove('open'));
  }
}

// =========================================================
// ASSIGNMENTS PAGE
// =========================================================
function initAssignmentsTabs() {
  const tabs = document.querySelectorAll('.view-tab');
  if (!tabs.length) return;
  const panels = document.querySelectorAll('.view-panel');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.view)?.classList.add('active');
    });
  });
}

// =========================================================
// NOTIFICATIONS PAGE
// =========================================================
function initNotificationsPage() {
  const btn = document.getElementById('markAllReadBtn');
  if (!btn) return;
  btn.addEventListener('click', () => {
    document.querySelectorAll('.notif-row-full.unread').forEach(r => r.classList.remove('unread'));
  });
}

// =========================================================
// CALENDAR PAGE
// =========================================================
function initCalendarPage() {
  const cells = document.querySelectorAll('.cal-cell:not(.empty)');
  if (!cells.length) return;
  cells.forEach(cell => {
    cell.addEventListener('click', () => {
      cells.forEach(c => c.classList.remove('today'));
      cell.classList.add('today');
    });
  });
}

// =========================================================
// MESSAGES PAGE
// =========================================================
function initMessagesPage() {
  const rows = document.querySelectorAll('.conv-row');
  if (!rows.length) return;
  const layout = document.querySelector('.messages-layout');
  rows.forEach(row => {
    row.addEventListener('click', () => {
      rows.forEach(r => r.classList.remove('active'));
      row.classList.add('active');
      row.classList.remove('unread');
      layout?.classList.add('chat-open');
    });
  });
  document.getElementById('backToConvList')?.addEventListener('click', () => {
    layout?.classList.remove('chat-open');
  });
}

// =========================================================
// PROFILE PAGE
// =========================================================
function initProfilePage() {
  const editBtn = document.getElementById('editProfileBtn');
  const infoForm = document.getElementById('personalInfoForm');
  const saveBtn = document.getElementById('saveInfoBtn');
  const cancelBtn = document.getElementById('cancelInfoBtn');

  if (editBtn && infoForm && saveBtn && cancelBtn) {
    let snapshot = null;

    const setEditing = editing => {
      infoForm.querySelectorAll('.info-input').forEach(el => {
        // Department and Academic Advisor are resolved server-side from FKs
        // (department_id / students.academic_advisor_id) and have no
        // self-service update path in ProfileController::update() — keep
        // them display-only even while the rest of the form is editable.
        if (el.hasAttribute('data-readonly-always')) return;
        if (editing) {
          el.removeAttribute('readonly');
          el.disabled = false;
        } else {
          el.setAttribute('readonly', 'readonly');
          el.disabled = el.tagName === 'SELECT';
        }
      });
      editBtn.style.display = editing ? 'none' : '';
      saveBtn.style.display = editing ? '' : 'none';
      cancelBtn.style.display = editing ? '' : 'none';
    };

    editBtn.addEventListener('click', () => {
      snapshot = {};
      infoForm.querySelectorAll('.info-input').forEach(el => { snapshot[el.id] = el.value; });
      setEditing(true);
      document.getElementById('infoName')?.focus();
    });

    cancelBtn.addEventListener('click', () => {
      if (snapshot) {
        infoForm.querySelectorAll('.info-input').forEach(el => {
          if (el.id in snapshot) el.value = snapshot[el.id];
        });
      }
      setEditing(false);
    });

    saveBtn.addEventListener('click', async () => {
      const previous = getCurrentUser() || {};
      const user = Object.assign({}, previous);
      user.name = document.getElementById('infoName')?.value.trim() || user.name;
      // Student ID is registrar-controlled — see data-readonly-always on infoIdentifier.
      user.programme = document.getElementById('infoProgramme')?.value.trim() || '';
      // Department stays whatever the server last resolved it to (read-only, see setEditing above).
      user.level = document.getElementById('infoLevel')?.value || '';
      user.email = document.getElementById('infoEmail')?.value.trim() || user.email;
      user.phone = document.getElementById('infoPhone')?.value.trim() || user.phone;
      user.emergencyContact = document.getElementById('infoEmergencyContact')?.value.trim() || '';
      // Academic Advisor stays whatever the server last resolved it to (read-only, see setEditing above).

      setCurrentUser(user);
      if (user.identifier) saveRegisteredUser(user);
      initSessionUser();
      setEditing(false);

      const errEl = document.getElementById('profileSaveError');
      if (errEl) errEl.style.display = 'none';

      // Persist to the server. ProfileController::update() accepts
      // full_name/phone/email/notify_* for everyone, plus programme/level/
      // emergency_contact_phone for students (emergency_contact_name isn't
      // collected by this UI as a separate field, so it's left untouched).
      const payload = { full_name: user.name, phone: user.phone, email: user.email };
      if (user.role === 'student') {
        payload.programme = user.programme;
        payload.level = user.level;
        payload.emergency_contact_phone = user.emergencyContact;
      }
      try {
        const res = await apiFetch('profile', { method: 'PUT', body: payload });
        if (res.ok && res.data?.success) {
          const merged = mergeUser(user, mapServerUserToLocal(res.data.data, user.roleLabel, user.role));
          setCurrentUser(merged);
          initSessionUser();
        } else if (res.status === 422 || res.status === 401 || res.status === 403) {
          // Server rejected the change (e.g. duplicate/invalid email) — revert
          // the optimistic local update so the UI doesn't claim a save that
          // didn't actually happen, and surface why.
          setCurrentUser(previous);
          initSessionUser();
          if (errEl) {
            const msg = res.data?.errors?.email?.[0] || res.data?.message || 'Could not save changes.';
            errEl.textContent = msg;
            errEl.style.display = '';
          }
        }
      } catch {
        // offline — the localStorage copy above still reflects the edit
      }
    });
  }

  const notifyToggles = { email: 'notifyEmailToggle', sms: 'notifySmsToggle', whatsapp: 'notifyWhatsappToggle' };
  Object.entries(notifyToggles).forEach(([channel, id]) => {
    const toggle = document.getElementById(id);
    if (!toggle) return;
    toggle.addEventListener('change', async () => {
      const user = getCurrentUser() || {};
      const channels = new Set(user.notifyChannels || []);
      toggle.checked ? channels.add(channel) : channels.delete(channel);
      user.notifyChannels = Array.from(channels);
      setCurrentUser(user);
      if (user.identifier) saveRegisteredUser(user);

      const flagName = { email: 'notify_email', sms: 'notify_sms', whatsapp: 'notify_whatsapp' }[channel];
      try {
        await apiFetch('profile', { method: 'PUT', body: { [flagName]: toggle.checked } });
      } catch {
        // offline — localStorage still has the updated preference
      }
    });
  });

  const avatarInput = document.getElementById('avatarUploadInput');
  if (!avatarInput) return;
  avatarInput.addEventListener('change', () => {
    const file = avatarInput.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async () => {
      // Show the local preview immediately for responsiveness...
      applyAvatarPhoto(reader.result);
      const user = getCurrentUser() || {};
      user.photo = reader.result;
      setCurrentUser(user);
      if (user.identifier) saveRegisteredUser(user);

      // ...then upload in the background and swap to the server URL on success.
      const formData = new FormData();
      formData.append('picture', file);
      try {
        const res = await apiFetch('profile/picture', { method: 'POST', body: formData });
        if (res.ok && res.data?.success && res.data.data?.profile_picture) {
          const serverUrl = 'uploads/' + res.data.data.profile_picture;
          applyAvatarPhoto(serverUrl);
          const refreshed = getCurrentUser() || {};
          refreshed.photo = serverUrl;
          setCurrentUser(refreshed);
          if (refreshed.identifier) saveRegisteredUser(refreshed);
        }
      } catch {
        // offline/upload failure — silently keep the local base64 preview
      }
    };
    reader.readAsDataURL(file);
  });
}

function applyAvatarPhoto(dataUrl) {
  // Target the explicit avatar ids plus any topbar/sidebar profile avatar,
  // so the uploaded picture shows in the header on every page — not just
  // the profile page — regardless of whether that span carries an id.
  document.querySelectorAll('#profileAvatar, #topbarAvatar, .topbar-profile .mock-avatar').forEach(el => {
    el.style.backgroundImage = `url(${dataUrl})`;
    el.style.backgroundSize = 'cover';
    el.style.backgroundPosition = 'center';
  });
}

// =========================================================
// SHARED HELPERS — live-data pages (announcements, assignments,
// courses, messages, calendar, notice board, notifications)
// =========================================================
const ANNOUNCEMENT_ROLE_SLUGS = [
  'lecturer', 'dept_executive', 'src_executive', 'jcrc_executive',
  'faculty_executive', 'hod', 'faculty_dean', 'registrar_office',
  'student_affairs', 'university_management', 'administrator'
];

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const then = new Date(dateStr.replace(' ', 'T'));
  if (isNaN(then.getTime())) return dateStr;
  const seconds = Math.floor((Date.now() - then.getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return minutes + 'm ago';
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return hours + 'h ago';
  const days = Math.floor(hours / 24);
  if (days < 7) return days + 'd ago';
  return then.toLocaleDateString();
}

function priorityBadgeClass(priority) {
  if (priority === 'emergency' || priority === 'high') return 'badge-danger';
  if (priority === 'low') return 'badge-green';
  return 'badge-gold';
}

let _myUserId = null;
async function getMyUserId() {
  if (_myUserId) return _myUserId;
  try {
    const res = await apiFetch('profile');
    if (res.ok && res.data?.success) {
      _myUserId = res.data.data.user_id;
      return _myUserId;
    }
  } catch { /* offline */ }
  return null;
}

function currentUserIsAnnouncementAuthor() {
  const user = getCurrentUser();
  return !!(user && user.role && ANNOUNCEMENT_ROLE_SLUGS.includes(user.role));
}

// =========================================================
// ANNOUNCEMENTS FEED (announcements.html)
// =========================================================
async function renderAnnouncementsFeed() {
  const container = document.getElementById('announcementsList');
  if (!container) return;

  const chipsBar = document.querySelector('.filter-bar');
  const chips = chipsBar ? Array.from(chipsBar.querySelectorAll('.chip')) : [];
  const searchInput = document.getElementById('announcementSearch');
  const categorySelect = document.getElementById('composerCategory');
  const newBtn = document.getElementById('newAnnouncementBtn');
  const composer = document.getElementById('composerCard');

  let categoriesByName = {};
  let activeCategoryId = null;

  // Categories power both the filter chips (already in the static markup)
  // and the composer's category <select>.
  try {
    const res = await apiFetch('announcement-categories');
    if (res.ok && res.data?.success) {
      res.data.data.forEach(cat => { categoriesByName[cat.category_name] = cat.category_id; });
      if (categorySelect) {
        categorySelect.innerHTML = res.data.data.map(cat =>
          `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`).join('');
      }
    }
  } catch { /* offline — chip filtering still works without ids matched */ }

  async function load() {
    container.innerHTML = '<p class="text-muted">Loading announcements…</p>';
    const params = new URLSearchParams();
    if (activeCategoryId) params.set('category', activeCategoryId);
    if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
    const qs = params.toString();

    try {
      const res = await apiFetch('announcements' + (qs ? '?' + qs : ''));
      if (!res.ok || !res.data?.success) {
        container.innerHTML = '<p class="text-muted">Could not load announcements right now.</p>';
        return;
      }
      renderList(res.data.data);
    } catch {
      container.innerHTML = '<p class="text-muted">Could not load announcements right now.</p>';
    }
  }

  function renderList(items) {
    if (!items.length) {
      container.innerHTML = '<p class="text-muted">No announcements yet.</p>';
      return;
    }
    container.innerHTML = items.map(a => {
      const badgeClass = priorityBadgeClass(a.priority);
      const cardClasses = ['post-card'];
      if (a.is_pinned == 1) cardClasses.push('pinned');
      if (a.priority === 'emergency') cardClasses.push('emergency');
      const roleLabel = ROLE_SLUG_LABELS[a.author_role] || a.author_role;
      const deptPart = a.department_name ? ' · ' + escapeHtml(a.department_name) : '';
      return `
        <article class="${cardClasses.join(' ')}" data-id="${a.announcement_id}">
          <div class="post-head">
            <span class="mock-avatar lg"></span>
            <div class="post-meta"><strong>${escapeHtml(a.author_name)}</strong><span class="post-role">${escapeHtml(roleLabel)}${deptPart}</span></div>
            <span class="mock-badge ${badgeClass}">${escapeHtml(a.category_name)}</span>
          </div>
          <h4 class="post-title">${escapeHtml(a.title)}</h4>
          <p class="post-body">${escapeHtml(a.body)}</p>
          <div class="post-footer">
            ${a.is_pinned == 1 ? '<span>📌 Pinned</span>' : ''}
            <span data-action="like" data-id="${a.announcement_id}" data-clickable>❤ <span class="like-count">${a.like_count}</span></span>
            <span data-action="comment" data-id="${a.announcement_id}" data-clickable>💬 <span class="comment-count">${a.comment_count}</span> comments</span>
            <span class="post-date">${timeAgo(a.created_at)}</span>
          </div>
          <div class="comment-thread" style="display:none;"></div>
        </article>`;
    }).join('');
  }

  // Chip filtering: exclusive "radio" behaviour (overrides the plain
  // toggle-active wiring from initAnnouncementsPage above).
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const label = chip.textContent.trim();
      activeCategoryId = label === 'All' ? null : (categoriesByName[label] || null);
      load();
    });
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 350);
  });

  // Like / comment click delegation (content is rebuilt on every load()).
  container.addEventListener('click', async e => {
    const likeEl = e.target.closest('[data-action="like"]');
    if (likeEl) {
      const id = likeEl.dataset.id;
      const res = await apiFetch(`announcements/${id}/like`, { method: 'POST' });
      if (res.ok && res.data?.success) {
        likeEl.querySelector('.like-count').textContent = res.data.data.like_count;
      }
      return;
    }
    const commentEl = e.target.closest('[data-action="comment"]');
    if (commentEl) {
      const article = commentEl.closest('.post-card');
      const thread = article.querySelector('.comment-thread');
      const id = commentEl.dataset.id;
      if (thread.style.display === 'none') {
        const res = await apiFetch(`announcements/${id}`);
        if (res.ok && res.data?.success) {
          const comments = res.data.data.comments || [];
          thread.innerHTML = comments.length
            ? comments.map(c => `<div class="comment-row"><span class="mock-avatar"></span><div><strong>${escapeHtml(c.full_name)}</strong><p>${escapeHtml(c.comment_text)}</p></div></div>`).join('')
              + '<div class="composer-row" style="margin-top:8px;"><input type="text" class="field-input new-comment-input" placeholder="Write a comment…"><button class="btn btn-primary btn-send-comment" data-id="' + id + '">Send</button></div>'
            : '<p class="text-muted">No comments yet.</p><div class="composer-row" style="margin-top:8px;"><input type="text" class="field-input new-comment-input" placeholder="Write a comment…"><button class="btn btn-primary btn-send-comment" data-id="' + id + '">Send</button></div>';
        }
        thread.style.display = '';
      } else {
        thread.style.display = 'none';
      }
      return;
    }
    const sendBtn = e.target.closest('.btn-send-comment');
    if (sendBtn) {
      const input = sendBtn.parentElement.querySelector('.new-comment-input');
      const text = input.value.trim();
      if (!text) return;
      const id = sendBtn.dataset.id;
      const res = await apiFetch(`announcements/${id}/comment`, { method: 'POST', body: { comment_text: text } });
      if (res.ok && res.data?.success) {
        input.value = '';
        load();
      }
    }
  });

  // Composer visibility — plain students cannot author announcements.
  if (!currentUserIsAnnouncementAuthor()) {
    if (newBtn) newBtn.style.display = 'none';
    if (composer) composer.style.display = 'none';
  } else if (newBtn && composer) {
    const publishBtn = document.getElementById('composerPublish');
    publishBtn?.addEventListener('click', async () => {
      const title = document.getElementById('composerTitle')?.value.trim();
      const body = document.getElementById('composerBody')?.value.trim();
      const categoryId = categorySelect?.value;
      const priority = document.getElementById('composerPriority')?.value || 'normal';
      const file = document.getElementById('composerAttachment')?.files?.[0];
      if (!title || !body || !categoryId) {
        alert('Title, body and category are required.');
        return;
      }

      let res;
      if (file) {
        const fd = new FormData();
        fd.append('title', title);
        fd.append('body', body);
        fd.append('category_id', categoryId);
        fd.append('priority', priority);
        fd.append('attachment', file);
        res = await apiFetch('announcements', { method: 'POST', body: fd });
      } else {
        res = await apiFetch('announcements', { method: 'POST', body: { title, body, category_id: categoryId, priority } });
      }

      if (res.ok && res.data?.success) {
        document.getElementById('composerTitle').value = '';
        document.getElementById('composerBody').value = '';
        if (document.getElementById('composerAttachment')) document.getElementById('composerAttachment').value = '';
        composer.classList.remove('open');
        load();
      } else {
        alert(res.data?.message || 'Could not publish announcement.');
      }
    });
  }

  load();
}

// =========================================================
// ASSIGNMENTS FEED (assignments.html)
// =========================================================
async function renderAssignmentsFeed() {
  const myGrid = document.getElementById('myAssignmentsGrid');
  const manageGrid = document.getElementById('manageAssignmentsGrid');
  if (!myGrid && !manageGrid) return;

  const user = getCurrentUser();
  let assignments = [];
  try {
    const res = await apiFetch('assignments');
    if (res.ok && res.data?.success) assignments = res.data.data;
  } catch { /* offline */ }

  if (myGrid) {
    if (!assignments.length) {
      myGrid.innerHTML = '<p class="text-muted">No assignments yet.</p>';
    } else if (user?.role === 'student') {
      myGrid.innerHTML = '<p class="text-muted">Loading submission status…</p>';
      const cards = await Promise.all(assignments.map(async a => {
        let mine = null;
        try {
          const subRes = await apiFetch(`assignments/${a.assignment_id}/my-submission`);
          if (subRes.ok && subRes.data?.success) mine = subRes.data.data;
        } catch { /* offline */ }

        let badge = '<span class="mock-badge badge-danger">Not Submitted</span>';
        let footNote = 'Not yet submitted';
        let actionBtn = `<button class="btn btn-primary btn-upload-assignment" data-id="${a.assignment_id}">Upload</button>`;
        if (mine) {
          if (mine.grade !== null && mine.grade !== undefined) {
            badge = `<span class="mock-badge badge-gold">Graded: ${escapeHtml(mine.grade)}</span>`;
            footNote = mine.feedback ? 'Feedback available' : 'Graded';
            actionBtn = '<button class="btn btn-outline" disabled>View Feedback</button>';
          } else if (mine.is_late == 1) {
            badge = '<span class="mock-badge badge-danger">Late</span>';
            footNote = 'Submitted late — awaiting grading';
            actionBtn = '<button class="btn btn-outline" disabled>View Submission</button>';
          } else {
            badge = '<span class="mock-badge badge-green">Submitted</span>';
            footNote = 'Awaiting grading';
            actionBtn = '<button class="btn btn-outline" disabled>View Submission</button>';
          }
        }

        return `
          <div class="assign-card-full" data-id="${a.assignment_id}">
            <div class="assign-top"><div><h4>${escapeHtml(a.title)}</h4><p>${a.description ? escapeHtml(a.description) : ''}</p><p>Due: ${a.due_date ? new Date(a.due_date.replace(' ', 'T')).toLocaleString() : 'TBA'}</p></div>${badge}</div>
            <div class="assign-foot"><span class="text-muted">${footNote}</span>${actionBtn}
            <input type="file" class="assignment-file-input" data-id="${a.assignment_id}" style="display:none;"></div>
          </div>`;
      }));
      myGrid.innerHTML = cards.join('');

      myGrid.addEventListener('click', e => {
        const btn = e.target.closest('.btn-upload-assignment');
        if (!btn) return;
        const input = myGrid.querySelector(`.assignment-file-input[data-id="${btn.dataset.id}"]`);
        input?.click();
      });
      myGrid.addEventListener('change', async e => {
        const input = e.target.closest('.assignment-file-input');
        if (!input) return;
        const file = input.files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        const res = await apiFetch(`assignments/${input.dataset.id}/submit`, { method: 'POST', body: fd });
        if (res.ok && res.data?.success) {
          renderAssignmentsFeed();
        } else {
          alert(res.data?.message || 'Submission failed.');
        }
      });
    } else {
      myGrid.innerHTML = '<p class="text-muted">Assignment submission tracking is available for students.</p>';
    }
  }

  if (manageGrid) {
    const canManage = user && (user.role === 'lecturer' || user.role === 'administrator');
    if (!canManage) {
      manageGrid.innerHTML = '<p class="text-muted">Only lecturers can manage assignments here.</p>';
    } else if (!assignments.length) {
      manageGrid.innerHTML = '<p class="text-muted">No assignments created yet.</p>';
    } else {
      manageGrid.innerHTML = '<p class="text-muted">Loading submission counts…</p>';
      const cards = await Promise.all(assignments.map(async a => {
        let count = 0;
        try {
          const subRes = await apiFetch(`assignments/${a.assignment_id}/submissions`);
          if (subRes.ok && subRes.data?.success) count = subRes.data.data.length;
        } catch { /* offline */ }
        return `
          <div class="assign-card-full" data-id="${a.assignment_id}">
            <div class="assign-top"><div><h4>${escapeHtml(a.title)}</h4><p>Due: ${a.due_date ? new Date(a.due_date.replace(' ', 'T')).toLocaleString() : 'TBA'}</p></div></div>
            <div class="assign-foot"><span class="text-muted">${count} submission${count === 1 ? '' : 's'}</span></div>
          </div>`;
      }));
      manageGrid.innerHTML = cards.join('');
    }
  }
}

// =========================================================
// COURSES FEED (courses.html)
// =========================================================
function initCoursesPage() {
  // placeholder for future course-page-only wiring (kept separate from
  // renderCoursesFeed so the fetch/render function stays reusable/testable)
}

async function renderCoursesFeed() {
  const grid = document.getElementById('coursesGrid');
  if (!grid) return;

  grid.innerHTML = '<p class="text-muted">Loading courses…</p>';
  const user = getCurrentUser();
  try {
    const res = await apiFetch('courses');
    if (!res.ok || !res.data?.success) {
      grid.innerHTML = '<p class="text-muted">Could not load courses right now.</p>';
      return;
    }
    const courses = res.data.data;
    if (!courses.length) {
      grid.innerHTML = '<p class="text-muted">No courses yet — check back once your department publishes the semester\'s course list.</p>';
      return;
    }
    grid.innerHTML = courses.map(c => `
      <div class="course-card" data-id="${c.course_id}">
        <span class="course-code">${escapeHtml(c.course_code)}</span>
        <h3>${escapeHtml(c.course_name)}</h3>
        <p>${c.lecturer_name ? escapeHtml(c.lecturer_name) : 'Lecturer TBA'} · ${c.credits ?? '—'} Credits</p>
        <div class="course-stats"><div><strong>${c.assignment_count ?? 0}</strong>Assignments</div><div><strong>${c.student_count ?? 0}</strong>Students</div></div>
        ${user?.role === 'student' ? `<button class="btn btn-outline btn-block btn-register-course" data-id="${c.course_id}">Register</button>` : '<a href="assignments.html" class="btn btn-outline btn-block">View Course</a>'}
      </div>`).join('');

    grid.addEventListener('click', async e => {
      const btn = e.target.closest('.btn-register-course');
      if (!btn) return;
      const res2 = await apiFetch(`courses/${btn.dataset.id}/register`, { method: 'POST' });
      if (res2.ok && res2.data?.success !== false) {
        btn.textContent = 'Registered';
        btn.disabled = true;
      } else {
        alert(res2.data?.message || 'Could not register for course.');
      }
    });
  } catch {
    grid.innerHTML = '<p class="text-muted">Could not load courses right now.</p>';
  }
}

// =========================================================
// SMS TEST BUTTON (student.html) — texts the logged-in user's own
// phone so you can confirm the Vynfy integration end-to-end.
// =========================================================
function initSmsTest() {
  const btn = document.getElementById('smsTestBtn');
  const out = document.getElementById('smsTestResult');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    if (out) { out.style.display = 'none'; }
    const res = await apiFetch('sms/test', { method: 'POST' });
    if (out) {
      out.style.display = '';
      const ok = res.ok && res.data?.success;
      out.style.color = ok ? 'var(--success)' : 'var(--danger)';
      out.textContent = res.data?.message || (ok ? 'Sent.' : 'Could not send.');
    }
    btn.disabled = false;
  });
}

// =========================================================
// HOD / ADMIN MANAGEMENT (dashboard.html) — approve lecturers,
// create courses, assign lecturers to courses.
// =========================================================
let _hodMgmtLoaded = false;
async function renderHodManagement() {
  const section = document.getElementById('hodManagement');
  if (!section) return;

  const user = getCurrentUser();
  const role = user?.role;
  if (role !== 'hod' && role !== 'administrator') {
    section.style.display = 'none';
    return;
  }
  section.style.display = '';
  if (_hodMgmtLoaded) return;   // avoid double-loading if called again post session-refresh
  _hodMgmtLoaded = true;

  const pendingBox = document.getElementById('pendingLecturers');
  const lecturerSelect = document.getElementById('courseLecturer');
  const coursesBox = document.getElementById('manageCoursesList');
  let approvedLecturers = [];

  function lecturerOptions(selectedId) {
    return '<option value="">— Unassigned —</option>' + approvedLecturers.map(l =>
      `<option value="${l.lecturer_id}"${Number(selectedId) === Number(l.lecturer_id) ? ' selected' : ''}>${escapeHtml(l.full_name)} (${escapeHtml(l.identifier)})</option>`).join('');
  }

  async function loadPending() {
    const res = await apiFetch('lecturers/pending');
    if (!res.ok || !res.data?.success) { pendingBox.innerHTML = '<p class="text-muted">Could not load.</p>'; return; }
    const list = res.data.data;
    pendingBox.innerHTML = list.length ? list.map(l => `
      <div class="pending-row" data-id="${l.user_id}">
        <div><strong>${escapeHtml(l.full_name)}</strong><span class="text-muted"> · ${escapeHtml(l.identifier)} · ${escapeHtml(l.email)}</span></div>
        <div class="pending-actions">
          <button class="btn btn-primary btn-sm btn-approve-lec" data-id="${l.user_id}">Approve</button>
          <button class="btn btn-outline btn-sm btn-reject-lec" data-id="${l.user_id}">Reject</button>
        </div>
      </div>`).join('') : '<p class="text-muted">No lecturers awaiting approval.</p>';
  }

  async function loadLecturers() {
    const res = await apiFetch('lecturers');
    approvedLecturers = (res.ok && res.data?.success) ? res.data.data : [];
    if (lecturerSelect) lecturerSelect.innerHTML = lecturerOptions('');
  }

  async function loadCourses() {
    const res = await apiFetch('courses');
    if (!res.ok || !res.data?.success) { coursesBox.innerHTML = '<p class="text-muted">Could not load courses.</p>'; return; }
    const courses = res.data.data;
    coursesBox.innerHTML = courses.length ? courses.map(c => `
      <div class="manage-course-row" data-id="${c.course_id}">
        <div><strong>${escapeHtml(c.course_code)}</strong> — ${escapeHtml(c.course_name)}
          <span class="text-muted">(${escapeHtml(c.lecturer_name || 'Unassigned')})</span></div>
        <select class="field-input course-reassign" data-id="${c.course_id}">${lecturerOptions(c.lecturer_id)}</select>
      </div>`).join('') : '<p class="text-muted">No courses yet.</p>';
  }

  pendingBox?.addEventListener('click', async e => {
    const approve = e.target.closest('.btn-approve-lec');
    const reject = e.target.closest('.btn-reject-lec');
    if (!approve && !reject) return;
    const id = (approve || reject).dataset.id;
    const action = approve ? 'approve' : 'reject';
    const res = await apiFetch(`lecturers/${id}/${action}`, { method: 'POST' });
    if (res.ok && res.data?.success) { await loadPending(); await loadLecturers(); }
    else alert(res.data?.message || 'Action failed.');
  });

  coursesBox?.addEventListener('change', async e => {
    const sel = e.target.closest('.course-reassign');
    if (!sel) return;
    const res = await apiFetch(`courses/${sel.dataset.id}`, { method: 'PUT', body: { lecturer_id: sel.value } });
    if (res.ok && res.data?.success) loadCourses();
    else alert(res.data?.message || 'Could not reassign.');
  });

  document.getElementById('createCourseBtn')?.addEventListener('click', async () => {
    const err = document.getElementById('createCourseError');
    if (err) err.style.display = 'none';
    const payload = {
      course_code: document.getElementById('courseCode')?.value.trim(),
      course_name: document.getElementById('courseName')?.value.trim(),
      description: document.getElementById('courseDesc')?.value.trim(),
      lecturer_id: document.getElementById('courseLecturer')?.value || '',
    };
    if (!payload.course_code || !payload.course_name) {
      if (err) { err.textContent = 'Course code and name are required.'; err.style.display = ''; }
      return;
    }
    const res = await apiFetch('courses', { method: 'POST', body: payload });
    if (res.ok && res.data?.success) {
      ['courseCode', 'courseName', 'courseDesc'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
      if (document.getElementById('courseLecturer')) document.getElementById('courseLecturer').value = '';
      loadCourses();
    } else if (err) {
      err.textContent = res.data?.message || 'Could not create course.';
      err.style.display = '';
    }
  });

  await loadPending();
  await loadLecturers();
  await loadCourses();
}

// =========================================================
// MESSAGE BADGES — nav "Messages" unread count + requests-tab count.
// Runs on every page so the sidebar badge is always live.
// =========================================================
function applyMessageBadges(counts) {
  const inbox = Number(counts?.inbox_unread) || 0;
  const requests = Number(counts?.requests) || 0;

  document.querySelectorAll('.side-link[href="messages.html"] .side-badge').forEach(b => {
    b.textContent = inbox;
    b.style.display = inbox > 0 ? '' : 'none';
  });
  const reqBadge = document.getElementById('requestsBadge');
  if (reqBadge) {
    reqBadge.textContent = requests;
    reqBadge.style.display = requests > 0 ? '' : 'none';
  }
}

async function updateMessageBadges() {
  try {
    const res = await apiFetch('messages/unread-count');
    if (res.ok && res.data?.success) applyMessageBadges(res.data.data);
    else applyMessageBadges({ inbox_unread: 0, requests: 0 });
  } catch {
    applyMessageBadges({ inbox_unread: 0, requests: 0 });
  }
}

// =========================================================
// MESSAGES FEED (messages.html)
// =========================================================
async function renderMessagesFeed() {
  const convItems = document.getElementById('convItems');
  if (!convItems) return;

  const chatBody = document.getElementById('chatBody');
  const chatHeadName = document.getElementById('chatHeadName');
  const chatHeadSub = document.getElementById('chatHeadSub');
  const chatInput = document.getElementById('chatInput');
  const chatSendBtn = document.getElementById('chatSendBtn');
  const layout = document.querySelector('.messages-layout');
  let activeConversationId = null;

  const myId = await getMyUserId();

  function convLabel(c) {
    if (c.title) return c.title;
    if (c.other_participant_name) return c.other_participant_name;
    return (c.is_group == 1 ? 'Group Conversation #' : 'Direct Message #') + c.conversation_id;
  }

  let activeBox = 'inbox';

  async function loadConversations() {
    const box = activeBox;
    const endpoint = box === 'requests' ? 'messages/requests' : 'messages';
    convItems.innerHTML = '<p class="text-muted" style="padding:14px;">Loading…</p>';
    try {
      const res = await apiFetch(endpoint);
      if (!res.ok || !res.data?.success) {
        convItems.innerHTML = '<p class="text-muted" style="padding:14px;">Could not load conversations.</p>';
        return;
      }
      const conversations = res.data.data;
      if (!conversations.length) {
        convItems.innerHTML = box === 'requests'
          ? '<p class="text-muted" style="padding:14px;">No message requests.</p>'
          : '<p class="text-muted" style="padding:14px;">No conversations yet.</p>';
        return;
      }
      convItems.innerHTML = conversations.map(c => {
        const unread = Number(c.unread_count) || 0;
        const badge = unread > 0 ? `<span class="conv-unread">${unread}</span>` : '';
        const actions = box === 'requests'
          ? `<div class="conv-request-actions">
               <button class="btn btn-primary btn-sm btn-accept-req" data-id="${c.conversation_id}" data-name="${escapeHtml(convLabel(c))}">Accept</button>
               <button class="btn btn-outline btn-sm btn-decline-req" data-id="${c.conversation_id}">Delete</button>
             </div>`
          : '';
        return `
        <div class="conv-row${c.conversation_id === activeConversationId ? ' active' : ''}${unread > 0 ? ' unread' : ''}" data-id="${c.conversation_id}" data-box="${box}">
          <span class="mock-avatar lg"></span>
          <div class="conv-main"><strong>${escapeHtml(convLabel(c))}</strong><p>${c.last_message ? escapeHtml(c.last_message) : 'No messages yet'}</p>${actions}</div>
          <div class="conv-meta"><span class="msg-time">${c.last_message_at ? timeAgo(c.last_message_at) : ''}</span>${badge}</div>
        </div>`;
      }).join('');
    } catch {
      convItems.innerHTML = '<p class="text-muted" style="padding:14px;">Could not load conversations.</p>';
    }
  }

  // Inbox / Requests tab switching.
  document.querySelectorAll('.conv-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.conv-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeBox = tab.dataset.box;
      loadConversations();
    });
  });

  async function openConversation(id, label) {
    activeConversationId = id;
    convItems.querySelectorAll('.conv-row').forEach(r => r.classList.toggle('active', r.dataset.id == id));
    layout?.classList.add('chat-open');
    if (chatHeadName) chatHeadName.textContent = label;
    if (chatHeadSub) chatHeadSub.textContent = '';
    chatBody.innerHTML = '<p class="text-muted">Loading messages…</p>';

    try {
      const res = await apiFetch(`messages/${id}`);
      if (!res.ok || !res.data?.success) {
        chatBody.innerHTML = '<p class="text-muted">Could not load messages.</p>';
        return;
      }
      const messages = res.data.data;
      if (!messages.length) {
        chatBody.innerHTML = '<p class="text-muted">No messages yet — say hello!</p>';
        return;
      }
      chatBody.innerHTML = messages.map(m => {
        const mine = myId && Number(m.sender_id) === Number(myId);
        return `<div class="chat-bubble ${mine ? 'sent' : 'received'}">${!mine ? '<strong style="display:block;font-size:.72rem;opacity:.7;">' + escapeHtml(m.sender_name) + '</strong>' : ''}${escapeHtml(m.body || '')}<span class="chat-bubble-time">${timeAgo(m.sent_at)}</span></div>`;
      }).join('');
      chatBody.scrollTop = chatBody.scrollHeight;
    } catch {
      chatBody.innerHTML = '<p class="text-muted">Could not load messages.</p>';
    }
  }

  convItems.addEventListener('click', async e => {
    const acceptBtn = e.target.closest('.btn-accept-req');
    if (acceptBtn) {
      const id = acceptBtn.dataset.id;
      const res = await apiFetch(`messages/${id}/accept`, { method: 'POST' });
      if (res.ok && res.data?.success) {
        activeBox = 'inbox';
        document.querySelectorAll('.conv-tab').forEach(t => t.classList.toggle('active', t.dataset.box === 'inbox'));
        await loadConversations();
        openConversation(id, acceptBtn.dataset.name || 'Conversation');
        refreshBadges();
      }
      return;
    }
    const declineBtn = e.target.closest('.btn-decline-req');
    if (declineBtn) {
      if (!confirm('Delete this message request? This cannot be undone.')) return;
      const res = await apiFetch(`messages/${declineBtn.dataset.id}/decline`, { method: 'POST' });
      if (res.ok && res.data?.success) { await loadConversations(); refreshBadges(); }
      return;
    }
    const row = e.target.closest('.conv-row');
    if (!row) return;
    const label = row.querySelector('strong')?.textContent || 'Conversation';
    openConversation(row.dataset.id, label).then(() => { if (row.dataset.box !== 'requests') refreshBadges(); });
  });

  // Refresh the requests-tab count + the global nav "Messages" badge.
  async function refreshBadges() {
    try {
      const res = await apiFetch('messages/unread-count');
      if (res.ok && res.data?.success) applyMessageBadges(res.data.data);
    } catch { /* ignore */ }
  }

  document.getElementById('backToConvList')?.addEventListener('click', () => {
    layout?.classList.remove('chat-open');
  });

  async function sendMessage() {
    const text = chatInput?.value.trim();
    if (!text || !activeConversationId) return;
    const res = await apiFetch('messages', { method: 'POST', body: { conversation_id: activeConversationId, body: text } });
    if (res.ok && res.data?.success) {
      chatInput.value = '';
      openConversation(activeConversationId, chatHeadName?.textContent || 'Conversation');
      loadConversations();
    }
  }
  chatSendBtn?.addEventListener('click', sendMessage);
  chatInput?.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

  initNewMessageCompose(loadConversations, openConversation);

  loadConversations();
}

// New Message compose: search the user directory, pick a recipient, send
// the first message. This is the only way to START a conversation — the
// conversation list only ever shows threads that already have a message.
function initNewMessageCompose(loadConversations, openConversation) {
  const openBtn = document.getElementById('newMessageBtn');
  const card = document.getElementById('newMessageCard');
  const cancelBtn = document.getElementById('newMessageCancel');
  const searchInput = document.getElementById('newMessageSearch');
  const results = document.getElementById('newMessageResults');
  const selectedBox = document.getElementById('newMessageSelected');
  const bodyInput = document.getElementById('newMessageBody');
  const sendBtn = document.getElementById('newMessageSend');
  const errEl = document.getElementById('newMessageError');
  if (!openBtn || !card) return;

  let selectedUser = null;
  let allUsers = [];      // full directory, loaded once when the card opens
  let directoryLoaded = false;

  function reset() {
    selectedUser = null;
    searchInput.value = '';
    bodyInput.value = '';
    results.innerHTML = '';
    selectedBox.style.display = 'none';
    if (errEl) errEl.style.display = 'none';
  }

  function renderDirectory(term) {
    const q = (term || '').trim().toLowerCase();
    const list = q
      ? allUsers.filter(u =>
          (u.full_name || '').toLowerCase().includes(q) ||
          (u.identifier || '').toLowerCase().includes(q))
      : allUsers;
    results.innerHTML = list.length
      ? list.slice(0, 100).map(u => `
        <div class="new-message-result" data-id="${u.user_id}" data-name="${escapeHtml(u.full_name)}">
          <span class="mock-avatar"></span>
          <div><strong>${escapeHtml(u.full_name)}</strong><span>${escapeHtml(ROLE_SLUG_LABELS[u.role_name] || u.role_name || '')}${u.department_name ? ' · ' + escapeHtml(u.department_name) : ''}</span></div>
        </div>`).join('')
      : '<p class="text-muted" style="padding:8px;">No matches.</p>';
  }

  async function loadDirectory() {
    results.innerHTML = '<p class="text-muted" style="padding:8px;">Loading people…</p>';
    const res = await apiFetch('users');
    if (res.ok && res.data?.success) {
      allUsers = res.data.data || [];
      directoryLoaded = true;
      renderDirectory('');
    } else {
      results.innerHTML = '<p class="text-muted" style="padding:8px;">Could not load the directory.</p>';
    }
  }

  openBtn.addEventListener('click', async () => {
    card.classList.add('open');
    if (!directoryLoaded) await loadDirectory(); else renderDirectory(searchInput.value);
    searchInput?.focus();
  });
  cancelBtn?.addEventListener('click', () => {
    card.classList.remove('open');
    reset();
  });

  // Everyone is loaded up-front, so filtering is instant + client-side.
  searchInput?.addEventListener('input', () => {
    if (directoryLoaded) renderDirectory(searchInput.value);
  });

  results?.addEventListener('click', e => {
    const row = e.target.closest('.new-message-result');
    if (!row) return;
    selectedUser = { id: row.dataset.id, name: row.dataset.name };
    selectedBox.innerHTML = `To: ${escapeHtml(selectedUser.name)} <button type="button" aria-label="Clear">&times;</button>`;
    selectedBox.style.display = '';
    results.innerHTML = '';
    searchInput.value = '';
    bodyInput?.focus();
  });

  selectedBox?.addEventListener('click', e => {
    if (e.target.tagName === 'BUTTON') { selectedUser = null; selectedBox.style.display = 'none'; }
  });

  sendBtn?.addEventListener('click', async () => {
    if (errEl) errEl.style.display = 'none';
    const text = bodyInput?.value.trim();
    if (!selectedUser) {
      if (errEl) { errEl.textContent = 'Pick a recipient first.'; errEl.style.display = ''; }
      return;
    }
    if (!text) {
      if (errEl) { errEl.textContent = 'Write a message first.'; errEl.style.display = ''; }
      return;
    }
    const recipientName = selectedUser.name;
    const res = await apiFetch('messages', { method: 'POST', body: { to_user_id: selectedUser.id, body: text } });
    if (res.ok && res.data?.success) {
      card.classList.remove('open');
      const conversationId = res.data.data?.conversation_id;
      reset();
      await loadConversations();
      if (conversationId) openConversation(conversationId, recipientName || 'Conversation');
    } else if (errEl) {
      errEl.textContent = res.data?.message || 'Could not send message.';
      errEl.style.display = '';
    }
  });
}

// =========================================================
// CALENDAR FEED (calendar.html)
// =========================================================
const EVENT_TYPE_TO_CHIP_CLASS = {
  exam: 'exam', deadline: 'deadline', meeting: 'dept', src_event: 'src',
  holiday: 'holiday', guest_lecture: 'seminar', seminar: 'seminar', other: 'dept'
};

async function renderCalendarFeed() {
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;

  const monthLabel = document.getElementById('calendarMonthLabel');
  const subtitle = document.getElementById('calendarSubtitle');
  const upcomingEl = document.getElementById('upcomingEvents');

  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  if (monthLabel) monthLabel.textContent = `${monthNames[month]} ${year}`;
  if (subtitle) subtitle.textContent = `${monthNames[month]} ${year} — academic events across campus.`;

  const from = `${year}-${String(month + 1).padStart(2, '0')}-01`;
  const lastDay = new Date(year, month + 1, 0).getDate();
  const to = `${year}-${String(month + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

  let events = [];
  try {
    const res = await apiFetch(`events?from=${from}&to=${to}`);
    if (res.ok && res.data?.success) events = res.data.data;
  } catch { /* offline */ }

  const eventsByDay = {};
  events.forEach(ev => {
    const d = new Date(ev.start_datetime.replace(' ', 'T')).getDate();
    (eventsByDay[d] = eventsByDay[d] || []).push(ev);
  });

  const firstWeekday = new Date(year, month, 1).getDay();
  let cellsHtml = '';
  for (let i = 0; i < firstWeekday; i++) cellsHtml += '<div class="cal-cell empty"></div>';
  const today = new Date();
  for (let day = 1; day <= lastDay; day++) {
    const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
    const dayEvents = eventsByDay[day] || [];
    const chips = dayEvents.map(ev =>
      `<span class="cal-chip ${EVENT_TYPE_TO_CHIP_CLASS[ev.event_type] || 'dept'}" title="${escapeHtml(ev.title)}">${escapeHtml(ev.title)}</span>`).join('');
    cellsHtml += `<div class="cal-cell${isToday ? ' today' : ''}"><span class="cal-daynum">${day}</span>${chips}</div>`;
  }
  grid.innerHTML = '<span class="cal-dow">Sun</span><span class="cal-dow">Mon</span><span class="cal-dow">Tue</span><span class="cal-dow">Wed</span><span class="cal-dow">Thu</span><span class="cal-dow">Fri</span><span class="cal-dow">Sat</span>' + cellsHtml;

  if (upcomingEl) {
    const upcoming = events
      .filter(ev => new Date(ev.start_datetime.replace(' ', 'T')) >= new Date(today.toDateString()))
      .sort((a, b) => new Date(a.start_datetime) - new Date(b.start_datetime))
      .slice(0, 6);
    if (!upcoming.length) {
      upcomingEl.innerHTML = '<p class="text-muted">No upcoming events this month.</p>';
    } else {
      upcomingEl.innerHTML = upcoming.map(ev => {
        const d = new Date(ev.start_datetime.replace(' ', 'T'));
        return `<div class="event-row"><div class="event-date"><strong>${d.getDate()}</strong><span>${monthNames[d.getMonth()].slice(0,3).toUpperCase()}</span></div><div><strong>${escapeHtml(ev.title)}</strong><p>${ev.location ? escapeHtml(ev.location) : ''}</p></div></div>`;
      }).join('');
    }
  }
}

// =========================================================
// NOTICE BOARD FEED (notice-board.html)
// =========================================================
async function renderNoticeBoardFeed() {
  const grid = document.getElementById('noticeGrid');
  if (!grid) return;

  const chipsBar = document.querySelector('.filter-bar');
  const chips = chipsBar ? Array.from(chipsBar.querySelectorAll('.chip')) : [];
  const searchInput = document.getElementById('noticeSearch');
  let activeCategory = null;
  const myId = await getMyUserId();
  const user = getCurrentUser();

  async function load() {
    grid.innerHTML = '<p class="text-muted">Loading notices…</p>';
    const params = new URLSearchParams();
    if (activeCategory) params.set('category', activeCategory);
    if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
    const qs = params.toString();

    try {
      const res = await apiFetch('documents' + (qs ? '?' + qs : ''));
      if (!res.ok || !res.data?.success) {
        grid.innerHTML = '<p class="text-muted">Could not load notices right now.</p>';
        return;
      }
      const docs = res.data.data;
      if (!docs.length) {
        grid.innerHTML = '<p class="text-muted">No notices posted yet.</p>';
        return;
      }
      grid.innerHTML = docs.map(d => {
        const canPin = user && (Number(d.uploaded_by) === Number(myId) || user.role === 'administrator');
        const cardClass = d.is_pinned == 1 ? 'notice-card pinned' : 'notice-card';
        return `
          <div class="${cardClass}" data-id="${d.document_id}">
            ${d.is_pinned == 1 ? '<div class="notice-pin">📌 Pinned</div>' : ''}
            <h4>${escapeHtml(d.title)}</h4>
            <p>${d.category ? escapeHtml(d.category) : ''}</p>
            <div class="notice-meta">${escapeHtml(d.uploader_name)} · ${new Date(d.uploaded_at.replace(' ', 'T')).toLocaleDateString()}</div>
            <a href="uploads/${d.file_path}" target="_blank" class="notice-download">⬇ Download</a>
            ${canPin ? `<button class="btn btn-ghost btn-toggle-pin" data-id="${d.document_id}" style="margin-top:8px;">${d.is_pinned == 1 ? 'Unpin' : 'Pin'}</button>` : ''}
          </div>`;
      }).join('');
    } catch {
      grid.innerHTML = '<p class="text-muted">Could not load notices right now.</p>';
    }
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const label = chip.textContent.trim();
      activeCategory = label === 'All' ? null : label;
      load();
    });
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 350);
  });

  grid.addEventListener('click', async e => {
    const btn = e.target.closest('.btn-toggle-pin');
    if (!btn) return;
    const res = await apiFetch(`documents/${btn.dataset.id}/pin`, { method: 'POST' });
    if (res.ok && res.data?.success) load();
  });

  load();
}

// =========================================================
// NOTIFICATIONS FEED (notifications.html)
// =========================================================
const NOTIF_TYPE_ICON = {
  assignment_due: '📝', assignment_graded: '🎓', announcement: '📢',
  message: '💬', event: '🗓', deadline: '⏰'
};

async function renderNotificationsFeed() {
  const panel = document.getElementById('notifPanel');
  if (!panel) return;

  const markAllBtn = document.getElementById('markAllReadBtn');

  async function load() {
    panel.innerHTML = '<p class="text-muted">Loading notifications…</p>';
    try {
      const res = await apiFetch('notifications');
      if (!res.ok || !res.data?.success) {
        panel.innerHTML = '<p class="text-muted">Could not load notifications right now.</p>';
        return;
      }
      const items = res.data.data;
      if (!items.length) {
        panel.innerHTML = '<p class="text-muted">Nothing here yet — you\'re all caught up.</p>';
        return;
      }
      const priorityClass = { high: 'high', medium: 'medium', low: 'low' };
      panel.innerHTML = items.map(n => `
        <div class="notif-row-full ${priorityClass[n.priority] || 'low'}${n.is_read == 0 ? ' unread' : ''}" data-id="${n.notification_id}">
          <span class="notif-icon-full">${NOTIF_TYPE_ICON[n.type] || '🔔'}</span>
          <div><strong>${escapeHtml(n.title)}</strong><p>${escapeHtml(n.body || '')}</p></div>
          <time>${timeAgo(n.created_at)}</time>
          <button class="btn btn-ghost btn-delete-notif" data-id="${n.notification_id}" title="Delete" style="margin-left:8px;">✕</button>
        </div>`).join('');
    } catch {
      panel.innerHTML = '<p class="text-muted">Could not load notifications right now.</p>';
    }
  }

  panel.addEventListener('click', async e => {
    const delBtn = e.target.closest('.btn-delete-notif');
    if (delBtn) {
      const res = await apiFetch(`notifications/${delBtn.dataset.id}`, { method: 'DELETE' });
      if (res.ok && res.data?.success) load();
      return;
    }
    const row = e.target.closest('.notif-row-full');
    if (row && row.classList.contains('unread')) {
      const res = await apiFetch(`notifications/${row.dataset.id}/read`, { method: 'POST' });
      if (res.ok && res.data?.success) {
        row.classList.remove('unread');
      }
    }
  });

  markAllBtn?.addEventListener('click', async () => {
    const res = await apiFetch('notifications/read-all', { method: 'POST' });
    if (res.ok && res.data?.success) load();
  });

  load();
}
