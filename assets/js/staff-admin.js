window.submitColFilter = function (key, val) {
  var url = new URL(window.location.href);
  if (val !== undefined && val !== null && String(val).trim() !== '') {
    url.searchParams.set(key, String(val).trim());
  } else {
    url.searchParams.delete(key);
  }
  window.location.href = url.toString();
};
document.addEventListener('click', function (event) {
  document.querySelectorAll('details.th-dropdown[open]').forEach(function (detail) {
    if (!detail.contains(event.target)) {
      detail.removeAttribute('open');
    }
  });
  const open = event.target.closest('[data-open-dialog]');
  if (open) {
    const dialog = document.getElementById(open.dataset.openDialog);
    if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    return;
  }
  const close = event.target.closest('[data-close-dialog]');
  if (close) { const dialog = close.closest('dialog'); if (dialog) dialog.close(); }
});
document.querySelectorAll('dialog.staff-dialog').forEach(function (dialog) {
  dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
});
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.staff-role').forEach(function (role) {
    if (role.textContent.trim().toLowerCase() === 'admin') role.closest('tr')?.querySelector('[data-open-dialog^="staff-edit-"]')?.remove();
  });
  document.querySelectorAll('dialog.staff-dialog select[name="role"]').forEach(function (select) {
    select.querySelectorAll('option[value="admin"]').forEach(function (option) { option.remove(); });
    if (select.value !== 'moderator') select.value = 'moderator';
  });
});
document.addEventListener('submit', function (event) {
  const field = event.target.querySelector('[data-confirm-exact]');
  if (field && field.value !== field.dataset.confirmExact) {
    event.preventDefault();
    field.setCustomValidity('Please enter the exact username to confirm permanent deletion.');
    field.reportValidity();
    field.addEventListener('input', () => field.setCustomValidity(''), {once:true});
  }
});
