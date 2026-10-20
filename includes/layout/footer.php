        </main>
    </div>
</div>
<script>
(() => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-confirm-shift]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.getAttribute('data-confirm-shift'))) {
                event.preventDefault();
            }
        });
    });

    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const sidebar = document.getElementById('app-sidebar');
    if (!toggle || !closeButton || !sidebar) return;

    const setOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        closeButton.hidden = !open;
    };

    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('sidebar-open')));
    closeButton.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
})();
</script>
</body>
</html>
