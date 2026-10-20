<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/password_rules.php';
require_once __DIR__ . '/../includes/order_submission.php';
require __DIR__ . '/../config/db.php';

require_role(['admin'], $pdo);

define('ADMIN_DASHBOARD', true);

const ADMIN_PANEL_CLASS = 'rounded-2xl border border-border-neutral bg-warm-white p-space-md shadow-sm';
const ADMIN_INPUT_CLASS = 'mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs';
const ADMIN_PRIMARY_BUTTON_CLASS = 'rounded-lg bg-primary-container px-space-md py-space-sm font-label-button text-warm-white transition-colors hover:bg-primary';
const ADMIN_TABLE_CLASS = 'min-w-full divide-y divide-border-neutral text-left text-sm';

$admin = current_staff_user();
$navigationConfig = require __DIR__ . '/../includes/nav_config.php';
$tabs = [];
foreach ($navigationConfig as $item) {
    if (!in_array('admin', $item['roles'], true)) {
        continue;
    }
    if (
        $item['key'] !== 'customer'
        && strpos($item['url'], '/admin/dashboard.php?tab=') === 0
        && is_file(__DIR__ . '/modules/' . $item['key'] . '.php')
    ) {
        $tabs[$item['key']] = ['label' => $item['label'], 'icon' => $item['icon']];
    }
}

function admin_redirect(string $tab, string $query = ''): void
{
    $allowedTabs = ['overview', 'menu', 'categories', 'modifiers', 'orders', 'sessions', 'staff', 'reports'];
    if (!in_array($tab, $allowedTabs, true)) {
        throw new InvalidArgumentException('Invalid admin dashboard tab.');
    }

    header(
        'Location: dashboard.php?tab=' . rawurlencode($tab) . ($query === '' ? '' : '&' . $query),
        true,
        302
    );
    exit;
}

function admin_render_module(string $tab, PDO $pdo, array $admin, string $activeTab): string
{
    $modulePath = __DIR__ . '/modules/' . $tab . '.php';
    $moduleLabels = [
        'overview' => 'Overview',
        'menu' => 'Menu items',
        'categories' => 'Categories',
        'modifiers' => 'Modifiers',
        'orders' => 'Orders',
        'sessions' => 'Duty sessions',
        'staff' => 'Staff accounts',
        'reports' => 'Reports',
    ];
    $bufferLevel = ob_get_level();
    $previousResponseCode = http_response_code();
    ob_start();

    try {
        include $modulePath;
        if ($tab !== $activeTab && is_int($previousResponseCode)) {
            http_response_code($previousResponseCode);
        }
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
        error_log('[admin-dashboard] Module "' . $tab . '" failed: ' . $exception->getMessage());
        if ($tab === $activeTab || !is_int($previousResponseCode)) {
            http_response_code(500);
        } else {
            http_response_code($previousResponseCode);
        }

        return '<section class="' . ADMIN_PANEL_CLASS . '" role="alert"><h2 class="font-headline-sm text-headline-sm text-primary">'
            . escape_html($moduleLabels[$tab] ?? 'Admin section')
            . '</h2><p class="mt-space-xs text-body-sm text-muted-text">This section could not be loaded. Please try again later.</p></section>';
    }
}

$postedTab = isset($_POST['section']) && is_string($_POST['section']) ? $_POST['section'] : '';
$requestedTab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : '';
$activeTab = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($tabs[$postedTab])
    ? $postedTab
    : (isset($tabs[$requestedTab]) ? $requestedTab : 'overview');

$moduleMarkup = [];
foreach (array_keys($tabs) as $tab) {
    $moduleMarkup[$tab] = admin_render_module($tab, $pdo, $admin, $activeTab);
}

$role = 'admin';
$user = $admin;
$pageTitle = $tabs[$activeTab]['label'];
$pageEyebrow = 'Administration';
$activeNav = $activeTab;
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="app-dashboard-panels">
    <?php foreach ($tabs as $tabKey => $tab): ?>
        <section
            id="panel-<?= escape_html($tabKey) ?>"
            class="app-dashboard-panel"
            data-tab-panel="<?= escape_html($tabKey) ?>"
            aria-labelledby="nav-<?= escape_html($tabKey) ?>"
            <?= $activeTab === $tabKey ? '' : 'hidden' ?>
        >
            <?= $moduleMarkup[$tabKey] ?>
        </section>
    <?php endforeach; ?>
</div>
<script>
(() => {
    const tabLinks = Array.from(document.querySelectorAll('[data-admin-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));

    function activateTab(tab, updateHistory) {
        const target = document.getElementById(`panel-${tab}`);
        if (!target) return;

        panels.forEach((panel) => {
            panel.hidden = panel !== target;
        });
        tabLinks.forEach((link) => {
            const active = link.dataset.adminTab === tab;
            link.classList.toggle('is-active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
        if (updateHistory) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            history.pushState({ tab }, '', url);
        }
        const title = tabLinks.find((link) => link.dataset.adminTab === tab)?.dataset.tabLabel || 'Admin';
        const pageTitle = document.querySelector('[data-app-page-title]');
        if (pageTitle) pageTitle.textContent = title;
        document.title = `${title} | M&E Kape Cup Admin`;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-shift')) return;

        const shiftId = form.dataset.confirmShift;
        const staffName = form.dataset.staffName || '';
        if (!window.confirm(`Close shift #${shiftId} for ${staffName}? This records a manual resolution time.`)) {
            event.preventDefault();
        }
    });

    tabLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            activateTab(link.dataset.adminTab, true);
        });
    });

    window.addEventListener('popstate', () => {
        const tab = new URL(window.location.href).searchParams.get('tab') || 'overview';
        activateTab(tab, false);
    });
})();
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
