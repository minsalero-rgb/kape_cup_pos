import type { ReactNode } from "react";

export function Icon({
  children,
  className = "",
}: {
  children: ReactNode;
  className?: string;
}) {
  const paths: Record<string, string> = {
    menu: "M3 6h18 M3 12h18 M3 18h18",
    logout: "M10 17l5-5-5-5 M15 12H3 M21 3v18",
    door_open: "M13 4h7v16h-7 M13 4L4 6v12l9 2 M16 12h.01",
    play_arrow: "M8 5v14l11-7z",
    admin_panel_settings: "M12 3l8 4v5c0 5-3.5 8-8 10-4.5-2-8-5-8-10V7l8-4z M9 12l2 2 4-4",
    point_of_sale: "M3 4h18v16H3z M7 8h3 M7 12h10 M7 16h6",
    skillet: "M4 11h12a4 4 0 0 1 0 8H8a4 4 0 0 1-4-4v-4z M16 13l5-5 M7 7l1-2 M12 7l1-2",
    monitoring: "M3 3v18h18 M7 14l4-4 4 3 6-7",
    inventory_2: "M3 7l9-4 9 4-9 4-9-4z M3 7v10l9 4 9-4V7 M12 11v10",
    storefront: "M3 10h18 M5 10v10h14V10 M4 10l1-6h14l1 6 M9 20v-6h6v6 M4 10a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0",
    open_in_new: "M14 3h7v7 M21 3l-9 9 M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6",
    arrow_back: "M19 12H5 M12 19l-7-7 7-7",
    admin_panel_settings: "M12 3l8 4v5c0 5-3.5 8-8 10-4.5-2-8-5-8-10V7l8-4z M9 12l2 2 4-4",
    info: "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z M12 11v5 M12 8h.01",
    search: "M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.35-4.35",
    close: "M18 6L6 18 M6 6l12 12",
    remove: "M5 12h14",
    add: "M12 5v14 M5 12h14",
    error: "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z M12 8v5 M12 16h.01",
    payments: "M3 6h18v13H3z M3 10h18 M7 15h3",
    credit_card: "M2 5h20v14H2z M2 10h20 M6 15h3",
    account_balance_wallet: "M3 6h17a2 2 0 0 1 2 2v11H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14 M16 12h6v4h-6a2 2 0 0 1 0-4z",
    check: "M5 12l4 4L19 6",
    check_circle: "M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0z M8 12l3 3 5-6",
    arrow_forward: "M5 12h14 M12 5l7 7-7 7",
    receipt_long: "M4 3h16v18l-3-2-3 2-3-2-3 2-4-2z M8 8h8 M8 12h8 M8 16h5",
    print: "M6 9V3h12v6 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v7H6z",
    schedule: "M12 8v4l3 2 M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z",
    download: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4 M7 10l5 5 5-5 M12 15V3",
    upload: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4 M17 8l-5-5-5 5 M12 3v12",
    description: "M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8",
    trending_up: "M3 17l6-6 4 4 8-8 M15 7h6v6",
    local_cafe: "M10 2v2 M14 2v2 M4 7h14v9a4 4 0 0 1-4 4h-6a4 4 0 0 1-4-4V7z M18 9h2a2 2 0 1 1 0 4h-2 M2 22h18",
    show_chart: "M3 3v18h18 M7 14l4-4 4 3 6-7",
    history: "M3 12a9 9 0 1 0 3-6.7L3 8 M3 3v5h5 M12 7v5l4 2",
  };
  const iconName = typeof children === "string" ? children : "";

  return (
    <span className={`material-symbols-outlined ${className}`} aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false">
        <path d={paths[iconName] ?? paths.info} />
      </svg>
    </span>
  );
}
