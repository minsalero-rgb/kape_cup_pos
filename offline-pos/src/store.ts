import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import { seedItems, seedOrders } from "./data";
import type {
  CartLine,
  Discount,
  Item,
  Order,
  OrderStatus,
  PaymentType,
  StockLogEntry,
} from "./types";

interface PosState {
  items: Item[];
  orders: Order[];
  stockLogs: StockLogEntry[];
  cart: CartLine[];
  discount: Discount;
  taxRate: number;
  addItemToCart: (itemId: string) => string | null;
  setQuantity: (itemId: string, quantity: number) => string | null;
  setLineNote: (itemId: string, note: string) => void;
  removeCartLine: (itemId: string) => void;
  clearCart: () => void;
  setDiscount: (discount: Discount) => void;
  setTaxRate: (taxRate: number) => void;
  updateOrderStatus: (orderId: string, status: OrderStatus) => void;
  voidOrder: (orderId: string) => string | null;
  saveItem: (item: Item) => string | null;
  deleteItem: (itemId: string) => void;
  adjustStock: (
    itemId: string,
    amount: number,
    reason: string,
  ) => string | null;
  importItems: (imported: Item[]) => string | null;
  placeOrder: (
    payment: PaymentType,
    cashTendered: number,
  ) => { order: Order | null; error: string | null };
}

const roundMoney = (amount: number): number =>
  Math.round((amount + Number.EPSILON) * 100) / 100;

export const getCartSubtotal = (cart: CartLine[], items: Item[]): number =>
  roundMoney(
    cart.reduce((sum, line) => {
      const item = items.find((entry) => entry.id === line.itemId);
      return sum + (item?.price ?? 0) * line.qty;
    }, 0),
  );

export const getDiscountAmount = (
  subtotal: number,
  discount: Discount,
): number =>
  roundMoney(
    Math.min(
      subtotal,
      discount.type === "percent"
        ? (subtotal * Math.min(100, Math.max(0, discount.value))) / 100
        : Math.max(0, discount.value),
    ),
  );

export const getCartTotal = (
  cart: CartLine[],
  items: Item[],
  discount: Discount,
  taxRate: number,
): number => {
  const subtotal = getCartSubtotal(cart, items);
  const taxableAmount = subtotal - getDiscountAmount(subtotal, discount);
  return roundMoney(taxableAmount * (1 + Math.min(100, Math.max(0, taxRate)) / 100));
};

