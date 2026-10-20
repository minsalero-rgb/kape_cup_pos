import { useEffect, useMemo, useRef, useState } from "react";
import type { ChangeEvent, FormEvent, ReactNode } from "react";
import { getCartTotal, getDiscountAmount, usePosStore } from "./store";
import { Icon } from "./ui";
import {
  getStaffNavigation,
  getStaffName,
  isAdminSession,
  StaffLogoutNavItem,
  StaffShiftControl,
} from "./StaffNavigation";
import type {
  CartLine,
  Item,
  Order,
  OrderStatus,
  PaymentType,
} from "./types";

export type AppPage = "pos" | "queue" | "reports" | "stock" | "customer";

const money = (amount: number): string =>
  new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);

const activeOrderCount = (orders: Order[]): number =>
  orders.filter((order) =>
    ["new", "preparing", "ready"].includes(order.status),
  ).length;

function NavSidebar({ page, close }: { page: AppPage; close: () => void }) {
  const orders = usePosStore((state) => state.orders);
  const items = usePosStore((state) => state.items);
  const activeCount = activeOrderCount(orders);
  const lowStockCount = items.filter(
    (item) => item.stock <= item.lowStockAt,
  ).length;
  const adminSession = isAdminSession();
  const navItems = getStaffNavigation();

  return (
    <aside className="sidebar">
      <a
        className="brand"
        href="?page=pos"
        data-page="pos"
        aria-label="M&E Kape Cup POS Register"
      >
        <img
          className="brand-mark"
          src="../assets/img/me-kape-cup-logo.png"
          alt=""
        />
        <span className="brand-copy">
          <strong>M&amp;E Kape Cup</strong>
          <small>Barista Cockpit</small>
        </span>
      </a>

      <div className="station">
        <span>Station</span>
        <strong>Terminal #01</strong>
      </div>

      <nav className="side-nav" aria-label="Main navigation">
        {navItems.map((item) => {
          const count = item.badge === "orders"
            ? activeCount
            : item.badge === "stock"
              ? lowStockCount
              : 0;
          return (
            <a
              key={item.key}
              className={`nav-link${page === item.key ? " active" : ""}`}
              href={item.url}
              data-page={item.key}
              aria-current={page === item.key ? "page" : undefined}
              aria-label={
                count > 0
                  ? `${item.label}, ${count} ${item.badge === "stock" ? "low-stock items" : "active orders"}`
                  : item.label
              }
              onClick={close}
            >
              <Icon>{item.icon}</Icon>
              <span>{item.label}</span>
              {count > 0 && <small className="nav-badge">{count}</small>}
            </a>
          );
        })}
      </nav>

      <div className="sidebar-bottom">
        <div className="app-nav-divider" role="separator" />
        <StaffShiftControl />
        <div className="offline-note">
          <span className="status-dot" />
          <span>
            <strong>{adminSession ? "Administrator access" : "Offline demo"}</strong>
            <small>{adminSession ? "Authorized management account" : "Saved on this browser"}</small>
          </span>
        </div>
        <div className="app-user-row">
          <span className="app-avatar" aria-hidden="true">
            {getStaffName().slice(0, 1).toUpperCase()}
          </span>
          <span className="app-user-name">{getStaffName()}</span>
          <StaffLogoutNavItem />
        </div>
        {adminSession && (
          <a
            className="sidebar-back"
            href="../admin/dashboard.php?tab=overview"
          >
            <Icon>admin_panel_settings</Icon>
            <span>Admin console</span>
          </a>
        )}
      </div>
    </aside>
  );
}

export function AppPageLayout({
  page,
  children,
}: {
  page: Exclude<AppPage, "customer">;
  children: ReactNode;
}) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  useEffect(() => {
    document.body.classList.toggle("sidebar-open", sidebarOpen);
    return () => document.body.classList.remove("sidebar-open");
  }, [sidebarOpen]);

  useEffect(() => {
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setSidebarOpen(false);
    };
    document.addEventListener("keydown", closeOnEscape);
    return () => document.removeEventListener("keydown", closeOnEscape);
  }, []);

  return (
    <div className="app-shell">
      <NavSidebar page={page} close={() => setSidebarOpen(false)} />
      <button
        className="app-sidebar-backdrop"
        type="button"
        aria-label="Close navigation"
        hidden={!sidebarOpen}
        onClick={() => setSidebarOpen(false)}
      />
      <main className="main-area">
        <header className="topbar">
          <div className="app-topbar-identity">
            <button
              className="app-menu-toggle"
              type="button"
              aria-label="Open navigation"
              aria-expanded={sidebarOpen}
              onClick={() => setSidebarOpen(!sidebarOpen)}
            >
              <Icon>menu</Icon>
            </button>
            <span className="eyebrow">Main Counter Branch</span>
          </div>
          <div className="topbar-right">
            <span className="today-label">
              {new Intl.DateTimeFormat("en-PH", {
                weekday: "short",
                month: "short",
                day: "numeric",
              }).format(new Date())}
            </span>
            <span className="app-topbar-name">
              {getStaffName()}
            </span>
            <span className="avatar" aria-label={getStaffName()}>
              {getStaffName().slice(0, 1).toUpperCase()}
            </span>
          </div>
        </header>
        <div className="page-body">{children}</div>
      </main>
    </div>
  );
}

function useToast() {
  const [toast, setToast] = useState("");
  useEffect(() => {
    if (!toast) return;
    const timer = window.setTimeout(() => setToast(""), 3000);
    return () => window.clearTimeout(timer);
  }, [toast]);
  const feedback = toast ? (
    <div className="toast" role="status" aria-live="polite">
      <Icon>check_circle</Icon>
      <span>{toast}</span>
    </div>
  ) : null;
  return { notify: setToast, feedback };
}

function EmptyState({
  icon,
  title,
  description,
  action,
}: {
  icon: string;
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <div className="empty-state page-empty">
      <Icon className="empty-state-icon">{icon}</Icon>
      <h3>{title}</h3>
      <p>{description}</p>
      {action}
    </div>
  );
}

