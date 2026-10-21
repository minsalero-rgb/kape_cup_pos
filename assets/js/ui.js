(() => {
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const closeButton = document.querySelector('[data-sidebar-close]');
  const mobileSidebarQuery = window.matchMedia('(max-width: 1023px)');

  const setSidebarOpen = (open, restoreFocus = false) => {
    const isOpen = open && mobileSidebarQuery.matches;
    document.body.classList.toggle('sidebar-open', isOpen);
    if (toggle) toggle.setAttribute('aria-expanded', String(isOpen));
    if (closeButton) closeButton.hidden = !isOpen;
    if (sidebar) {
      sidebar.inert = mobileSidebarQuery.matches && !isOpen;
      sidebar.setAttribute('aria-hidden', String(mobileSidebarQuery.matches && !isOpen));
    }

    if (isOpen && sidebar) {
      const firstFocusable = sidebar.querySelector('a[href], button:not(:disabled)');
      if (firstFocusable) firstFocusable.focus();
    } else if (restoreFocus && toggle) {
      toggle.focus();
    }
  };

  if (sidebar && toggle && closeButton) {
    setSidebarOpen(false);
    toggle.addEventListener('click', () => {
      setSidebarOpen(!document.body.classList.contains('sidebar-open'), true);
    });
    closeButton.addEventListener('click', () => setSidebarOpen(false, true));
    sidebar.addEventListener('click', (event) => {
      if (event.target.closest('a')) setSidebarOpen(false);
    });
    mobileSidebarQuery.addEventListener('change', () => setSidebarOpen(false));
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

    const prompt = form.dataset.confirm || (
      form.dataset.confirmShift
        ? form.dataset.staffName
          ? `Close shift #${form.dataset.confirmShift} for ${form.dataset.staffName}? This records a manual resolution time.`
          : form.dataset.confirmShift
        : ''
    );
    if (!prompt) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    pendingForm = form;
    message.textContent = prompt;
    dialog.showModal();
  }, true);

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
    if (event.key === 'Escape' && dialog.open) {
      event.preventDefault();
      dialog.close();
      return;
    }

    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      setSidebarOpen(false, true);
      return;
    }

    if (
      event.key === 'Tab'
      && sidebar
      && document.body.classList.contains('sidebar-open')
    ) {
      const focusable = Array.from(
        sidebar.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')
      );
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
})();