const createOrderId = (): string =>
  typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : `order-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;

export const usePosStore = create<PosState>()(
  persist(
    (set, get) => ({
      items: seedItems,
      orders: seedOrders,
      stockLogs: [],
      cart: [],
      discount: { type: "percent", value: 0 },
      taxRate: 12,
      addItemToCart: (itemId) => {
        const { items, cart } = get();
        const item = items.find((entry) => entry.id === itemId);
        if (!item) return "That item is no longer available.";
        const currentQuantity =
          cart.find((line) => line.itemId === itemId)?.qty ?? 0;
        if (item.stock <= currentQuantity) {
          return item.stock === 0
            ? `${item.name} is sold out.`
            : `Only ${item.stock} ${item.name} available.`;
        }

        set({
          cart: cart.some((line) => line.itemId === itemId)
            ? cart.map((line) =>
                line.itemId === itemId
                  ? { ...line, qty: line.qty + 1 }
                  : line,
              )
            : [...cart, { itemId, qty: 1, note: "" }],
        });
        return null;
      },
      setQuantity: (itemId, quantity) => {
        const { items, cart } = get();
        const item = items.find((entry) => entry.id === itemId);
        if (!item) return "That item is no longer available.";
        if (quantity > item.stock) {
          return `Only ${item.stock} ${item.name} available.`;
        }
        if (quantity <= 0) {
          set({ cart: cart.filter((line) => line.itemId !== itemId) });
          return null;
        }
        set({
          cart: cart.map((line) =>
            line.itemId === itemId ? { ...line, qty: quantity } : line,
          ),
        });
        return null;
      },
      setLineNote: (itemId, note) =>
        set({
          cart: get().cart.map((line) =>
            line.itemId === itemId ? { ...line, note } : line,
          ),
        }),
      removeCartLine: (itemId) =>
        set({ cart: get().cart.filter((line) => line.itemId !== itemId) }),
      clearCart: () => set({ cart: [] }),
      setDiscount: (discount) =>
        set({
          discount: {
            type: discount.type,
            value: Number.isFinite(discount.value)
              ? Math.min(
                  discount.type === "percent" ? 100 : Number.MAX_SAFE_INTEGER,
                  Math.max(0, discount.value),
                )
              : 0,
          },
        }),
      setTaxRate: (taxRate) =>
        set({
          taxRate: Number.isFinite(taxRate)
            ? Math.min(100, Math.max(0, taxRate))
            : 0,
        }),
      updateOrderStatus: (orderId, status) =>
        set({
          orders: get().orders.map((order) =>
            order.id === orderId ? { ...order, status } : order,
          ),
        }),
      voidOrder: (orderId) => {
        const { orders, items, stockLogs } = get();
        const order = orders.find((entry) => entry.id === orderId);
        if (!order) return "Order could not be found.";
        if (order.status === "cancelled") return "This order is already voided.";

        const itemNames = new Map(items.map((item) => [item.id, item.name]));
        const restockedItems = new Map<string, number>();
        for (const line of order.items) {
          restockedItems.set(
            line.itemId,
            (restockedItems.get(line.itemId) ?? 0) + line.qty,
          );
        }
        const now = new Date().toISOString();
        const logs: StockLogEntry[] = Array.from(restockedItems, ([itemId, amount]) => ({
          id: createOrderId(),
          itemId,
          itemName: itemNames.get(itemId) ?? "Deleted item",
          change: amount,
          reason: `Restocked from voided order #${order.number}`,
          createdAt: now,
        }));

        set({
          orders: orders.map((entry) =>
            entry.id === orderId ? { ...entry, status: "cancelled" } : entry,
          ),
          items: items.map((item) => ({
            ...item,
            stock: item.stock + (restockedItems.get(item.id) ?? 0),
          })),
          stockLogs: [...logs, ...stockLogs],
        });
        return null;
      },
      saveItem: (item) => {
        const { items, cart } = get();
        const duplicate = items.find(
          (entry) =>
            entry.name.trim().toLocaleLowerCase() ===
              item.name.trim().toLocaleLowerCase() &&
            entry.id !== item.id,
        );
        if (duplicate) return "An item with that name already exists.";
        if (!item.name.trim() || !item.category.trim()) {
          return "Enter an item name and category.";
        }
        if (!Number.isFinite(item.price) || item.price <= 0) {
          return "Price must be greater than zero.";
        }
        if (
          !Number.isInteger(item.stock) ||
          item.stock < 0 ||
          !Number.isInteger(item.lowStockAt) ||
          item.lowStockAt < 0
        ) {
          return "Stock and low-stock threshold must be whole numbers of zero or more.";
        }
        if (
          cart.some((line) => line.itemId === item.id && line.qty > item.stock)
        ) {
          return "Stock cannot be lower than the quantity currently in the cart.";
        }

        const existing = items.some((entry) => entry.id === item.id);
        set({
          items: existing
            ? items.map((entry) =>
                entry.id === item.id ? { ...item, name: item.name.trim(), category: item.category.trim() } : entry,
              )
            : [...items, { ...item, name: item.name.trim(), category: item.category.trim() }],
        });
        return null;
      },
      deleteItem: (itemId) => {
        const { items, cart } = get();
        set({
          items: items.filter((item) => item.id !== itemId),
          cart: cart.filter((line) => line.itemId !== itemId),
        });
      },
      adjustStock: (itemId, amount, reason) => {
        const { items, stockLogs } = get();
        const item = items.find((entry) => entry.id === itemId);
        if (!item) return "Item could not be found.";
        if (!Number.isInteger(amount) || amount === 0) {
          return "Stock adjustment must be a non-zero whole number.";
        }
        if (item.stock + amount < 0) {
          return "Stock cannot go below zero.";
        }
        if (!reason.trim()) return "Enter a reason for the stock adjustment.";
        set({
          items: items.map((entry) =>
            entry.id === itemId
              ? { ...entry, stock: entry.stock + amount }
              : entry,
          ),
          stockLogs: [
            {
              id: createOrderId(),
              itemId,
              itemName: item.name,
              change: amount,
              reason: reason.trim(),
              createdAt: new Date().toISOString(),
            },
            ...stockLogs,
          ],
        });
        return null;
      },
      importItems: (imported) => {
        const names = new Set<string>();
        for (const item of imported) {
          const normalizedName = item.name.trim().toLocaleLowerCase();
          if (!normalizedName || names.has(normalizedName)) {
            return "Imported items must have unique, non-empty names.";
          }
          names.add(normalizedName);
          if (
            !item.category.trim() ||
            !Number.isFinite(item.price) ||
            item.price <= 0 ||
            !Number.isInteger(item.stock) ||
            item.stock < 0 ||
            !Number.isInteger(item.lowStockAt) ||
            item.lowStockAt < 0
          ) {
            return `Check price, category, stock, and low-stock threshold for "${item.name}".`;
          }
        }

        const { items } = get();
        const byId = new Map(items.map((item) => [item.id, item]));
        for (const item of imported) {
          byId.set(item.id, { ...item, name: item.name.trim(), category: item.category.trim() });
        }
        set({ items: Array.from(byId.values()) });
        return null;
      },
      placeOrder: (payment, cashTendered) => {
        const { items, orders, cart, discount, taxRate } = get();
        if (cart.length === 0) {
          return { order: null, error: "Add an item before checking out." };
        }

        for (const line of cart) {
          const item = items.find((entry) => entry.id === line.itemId);
          if (!item || item.stock < line.qty) {
            return {
              order: null,
              error: `${item?.name ?? "An item"} no longer has enough stock.`,
            };
          }
        }

        const total = getCartTotal(cart, items, discount, taxRate);
        if (
          payment === "cash" &&
          (!Number.isFinite(cashTendered) || cashTendered < total)
        ) {
          return {
            order: null,
            error: "Cash received must cover the order total.",
          };
        }

        const lastNumber = orders.reduce(
          (maximum, order) => Math.max(maximum, Number(order.number) || 0),
          0,
        );
        const order: Order = {
          id: createOrderId(),
          number: String(lastNumber + 1).padStart(4, "0"),
          items: cart.map(({ itemId, qty, note }) => ({ itemId, qty, note })),
          total,
          status: "new",
          payment,
          createdAt: new Date().toISOString(),
        };
        const quantities = new Map(
          cart.map(({ itemId, qty }) => [itemId, qty]),
        );

        set({
          items: items.map((item) => ({
            ...item,
            stock: item.stock - (quantities.get(item.id) ?? 0),
          })),
          orders: [order, ...orders],
          cart: [],
          discount: { type: discount.type, value: 0 },
        });

        return { order, error: null };
      },
    }),
    {
      name: "mekape-offline-pos-v1",
      version: 1,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        items: state.items,
        orders: state.orders,
        stockLogs: state.stockLogs,
        cart: state.cart,
        discount: state.discount,
        taxRate: state.taxRate,
      }),
    },
  ),
);