function OrderItems({ order, items }: { order: Order; items: Item[] }) {
  return (
    <ul className="order-items">
      {order.items.map((line, index) => {
        const item = items.find((entry) => entry.id === line.itemId);
        return (
          <li key={`${line.itemId}-${index}`}>
            <span>
              <strong>{line.qty}×</strong> {item?.name ?? "Removed item"}
              {line.note && <small>{line.note}</small>}
            </span>
            {item && <span>{money(item.price * line.qty)}</span>}
          </li>
        );
      })}
    </ul>
  );
}

function elapsedMinutes(createdAt: string, now: number): number {
  const timestamp = Date.parse(createdAt);
  return Number.isNaN(timestamp)
    ? 0
    : Math.max(0, Math.floor((now - timestamp) / 60_000));
}

const QUEUE_COLUMNS: Array<{
  status: Extract<OrderStatus, "new" | "preparing" | "ready">;
  label: string;
  color: string;
}> = [
  { status: "new", label: "New", color: "new" },
  { status: "preparing", label: "Preparing", color: "preparing" },
  { status: "ready", label: "Ready", color: "ready" },
];

export function KitchenQueue() {
  const orders = usePosStore((state) => state.orders);
  const items = usePosStore((state) => state.items);
  const updateOrderStatus = usePosStore((state) => state.updateOrderStatus);
  const voidOrder = usePosStore((state) => state.voidOrder);
  const { notify, feedback } = useToast();
  const [now, setNow] = useState(Date.now());
  const seenLatestOrder = useRef<string | null>(null);
  const activeOrders = useMemo(
    () =>
      orders
        .filter((order) =>
          ["new", "preparing", "ready"].includes(order.status),
        )
        .sort(
          (left, right) =>
            Date.parse(left.createdAt) - Date.parse(right.createdAt),
        ),
    [orders],
  );

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 30_000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    const latest = orders[0];
    if (!latest || latest.status !== "new") return;
    if (seenLatestOrder.current === null) {
      seenLatestOrder.current = latest.id;
      return;
    }
    if (seenLatestOrder.current === latest.id) return;
    seenLatestOrder.current = latest.id;
    notify(`New order #${latest.number} received.`);
    try {
      const AudioContextClass =
        window.AudioContext ??
        (
          window as typeof window & {
            webkitAudioContext?: typeof AudioContext;
          }
        ).webkitAudioContext;
      if (AudioContextClass) {
        const context = new AudioContextClass();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = "sine";
        oscillator.frequency.value = 660;
        gain.gain.value = 0.07;
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.16);
        oscillator.onended = () => void context.close();
      }
    } catch (error) {
      console.error("Unable to play the new order alert.", error);
    }
  }, [orders, notify]);

  const advanceOrder = (order: Order) => {
    const next: Record<
      Extract<OrderStatus, "new" | "preparing" | "ready">,
      OrderStatus
    > = {
      new: "preparing",
      preparing: "ready",
      ready: "served",
    };
    updateOrderStatus(order.id, next[order.status as keyof typeof next]);
    notify(
      order.status === "ready"
        ? `Order #${order.number} marked served.`
        : `Order #${order.number} moved to ${next[order.status as keyof typeof next]}.`,
    );
  };

  const cancelOrder = (order: Order) => {
    if (
      !window.confirm(
        `Cancel order #${order.number}? Items will be returned to stock.`,
      )
    ) {
      return;
    }
    const error = voidOrder(order.id);
    notify(error ?? `Order #${order.number} cancelled and restocked.`);
  };

  return (
    <>
      <div className="page-intro">
        <div>
          <p className="eyebrow">Orders in progress</p>
          <h2>Kitchen Queue</h2>
          <p>Move each order forward as it is prepared and served.</p>
        </div>
        <div className="page-stat">
          <strong>{activeOrders.length}</strong>
          <span>active orders</span>
        </div>
      </div>

      {activeOrders.length === 0 ? (
        <EmptyState
          icon="skillet"
          title="The queue is clear"
          description="New POS orders will appear here automatically when they are placed."
        />
      ) : (
        <div className="queue-grid">
          {QUEUE_COLUMNS.map((column) => {
            const columnOrders = activeOrders.filter(
              (order) => order.status === column.status,
            );
            return (
              <section
                className={`queue-column column-${column.color}`}
                key={column.status}
                aria-labelledby={`queue-${column.status}`}
              >
                <header className="queue-column-header">
                  <div className="queue-column-title">
                    <span className={`queue-dot ${column.color}`} />
                    <h3 id={`queue-${column.status}`}>{column.label}</h3>
                  </div>
                  <span className="count-pill">{columnOrders.length}</span>
                </header>
                {columnOrders.length === 0 ? (
                  <p className="column-empty">No orders here yet.</p>
                ) : (
                  <div className="queue-cards">
                    {columnOrders.map((order) => {
                      const elapsed = elapsedMinutes(order.createdAt, now);
                      const urgency =
                        elapsed >= 10
                          ? "late"
                          : elapsed >= 5
                            ? "waiting"
                            : "fresh";
                      const actionLabel =
                        order.status === "new"
                          ? "Start preparing"
                          : order.status === "preparing"
                            ? "Mark ready"
                            : "Mark served";
                      return (
                        <article className="queue-card" key={order.id}>
                          <div className="queue-card-heading">
                            <span>Order #{order.number}</span>
                            <time className={`elapsed-pill ${urgency}`}>
                              <Icon>schedule</Icon>
                              {elapsed} min
                            </time>
                          </div>
                          <OrderItems order={order} items={items} />
                          <footer className="queue-card-footer">
                            <span>{money(order.total)}</span>
                            <button
                              className="button-primary button-small"
                              type="button"
                              onClick={() => advanceOrder(order)}
                            >
                              {actionLabel}
                            </button>
                          </footer>
                          <button
                            className="text-button danger-text queue-cancel"
                            type="button"
                            onClick={() => cancelOrder(order)}
                          >
                            Cancel order
                          </button>
                        </article>
                      );
                    })}
                  </div>
                )}
              </section>
            );
          })}
        </div>
      )}
      {feedback}
    </>
  );
}

