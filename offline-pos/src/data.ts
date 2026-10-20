import type { Item, Order, PaymentType } from "./types";

export const seedItems: Item[] = [
  {
    id: "spanish-latte",
    name: "Barista's Spanish Latte",
    category: "Coffee",
    price: 185,
    stock: 18,
    lowStockAt: 5,
  },
  {
    id: "caramel-macchiato",
    name: "Caramel Macchiato",
    category: "Coffee",
    price: 175,
    stock: 12,
    lowStockAt: 5,
  },
  {
    id: "cafe-americano",
    name: "Cafe Americano",
    category: "Coffee",
    price: 130,
    stock: 9,
    lowStockAt: 4,
  },
  {
    id: "vanilla-cold-brew",
    name: "Vanilla Cold Brew",
    category: "Coffee",
    price: 165,
    stock: 7,
    lowStockAt: 4,
  },
  {
    id: "matcha-latte",
    name: "Matcha Latte",
    category: "Non-coffee",
    price: 170,
    stock: 11,
    lowStockAt: 4,
  },
  {
    id: "blueberry-soda",
    name: "Blueberry Soda",
    category: "Non-coffee",
    price: 145,
    stock: 3,
    lowStockAt: 4,
  },
  {
    id: "butter-croissant",
    name: "Butter Croissant",
    category: "Pastries",
    price: 95,
    stock: 8,
    lowStockAt: 3,
  },
  {
    id: "ube-cheesecake",
    name: "Ube Cheesecake",
    category: "Pastries",
    price: 155,
    stock: 0,
    lowStockAt: 3,
  },
];

const daysAgo = (days: number): string =>
  new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString();

const pastOrder = (
  id: string,
  number: string,
  days: number,
  items: Order["items"],
  total: number,
  payment: PaymentType,
): Order => ({
  id,
  number,
  items,
  total,
  payment,
  status: "served",
  createdAt: daysAgo(days),
});

export const seedOrders: Order[] = [
  pastOrder(
    "seed-order-1041",
    "1041",
    1,
    [
      { itemId: "spanish-latte", qty: 2, note: "Oat milk" },
      { itemId: "butter-croissant", qty: 1, note: "" },
    ],
    465,
    "cash",
  ),
  pastOrder(
    "seed-order-1042",
    "1042",
    3,
    [{ itemId: "matcha-latte", qty: 1, note: "Less sweet" }],
    170,
    "e-wallet",
  ),
  pastOrder(
    "seed-order-1043",
    "1043",
    6,
    [
      { itemId: "cafe-americano", qty: 2, note: "" },
      { itemId: "ube-cheesecake", qty: 1, note: "" },
    ],
    415,
    "card",
  ),
];
