'use strict';
document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname.replace(/\/$/, '');
  document.querySelectorAll('.side-nav a').forEach(link => {
    const href = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '');
    if (href === path) link.setAttribute('aria-current', 'page');
  });
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  if (!document.body.classList.contains('user-area')) return;
  const sidebar = document.querySelector('.sidebar');
  const topTitle = document.querySelector('.top-title');
  const logo = sidebar?.querySelector('.logo');
  const links = [...(sidebar?.querySelectorAll('.side-nav a') || [])];
  if (!topTitle || !logo || !links.length) return;
  const navbar = document.createElement('nav');
  navbar.className = 'client-top-nav';
  navbar.setAttribute('aria-label', 'Main navigation');
  links.forEach(link => {
    const tab = link.cloneNode(true);
    tab.innerHTML = tab.textContent.trim().replace(/^[^A-Za-z]+\s*/, '');
    navbar.append(tab);
  });
  const logoClone = logo.cloneNode(true);
  logoClone.classList.add('top-logo');
  topTitle.replaceChildren(logoClone, navbar);
});

document.addEventListener('DOMContentLoaded', () => {
  const greeting = document.querySelector('.hello');
  const sidebarAvatar = document.querySelector('.sidebar-account .account-avatar');
  const profileLink = document.querySelector('.sidebar-account');
  if (!greeting || !sidebarAvatar || !profileLink) return;
  const avatar = sidebarAvatar.cloneNode(true);
  avatar.classList.add('top-hello-avatar');
  avatar.removeAttribute('aria-hidden');
  greeting.prepend(avatar);
  greeting.classList.add('profile-quick-link');
  greeting.setAttribute('role', 'link');
  greeting.setAttribute('tabindex', '0');
  greeting.setAttribute('aria-label', 'Open your profile');
  const openProfile = () => { window.location.href = profileLink.href; };
  greeting.addEventListener('click', openProfile);
  greeting.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openProfile(); } });
});

document.addEventListener('DOMContentLoaded', () => {
  const title = document.querySelector('.top-title');
  if (!title || !title.textContent.includes('✨')) return;
  title.textContent = title.textContent.replace('✨', '').trim();
  const mascot = document.createElement('span');
  mascot.className = 'chibi chibi-cool chibi-navbar';
  mascot.setAttribute('aria-hidden', 'true');
  title.append(' ', mascot);
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.comment').forEach(comment => {
    const deleteForm = comment.querySelector('form[action*="delete-comment.php"]');
    const commentText = comment.querySelector('p');
    if (!deleteForm || !commentText) return;
    const csrf = deleteForm.querySelector('[name="_csrf"]');
    if (!csrf) return;
    const editButton = document.createElement('button');
    editButton.className = 'comment-pencil'; editButton.type = 'button'; editButton.title = 'Edit comment'; editButton.setAttribute('aria-label', 'Edit comment'); editButton.textContent = '✎';
    deleteForm.before(editButton);
    editButton.addEventListener('click', () => {
      if (comment.querySelector('.comment-inline-edit')) return;
      const editor = document.createElement('form');
      editor.method = 'post'; editor.action = deleteForm.action.replace('delete-comment.php', 'edit-comment.php'); editor.className = 'comment-inline-edit';
      const token = csrf.cloneNode(true);
      const textarea = document.createElement('textarea');
      textarea.name = 'content'; textarea.maxLength = 2000; textarea.required = true; textarea.value = commentText.textContent.trim();
      const controls = document.createElement('div'); controls.className = 'inline-actions';
      const save = document.createElement('button'); save.className = 'btn primary'; save.type = 'submit'; save.textContent = 'Save';
      const cancel = document.createElement('button'); cancel.className = 'btn ghost'; cancel.type = 'button'; cancel.textContent = 'Cancel';
      cancel.addEventListener('click', () => editor.replaceWith(commentText));
      controls.append(save, cancel); editor.append(token, textarea, controls);
      commentText.replaceWith(editor); textarea.focus();
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const cluster = document.querySelector('.hero-mood');
  if (!cluster) return;
  const faces = [...cluster.querySelectorAll('.hero-mood-face')];
  const main = cluster.querySelector('.hero-mood-main');
  const bubble = cluster.querySelector('[data-mood-bubble]');
  let current = 0;
  const showFace = index => {
    current = (index + faces.length) % faces.length;
    main.className = `chibi chibi-hero hero-mood-main ${faces[current].dataset.face}`;
    main.classList.remove('is-changing');
    requestAnimationFrame(() => main.classList.add('is-changing'));
    window.setTimeout(() => main.classList.remove('is-changing'), 650);
    if (bubble) bubble.textContent = faces[current].dataset.bubble || '';
    faces.forEach((face, faceIndex) => face.classList.toggle('is-current', faceIndex === current));
  };
  faces.forEach((face, index) => face.addEventListener('click', () => showFace(index)));
  showFace(0);
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.profile-secondary-grid .panel').forEach(panel => {
    const strip = panel.querySelector('[data-horizontal-strip]');
    if (!strip) return;
    const distance = () => strip.clientWidth + 16;
    panel.querySelector('[data-strip-prev]')?.addEventListener('click', () => strip.scrollBy({ left: -distance(), behavior: 'smooth' }));
    panel.querySelector('[data-strip-next]')?.addEventListener('click', () => strip.scrollBy({ left: distance(), behavior: 'smooth' }));
  });
});


