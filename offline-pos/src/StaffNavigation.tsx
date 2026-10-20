import { Icon } from "./ui";

export interface StaffNavItem {
  key: string;
  label: string;
  icon: string;
  url: string;
  badge?: "orders" | "stock";
}

function isStaffNavItem(value: unknown): value is StaffNavItem {
  if (typeof value !== "object" || value === null) return false;
  const item = value as Record<string, unknown>;
  return (
    typeof item.key === "string" &&
    ["pos", "queue", "reports", "stock", "customer"].includes(item.key) &&
    typeof item.label === "string" &&
    typeof item.icon === "string" &&
    typeof item.url === "string" &&
    (item.badge === undefined ||
      item.badge === "orders" ||
      item.badge === "stock")
  );
}

export function getStaffNavigation(): StaffNavItem[] {
  const config = document.getElementById("app-navigation-config");
  if (!config) throw new Error("Staff navigation configuration is missing.");

  const parsed: unknown = JSON.parse(config.textContent ?? "");
  if (!Array.isArray(parsed) || !parsed.every(isStaffNavItem)) {
    throw new Error("Staff navigation configuration is invalid.");
  }
  return parsed;
}

export function StaffLogoutNavItem() {
  const csrfToken =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
    "";

  return (
    <form className="nav-logout-form" action="logout.php" method="post">
      <input type="hidden" name="csrf_token" value={csrfToken} />
      <button
        className="app-logout-button"
        type="submit"
        aria-label="Log out"
      >
        <Icon>logout</Icon>
        <span>Log out</span>
      </button>
    </form>
  );
}

export function getStaffName(): string {
  return (
    document.querySelector<HTMLMetaElement>('meta[name="staff-name"]')?.content ??
    "Staff account"
  );
}

export function StaffShiftControl() {
  const csrfToken =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
    "";
  const shiftOpen =
    document.querySelector<HTMLMetaElement>('meta[name="shift-open"]')?.content ===
    "true";

  return (
    <form
      className="nav-shift-control"
      action={shiftOpen ? "end_shift.php" : "start_shift.php"}
      method="post"
      onSubmit={(event) => {
        if (shiftOpen && !window.confirm("End your current staff shift?")) {
          event.preventDefault();
        }
      }}
    >
      <input type="hidden" name="csrf_token" value={csrfToken} />
      <button
        className="nav-link shift-control-button"
        type="submit"
        aria-label={shiftOpen ? "End shift" : "Start shift"}
      >
        <Icon>{shiftOpen ? "door_open" : "play_arrow"}</Icon>
        <span>{shiftOpen ? "End shift" : "Start shift"}</span>
      </button>
    </form>
  );
}

export function isAdminSession(): boolean {
  return (
    document.querySelector<HTMLMetaElement>('meta[name="staff-role"]')?.content ===
    "admin"
  );
}
