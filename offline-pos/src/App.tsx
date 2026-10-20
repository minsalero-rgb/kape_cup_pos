import { useEffect, useMemo, useState } from "react";
import type { FormEvent } from "react";
import {
  getCartSubtotal,
  getCartTotal,
  getDiscountAmount,
  usePosStore,
} from "./store";
import type { Order, PaymentType } from "./types";
import {
  CustomerView,
  AppPageLayout,
  KitchenQueue,
  SalesReports,
  StockItems,
} from "./OfflinePages";
import type { AppPage } from "./OfflinePages";
import { Icon } from "./ui";

const currency = new Intl.NumberFormat("en-PH", {
  style: "currency",
  currency: "PHP",
});

const formatMoney = (amount: number): string => currency.format(amount);

const paymentLabels: Record<PaymentType, string> = {
  cash: "Cash",
  card: "Card",
  "e-wallet": "E-wallet",
};

function PosRegister() {
  const items = usePosStore((state) => state.items);
  const orders = usePosStore((state) => state.orders);
  const cart = usePosStore((state) => state.cart);
  const discount = usePosStore((state) => state.discount);
  const taxRate = usePosStore((state) => state.taxRate);
  const addItemToCart = usePosStore((state) => state.addItemToCart);
  const setQuantity = usePosStore((state) => state.setQuantity);
  const setLineNote = usePosStore((state) => state.setLineNote);
  const removeCartLine = usePosStore((state) => state.removeCartLine);
  const clearCart = usePosStore((state) => state.clearCart);
  const setDiscount = usePosStore((state) => state.setDiscount);
  const setTaxRate = usePosStore((state) => state.setTaxRate);
  const placeOrder = usePosStore((state) => state.placeOrder);
  const [activeCategory, setActiveCategory] = useState("All");
  const [search, setSearch] = useState("");
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [payment, setPayment] = useState<PaymentType>("cash");
  const [cashTendered, setCashTendered] = useState("");
  const [checkoutError, setCheckoutError] = useState("");
  const [receipt, setReceipt] = useState<Order | null>(null);
  const [receiptTotals, setReceiptTotals] = useState<{
    subtotal: number;
    discount: number;
    tax: number;
  } | null>(null);
  const [receiptCash, setReceiptCash] = useState<{
    received: number;
    change: number;
  } | null>(null);
  const [toast, setToast] = useState("");

  const subtotal = getCartSubtotal(cart, items);
  const discountAmount = getDiscountAmount(subtotal, discount);
  const total = getCartTotal(cart, items, discount, taxRate);
  const itemCount = cart.reduce((sum, line) => sum + line.qty, 0);
  const change =
    payment === "cash"
      ? Math.max(0, Number(cashTendered || 0) - total)
      : 0;

  const filteredItems = useMemo(
    () =>
      items.filter((item) => {
        const inCategory =
          activeCategory === "All" || item.category === activeCategory;
        const matchesSearch = item.name
          .toLocaleLowerCase()
          .includes(search.trim().toLocaleLowerCase());
        return inCategory && matchesSearch;
      }),
    [activeCategory, items, search],
  );
  const categoryTabs = useMemo(
    () => ["All", ...new Set(items.map((item) => item.category))],
    [items],
  );

  useEffect(() => {
    if (!toast) return;
    const timeout = window.setTimeout(() => setToast(""), 2800);
    return () => window.clearTimeout(timeout);
  }, [toast]);

  useEffect(() => {
    if (!checkoutOpen && !receipt) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setCheckoutOpen(false);
        setReceipt(null);
        setReceiptTotals(null);
        setReceiptCash(null);
        setCheckoutError("");
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [checkoutOpen, receipt]);

  const notify = (message: string) => setToast(message);

  const handleAddItem = (itemId: string) => {
    const error = addItemToCart(itemId);
    notify(error ?? "Added to cart.");
  };

  const handleQuantity = (itemId: string, quantity: number) => {
    const error = setQuantity(itemId, quantity);
    if (error) notify(error);
  };

  const handleRemoveLine = (itemId: string, itemName: string) => {
    if (!window.confirm(`Remove ${itemName} from this order?`)) return;
    removeCartLine(itemId);
    notify("Item removed from cart.");
  };

  const handleClearCart = () => {
    if (!cart.length) return;
    if (!window.confirm("Clear all items from this order?")) return;
    clearCart();
    notify("Cart cleared.");
  };

  const handleCheckout = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const paidSubtotal = subtotal;
    const paidDiscount = discountAmount;
    const paidTax = Math.max(0, total - paidSubtotal + paidDiscount);
    const result = placeOrder(payment, Number(cashTendered || 0));
    if (!result.order) {
      setCheckoutError(result.error ?? "Order could not be placed.");
      return;
    }
    setCheckoutError("");
    setCheckoutOpen(false);
    setReceipt(result.order);
    setReceiptTotals({
      subtotal: paidSubtotal,
      discount: paidDiscount,
      tax: paidTax,
    });
    setReceiptCash(
      payment === "cash"
        ? { received: Number(cashTendered), change }
        : null,
    );
    setCashTendered("");
    notify(`Order #${result.order.number} placed.`);
  };

  const latestOrder = orders[0];
  const receiptItems = receipt
    ? receipt.items.map((line) => ({
        ...line,
        item: items.find((entry) => entry.id === line.itemId),
      }))
    : [];

  return (
    <div className="pos-page-content">
        <div className="page-intro">
          <div>
            <p className="eyebrow">Barista cockpit</p>
            <h2>POS Register</h2>
            <p>Build an order from the current menu.</p>
          </div>
        </div>
        <div className="offline-banner" role="note">
          <Icon>info</Icon>
          <span>
            Offline demo: orders and stock are saved only in this browser. They
            do not update the PHP/MySQL POS.
          </span>
        </div>

        <div className="workspace">
          <section className="catalog" aria-labelledby="catalog-title">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Make something lovely</p>
                <h2 id="catalog-title">Choose an item</h2>
              </div>
              <span className="catalog-count">{filteredItems.length} items</span>
            </div>

            <label className="search-field">
              <Icon>search</Icon>
              <span className="sr-only">Search menu items</span>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search menu items"
              />
              {search && (
                <button
                  className="clear-search"
                  type="button"
                  aria-label="Clear search"
                  onClick={() => setSearch("")}
                >
                  <Icon>close</Icon>
                </button>
              )}
            </label>

            <div className="category-tabs" aria-label="Menu categories">
              {categoryTabs.map((category) => (
                <button
                  className={`category-tab${activeCategory === category ? " selected" : ""}`}
                  type="button"
                  key={category}
                  aria-pressed={activeCategory === category}
                  onClick={() => setActiveCategory(category)}
                >
                  {category}
                </button>
              ))}
            </div>
            {receiptTotals && (
              <div className="receipt-breakdown">
                <div>
                  <span>Subtotal</span>
                  <span>{formatMoney(receiptTotals.subtotal)}</span>
                </div>
                <div>
                  <span>Discount</span>
                  <span>− {formatMoney(receiptTotals.discount)}</span>
                </div>
                <div>
                  <span>Tax</span>
                  <span>{formatMoney(receiptTotals.tax)}</span>
                </div>
                {receiptCash && (
                  <>
                    <div>
                      <span>Cash received</span>
                      <span>{formatMoney(receiptCash.received)}</span>
                    </div>
                    <div>
                      <span>Change</span>
                      <span>{formatMoney(receiptCash.change)}</span>
                    </div>
                  </>
                )}
              </div>
            )}

            {filteredItems.length ? (
              <div className="product-grid">
                {filteredItems.map((item) => {
                  const inCart =
                    cart.find((line) => line.itemId === item.id)?.qty ?? 0;
                  const soldOut = item.stock <= 0;
                  const stockLow = item.stock > 0 && item.stock <= item.lowStockAt;
                  return (
                    <button
                      key={item.id}
                      className={`product-card${soldOut ? " sold-out" : ""}`}
                      type="button"
                      disabled={soldOut}
                      onClick={() => handleAddItem(item.id)}
                      aria-label={`${item.name}, ${formatMoney(item.price)}${soldOut ? ", sold out" : `, ${item.stock - inCart} available`}`}
                    >
                      <span className={`product-art art-${item.category.toLowerCase().replace(/[^a-z]+/g, "-")}`}>
                        <span aria-hidden="true">
                          {item.category === "Pastries" ? "🥐" : item.category === "Non-coffee" ? "🍵" : "☕"}
                        </span>
                        {soldOut && <span className="sold-out-tag">Sold out</span>}
                      </span>
                      <span className="product-meta">
                        <span className="product-category">{item.category}</span>
                        <strong>{item.name}</strong>
                        <span className="product-footer">
                          <span>{formatMoney(item.price)}</span>
                          <span className={stockLow ? "stock-low" : "stock-count"}>
                            {soldOut ? "Unavailable" : `${item.stock - inCart} left`}
                          </span>
                        </span>
                      </span>
                      {inCart > 0 && (
                        <span className="in-cart-badge">{inCart} in cart</span>
                      )}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="empty-state">
                <span className="empty-icon" aria-hidden="true">⌕</span>
                <h3>No menu items found</h3>
                <p>Try another name or select a different category.</p>
                <button
                  className="text-button"
                  type="button"
                  onClick={() => {
                    setSearch("");
                    setActiveCategory("All");
                  }}
                >
                  Show all menu items
                </button>
              </div>
            )}
          </section>

          <aside className="cart-panel" aria-labelledby="cart-title">
            <div className="cart-header">
              <div>
                <p className="eyebrow">Current order</p>
                <h2 id="cart-title">
                  Your cart <span className="count-pill">{itemCount}</span>
                </h2>
              </div>
              <button
                type="button"
                className="text-button danger-text"
                disabled={!cart.length}
                onClick={handleClearCart}
              >
                Clear
              </button>
            </div>

            {cart.length ? (
              <div className="cart-lines">
                {cart.map((line) => {
                  const item = items.find((entry) => entry.id === line.itemId);
                  if (!item) return null;
                  return (
                    <article className="cart-line" key={line.itemId}>
                      <div className="cart-line-main">
                        <div className="cart-line-title">
                          <div>
                            <h3>{item.name}</h3>
                            <p>{formatMoney(item.price)} each</p>
                          </div>
                          <strong>{formatMoney(item.price * line.qty)}</strong>
                        </div>
                        <div className="cart-line-controls">
                          <div className="quantity-control" aria-label={`Quantity for ${item.name}`}>
                            <button
                              type="button"
                              aria-label={`Decrease ${item.name} quantity`}
                              onClick={() => {
                                if (line.qty === 1) {
                                  handleRemoveLine(item.id, item.name);
                                } else {
                                  handleQuantity(item.id, line.qty - 1);
                                }
                              }}
                            >
                              <Icon>remove</Icon>
                            </button>
                            <span aria-live="polite">{line.qty}</span>
                            <button
                              type="button"
                              aria-label={`Increase ${item.name} quantity`}
                              disabled={line.qty >= item.stock}
                              onClick={() => handleQuantity(item.id, line.qty + 1)}
                            >
                              <Icon>add</Icon>
                            </button>
                          </div>
                          <button
                            type="button"
                            className="remove-button"
                            onClick={() => handleRemoveLine(item.id, item.name)}
                          >
                            Remove
                          </button>
                        </div>
                        <label className="note-field">
                          <span>Item note for {item.name}</span>
                          <input
                            aria-label={`Note for ${item.name}`}
                            value={line.note}
                            maxLength={120}
                            onChange={(event) =>
                              setLineNote(item.id, event.target.value)
                            }
                            onBlur={() => notify("Item note saved in the cart.")}
                            placeholder="e.g. oat milk, less ice"
                          />
                        </label>
                      </div>
                    </article>
                  );
                })}
              </div>
            ) : (
              <div className="cart-empty">
                <span className="cart-empty-icon" aria-hidden="true">☕</span>
                <h3>Your order is waiting</h3>
                <p>Choose a drink or pastry to begin.</p>
              </div>
            )}

            <div className="order-options">
              <div className="option-title">
                <strong>Discount</strong>
                <span>Optional</span>
              </div>
              <div className="discount-row">
                <label className="sr-only" htmlFor="discount-type">
                  Discount type
                </label>
                <select
                  id="discount-type"
                  value={discount.type}
                  onChange={(event) => {
                    setDiscount({
                      type: event.target.value as "percent" | "fixed",
                      value: discount.value,
                    });
                    notify("Discount type updated.");
                  }}
                >
                  <option value="percent">Percent (%)</option>
                  <option value="fixed">Fixed (₱)</option>
                </select>
                <label className="discount-input">
                  <span className="sr-only">Discount amount</span>
                  <input
                    type="number"
                    min="0"
                    max={discount.type === "percent" ? 100 : undefined}
                    step={discount.type === "percent" ? 1 : 0.01}
                    value={discount.value}
                    onChange={(event) =>
                      setDiscount({
                        ...discount,
                        value: Number(event.target.value),
                      })
                    }
                    onBlur={() => notify("Discount applied.")}
                  />
                  <span>{discount.type === "percent" ? "%" : "₱"}</span>
                </label>
              </div>

              <div className="option-title tax-title">
                <label htmlFor="tax-rate">Tax rate</label>
                <div className="tax-input">
                  <input
                    id="tax-rate"
                    type="number"
                    min="0"
                    max="100"
                    step="0.1"
                    value={taxRate}
                    onChange={(event) => setTaxRate(Number(event.target.value))}
                    onBlur={() => notify("Tax rate updated.")}
                  />
                  <span>%</span>
                </div>
              </div>
            </div>

            <div className="totals">
              <div className="total-row">
                <span>Subtotal</span>
                <span>{formatMoney(subtotal)}</span>
              </div>
              <div className="total-row">
                <span>Discount</span>
                <span className="discount-value">− {formatMoney(discountAmount)}</span>
              </div>
              <div className="total-row">
                <span>Tax ({taxRate.toFixed(1)}%)</span>
                <span>{formatMoney(Math.max(0, total - subtotal + discountAmount))}</span>
              </div>
              <div className="total-row grand-total">
                <strong>Total due</strong>
                <strong>{formatMoney(total)}</strong>
              </div>
            </div>

            <button
              className="checkout-button"
              type="button"
              disabled={!cart.length}
              onClick={() => {
                setCheckoutError("");
                setCheckoutOpen(true);
              }}
            >
              <span>Proceed to payment</span>
              <Icon>arrow_forward</Icon>
            </button>

            <div className="last-order">
              <Icon>receipt_long</Icon>
              <span>
                {latestOrder
                  ? `Last order #${latestOrder.number}`
                  : "No orders yet"}
              </span>
              {latestOrder && (
                <span className="last-order-status">{latestOrder.status}</span>
              )}
            </div>
          </aside>
        </div>
      </main>

      {toast && (
        <div className="toast" role="status" aria-live="polite">
          <Icon>check_circle</Icon>
          <span>{toast}</span>
        </div>
      )}

      {checkoutOpen && (
        <div
          className="modal-backdrop"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setCheckoutOpen(false);
          }}
        >
          <section
            className="checkout-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="checkout-title"
            aria-describedby="checkout-total"
          >
            <div className="modal-heading">
              <div>
                <p className="eyebrow">Almost there</p>
                <h2 id="checkout-title">Take payment</h2>
              </div>
              <button
                className="icon-button"
                type="button"
                aria-label="Close payment dialog"
                onClick={() => setCheckoutOpen(false)}
              >
                <Icon>close</Icon>
              </button>
            </div>

            <form onSubmit={handleCheckout}>
              <div className="checkout-total" id="checkout-total">
                <span>Amount due</span>
                <strong>{formatMoney(total)}</strong>
              </div>

              <fieldset className="payment-methods">
                <legend>Payment method</legend>
                {(["cash", "card", "e-wallet"] as PaymentType[]).map((method) => (
                  <label
                    className={`payment-choice${payment === method ? " selected" : ""}`}
                    key={method}
                  >
                    <input
                      type="radio"
                      name="payment"
                      value={method}
                      checked={payment === method}
                      onChange={() => {
                        setPayment(method);
                        setCheckoutError("");
                      }}
                    />
                    <Icon>
                      {method === "cash"
                        ? "payments"
                        : method === "card"
                          ? "credit_card"
                          : "account_balance_wallet"}
                    </Icon>
                    <span>{paymentLabels[method]}</span>
                  </label>
                ))}
              </fieldset>

              {payment === "cash" && (
                <div className="cash-entry">
                  <label htmlFor="cash-tendered">Cash received</label>
                  <div className="cash-input">
                    <span>₱</span>
                    <input
                      id="cash-tendered"
                      type="number"
                      min={total}
                      step="0.01"
                      value={cashTendered}
                      onChange={(event) => {
                        setCashTendered(event.target.value);
                        setCheckoutError("");
                      }}
                      placeholder="0.00"
                      autoFocus
                    />
                  </div>
                  <div className="change-row">
                    <span>Change</span>
                    <strong>{formatMoney(change)}</strong>
                  </div>
                </div>
              )}

              {checkoutError && (
                <p className="form-error" role="alert">
                  <Icon>error</Icon>
                  {checkoutError}
                </p>
              )}

              <div className="modal-actions">
                <button
                  className="secondary-button"
                  type="button"
                  onClick={() => setCheckoutOpen(false)}
                >
                  Go back
                </button>
                <button className="checkout-button" type="submit">
                  Place order
                  <Icon>check</Icon>
                </button>
              </div>
            </form>
          </section>
        </div>
      )}

      {receipt && (
        <div className="modal-backdrop receipt-backdrop">
          <section
            className="receipt-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="receipt-title"
          >
            <div className="receipt-brand">
              <img
                className="brand-mark"
                src="../assets/img/me-kape-cup-logo.png"
                alt=""
              />
              <p>M&amp;E Kape Cup</p>
              <span>Main Counter Branch</span>
            </div>
            <div className="receipt-success">
              <Icon>check_circle</Icon>
              <h2 id="receipt-title">Thank you!</h2>
              <p>Order #{receipt.number} has been placed.</p>
            </div>
            <div className="receipt-meta">
              <span>{new Date(receipt.createdAt).toLocaleString("en-PH")}</span>
              <span>{paymentLabels[receipt.payment]}</span>
            </div>
            <div className="receipt-lines">
              {receiptItems.map((line, index) => (
                <div className="receipt-line" key={`${line.itemId}-${index}`}>
                  <span>
                    {line.qty} × {line.item?.name ?? "Menu item"}
                    {line.note && <small>{line.note}</small>}
                  </span>
                  <strong>
                    {formatMoney((line.item?.price ?? 0) * line.qty)}
                  </strong>
                </div>
              ))}
            </div>
            <div className="receipt-total">
              <span>Total paid</span>
              <strong>{formatMoney(receipt.total)}</strong>
            </div>
            <div className="receipt-actions no-print">
              <button
                className="secondary-button"
                type="button"
                onClick={() => {
                  setReceipt(null);
                  setReceiptTotals(null);
                  setReceiptCash(null);
                }}
              >
                Close
              </button>
              <button
                className="checkout-button"
                type="button"
                onClick={() => window.print()}
              >
                Print receipt
                <Icon>print</Icon>
              </button>
            </div>
          </section>
        </div>
      )}
    </div>
  );
}

const getPageFromLocation = (): AppPage => {
  const page = new URLSearchParams(window.location.search).get("page");
  return page === "queue" || page === "reports" || page === "stock" || page === "customer"
    ? page
    : "pos";
};

export function App() {
  const [page, setPage] = useState<AppPage>(getPageFromLocation);

  useEffect(() => {
    const syncPage = () => setPage(getPageFromLocation());
    const navigate = (event: MouseEvent) => {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return;
      }
      const target = event.target;
      const link =
        target instanceof Element
          ? target.closest<HTMLAnchorElement>("a[data-page]")
          : null;
      if (!link) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin) return;
      event.preventDefault();
      window.history.pushState({}, "", url);
      syncPage();
    };

    window.addEventListener("popstate", syncPage);
    document.addEventListener("click", navigate);
    return () => {
      window.removeEventListener("popstate", syncPage);
      document.removeEventListener("click", navigate);
    };
  }, []);

  if (page === "customer") return <CustomerView />;
  const pageContent = page === "pos"
    ? <PosRegister />
    : {
      queue: <KitchenQueue />,
      reports: <SalesReports />,
      stock: <StockItems />,
    }[page];
  return <AppPageLayout page={page}>{pageContent}</AppPageLayout>;
}