document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('bell-trigger');
  const panel = document.getElementById('bell-panel');
  if (!btn || !panel) return;

  function close() {
    panel.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    btn.setAttribute('aria-expanded', String(!panel.hidden));
  });

  document.addEventListener('click', (e) => {
    if (!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });

  // AJAX mark as read handler: no redirect, no jumping to notification page, badge disappears instantly
  panel.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form || !form.action) return;
    e.preventDefault();

    const isReadAll = form.querySelector('.bell-readall') !== null || form.action.includes('read-all');

    // Optimistically update UI immediately for snappy response
    if (isReadAll) {
      panel.querySelectorAll('.bell-item.unread').forEach(item => item.classList.remove('unread'));
      panel.querySelectorAll('.bell-item form').forEach(f => f.remove());
      const badge = btn.querySelector('.bell-count');
      if (badge) badge.remove();
    } else {
      const item = form.closest('.bell-item');
      if (item) {
        item.classList.remove('unread');
        form.remove();
      }
      const remainingUnread = panel.querySelectorAll('.bell-item.unread').length;
      const badge = btn.querySelector('.bell-count');
      if (badge) {
        if (remainingUnread > 0) {
          badge.textContent = '+' + remainingUnread;
        } else {
          badge.remove();
        }
      }
    }

    try {
      await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    } catch (err) {
      console.error('Notification update failed:', err);
    }
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const picker = document.getElementById('avatar-picker');
  const toggles = document.querySelectorAll('[data-toggle-avatar-picker]');
  if (picker && toggles.length) {
    toggles.forEach(button => button.addEventListener('click', () => {
      picker.hidden = !picker.hidden;
      toggles.forEach(toggle => toggle.setAttribute('aria-expanded', String(!picker.hidden)));
      if (!picker.hidden) picker.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }));
  }
  const input = document.querySelector('[data-avatar-upload]');
  const dialog = document.getElementById('avatar-crop-dialog');
  const canvas = document.getElementById('avatar-crop-canvas');
  const zoom = document.getElementById('avatar-crop-zoom');
  const cancel = document.getElementById('avatar-crop-cancel');
  const save = document.getElementById('avatar-crop-save');
  if (!input || !dialog || !canvas || !zoom || !cancel || !save) return;

  const context = canvas.getContext('2d');
  const state = { image: null, scale: 1, baseScale: 1, x: 0, y: 0, dragging: false, startX: 0, startY: 0, originX: 0, originY: 0, objectUrl: null };
  const size = canvas.width;
  const clamp = () => {
    const width = state.image.width * state.scale;
    const height = state.image.height * state.scale;
    state.x = Math.min(0, Math.max(size - width, state.x));
    state.y = Math.min(0, Math.max(size - height, state.y));
  };
  const draw = () => {
    if (!state.image) return;
    context.clearRect(0, 0, size, size);
    context.save();
    context.beginPath();
    context.arc(size / 2, size / 2, size / 2 - 10, 0, Math.PI * 2);
    context.clip();
    context.drawImage(state.image, state.x, state.y, state.image.width * state.scale, state.image.height * state.scale);
    context.restore();
    context.beginPath();
    context.arc(size / 2, size / 2, size / 2 - 10, 0, Math.PI * 2);
    context.lineWidth = 12;
    context.strokeStyle = '#71d7d1';
    context.stroke();
  };
  const close = () => {
    if (dialog.open) dialog.close();
    if (state.objectUrl) URL.revokeObjectURL(state.objectUrl);
    state.objectUrl = null;
    input.value = '';
  };
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    if (state.objectUrl) URL.revokeObjectURL(state.objectUrl);
    state.objectUrl = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      state.image = image;
      state.baseScale = Math.max(size / image.width, size / image.height);
      state.scale = state.baseScale;
      state.x = (size - image.width * state.scale) / 2;
      state.y = (size - image.height * state.scale) / 2;
      zoom.value = '1';
      draw();
      dialog.showModal();
    };
    image.src = state.objectUrl;
  });
  zoom.addEventListener('input', () => {
    if (!state.image) return;
    const centreX = (size / 2 - state.x) / state.scale;
    const centreY = (size / 2 - state.y) / state.scale;
    state.scale = state.baseScale * Number(zoom.value);
    state.x = size / 2 - centreX * state.scale;
    state.y = size / 2 - centreY * state.scale;
    clamp(); draw();
  });
  canvas.addEventListener('pointerdown', event => {
    if (!state.image) return;
    state.dragging = true; state.startX = event.clientX; state.startY = event.clientY; state.originX = state.x; state.originY = state.y;
    canvas.setPointerCapture(event.pointerId); canvas.classList.add('is-dragging');
  });
  canvas.addEventListener('pointermove', event => {
    if (!state.dragging) return;
    state.x = state.originX + (event.clientX - state.startX) * (size / canvas.clientWidth);
    state.y = state.originY + (event.clientY - state.startY) * (size / canvas.clientHeight);
    clamp(); draw();
  });
  const stopDrag = () => { state.dragging = false; canvas.classList.remove('is-dragging'); };
  canvas.addEventListener('pointerup', stopDrag); canvas.addEventListener('pointercancel', stopDrag);
  cancel.addEventListener('click', close);
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  save.addEventListener('click', () => {
    save.disabled = true; save.textContent = 'Saving…';
    canvas.toBlob(blob => {
      if (!blob) { save.disabled = false; save.textContent = 'Use this photo'; return; }
      const form = input.closest('form');
      const data = new FormData();
      data.append('_csrf', form.querySelector('[name="_csrf"]').value);
      data.append('avatar', blob, 'avatar.png');
      fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(response => { if (!response.ok) throw new Error('Upload failed'); window.location.reload(); })
        .catch(() => { save.disabled = false; save.textContent = 'Try again'; alert('We could not save this photo. Please try again.'); });
    }, 'image/png');
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const eyeShow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeHide = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach(input => {
    if (input.closest('.pw-toggle-wrap')) return;
    const wrap = document.createElement('span');
    wrap.className = 'pw-toggle-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle-btn';
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = eyeShow;
    wrap.appendChild(btn);

    btn.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
      btn.innerHTML = showing ? eyeShow : eyeHide;
    });
  });
});