type DateFilter = "today" | "7" | "30" | "custom";
type OrderSort = "newest" | "oldest" | "highest" | "lowest";

function getDateRange(filter: DateFilter, startValue: string, endValue: string) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  if (filter === "custom") {
    const start = startValue ? new Date(`${startValue}T00:00:00`) : today;
    const end = endValue ? new Date(`${endValue}T23:59:59.999`) : new Date();
    return { start, end };
  }
  const start = new Date(today);
  if (filter === "7") start.setDate(today.getDate() - 6);
  if (filter === "30") start.setDate(today.getDate() - 29);
  return { start, end: new Date() };
}

const csvEscape = (value: string | number): string =>
  `"${String(value).replace(/"/g, '""')}"`;

function downloadCsv(filename: string, rows: Array<Array<string | number>>) {
  const content = rows.map((row) => row.map(csvEscape).join(",")).join("\r\n");
  const url = URL.createObjectURL(
    new Blob([content], { type: "text/csv;charset=utf-8" }),
  );
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function MetricCard({
  label,
  value,
  footnote,
  icon,
}: {
  label: string;
  value: string;
  footnote: string;
  icon: string;
}) {
  return (
    <article className="metric-card">
      <div className="metric-icon">
        <Icon>{icon}</Icon>
      </div>
      <p>{label}</p>
      <strong>{value}</strong>
      <small>{footnote}</small>
    </article>
  );
}

export function SalesReports() {
  const orders = usePosStore((state) => state.orders);
  const items = usePosStore((state) => state.items);
  const voidOrder = usePosStore((state) => state.voidOrder);
  const { notify, feedback } = useToast();
  const [filter, setFilter] = useState<DateFilter>("7");
  const [customStart, setCustomStart] = useState("");
  const [customEnd, setCustomEnd] = useState("");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<OrderSort>("newest");

  const completedOrders = useMemo(
    () => orders.filter((order) => order.status === "served"),
    [orders],
  );
  const { start, end } = getDateRange(filter, customStart, customEnd);
  const filteredOrders = useMemo(
    () =>
      completedOrders
        .filter((order) => {
          const date = Date.parse(order.createdAt);
          return date >= start.getTime() && date <= end.getTime();
        })
        .filter((order) => {
          const query = search.trim().toLocaleLowerCase();
          return (
            !query ||
            order.number.toLocaleLowerCase().includes(query) ||
            order.payment.toLocaleLowerCase().includes(query) ||
            order.items.some((line) =>
              (items.find((item) => item.id === line.itemId)?.name ?? "")
                .toLocaleLowerCase()
                .includes(query),
            )
          );
        })
        .sort((left, right) => {
          if (sort === "oldest") {
            return Date.parse(left.createdAt) - Date.parse(right.createdAt);
          }
          if (sort === "highest") return right.total - left.total;
          if (sort === "lowest") return left.total - right.total;
          return Date.parse(right.createdAt) - Date.parse(left.createdAt);
        }),
    [completedOrders, end, items, search, sort, start],
  );
  const revenue = filteredOrders.reduce((sum, order) => sum + order.total, 0);
  const itemsSold = filteredOrders.reduce(
    (sum, order) =>
      sum + order.items.reduce((count, line) => count + line.qty, 0),
    0,
  );
  const averageOrder = filteredOrders.length ? revenue / filteredOrders.length : 0;

  const dailySales = useMemo(() => {
    const dayCount = filter === "today" ? 1 : filter === "30" ? 30 : 7;
    const days = Array.from({ length: dayCount }, (_, index) => {
      const date = new Date(end);
      date.setHours(0, 0, 0, 0);
      date.setDate(date.getDate() - (dayCount - index - 1));
      return date;
    });
    return days.map((date) => {
      const nextDay = new Date(date);
      nextDay.setDate(nextDay.getDate() + 1);
      const value = filteredOrders
        .filter((order) => {
          const timestamp = Date.parse(order.createdAt);
          return timestamp >= date.getTime() && timestamp < nextDay.getTime();
        })
        .reduce((sum, order) => sum + order.total, 0);
      return {
        date,
        label: new Intl.DateTimeFormat("en-PH", {
          month: "short",
          day: "numeric",
        }).format(date),
        value,
      };
    });
  }, [end, filter, filteredOrders]);
  const maxDailySales = Math.max(...dailySales.map((day) => day.value), 1);

  const itemSales = useMemo(() => {
    const sales = new Map<string, number>();
    filteredOrders.forEach((order) =>
      order.items.forEach((line) =>
        sales.set(line.itemId, (sales.get(line.itemId) ?? 0) + line.qty),
      ),
    );
    return Array.from(sales, ([itemId, quantity]) => ({
      item: items.find((entry) => entry.id === itemId),
      quantity,
    }))
      .filter((entry) => entry.item)
      .sort((left, right) => right.quantity - left.quantity)
      .slice(0, 5);
  }, [filteredOrders, items]);
  const maxItemSales = Math.max(
    ...itemSales.map((entry) => entry.quantity),
    1,
  );
  const payments = (["cash", "card", "e-wallet"] as PaymentType[]).map(
    (payment) => ({
      payment,
      count: filteredOrders.filter((order) => order.payment === payment).length,
      sales: filteredOrders
        .filter((order) => order.payment === payment)
        .reduce((sum, order) => sum + order.total, 0),
    }),
  );

  const exportReport = () => {
    downloadCsv(
      "mekape-sales-report.csv",
      [
        ["Order number", "Date", "Status", "Payment", "Total"],
        ...filteredOrders.map((order) => [
          order.number,
          new Date(order.createdAt).toLocaleString("en-PH"),
          order.status,
          order.payment,
          order.total.toFixed(2),
        ]),
      ],
    );
    notify("Sales report exported to CSV.");
  };

  const refundOrder = (order: Order) => {
    if (
      !window.confirm(
        `Void order #${order.number} for ${money(order.total)}? Items will be returned to stock.`,
      )
    ) {
      return;
    }
    const error = voidOrder(order.id);
    notify(error ?? `Order #${order.number} voided and inventory restored.`);
  };

  return (
    <>
      <div className="page-intro">
        <div>
          <p className="eyebrow">Completed orders only</p>
          <h2>Sales &amp; Reports</h2>
          <p>Review completed sales, order trends, and payment mix.</p>
        </div>
        <button
          className="button-secondary"
          type="button"
          onClick={exportReport}
        >
          <Icon>download</Icon> Export CSV
        </button>
      </div>

      <section className="filter-bar" aria-label="Report date range">
        <div className="filter-tabs">
          {(
            [
              ["today", "Today"],
              ["7", "7 days"],
              ["30", "30 days"],
              ["custom", "Custom"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              className={`filter-tab${filter === value ? " selected" : ""}`}
              type="button"
              aria-pressed={filter === value}
              onClick={() => setFilter(value)}
            >
              {label}
            </button>
          ))}
        </div>
        {filter === "custom" && (
          <div className="custom-date-range">
            <label>
              From
              <input
                type="date"
                value={customStart}
                max={customEnd || undefined}
                onChange={(event) => setCustomStart(event.target.value)}
              />
            </label>
            <label>
              To
              <input
                type="date"
                value={customEnd}
                min={customStart || undefined}
                onChange={(event) => setCustomEnd(event.target.value)}
              />
            </label>
          </div>
        )}
      </section>

      <div className="metric-grid">
        <MetricCard
          label="Revenue"
          value={money(revenue)}
          footnote="Completed sales"
          icon="payments"
        />
        <MetricCard
          label="Orders"
          value={String(filteredOrders.length)}
          footnote="Completed orders"
          icon="receipt_long"
        />
        <MetricCard
          label="Average order"
          value={money(averageOrder)}
          footnote="Revenue per order"
          icon="trending_up"
        />
        <MetricCard
          label="Items sold"
          value={String(itemsSold)}
          footnote="Units in completed orders"
          icon="local_cafe"
        />
      </div>

      <div className="report-chart-grid">
        <section className="report-panel">
          <header className="panel-heading">
            <div>
              <h3>Sales over time</h3>
              <p>Completed revenue by day</p>
            </div>
            <Icon>show_chart</Icon>
          </header>
          {revenue === 0 ? (
            <p className="chart-empty">Completed sales will appear here.</p>
          ) : (
            <div className="bar-chart" aria-label="Sales over time">
              {dailySales.map((day) => (
                <div
                  className="bar-column"
                  key={day.date.toISOString().slice(0, 10)}
                  title={`${day.label}: ${money(day.value)}`}
                >
                  <span className="bar-value">
                    {day.value > 0 ? money(day.value) : ""}
                  </span>
                  <div className="bar-track">
                    <span
                      className="bar-fill"
                      style={{
                        height: `${Math.max(5, (day.value / maxDailySales) * 100)}%`,
                      }}
                    />
                  </div>
                  <span className="bar-label">{day.label}</span>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="report-panel">
          <header className="panel-heading">
            <div>
              <h3>Best-selling items</h3>
              <p>Units sold in the selected range</p>
            </div>
            <Icon>local_cafe</Icon>
          </header>
          {itemSales.length === 0 ? (
            <p className="chart-empty">Item sales will appear here.</p>
          ) : (
            <div className="ranking-list">
              {itemSales.map((entry) => (
                <div className="ranking-row" key={entry.item!.id}>
                  <div className="ranking-label">
                    <span>{entry.item!.name}</span>
                    <strong>{entry.quantity}</strong>
                  </div>
                  <div
                    className="ranking-track"
                    aria-label={`${entry.quantity} units sold`}
                  >
                    <span
                      style={{
                        width: `${(entry.quantity / maxItemSales) * 100}%`,
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="report-panel payment-panel">
          <header className="panel-heading">
            <div>
              <h3>Payment types</h3>
              <p>Completed order distribution</p>
            </div>
            <Icon>account_balance_wallet</Icon>
          </header>
          <div className="payment-breakdown">
            {payments.map((payment) => (
              <div className="payment-breakdown-row" key={payment.payment}>
                <span className={`payment-dot ${payment.payment}`} />
                <span className="payment-name">{payment.payment}</span>
                <strong>{payment.count}</strong>
                <span>{money(payment.sales)}</span>
              </div>
            ))}
          </div>
        </section>
      </div>

      <section className="report-panel orders-report">
        <header className="panel-heading orders-heading">
          <div>
            <h3>Completed orders</h3>
            <p>Void a mistaken order to remove it from sales totals.</p>
          </div>
          <label className="table-search">
            <Icon>search</Icon>
            <span className="sr-only">Search orders</span>
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search order or item"
            />
          </label>
        </header>
        <div className="table-toolbar">
          <span>{filteredOrders.length} orders</span>
          <label>
            Sort
            <select
              value={sort}
              onChange={(event) => setSort(event.target.value as OrderSort)}
            >
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="highest">Highest total</option>
              <option value="lowest">Lowest total</option>
            </select>
          </label>
        </div>
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Date &amp; time</th>
                <th>Items</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {filteredOrders.map((order) => (
                <tr key={order.id}>
                  <td className="table-order-number">#{order.number}</td>
                  <td>{new Date(order.createdAt).toLocaleString("en-PH")}</td>
                  <td>
                    {order.items.reduce((sum, line) => sum + line.qty, 0)}
                  </td>
                  <td className="capitalize">{order.payment}</td>
                  <td className="table-money">{money(order.total)}</td>
                  <td>
                    <button
                      className="text-button danger-text"
                      type="button"
                      onClick={() => refundOrder(order)}
                    >
                      Void / refund
                    </button>
                  </td>
                </tr>
              ))}
              {filteredOrders.length === 0 && (
                <tr>
                  <td className="table-empty" colSpan={6}>
                    No completed orders match this date range and search.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      {feedback}
    </>
  );
}

const ITEM_CSV_HEADERS = [
  "name",
  "category",
  "price",
  "stock",
  "lowStockAt",
  "image",
];

function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let field = "";
  let quoted = false;

  for (let index = 0; index < text.length; index += 1) {
    const character = text[index];
    if (quoted) {
      if (character === '"' && text[index + 1] === '"') {
        field += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        field += character;
      }
    } else if (character === '"' && field.length === 0) {
      quoted = true;
    } else if (character === ",") {
      row.push(field);
      field = "";
    } else if (character === "\n") {
      row.push(field.replace(/\r$/, ""));
      if (row.some((value) => value.trim() !== "")) rows.push(row);
      row = [];
      field = "";
    } else {
      field += character;
    }
  }

  if (quoted) throw new Error("CSV contains a quoted field that was not closed.");
  row.push(field.replace(/\r$/, ""));
  if (row.some((value) => value.trim() !== "")) rows.push(row);
  return rows;
}

const makeItemId = (name: string): string =>
  name
    .trim()
    .toLocaleLowerCase()
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "") || `item-${crypto.randomUUID()}`;

interface ItemDraft {
  id: string;
  name: string;
  category: string;
  price: string;
  stock: string;
  lowStockAt: string;
  image: string;
}

const emptyItemDraft = (): ItemDraft => ({
  id: "",
  name: "",
  category: "",
  price: "",
  stock: "0",
  lowStockAt: "5",
  image: "",
});

function ItemEditor({
  draft,
  setDraft,
  error,
  onSubmit,
  onClose,
}: {
  draft: ItemDraft;
  setDraft: (draft: ItemDraft) => void;
  error: string;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onClose: () => void;
}) {
  const update = (key: keyof ItemDraft, value: string) =>
    setDraft({ ...draft, [key]: value });
  return (
    <div className="modal-backdrop">
      <section
        className="checkout-modal editor-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="item-editor-title"
      >
        <header className="modal-heading">
          <div>
            <p className="eyebrow">Menu inventory</p>
            <h2 id="item-editor-title">
              {draft.id ? "Edit item" : "Add an item"}
            </h2>
          </div>
          <button
            className="icon-button"
            type="button"
            aria-label="Close item editor"
            onClick={onClose}
          >
            <Icon>close</Icon>
          </button>
        </header>
        <form className="item-form" onSubmit={onSubmit}>
          <label>
            Item name
            <input
              required
              maxLength={80}
              value={draft.name}
              onChange={(event) => update("name", event.target.value)}
            />
          </label>
          <label>
            Category
            <input
              required
              maxLength={50}
              list="item-categories"
              value={draft.category}
              onChange={(event) => update("category", event.target.value)}
            />
            <datalist id="item-categories">
              {["Coffee", "Non-coffee", "Pastries"].map((category) => (
                <option key={category} value={category} />
              ))}
            </datalist>
          </label>
          <div className="form-two-col">
            <label>
              Price (₱)
              <input
                required
                type="number"
                min="0.01"
                step="0.01"
                value={draft.price}
                onChange={(event) => update("price", event.target.value)}
              />
            </label>
            <label>
              Stock
              <input
                required
                type="number"
                min="0"
                step="1"
                value={draft.stock}
                onChange={(event) => update("stock", event.target.value)}
              />
            </label>
          </div>
          <div className="form-two-col">
            <label>
              Low-stock alert at
              <input
                required
                type="number"
                min="0"
                step="1"
                value={draft.lowStockAt}
                onChange={(event) => update("lowStockAt", event.target.value)}
              />
            </label>
            <label>
              Image URL (optional)
              <input
                type="url"
                value={draft.image}
                onChange={(event) => update("image", event.target.value)}
                placeholder="https://"
              />
            </label>
          </div>
          {error && (
            <p className="form-error" role="alert">
              <Icon>error</Icon>
              {error}
            </p>
          )}
          <div className="modal-actions">
            <button
              className="secondary-button"
              type="button"
              onClick={onClose}
            >
              Cancel
            </button>
            <button className="button-primary" type="submit">
              Save item
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}

function RestockEditor({
  item,
  amount,
  setAmount,
  reason,
  setReason,
  error,
  onSubmit,
  onClose,
}: {
  item: Item;
  amount: string;
  setAmount: (value: string) => void;
  reason: string;
  setReason: (value: string) => void;
  error: string;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onClose: () => void;
}) {
  return (
    <div className="modal-backdrop">
      <section
        className="checkout-modal editor-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="restock-title"
      >
        <header className="modal-heading">
          <div>
            <p className="eyebrow">{item.name}</p>
            <h2 id="restock-title">Restock item</h2>
          </div>
          <button
            className="icon-button"
            type="button"
            aria-label="Close restock dialog"
            onClick={onClose}
          >
            <Icon>close</Icon>
          </button>
        </header>
        <form className="item-form" onSubmit={onSubmit}>
          <label>
            Quantity to add
            <input
              required
              type="number"
              min="1"
              step="1"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              autoFocus
            />
          </label>
          <label>
            Restock reason
            <input
              required
              maxLength={140}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              placeholder="e.g. Supplier delivery"
            />
          </label>
          {error && (
            <p className="form-error" role="alert">
              <Icon>error</Icon>
              {error}
            </p>
          )}
          <div className="modal-actions">
            <button
              className="secondary-button"
              type="button"
              onClick={onClose}
            >
              Cancel
            </button>
            <button className="button-primary" type="submit">
              Record restock
            </button>
          </div>
        </form>
      </section>
    </div>
  );
}

export function StockItems() {
  const items = usePosStore((state) => state.items);
  const stockLogs = usePosStore((state) => state.stockLogs);
  const saveItem = usePosStore((state) => state.saveItem);
  const deleteItem = usePosStore((state) => state.deleteItem);
  const adjustStock = usePosStore((state) => state.adjustStock);
  const importItems = usePosStore((state) => state.importItems);
  const { notify, feedback } = useToast();
  const fileInput = useRef<HTMLInputElement>(null);
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("All");
  const [lowOnly, setLowOnly] = useState(false);
  const [draft, setDraft] = useState<ItemDraft | null>(null);
  const [editorError, setEditorError] = useState("");
  const [restockItem, setRestockItem] = useState<Item | null>(null);
  const [restockAmount, setRestockAmount] = useState("1");
  const [restockReason, setRestockReason] = useState("");
  const [restockError, setRestockError] = useState("");

  const categories = useMemo(
    () => ["All", ...new Set(items.map((item) => item.category))],
    [items],
  );
  const visibleItems = useMemo(
    () =>
      items.filter((item) => {
        const matchesSearch = `${item.name} ${item.category}`
          .toLocaleLowerCase()
          .includes(search.trim().toLocaleLowerCase());
        const matchesCategory =
          category === "All" || item.category === category;
        return (
          matchesSearch &&
          matchesCategory &&
          (!lowOnly || item.stock <= item.lowStockAt)
        );
      }),
    [category, items, lowOnly, search],
  );
  const lowStockItems = items.filter(
    (item) => item.stock <= item.lowStockAt,
  ).length;

  const beginEdit = (item?: Item) => {
    setEditorError("");
    setDraft(
      item
        ? {
            id: item.id,
            name: item.name,
            category: item.category,
            price: String(item.price),
            stock: String(item.stock),
            lowStockAt: String(item.lowStockAt),
            image: item.image ?? "",
          }
        : emptyItemDraft(),
    );
  };

  const submitItem = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!draft) return;
    const item: Item = {
      id: draft.id || makeItemId(draft.name),
      name: draft.name.trim(),
      category: draft.category.trim(),
      price: Number(draft.price),
      stock: Number(draft.stock),
      lowStockAt: Number(draft.lowStockAt),
      ...(draft.image.trim() ? { image: draft.image.trim() } : {}),
    };
    const error = saveItem(item);
    if (error) {
      setEditorError(error);
      return;
    }
    setDraft(null);
    notify(`${item.name} saved.`);
  };

  const removeItem = (item: Item) => {
    if (
      !window.confirm(
        `Delete ${item.name}? This also removes it from the current cart.`,
      )
    ) {
      return;
    }
    deleteItem(item.id);
    notify(`${item.name} deleted.`);
  };

  const quickAdjust = (item: Item, amount: number) => {
    if (
      amount < 0 &&
      !window.confirm(`Reduce ${item.name} stock by ${Math.abs(amount)}?`)
    ) {
      return;
    }
    const error = adjustStock(
      item.id,
      amount,
      amount > 0 ? "Manual quick stock adjustment" : "Manual stock reduction",
    );
    notify(error ?? `${item.name} stock updated.`);
  };

  const submitRestock = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!restockItem) return;
    const amount = Number(restockAmount);
    if (!Number.isInteger(amount) || amount < 1) {
      setRestockError("Quantity must be a whole number greater than zero.");
      return;
    }
    const error = adjustStock(restockItem.id, amount, restockReason);
    if (error) {
      setRestockError(error);
      return;
    }
    notify(`${restockItem.name} restocked by ${amount}.`);
    setRestockItem(null);
    setRestockAmount("1");
    setRestockReason("");
  };

  const exportItems = () => {
    downloadCsv("mekape-stock-items.csv", [
      ITEM_CSV_HEADERS,
      ...items.map((item) => [
        item.name,
        item.category,
        item.price.toFixed(2),
        item.stock,
        item.lowStockAt,
        item.image ?? "",
      ]),
    ]);
    notify("Stock list exported to CSV.");
  };

  const importFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    try {
      const rows = parseCsv(await file.text());
      const header = rows[0]?.map((value) => value.trim());
      if (!header || ITEM_CSV_HEADERS.some((name) => !header.includes(name))) {
        throw new Error(
          `CSV must include these columns: ${ITEM_CSV_HEADERS.join(", ")}.`,
        );
      }
      const indexes = new Map(header.map((name, index) => [name, index]));
      const imported: Item[] = rows.slice(1).map((row) => {
        const read = (key: string) => row[indexes.get(key) ?? -1] ?? "";
        const name = read("name").trim();
        return {
          id: makeItemId(name),
          name,
          category: read("category").trim(),
          price: Number(read("price")),
          stock: Number(read("stock")),
          lowStockAt: Number(read("lowStockAt")),
          ...(read("image").trim() ? { image: read("image").trim() } : {}),
        };
      });
      if (imported.length === 0) {
        throw new Error("The selected CSV does not contain any item rows.");
      }
      const error = importItems(imported);
      if (error) throw new Error(error);
      notify(`${imported.length} item${imported.length === 1 ? "" : "s"} imported.`);
    } catch (error) {
      notify(
        error instanceof Error
          ? `Import failed: ${error.message}`
          : "Import failed because the file could not be read.",
      );
    }
  };

  const importTemplate = () => {
    downloadCsv("mekape-stock-template.csv", [ITEM_CSV_HEADERS]);
    notify("CSV template downloaded.");
  };

  return (
    <>
      <div className="page-intro">
        <div>
          <p className="eyebrow">Menu and inventory</p>
          <h2>Stock &amp; Items</h2>
          <p>Update menu details, record restocks, and monitor low inventory.</p>
        </div>
        <div className="stock-actions">
          <input
            ref={fileInput}
            className="sr-only"
            type="file"
            accept=".csv,text/csv"
            aria-label="Import stock CSV"
            onChange={importFile}
          />
          <button
            className="button-secondary"
            type="button"
            onClick={importTemplate}
          >
            <Icon>description</Icon> CSV template
          </button>
          <button
            className="button-secondary"
            type="button"
            onClick={() => fileInput.current?.click()}
          >
            <Icon>upload</Icon> Import CSV
          </button>
          <button
            className="button-primary"
            type="button"
            onClick={() => beginEdit()}
          >
            <Icon>add</Icon> Add item
          </button>
        </div>
      </div>

      <div className="stock-summary-grid">
        <div className="stock-summary">
          <span>Total items</span>
          <strong>{items.length}</strong>
        </div>
        <div className={`stock-summary${lowStockItems ? " low-summary" : ""}`}>
          <span>Low stock</span>
          <strong>{lowStockItems}</strong>
        </div>
        <div className="stock-summary">
          <span>Out of stock</span>
          <strong>{items.filter((item) => item.stock === 0).length}</strong>
        </div>
      </div>

      <section className="report-panel stock-table-panel">
        <div className="stock-filters">
          <label className="table-search">
            <Icon>search</Icon>
            <span className="sr-only">Search items</span>
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search items"
            />
          </label>
          <label className="select-filter">
            Category
            <select
              value={category}
              onChange={(event) => setCategory(event.target.value)}
            >
              {categories.map((entry) => (
                <option key={entry}>{entry}</option>
              ))}
            </select>
          </label>
          <label className="low-stock-toggle">
            <input
              type="checkbox"
              checked={lowOnly}
              onChange={(event) => setLowOnly(event.target.checked)}
            />
            Low stock only
          </label>
          <button
            className="button-secondary export-stock"
            type="button"
            onClick={exportItems}
          >
            <Icon>download</Icon> Export CSV
          </button>
        </div>

        <div className="table-scroll">
          <table className="data-table stock-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Low at</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {visibleItems.map((item) => {
                const low = item.stock <= item.lowStockAt;
                return (
                  <tr key={item.id} className={low ? "low-stock-row" : ""}>
                    <td>
                      <div className="stock-item-name">
                        {item.image ? (
                          <img src={item.image} alt="" />
                        ) : (
                          <span className="stock-item-icon">☕</span>
                        )}
                        <strong>{item.name}</strong>
                      </div>
                    </td>
                    <td>{item.category}</td>
                    <td className="table-money">{money(item.price)}</td>
                    <td>
                      <span
                        className={`stock-quantity${low ? " low" : ""}${item.stock === 0 ? " out" : ""}`}
                      >
                        {item.stock}
                        {low && <small>{item.stock === 0 ? "Out" : "Low"}</small>}
                      </span>
                    </td>
                    <td>{item.lowStockAt}</td>
                    <td>
                      <div className="stock-row-actions">
                        <button
                          className="quantity-icon-button"
                          type="button"
                          aria-label={`Reduce ${item.name} stock by one`}
                          disabled={item.stock === 0}
                          onClick={() => quickAdjust(item, -1)}
                        >
                          <Icon>remove</Icon>
                        </button>
                        <button
                          className="quantity-icon-button"
                          type="button"
                          aria-label={`Increase ${item.name} stock by one`}
                          onClick={() => quickAdjust(item, 1)}
                        >
                          <Icon>add</Icon>
                        </button>
                        <button
                          className="table-action"
                          type="button"
                          onClick={() => {
                            setRestockError("");
                            setRestockItem(item);
                          }}
                        >
                          Restock
                        </button>
                        <button
                          className="table-action"
                          type="button"
                          onClick={() => beginEdit(item)}
                        >
                          Edit
                        </button>
                        <button
                          className="table-action danger-text"
                          type="button"
                          onClick={() => removeItem(item)}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
              {visibleItems.length === 0 && (
                <tr>
                  <td className="table-empty" colSpan={6}>
                    {items.length === 0
                      ? "Add a menu item or import your stock CSV to get started."
                      : "No items match these filters. Adjust the search or category."}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="report-panel stock-log-panel">
        <header className="panel-heading">
          <div>
            <h3>Recent stock activity</h3>
            <p>Manual adjustments and order voids</p>
          </div>
          <Icon>history</Icon>
        </header>
        {stockLogs.length === 0 ? (
          <p className="stock-log-empty">
            Stock adjustments and restocks will be recorded here.
          </p>
        ) : (
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Change</th>
                  <th>Reason</th>
                  <th>Date &amp; time</th>
                </tr>
              </thead>
              <tbody>
                {stockLogs.slice(0, 8).map((log) => (
                  <tr key={log.id}>
                    <td>{log.itemName}</td>
                    <td className={log.change > 0 ? "stock-increase" : "stock-decrease"}>
                      {log.change > 0 ? "+" : ""}
                      {log.change}
                    </td>
                    <td>{log.reason}</td>
                    <td>{new Date(log.createdAt).toLocaleString("en-PH")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {draft && (
        <ItemEditor
          draft={draft}
          setDraft={setDraft}
          error={editorError}
          onSubmit={submitItem}
          onClose={() => setDraft(null)}
        />
      )}
      {restockItem && (
        <RestockEditor
          item={restockItem}
          amount={restockAmount}
          setAmount={setRestockAmount}
          reason={restockReason}
          setReason={setRestockReason}
          error={restockError}
          onSubmit={submitRestock}
          onClose={() => setRestockItem(null)}
        />
      )}
      {feedback}
    </>
  );
}

interface CustomerDisplay {
  cart: CartLine[];
  items: Item[];
  subtotal: number;
  discount: number;
  taxRate: number;
  total: number;
}

const getLocalCustomerState = (): CustomerDisplay => {
  const state = usePosStore.getState();
  const subtotal = state.cart.reduce((sum, line) => {
    const item = state.items.find((entry) => entry.id === line.itemId);
    return sum + (item?.price ?? 0) * line.qty;
  }, 0);
  const total = getCartTotal(
    state.cart,
    state.items,
    state.discount,
    state.taxRate,
  );
  return {
    cart: state.cart,
    items: state.items,
    subtotal,
    discount: getDiscountAmount(subtotal, state.discount),
    taxRate: state.taxRate,
    total,
  };
};

export function CustomerView() {
  const [display, setDisplay] = useState<CustomerDisplay>(getLocalCustomerState);
  const [thankYouOrder, setThankYouOrder] = useState<Order | null>(null);
  const [connection, setConnection] = useState(
    typeof BroadcastChannel === "undefined" ? "same-screen" : "connecting",
  );

  useEffect(() => {
    const syncLocalState = () => setDisplay(getLocalCustomerState());
    const unsubscribe = usePosStore.subscribe(syncLocalState);
    let channel: BroadcastChannel | null = null;
    if (typeof BroadcastChannel !== "undefined") {
      channel = new BroadcastChannel("mekape-pos-customer");
      channel.onmessage = (event: MessageEvent) => {
        const message = event.data as
          | { type: "cart:update"; cart: CartLine[]; items: Item[]; subtotal: number; discount: number; taxRate: number; total: number }
          | { type: "order:placed"; order: Order }
          | { type: "cart:request" };
        if (message.type === "cart:update") {
          setDisplay({
            cart: message.cart,
            items: message.items,
            subtotal: message.subtotal,
            discount: message.discount,
            taxRate: message.taxRate,
            total: message.total,
          });
          setConnection("connected");
          if (message.cart.length > 0) setThankYouOrder(null);
        } else if (message.type === "order:placed") {
          setThankYouOrder(message.order);
          setDisplay(getLocalCustomerState());
        }
      };
      channel.postMessage({ type: "cart:request" });
      window.setTimeout(() => {
        setConnection((current) =>
          current === "connecting" ? "waiting" : current,
        );
      }, 1600);
    }
    return () => {
      unsubscribe();
      channel?.close();
    };
  }, []);

  useEffect(() => {
    if (!thankYouOrder) return;
    const timeout = window.setTimeout(() => setThankYouOrder(null), 10_000);
    return () => window.clearTimeout(timeout);
  }, [thankYouOrder]);

  const itemCount = display.cart.reduce((sum, line) => sum + line.qty, 0);
  const sameScreen = connection === "same-screen";

  return (
    <main className="customer-screen">
      <header className="customer-topbar">
        <div className="customer-brand">
          <img
            className="brand-mark"
            src="../assets/img/me-kape-cup-logo.png"
            alt=""
          />
          <span>
            <strong>M&amp;E Kape Cup</strong>
            <small>Order preview</small>
          </span>
        </div>
        <div className="customer-live">
          <span className={`status-dot${connection === "connected" ? "" : " idle"}`} />
          {sameScreen
            ? "Live order preview"
            : connection === "connected"
              ? "Connected to POS"
              : "Waiting for POS"}
        </div>
      </header>
      <div className="customer-content">
        <section className="customer-card" aria-live="polite">
          {thankYouOrder ? (
            <div className="customer-thankyou">
              <span className="thankyou-icon">
                <Icon>check_circle</Icon>
              </span>
              <p className="eyebrow">Order received</p>
              <h1>Thank you!</h1>
              <p>
                We&apos;re preparing order <strong>#{thankYouOrder.number}</strong>.
              </p>
              <div className="thankyou-total">
                <span>Paid</span>
                <strong>{money(thankYouOrder.total)}</strong>
              </div>
              <span className="customer-coffee-mark">Made with care, just for you.</span>
            </div>
          ) : (
            <>
              <div className="customer-card-heading">
                <p className="eyebrow">Made fresh for you</p>
                <h1>Your order</h1>
                <span className="customer-item-count">
                  {itemCount} {itemCount === 1 ? "item" : "items"}
                </span>
              </div>
              {display.cart.length === 0 ? (
                <div className="customer-empty">
                  <span aria-hidden="true">☕</span>
                  <h2>Welcome to M&amp;E Kape Cup</h2>
                  <p>Your order will appear here as it is prepared.</p>
                </div>
              ) : (
                <>
                  <div className="customer-lines">
                    {display.cart.map((line) => {
                      const item = display.items.find(
                        (entry) => entry.id === line.itemId,
                      );
                      if (!item) return null;
                      return (
                        <article className="customer-line" key={line.itemId}>
                          <span className="customer-item-art" aria-hidden="true">
                            {item.category === "Pastries"
                              ? "🥐"
                              : item.category === "Non-coffee"
                                ? "🍵"
                                : "☕"}
                          </span>
                          <span className="customer-line-name">
                            <strong>{item.name}</strong>
                            <small>
                              {line.qty} × {money(item.price)}
                            </small>
                            {line.note && (
                              <em>Note: {line.note}</em>
                            )}
                          </span>
                          <strong className="customer-line-price">
                            {money(item.price * line.qty)}
                          </strong>
                        </article>
                      );
                    })}
                  </div>
                  <div className="customer-totals">
                    <div>
                      <span>Subtotal</span>
                      <span>{money(display.subtotal)}</span>
                    </div>
                    <div>
                      <span>Discount</span>
                      <span>− {money(display.discount)}</span>
                    </div>
                    <div>
                      <span>Tax ({display.taxRate}%)</span>
                      <span>
                        {money(
                          Math.max(
                            0,
                            display.total -
                              display.subtotal +
                              display.discount,
                          ),
                        )}
                      </span>
                    </div>
                    <div className="customer-grand-total">
                      <strong>Total</strong>
                      <strong>{money(display.total)}</strong>
                    </div>
                  </div>
                  <p className="customer-thanks">
                    Thank you for supporting our little coffee shop.
                  </p>
                </>
              )}
            </>
          )}
        </section>
      </div>
      <a
        className="customer-exit no-print"
        href="?page=pos"
        data-page="pos"
        aria-label="Return to POS Register"
      >
        <Icon>point_of_sale</Icon>
      </a>
    </main>
  );
}
