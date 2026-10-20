import React from "react";
import ReactDOM from "react-dom/client";
import { App } from "./App";
import {
  getCartSubtotal,
  getCartTotal,
  getDiscountAmount,
  usePosStore,
} from "./store";

const customerChannel =
  typeof BroadcastChannel === "undefined"
    ? null
    : new BroadcastChannel("mekape-pos-customer");

const tabId =
  typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random()}`;
let applyingRemoteState = false;
let lastOrderId = usePosStore.getState().orders[0]?.id ?? null;

const publishCart = (state: ReturnType<typeof usePosStore.getState>) => {
  if (applyingRemoteState) return;
  const subtotal = getCartSubtotal(state.cart, state.items);
  const sharedState = {
    items: state.items,
    orders: state.orders,
    stockLogs: state.stockLogs,
    cart: state.cart,
    discount: state.discount,
    taxRate: state.taxRate,
  };
  customerChannel?.postMessage({
    type: "shared:state",
    source: tabId,
    state: sharedState,
  });
  customerChannel?.postMessage({
    type: "cart:update",
    cart: state.cart,
    items: state.items,
    subtotal,
    discount: getDiscountAmount(subtotal, state.discount),
    taxRate: state.taxRate,
    total: getCartTotal(
      state.cart,
      state.items,
      state.discount,
      state.taxRate,
    ),
  });
  const latestOrder = state.orders[0];
  if (latestOrder && latestOrder.id !== lastOrderId) {
    customerChannel?.postMessage({
      type: "order:placed",
      order: latestOrder,
    });
    lastOrderId = latestOrder.id;
  }
};

if (customerChannel) {
  customerChannel.onmessage = (
    event: MessageEvent<{
      type?: string;
      source?: string;
      state?: Partial<ReturnType<typeof usePosStore.getState>>;
    }>,
  ) => {
    if (event.data?.type === "cart:request") {
      publishCart(usePosStore.getState());
    } else if (
      event.data?.type === "shared:state" &&
      event.data.source !== tabId &&
      event.data.state
    ) {
      const latestRemoteOrder = event.data.state.orders?.[0];
      if (latestRemoteOrder) lastOrderId = latestRemoteOrder.id;
      applyingRemoteState = true;
      usePosStore.setState(event.data.state);
      applyingRemoteState = false;
    }
  };
}

usePosStore.subscribe(publishCart);
publishCart(usePosStore.getState());

ReactDOM.createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
