(() => {
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const closeButton = document.querySelector('[data-sidebar-close]');

  const setSidebarOpen = (open) => {
    document.body.classList.toggle('sidebar-open', open);
    if (toggle) toggle.setAttribute('aria-expanded', String(open));
    if (closeButton) closeButton.hidden = !open;
  };

  if (sidebar && toggle && closeButton) {
    toggle.addEventListener('click', () => {
      setSidebarOpen(!document.body.classList.contains('sidebar-open'));
    });
    closeButton.addEventListener('click', () => setSidebarOpen(false));
    sidebar.addEventListener('click', (event) => {
      if (event.target.closest('a')) setSidebarOpen(false);
    });
  }

  let confirmedForm = null;
  let pendingForm = null;
  const dialog = document.createElement('dialog');
  dialog.className = 'ui-confirm-dialog';
  dialog.setAttribute('aria-labelledby', 'ui-confirm-title');
  dialog.innerHTML = '<h2 id="ui-confirm-title">Please confirm</h2><p data-confirm-message></p><div class="ui-confirm-actions"><button class="ui-button ui-button--secondary" type="button" data-confirm-cancel>Cancel</button><button class="ui-button" type="button" data-confirm-accept>Continue</button></div>';
  document.body.append(dialog);

  const message = dialog.querySelector('[data-confirm-message]');
  const cancel = dialog.querySelector('[data-confirm-cancel]');
  const accept = dialog.querySelector('[data-confirm-accept]');

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (confirmedForm === form) {
      confirmedForm = null;
      return;
    }

    const prompt = form.dataset.confirm || form.dataset.confirmShift;
    if (!prompt) return;

    event.preventDefault();
    pendingForm = form;
    message.textContent = prompt;
    dialog.showModal();
  });

  cancel.addEventListener('click', () => dialog.close());
  accept.addEventListener('click', () => {
    if (!pendingForm) return;
    const form = pendingForm;
    pendingForm = null;
    confirmedForm = form;
    dialog.close();
    form.requestSubmit();
  });

  dialog.addEventListener('close', () => {
    pendingForm = null;
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      setSidebarOpen(false);
    }
  });
})();
