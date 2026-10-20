export interface Item {
  id: string;
  name: string;
  category: string;
  price: number;
  stock: number;
  lowStockAt: number;
  image?: string;
}

export interface CartLine {
  itemId: string;
  qty: number;
  note: string;
}

export type OrderStatus =
  | "new"
  | "preparing"
  | "ready"
  | "served"
  | "cancelled";

export type PaymentType = "cash" | "card" | "e-wallet";

export interface Order {
  id: string;
  number: string;
  items: Array<{
    itemId: string;
    qty: number;
    note: string;
  }>;
  total: number;
  status: OrderStatus;
  payment: PaymentType;
  createdAt: string;
}

export interface StockLogEntry {
  id: string;
  itemId: string;
  itemName: string;
  change: number;
  reason: string;
  createdAt: string;
}

export type DiscountType = "percent" | "fixed";

export interface Discount {
  type: DiscountType;
  value: number;
}
