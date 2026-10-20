const categories = [
  "Coffee Series",
  "Matcha Series",
  "Fruit Milk",
  "Soda",
  "Fruit Shake",
  "Latte Series with Whipped Cream",
  "Main Courses",
  "Snacks",
];

const icons = {
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  search: '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/>',
  bag: '<path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
  cart: '<path d="M3 4h2l2.2 11h11.4L21 8H6"/><circle cx="9" cy="19" r="1"/><circle cx="18" cy="19" r="1"/>',
  order: '<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
  home: '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
  drink: '<path d="M6 4h12l-1 17H7L6 4Z"/><path d="M5 8h14M9 2l2 2m3-2 2 2"/>',
  check: '<path d="m5 12 4 4L19 6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  alert: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/>',
  close: '<path d="m6 6 12 12M18 6 6 18"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  arrow: '<path d="M5 12h14m-7-7 7 7-7 7"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  coffee: '<path d="M4 8h14v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Zm14 2h2a2 2 0 1 1 0 4h-2M2 22h18M8 4V2m5 2V2"/>',
  home2: '<path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-6h6v6"/>',
  box: '<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>',
  chart: '<path d="M3 3v18h18M7 14l4-4 4 3 6-7"/>',
  people: '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0m1-10a3 3 0 1 0 0-6m1 10a6 6 0 0 1 4 6"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.6.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.6-1.4-2.4 1.4-1.1a8 8 0 0 1 0-1.9l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.6-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.6.9l1.7-.6 1.4 2.4-1.4 1.1a8 8 0 0 1 0 1.8Z"/>',
  signout: '<path d="M10 17l5-5-5-5m5 5H3m9-9h8v18h-8"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',
  lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/>',
  card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/>',
  table: '<path d="M3 7h18M5 7v13m14-13v13M3 13h18"/>',
  refresh: '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.9 5a7 7 0 0 0 11.5-2"/>',
  sliders: '<path d="M4 7h10m4 0h2M4 17h2m4 0h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
};
const icon = (key) => `<svg aria-hidden="true" viewBox="0 0 24 24">${icons[key] || icons.info}</svg>`;

const sections = {
  customer: [
    ["C1", "Home"], ["C2", "Menu"], ["C3", "Product options"], ["C4", "Cart"],
    ["C5", "Checkout"], ["C6", "Order placed"], ["C7", "Track order"], ["C8", "Error & empty states"],
  ],
  staff: [
    ["S1", "Login"], ["S2", "Start shift"], ["S3", "Register"], ["S4", "Kitchen queue"],
    ["S5", "Stock"], ["S6", "My sales"], ["S7", "End shift"], ["S8", "Change password"],
  ],
  admin: [
    ["A1", "Login"], ["A2", "Overview"], ["A3", "Orders"], ["A4", "Menu items"],
    ["A5", "Categories"], ["A6", "Modifiers"], ["A7", "Staff accounts"], ["A8", "Duty sessions"], ["A9", "Reports"],
  ],
  system: [["D1", "Design system"]],
};
const viewports = { customer: "390 × 844 · mobile first", staff: "1280 × 800 · landscape tablet", admin: "1440 × 900 · desktop", system: "Design tokens & components" };

const stateOptions = {
  C2: ["Default", "Ordering closed", "Empty", "Loading", "Error"],
  C4: ["Default", "With items", "Ordering closed", "Empty", "Loading", "Error"],
  C5: ["Default", "Ordering closed", "Empty", "Loading", "Error"],
  S4: ["Default", "Intake closed", "Empty", "Loading", "Error"],
};
const prototypeState = { shell: "customer", screen: "C1", variant: "Default", cartCount: 0, ticketCount: 0, activeCategory: categories[0], orderingOpen: true, muted: false, shiftStarted: true };
const root = document.getElementById("prototype");

function prototypeTag() { return '<span class="fictional-tag">Prototype data (fictional)</span>'; }
function head(title, description, code) {
  return `<header class="screen-head"><div><p class="eyebrow">${code} · ${viewports[prototypeState.shell]}</p><h1>${title}</h1><p>${description}</p></div>${prototypeTag()}${statePicker(code)}</header>`;
}
function statePicker(code) {
  if (!stateOptions[code]) return "";
  return `<div class="screen-state-controls" aria-label="Prototype state">${stateOptions[code].map((state) =>
    `<button type="button" data-variant="${state}" aria-pressed="${prototypeState.variant === state}">${state}</button>`
  ).join("")}</div>`;
}
function pageShell(title, desc, code, content, active) {
  const orderingOpen = prototypeState.orderingOpen && prototypeState.variant !== "Ordering closed";
  return `${head(title, desc, code)}<section class="customer-shell"><header class="customer-header"><a class="customer-logo" href="#" data-screen="C1"><img src="../assets/img/me-kape-cup-logo.png" alt=""><strong>M&amp;E Kape Cup</strong></a><nav class="customer-nav" aria-label="Customer navigation"><a class="${active === "C1" ? "active" : ""}" href="#" data-screen="C1">${icon("home")} Menu</a><a class="${active === "C7" ? "active" : ""}" href="#" data-screen="C7">${icon("order")} Track Order</a><a class="${active === "C4" ? "active" : ""}" href="#" data-screen="C4">${icon("bag")} Cart (${prototypeState.cartCount})</a></nav></header>${orderingOpen ? "" : `<div class="closed-banner banner"><span class="badge pending">${icon("alert")} Ordering closed</span><span>Ordering is closed right now. You can browse the menu, but you cannot place an order yet.</span></div>`}<main class="customer-shell-main">${content}</main><footer class="customer-footer"><a href="#" data-screen="S1">Staff login</a><span>[Contact details pending confirmation]</span></footer><nav class="customer-mobile-nav"><a href="#" data-screen="C2">${icon("home")}<span>Menu</span></a><a href="#" data-screen="C4">${icon("cart")}<span>Cart · ${prototypeState.cartCount} · [Total]</span></a><a href="#" data-screen="C7">${icon("order")}<span>Track Order</span></a></nav></section>`;
}
function orderClosedButton(label, action = "add") {
  return prototypeState.orderingOpen && prototypeState.variant !== "Ordering closed"
    ? `<button type="button" class="button" data-action="${action}">${label}</button>`
    : '<button type="button" class="button" disabled>Ordering closed</button>';
}
function variantContent(defaultContent, empty, loading, error, closed) {
  const variant = prototypeState.variant;
  if (variant === "Empty") return empty;
  if (variant === "Loading") return loading;
  if (variant === "Error") return error;
  if (variant === "Ordering closed" || variant === "Intake closed") return closed || defaultContent;
  return defaultContent;
}
function customerScreen(code) {
  const cats = categories.map((name, index) => `<a class="category-tile" href="#" data-category="${index + 1}">${icon(["coffee","drink","drink","drink","drink","coffee","table","box"][index])}<span>${name}</span></a>`).join("");
  const product = (index, unavailable = false) => `<article class="product-card ${unavailable ? "unavailable" : ""}"><div class="product-art"><img src="../assets/img/${["coffee","matcha","fruit-milk","soda","fruit-shake","latte","main-courses","snacks"][index]}.png" alt="">${unavailable ? `<span class="badge neutral">${icon("alert")} Unavailable</span>` : ""}</div><div class="product-info"><h3>[Product name]</h3><span class="price">[Price]</span>${unavailable ? '<button class="button" disabled>Unavailable</button>' : orderClosedButton("Add", index === 0 ? "options" : "add")}</div></article>`;
  const menuContent = `<div class="search-row"><label class="search-wrap">${icon("search")}<span class="sr-only">Search menu</span><input class="search-input" type="search" placeholder="Search the menu"></label></div><nav class="category-chips" aria-label="Menu categories">${categories.map((cat) => `<button class="chip ${prototypeState.activeCategory === cat ? "active" : ""}" data-chip="${cat}" aria-pressed="${prototypeState.activeCategory === cat}">${cat}</button>`).join("")}</nav><div class="row spread"><h2 class="section-title">${prototypeState.activeCategory}</h2>${prototypeTag()}</div><div class="product-grid">${product(0)}${product(1)}${product(2,true)}${product(3)}</div>${prototypeState.cartCount ? '<button type="button" class="button cart-float" data-screen="C4">View cart ('+prototypeState.cartCount+') - [Total]</button>' : ""}`;
  const menu = variantContent(menuContent, `<div class="empty-state">${icon("search")}<h2>No items here yet</h2><p>Try another category or check back later.</p><button class="button-secondary">Browse all categories</button></div>`, `<div class="loading-state" aria-live="polite"><h2>Loading the menu…</h2><div class="grid grid-2" style="width:100%"><div class="card stack"><div class="skeleton" style="height:110px"></div><div class="skeleton"></div></div><div class="card stack"><div class="skeleton" style="height:110px"></div><div class="skeleton"></div></div></div></div>`, `<div class="error-state">${icon("refresh")}<h2>We’ll be right back</h2><p>The menu didn’t load. Please try again.</p><button class="button-secondary" data-variant="Default">Retry</button></div>`, menuContent);
  const cartLine = `<article class="cart-line"><div><div class="banner error cart-line-warning">${icon("alert")} This item is no longer available. <button class="button-text">Remove</button></div><strong>[Product name]</strong><p class="muted">[Selected options]</p><label class="field"><span>Item note</span><textarea placeholder="Add a note (optional)"></textarea></label></div><div class="stack" style="justify-items:end"><strong>[Price]</strong><div class="qty-stepper"><button aria-label="Decrease quantity">${icon("minus")}</button><output>1</output><button aria-label="Increase quantity">${icon("plus")}</button></div><button class="button-text">Remove</button></div></article>`;
  const emptyCart = `<div class="empty-state card">${icon("coffee")}<h2>Your cart is empty</h2><p>Pick something you love from the menu.</p><button class="button" data-screen="C2">Browse menu</button></div>`;
  const filledCart = `<div class="card"><h2 class="section-title">Your cart</h2>${cartLine}<div class="cart-summary row spread"><strong>Subtotal</strong><strong>[Subtotal]</strong></div><button class="button" style="width:100%;margin-top:14px" data-screen="C5">Checkout</button></div>`;
  const cart = variantContent(prototypeState.cartCount ? filledCart : emptyCart, emptyCart, `<div class="card loading-state"><h2>Loading your cart…</h2><div class="skeleton" style="width:80%"></div></div>`, `<div class="banner error"><strong>Cart couldn’t load.</strong> Please refresh and try again.</div>`, `<div class="card empty-state"><h2>Ordering is closed</h2><p>Your cart is saved while ordering is paused.</p><button class="button" disabled>Ordering closed</button></div>`);
  const checkout = variantContent(`<div class="progress"><span class="done">Cart</span><span class="current">Details</span><span>Done</span></div><div class="grid grid-2"><div class="card stack"><div class="field"><label for="guest-name">Your name</label><input id="guest-name" placeholder="Name for your order"></div><div><span class="field-label">Order type</span><div class="payment-grid" style="grid-template-columns:repeat(2,1fr);margin-top:8px"><label class="payment-option"><span>Dine-In</span><input type="radio" name="order-type" checked></label><label class="payment-option"><span>Take-Out</span><input type="radio" name="order-type"></label></div></div><div class="field"><label for="table-label">Table</label><select id="table-label"><option>[Choose a table]</option></select></div></div><div class="card stack"><h2 class="section-title">Pay at the counter</h2><div class="payment-grid">${["Cash","GCash","Credit Card"].map((p,i)=>`<label class="payment-option">${icon(i===0?"bag":"card")}<strong>${p}</strong><input type="radio" name="payment" ${i===0?"checked":""}><span>You pay at the counter</span></label>`).join("")}</div><p class="row spread"><strong>Order total</strong><strong>[Total]</strong></p><button class="button" style="width:100%" data-screen="C6">Place Order</button></div></div>`, `<div class="empty-state card"><h2>Your cart is empty</h2><p>Add an item before checkout.</p><button class="button" data-screen="C2">Browse menu</button></div>`, `<div class="card loading-state"><h2>Loading checkout…</h2><div class="skeleton"></div></div>`, `<div class="banner error">We couldn’t load checkout details. Please return to your cart and try again.</div>`, `<div class="card stack"><h2>Ordering is closed right now</h2><p>You can browse the menu, but you cannot place an order yet.</p><button class="button" disabled>Ordering closed</button></div>`);
  const statusTracker = `<div class="status-track">${[["Pending","Please pay at the counter."],["Paid","A staff member has confirmed payment."],["Preparing","Your order is being made."],["Completed","Your order is ready." ]].map((step,index)=>`<div class="status-step ${index===0?"current":""}"><span class="step-mark">${icon(index===0?"clock":index===2?"drink":"check")}</span><div><strong>${step[0]}</strong><span class="muted">${step[1]}</span></div></div>`).join("")}</div>`;
  let content;
  let title;
  let description;
  let active = code;
  if (code === "C1") {
    title = "Welcome to M&E Kape Cup";
    description = "A calm, simple way to find your next favorite.";
    content = `<section class="hero"><div class="hero-copy"><p class="eyebrow" style="color:var(--cream)">M&amp;E Kape Cup</p><h2>A little comfort, one cup at a time.</h2><p>Browse the menu and make your order at your own pace.</p><a class="button" href="#" data-screen="C2">Order Now ${icon("arrow")}</a></div><img class="hero-photo" src="../assets/img/coffee.png" alt="Coffee drink"></section><section class="stack" style="margin-top:28px"><div class="row spread"><h2 class="section-title">Explore the menu</h2><a href="#" data-screen="C2">View menu</a></div><div class="grid grid-4">${cats}</div></section><section class="stack" style="margin-top:28px"><h2 class="section-title">How it works</h2><div class="steps-strip"><div class="step-card"><span class="step-number">1</span><strong>Choose</strong></div><div class="step-card"><span class="step-number">2</span><strong>Pay at the counter</strong></div><div class="step-card"><span class="step-number">3</span><strong>We call your name</strong></div></div></section>`;
  } else if (code === "C2") {
    title = "Menu";
    description = "Find something to enjoy. [Product name] · [Price]";
    content = menu; active = "C2";
  } else if (code === "C3") {
    title = "Product options";
    description = "Customize your choice before adding it to your cart.";
    content = `<div class="sheet-backdrop"><section class="card product-sheet" role="dialog" aria-modal="true" aria-labelledby="options-title"><div class="sheet-layout"><img class="sheet-image" src="../assets/img/coffee.png" alt=""><div class="stack"><div><h2 class="section-title" id="options-title">[Product name]</h2><p class="muted">[Product description]</p><p><strong>[Price]</strong></p></div><div><div class="row spread"><strong>Size</strong><span class="badge neutral">Required</span></div><p class="muted">Choose one</p><div class="option-list"><label class="option-row"><input type="radio" name="size"><span>[Size]</span><span class="muted">[Price]</span></label><label class="option-row"><input type="radio" name="size"><span>[Size]</span><span class="muted">[Price]</span></label></div></div><div class="banner error" data-options-error role="alert" hidden>Choose a size to continue.</div><div><div class="row spread"><strong>[Modifier group]</strong><span class="badge neutral">Optional</span></div><p class="muted">Choose up to [n]</p><div class="option-list"><label class="option-row"><input type="checkbox"><span>[Option]</span><span class="muted">[Price change]</span></label></div></div><div class="row spread"><strong>Quantity</strong><div class="qty-stepper"><button aria-label="Decrease quantity">${icon("minus")}</button><output>1</output><button aria-label="Increase quantity">${icon("plus")}</button></div></div><div class="field"><label for="item-note">Add a note <span class="muted">(optional)</span></label><textarea id="item-note" placeholder="Anything we should know?"></textarea></div></div></div>${prototypeState.orderingOpen ? '<button class="button sticky-add" data-action="add">Add to Cart - [Price]</button>' : '<button class="button sticky-add" disabled>Ordering closed</button>'}</section></div>`;
  } else if (code === "C4") {
    title = "Your cart";
    description = "Check your items and notes before checkout.";
    content = cart; active = "C4";
  } else if (code === "C5") {
    title = "Checkout";
    description = "A few details, then place your order.";
    content = checkout; active = "C4";
  } else if (code === "C6") {
    title = "Order placed";
    description = "Your order is ready for counter payment.";
    content = `<section class="card"><p class="eyebrow">Your order number</p><h2 class="order-number">[Order number]</h2><p><strong>Take a screenshot so you can find your order later.</strong></p><span class="badge pending">${icon("clock")} Pending</span><p class="muted">Last updated: [time]</p>${statusTracker}<button class="button" data-screen="C2">Place another order</button></section>`; active = "C7";
  } else if (code === "C7") {
    title = "Track your order";
    description = "Use the order number and name from checkout.";
    content = `<section class="card stack" style="max-width:600px"><div class="field"><label for="order-number">Order number</label><input id="order-number" placeholder="[Order number]"></div><div class="field"><label for="order-name">Name used on the order</label><input id="order-name" placeholder="Your name"></div><button class="button" data-action="track">Track Order</button><div class="banner error">${icon("alert")}<span>We couldn’t find that order. Check the number and name, then try again.</span></div></section>`; active = "C7";
  } else {
    title = "Helpful states";
    description = "Clear recovery paths for common moments.";
    content = `<div class="grid grid-2"><section class="card error-state">${icon("refresh")}<h2>We’ll be right back</h2><p>The menu didn’t load. Please try again.</p><button class="button-secondary">Retry</button></section><section class="card empty-state">${icon("search")}<h2>No results found</h2><p>Try a different search or browse all categories.</p><button class="button-secondary" data-screen="C2">Browse menu</button></section><section class="card error-state">${icon("lock")}<h2>Your session expired</h2><p>For your privacy, start again from the menu.</p><button class="button" data-screen="C1">Back to menu</button></section></div>`;
  }
  return pageShell(title, description, code, content, active);
}

function staffShell(code, title, desc, content) {
  const nav = [["Register","drink"],["Kitchen Queue","table"],["Stock","box"],["My Sales","chart"]];
  const current = code === "S3" ? "Register" : code === "S4" ? "Kitchen Queue" : code === "S5" ? "Stock" : "My Sales";
  return `${head(title, desc, code)}<section class="staff-shell"><nav class="staff-rail" aria-label="Staff navigation"><div class="staff-rail-brand"><img src="../assets/img/me-kape-cup-logo.png" alt=""><strong>M&amp;E Kape Cup</strong></div>${nav.map(([label,ico])=>`<a class="${current===label?"active":""}" href="#" data-screen="${label==="Register"?"S3":label==="Kitchen Queue"?"S4":label==="Stock"?"S5":"S6"}">${icon(ico)}<span>${label}</span></a>`).join("")}</nav><div class="staff-area"><header class="staff-topbar"><strong>[Staff name]</strong><span class="shift-pill">${icon("check")} SHIFT STATUS · On shift since [time]</span><button class="button-secondary" data-screen="S7">End Shift</button></header><main class="staff-content">${content}</main></div></section>`;
}
function staffScreen(code) {
  const products = `<div class="category-chips">${categories.map((cat,i)=>`<button class="chip ${i===0?"active":""}">${cat}</button>`).join("")}</div><div class="product-grid">${[0,1,2,3,4,5].map((x)=>`<button class="product-card" ${x===5?'disabled title="Out of stock"':""} data-action="add"><span class="product-art">${icon("drink")}</span><span class="product-info"><strong>[Product name]</strong><span>[Price] · <span class="badge ${x===2?"pending":"neutral"}">${x===2?icon("alert")+" Low":x===5?"Unavailable":"In stock"}</span></span></span></button>`).join("")}</div>`;
  const ticket = `<aside class="card ticket"><div class="row spread"><h2 class="section-title">Current ticket</h2><span class="badge neutral">${prototypeState.ticketCount || "[n]"} items</span></div><article class="cart-line"><div><strong>[Product name] × [quantity]</strong><p class="muted">[Modifiers]</p><p class="muted">Note: [Order note]</p></div><strong>[Line total]</strong></article><div class="stack"><div class="field"><label>Customer name</label><input placeholder="Name for the order"></div><div class="grid grid-2"><div class="field"><label>Order type</label><select><option>Dine-In</option><option>Take-Out</option></select></div><div class="field"><label>Table</label><select><option>[Table label]</option></select></div></div><div class="field"><label>Payment method</label><select><option>Cash</option><option>GCash</option><option>Credit Card</option></select></div><p class="row spread"><strong>Total</strong><strong>[Total]</strong></p><button class="button">Create Order</button></div></aside>`;
  let title, desc, content;
  if (code === "S1" || code === "A1") {
    title = code === "A1" ? "Admin login" : "Staff login";
    desc = "Sign in to continue to the counter tools.";
    content = `<section class="prototype-dialog"><div class="row">${icon("lock")}<div><h2>Welcome back</h2><p class="muted">Sign in to your ${code === "A1" ? "admin" : "staff"} account.</p></div></div><div class="stack"><div class="field"><label>Username</label><input autocomplete="username" placeholder="Your username"></div><div class="field"><label>Password</label><div class="row"><input style="flex:1" type="password" autocomplete="current-password" placeholder="Your password"><button type="button" class="button-secondary" aria-label="Show password">Show</button></div></div><button class="button">Sign in</button><div class="banner error">${icon("alert")} Too many attempts, try again in a few minutes.</div></div></section>`;
  } else if (code === "S2") {
    title = "Start your shift"; desc = "Ordering opens only while a staff member is on shift.";
    content = `${prototypeState.shiftStarted ? `<div class="banner success">${icon("check")} Customers will be able to order.</div>` : `<div class="banner info" style="margin-bottom:16px"><strong>Start your shift to open ordering</strong><button class="button" data-action="start-shift">Start Shift</button></div>`}<div class="grid grid-2" style="margin-top:18px">${["Register","Kitchen Queue","Stock","My Sales"].map((name,i)=>`<section class="card" style="${prototypeState.shiftStarted?"":"opacity:.6"}"><h2>${name}</h2><p>${prototypeState.shiftStarted?"Available for this shift.":"Available after you start a shift."}</p><button class="button-secondary" ${prototypeState.shiftStarted?'data-screen="'+["S3","S4","S5","S6"][i]+'"':"disabled"}>${prototypeState.shiftStarted?"Open":"Start shift to continue"}</button></section>`).join("")}</div>`;
  } else if (code === "S3") {
    title = "Register"; desc = "Build a counter order, then create it for the kitchen.";
    content = `<div class="pos-zones"><section class="card"><div class="row spread"><h2 class="section-title">Choose items</h2><span class="fictional-tag">Prototype data (fictional)</span></div><label class="search-wrap">${icon("search")}<input class="search-input" placeholder="Search menu items"></label>${products}</section>${ticket}</div>`;
  } else if (code === "S4") {
    title = "Kitchen queue"; desc = "Move each order forward after the counter confirms payment.";
    const orderCard = (status, action, warning = false) => `<article class="queue-card"><div class="row spread"><strong class="queue-number">#[Order number]</strong><span class="badge ${status.toLowerCase()}">${icon(status==="Paid"?"check":status==="Pending"?"clock":"drink")} ${status}</span></div><strong>[Customer name]</strong><span>[Dine-In · Table label] / [Take-Out]</span><span>Payment: [Cash / GCash / Credit Card]</span><div><strong>[Product name] × [quantity]</strong><p class="muted">[Modifier] · Note: [Order note]</p></div><span class="wait-time ${warning?"warning":""}">${icon("clock")} Waiting [duration] · Amber after [n] minutes</span><button class="button" ${status==="Pending"?'data-action="confirm-payment"':""}>${action}</button></article>`;
    const board = `<div class="row spread" style="margin-bottom:14px"><span class="banner info" role="alert">${icon("alert")} New order alert · ${prototypeState.muted?"sound muted":"audible alert on"}</span><button class="button-secondary" data-action="mute">${prototypeState.muted?"Unmute alerts":"Mute alerts"}</button></div><div class="order-board">${[["Pending","Mark as Paid",true],["Paid","Start Preparing"],["Preparing","Mark as Completed"]].map(([status,action,warn])=>`<section class="queue-column"><h2>${status}<span class="badge neutral">[n]</span></h2>${orderCard(status,action,warn)}</section>`).join("")}</div><details class="prototype-dialog" data-payment-dialog><summary>Mark as Paid confirmation dialog</summary><div><h2 id="payment-confirm-title">Confirm counter payment</h2><p>Confirm payment for [Order number] before moving it to Paid.</p><div class="dialog-actions"><button class="button-secondary">Cancel</button><button class="button">Mark as Paid</button></div></div></details>`;
    const empties = `<div class="order-board">${["Pending","Paid","Preparing"].map((s)=>`<section class="queue-column"><h2>${s}<span class="badge neutral">0</span></h2><div class="empty-state">${icon("check")}<strong>No ${s.toLowerCase()} orders</strong></div></section>`).join("")}</div>`;
    content = variantContent(board, empties, `<div class="order-board">${[0,1,2].map(()=>`<section class="queue-column stack"><div class="skeleton"></div><div class="skeleton" style="height:120px"></div></section>`).join("")}</div>`, `<div class="banner error">${icon("refresh")} Kitchen queue couldn’t load. <button class="button-secondary" data-variant="Default">Retry</button></div>`, `<div class="banner info">Ordering intake is closed. Existing orders can still move through the kitchen.</div>${board}`);
  } else if (code === "S5") {
    title = "Stock"; desc = "Adjust stock with a clear reason for each change.";
    content = `<div class="card stack"><div class="row spread"><label class="search-wrap">${icon("search")}<input class="search-input" placeholder="Search items"></label><label><input type="checkbox"> Low stock</label></div><div class="stock-row"><strong>[Product name]</strong><span>[Quantity]</span><span class="badge pending">${icon("alert")} Low stock</span><div class="qty-stepper"><button>${icon("minus")}</button><output>[n]</output><button>${icon("plus")}</button></div></div><div class="field"><label>Reason for adjustment</label><input placeholder="Required for stock change"></div><button class="button-secondary">Save stock change</button><p class="muted">Last change: [time]</p></div>`;
  } else if (code === "S6") {
    title = "My sales"; desc = "Completed orders for your own account only.";
    content = `<div class="screen-state-controls"><button>Today</button><button>7 days</button><button>30 days</button></div><div class="grid grid-3">${["Orders","Sales","Average order"].map((k)=>`<article class="kpi"><span>${k}</span><strong>[${k==="Orders"?"n":"Amount"}]</strong><small>Completed orders · My account</small></article>`).join("")}</div><div class="grid grid-2" style="margin-top:16px"><section class="card"><h2 class="section-title">Payment methods</h2>${["Cash","GCash","Credit Card"].map(k=>`<p class="row spread"><span>${k}</span><strong>[Amount]</strong></p>`).join("")}</section><section class="card"><h2 class="section-title">Completed orders</h2><p>[Order number] · [Total] · [Payment method]</p></section></div>`;
  } else if (code === "S7") {
    title = "End shift"; desc = "Review open orders before ending your shift.";
    content = `<div class="prototype-dialog"><h2>End your shift?</h2><p>Ending your shift closes customer ordering if you are the only one on shift.</p><section class="card"><h3>Orders still open</h3><p>[Order number] · [Status]</p><p>[Order number] · [Status]</p></section><div class="dialog-actions"><button class="button-secondary">Keep working</button><button class="button-danger">End Shift</button></div></div><div class="banner info">${icon("alert")} Sign out? Your shift is still open. End your shift before signing out.</div>`;
  } else {
    title = "Change password"; desc = "Choose a strong password for your account.";
    content = `<section class="card stack" style="max-width:650px"><div class="field"><label>Current password</label><input type="password"></div><div class="field"><label>New password</label><input type="password" placeholder="At least 12 characters"></div><ul><li>At least 12 characters</li><li>One uppercase letter</li><li>One lowercase letter</li><li>One number or symbol</li></ul><div class="field error"><label>Confirm new password</label><input type="password"><span class="error-text">Passwords do not match.</span></div><button class="button">Change Password</button></section>`;
  }
  if (code === "S1" || code === "A1") return `${head(title, desc, code)}<div class="login-canvas">${content}</div>`;
  return staffShell(code, title, desc, content);
}

const adminGroups = [["Today", [["Overview","home2","A2"],["Orders","order","A3"]]],["Menu", [["Items","box","A4"],["Categories","menu","A5"],["Modifiers","sliders","A6"]]],["People", [["Staff Accounts","people","A7"],["Duty Sessions","clock","A8"]]],["Insights", [["Reports","chart","A9"]]]];
function adminShell(code,title,desc,content) {
  const links = adminGroups.map(([group,items])=>`<div class="admin-group"><p class="admin-group-title">${group}</p>${items.map(([label,ico,key])=>`<a class="admin-link ${key===code?"active":""}" href="#" data-screen="${key}">${icon(ico)}${label}</a>`).join("")}</div>`).join("");
  return `${head(title,desc,code)}<section class="admin-shell"><aside class="admin-sidebar"><div class="admin-brand"><img src="../assets/img/me-kape-cup-logo.png" alt=""><strong>M&amp;E Kape Cup</strong></div>${links}</aside><div class="admin-area"><header class="admin-topbar"><strong>[Admin name]</strong><nav class="admin-top-links"><a href="#" data-screen="C1">${icon("home")} Preview customer site</a><a href="#" data-screen="A1">${icon("signout")} Sign out</a></nav></header><main class="admin-content">${content}</main></div></section>`;
}
function adminScreen(code) {
  const table = (heads, rows=3) => `<div class="table-wrap"><table><thead><tr>${heads.map(h=>`<th>${h}</th>`).join("")}</tr></thead><tbody>${Array.from({length:rows},()=>`<tr>${heads.map((h,i)=>`<td>${h==="Status"?`<span class="badge pending">${icon("clock")} Pending</span>`:h==="Action"?'<button class="button-secondary">View</button>':i===0?"[Order number]":h==="Payment"?"[Payment method]":"[Prototype value]"}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
  const sidePanel = (label, fields) => `<aside class="card stack"><div class="row spread"><h2 class="section-title">${label}</h2><button class="button-text" aria-label="Close">×</button></div>${fields.map(f=>`<div class="field"><label>${f}</label>${f==="Category"?`<select>${categories.map(c=>`<option>${c}</option>`).join("")}</select>`:`<input placeholder="[${f}]">`}</div>`).join("")}<button class="button">Save changes</button></aside>`;
  const titleMap = {A2:"Overview",A3:"Orders",A4:"Menu items",A5:"Categories",A6:"Modifiers",A7:"Staff accounts",A8:"Duty sessions",A9:"Reports"};
  const title = titleMap[code] || "Admin login";
  const descMap = {A2:"A quick view of today’s operations.",A3:"Find an order and move it to the next status.",A4:"Manage availability, stock, and menu details.",A5:"Keep menu categories in the required order.",A6:"Configure the options available for each item.",A7:"Manage staff access and account status.",A8:"Review shift history and open sessions.",A9:"Completed orders only."};
  let content;
  if (code === "A1") return staffScreen("A1");
  if (code === "A2") {
    content = `<div class="grid grid-4">${["Completed sales today","Orders not yet completed","Open duty sessions","Active staff"].map(k=>`<article class="kpi"><span>${k}</span><strong>[${k==="Orders not yet completed"||k==="Open duty sessions"||k==="Active staff"?"n":"Amount"}]</strong><small>${prototypeTag()}</small></article>`).join("")}</div><div class="grid grid-2" style="margin-top:16px"><section class="card"><h2 class="section-title">Needs attention</h2><div class="attention-list"><div class="attention-item">${icon("clock")} Stale open shifts · [n]</div><div class="attention-item">${icon("box")} Low-stock items · [n]</div><div class="attention-item">${icon("alert")} Long-waiting orders · [n]</div></div></section><section class="card"><h2 class="section-title">Latest orders</h2>${table(["Order","Name","Status","Total"],2)}</section></div>`;
  } else if (code === "A3") {
    content = `<div class="row" style="flex-wrap:wrap;margin-bottom:14px"><select class="search-input"><option>All statuses</option><option>Pending</option><option>Paid</option><option>Preparing</option><option>Completed</option></select><input class="search-input" type="date" aria-label="Date"><select class="search-input"><option>All payment methods</option><option>Cash</option><option>GCash</option><option>Credit Card</option></select><input class="search-input" placeholder="Search number or name"></div>${table(["Order","Name","Status","Payment","Date","Action"])}<section class="card stack" style="margin-top:16px"><h2 class="section-title">Order details · [Order number]</h2><span class="badge paid">${icon("check")} Paid</span><p>[Dine-In · Table label] · [Product name] × [quantity] · [Modifiers and notes]</p><button class="button">Start Preparing</button></section>`;
  } else if (code === "A4") {
    content = `<div class="row spread"><div class="row"><input class="search-input" placeholder="Search menu items"><select class="search-input">${categories.map(c=>`<option>${c}</option>`).join("")}</select></div><button class="button">Add item</button></div><div class="row" style="margin:12px 0"><label><input type="checkbox" checked> Active</label><label><input type="checkbox"> Sold out</label></div>${table(["Item","Category","Price","Stock","Active","Action"])}<div style="margin-top:16px">${sidePanel("Add item",["Name","Category","Price","Description","Stock","Threshold"])}</div>`;
  } else if (code === "A5") {
    content = `<section class="card"><p class="muted">Drag to reorder. The required category order is fixed.</p><div class="stack">${categories.map((c,i)=>`<div class="option-row"><span aria-label="Reorder" style="cursor:grab">${icon("menu")}</span><strong>${i+1}. ${c}</strong><button class="button-secondary" disabled title="Category still has items">Delete</button></div>`).join("")}</div><p class="muted">Delete is unavailable while a category still has items.</p></section>`;
  } else if (code === "A6") {
    content = `<div class="field" style="max-width:440px"><label>Choose an item</label><select><option>[Product name]</option></select></div><section class="card stack" style="margin-top:16px"><details open><summary><strong>[Modifier group]</strong> · Required · min [n] / max [n]</summary><div class="stack" style="margin-top:12px"><div class="option-row">[Option] <span class="muted">Price change: [Price]</span></div><div class="option-row">[Option] <span class="muted">Price change: [Price]</span></div><button class="button-secondary">Add option</button></div></details><button class="button-secondary">Add modifier group</button></section>`;
  } else if (code === "A7") {
    content = `<div class="row spread"><h2 class="section-title">Staff accounts</h2><button class="button">Add staff</button></div>${table(["Name","Username","Role","Active status","Actions"])}<div style="margin-top:16px" class="grid grid-2">${sidePanel("Add staff",["Name","Username","Role","Temporary password"])}<section class="card stack"><h2 class="section-title">Confirm account action</h2><p>Deactivate or reset password for [Staff name]?</p><div class="row"><button class="button-danger">Deactivate</button><button class="button-secondary">Reset Password</button></div></section></div>`;
  } else if (code === "A8") {
    content = `<div class="banner" style="margin-bottom:14px">${icon("alert")} Open sessions older than [n] hours are marked “Possibly forgotten”.</div>${table(["Staff","Started","Ended","Duration","Status","Action"])}<section class="prototype-dialog"><h2>Close session?</h2><p>Close the open session for [Staff name]?</p><div class="dialog-actions"><button class="button-secondary">Cancel</button><button class="button">Close session</button></div></section>`;
  } else {
    content = `<div class="row spread" style="flex-wrap:wrap"><div class="row"><label class="field"><span>From</span><input type="date"></label><label class="field"><span>To</span><input type="date"></label></div><button class="button-secondary">Export report</button></div><p class="muted">Completed orders only · ${prototypeTag()}</p><div class="grid grid-3">${["Completed sales","Completed orders","Average order"].map(k=>`<article class="kpi"><span>${k}</span><strong>[${k.includes("orders")?"n":"Amount"}]</strong></article>`).join("")}</div><div class="grid grid-2" style="margin-top:16px"><section class="card"><h2 class="section-title">Sales over time</h2><div class="chart-placeholder">${[28,45,36,66,49,76,54,68,40,59].map(h=>`<span style="--bar-height:${h}%"></span>`).join("")}</div><p class="muted">[Date range]</p></section><section class="card"><h2 class="section-title">Payment-method split</h2>${["Cash","GCash","Credit Card"].map(m=>`<p class="row spread"><span>${m}</span><strong>[Amount] · [share]</strong></p>`).join("")}</section></div><section class="card" style="margin-top:16px"><h2 class="section-title">Top items</h2>${table(["Item","Quantity","Sales"])}</section>`;
  }
  return adminShell(code,title,descMap[code] || "Sign in to manage the cafe.",content);
}

function designSystem() {
  const colors = [["Espresso Brown","#3B1F14","var(--espresso)","white"],["Cream","#F4E6C8","var(--cream)","var(--dark)"],["Caramel Gold","#D9B77E","var(--caramel)","var(--dark)"],["Matcha Green","#78945B","var(--matcha)","white"],["Warm White","#FFFCF6","var(--warm-white)","var(--dark)"],["Dark Text","#29221D","var(--dark)","white"],["Muted Text","#74685E","var(--muted)","white"],["Accessible Red","#A62C22","var(--red)","white"],["Amber","#89520C","var(--amber)","white"],["Neutral gray","#F0EEEA","var(--gray-soft)","var(--dark)"]];
  return `${head("Design system","Shared design language for customer, staff, and admin experiences.","D1")}<section class="stack"><article class="card"><h2 class="section-title">Brand tokens</h2><div class="grid grid-4">${colors.map(([name,hex,bg,color])=>`<div class="design-swatch" style="background:${bg};color:${color}">${name}<br>${hex}</div>`).join("")}</div><p>Four/eight-pixel spacing scale · 12–16 px cards · subtle borders and shadows · system sans for operations · system serif for brand headings.</p></article><article class="card"><h2 class="section-title">Buttons · default, hover, focus, disabled, loading</h2><div class="control-showcase"><button class="button">Primary action</button><button class="button-secondary">Secondary</button><button class="button-text">Text action</button><button class="button-danger">Destructive</button><button class="button" disabled>Disabled</button><button class="button loading">Loading</button></div><p class="muted">Hover a control; keyboard-focus a control to view the high-contrast outline.</p></article><article class="card"><h2 class="section-title">Forms · default, focus, error, disabled</h2><div class="grid grid-3"><div class="field"><label>Default field</label><input placeholder="Enter a value"></div><div class="field error"><label>Error field</label><input value="Invalid value" aria-invalid="true"><span class="error-text">Please check this value.</span></div><div class="field"><label>Disabled field</label><input disabled value="Unavailable"></div></div><div class="field" style="margin-top:14px"><label>Selection</label><select><option>Choose an option</option><option>Available option</option></select></div></article><article class="card"><h2 class="section-title">Status badges · icon + text</h2><div class="control-showcase"><span class="badge pending">${icon("clock")} Pending</span><span class="badge paid">${icon("check")} Paid</span><span class="badge preparing">${icon("drink")} Preparing</span><span class="badge completed">${icon("check")} Completed</span></div></article><article class="card"><h2 class="section-title">Dialogs, banners, tables</h2><div class="grid grid-2"><div class="prototype-dialog"><h2>Confirm action</h2><p>Are you sure you want to continue?</p><div class="dialog-actions"><button class="button-secondary">Cancel</button><button class="button">Confirm</button></div></div><div class="stack"><div class="banner">${icon("info")} Information banner</div><div class="banner success">${icon("check")} Success banner</div><div class="banner error">${icon("alert")} Error banner</div></div></div><div style="margin-top:16px">${tableMarkup()}</div></article><article class="card"><h2 class="section-title">Empty, loading, and error states</h2><div class="grid grid-3"><div class="empty-state">${icon("bag")}<strong>Nothing here yet</strong><span class="muted">Empty state copy</span></div><div class="loading-state"><div class="skeleton" style="width:80%"></div><div class="skeleton" style="width:60%"></div><span>Loading…</span></div><div class="error-state">${icon("refresh")}<strong>We’ll be right back</strong><button class="button-secondary">Retry</button></div></div></article><article class="card"><h2 class="section-title">Status progression</h2><div class="control-showcase"><span class="badge pending">◷ Pending</span><span aria-hidden="true">→</span><span class="badge paid">✓ Paid</span><span aria-hidden="true">→</span><span class="badge preparing">◉ Preparing</span><span aria-hidden="true">→</span><span class="badge completed">✓ Completed</span></div><p class="muted">Forward only. Payment is verified manually at the counter.</p></article></section>`;
}
function tableMarkup() {
  return `<div class="table-wrap"><table><thead><tr><th>Order</th><th>Status</th><th>Payment</th></tr></thead><tbody><tr><td>[Order number]</td><td><span class="badge pending">${icon("clock")} Pending</span></td><td>[Payment method]</td></tr></tbody></table></div>`;
}

function screenTitle(screen) {
  for (const [shell, screens] of Object.entries(sections)) {
    const found = screens.find(([code]) => code === screen);
    if (found) return { shell, code: found[0], title: found[1] };
  }
  return { shell: "customer", code: "C1", title: "Home" };
}
function render() {
  const { shell, code } = screenTitle(prototypeState.screen);
  prototypeState.shell = shell;
  if (!stateOptions[code]) prototypeState.variant = "Default";
  const toolbar = `<header class="prototype-toolbar"><div class="proto-brand"><img src="../assets/img/me-kape-cup-logo.png" alt=""><div><strong>M&amp;E Kape Cup</strong><small>UI/UX prototype · no live orders</small></div></div><nav class="proto-switcher" aria-label="Prototype area">${Object.entries({customer:"Customer",staff:"Staff",admin:"Admin",system:"Design system"}).map(([key,label])=>`<button data-shell="${key}" aria-pressed="${shell===key}">${label}</button>`).join("")}</nav><button class="toolbar-action" data-ordering-toggle aria-pressed="${!prototypeState.orderingOpen}">${icon(prototypeState.orderingOpen?"check":"alert")} Ordering ${prototypeState.orderingOpen?"open":"closed"}</button></header>`;
  const groupNav = Object.entries(sections).map(([group,items])=>`<div class="index-group ${group===shell?"active-group":""}"><p class="index-caption">${group==="system"?"System":group}</p>${items.map(([code,label])=>`<button class="index-item" data-screen="${code}" aria-current="${code===prototypeState.screen?"page":"false"}"><span class="screen-code">${code}</span><span>${label}</span></button>`).join("")}</div>`).join("");
  let page = shell==="customer" ? customerScreen(code) : shell==="staff" ? staffScreen(code) : shell==="admin" ? adminScreen(code) : designSystem();
  root.innerHTML = `<div class="prototype-frame">${toolbar}<div class="prototype-workspace"><aside class="prototype-index" aria-label="Prototype screens">${groupNav}</aside><main class="prototype-main"><div class="prototype-content">${page}</div></main></div></div>`;
  root.querySelectorAll('[aria-label="Close"]').forEach((button) => {
    button.innerHTML = icon("close");
  });
  if (code === "D1") {
    for (const [status, statusIcon] of [["pending","clock"],["paid","check"],["preparing","drink"],["completed","check"]]) {
      root.querySelectorAll(`.badge.${status}`).forEach((badge) => {
        badge.innerHTML = `${icon(statusIcon)} ${status[0].toUpperCase()}${status.slice(1)}`;
      });
    }
    root.querySelectorAll('.control-showcase span[aria-hidden="true"]').forEach((separator) => {
      separator.innerHTML = icon("arrow");
    });
  }
  if (code === "C3") {
    const sheet = root.querySelector(".product-sheet");
    const closeOptions = document.createElement("button");
    closeOptions.type = "button";
    closeOptions.className = "button-text";
    closeOptions.dataset.screen = "C2";
    closeOptions.setAttribute("aria-label", "Close product options");
    closeOptions.textContent = "Close";
    sheet.prepend(closeOptions);
  }
  if (code === "C5") {
    const tableField = [...root.querySelectorAll(".field")].find((field) => field.querySelector("label")?.textContent === "Table");
    if (tableField) tableField.classList.add("table-choice");
    const checkoutPanel = root.querySelector(".customer-shell-main .grid.grid-2 .card:nth-child(2)");
    const totalRow = [...(checkoutPanel?.querySelectorAll("p") || [])].find((row) => row.textContent.includes("Order total"));
    if (checkoutPanel && totalRow) {
      const summaryLine = document.createElement("p");
      summaryLine.className = "row spread";
      summaryLine.innerHTML = "<span>[Product name] × [quantity]</span><strong>[Line total]</strong>";
      checkoutPanel.insertBefore(summaryLine, totalRow);
    }
  }
  if ((code === "S1" || code === "A1")) {
    const showPassword = root.querySelector('[aria-label="Show password"]');
    if (showPassword) showPassword.dataset.action = "show-password";
  }
  if (shell === "admin" && code !== "A1") {
    const topbar = root.querySelector(".admin-topbar");
    const menuToggle = document.createElement("button");
    menuToggle.type = "button";
    menuToggle.className = "admin-menu-toggle";
    menuToggle.dataset.action = "admin-drawer";
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.innerHTML = `${icon("menu")} Menu`;
    topbar.prepend(menuToggle);
  }
  if (code === "S2") {
    const shiftPill = root.querySelector(".shift-pill");
    if (shiftPill) shiftPill.innerHTML = `${icon(prototypeState.shiftStarted ? "check" : "alert")} SHIFT STATUS · ${prototypeState.shiftStarted ? "On shift since [time]" : "No active shift"}`;
    if (!prototypeState.shiftStarted) {
      root.querySelector(".staff-topbar .button-secondary").disabled = true;
      root.querySelectorAll(".staff-rail a").forEach((link) => {
        link.setAttribute("aria-disabled", "true");
        link.tabIndex = -1;
      });
    }
  }
  if (!prototypeState.orderingOpen && shell === "customer") {
    root.querySelectorAll('[data-action="add"]').forEach((button) => {
      button.disabled = true;
      button.textContent = "Ordering closed";
    });
    const placeOrder = root.querySelector('.customer-shell [data-screen="C6"]');
    if (placeOrder) {
      placeOrder.disabled = true;
      placeOrder.textContent = "Ordering closed";
    }
  }
  if (code === "C5") {
    const tableChoice = root.querySelector(".table-choice");
    const orderTypeInputs = root.querySelectorAll('input[name="order-type"]');
    if (tableChoice && orderTypeInputs.length > 1) tableChoice.hidden = !orderTypeInputs[0].checked;
  }
}
root.addEventListener("click", (event) => {
  const button = event.target.closest("button, a");
  if (!button) return;
  const targetScreen = button.dataset.screen;
  const shell = button.dataset.shell;
  const variant = button.dataset.variant;
  if (button.hasAttribute("data-ordering-toggle")) {
    prototypeState.orderingOpen = !prototypeState.orderingOpen;
    prototypeState.shiftStarted = prototypeState.orderingOpen;
    render();
  } else if (shell) {
    prototypeState.shell = shell;
    prototypeState.screen = sections[shell][0][0];
    prototypeState.variant = "Default";
    render();
  } else if (targetScreen) {
    event.preventDefault();
    prototypeState.screen = targetScreen;
    prototypeState.variant = "Default";
    if (targetScreen === "S2") {
      prototypeState.shiftStarted = false;
      prototypeState.orderingOpen = false;
    }
    render();
  } else if (variant) {
    prototypeState.variant = variant;
    if (prototypeState.screen === "C4" && variant === "With items") prototypeState.cartCount = Math.max(1, prototypeState.cartCount);
    if (prototypeState.screen === "C4" && variant === "Empty") prototypeState.cartCount = 0;
    render();
  } else if (button.dataset.action === "add") {
    if (!prototypeState.orderingOpen) return;
    if (prototypeState.shell === "customer" && prototypeState.screen === "C3" && !root.querySelector('input[name="size"]:checked')) {
      const validation = root.querySelector("[data-options-error]");
      if (validation) validation.hidden = false;
      root.querySelector('input[name="size"]')?.focus();
      return;
    }
    if (prototypeState.shell === "staff") prototypeState.ticketCount += 1;
    else prototypeState.cartCount += 1;
    if (prototypeState.shell === "customer" && prototypeState.screen === "C3") prototypeState.screen = "C2";
    render();
  } else if (button.dataset.action === "options") {
    prototypeState.screen = "C3";
    render();
  } else if (button.dataset.action === "start-shift") {
    prototypeState.shiftStarted = true;
    prototypeState.orderingOpen = true;
    render();
  } else if (button.dataset.action === "track") {
    const message = root.querySelector(".banner.error");
    if (message) message.scrollIntoView({ behavior: "smooth", block: "center" });
  } else if (button.dataset.action === "mute") {
    prototypeState.muted = !prototypeState.muted;
    render();
  } else if (button.dataset.action === "confirm-payment") {
    const dialog = root.querySelector("[data-payment-dialog]");
    if (dialog) {
      dialog.open = true;
      dialog.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  } else if (button.dataset.action === "show-password") {
    const password = button.previousElementSibling;
    if (password) password.type = password.type === "password" ? "text" : "password";
    button.textContent = password?.type === "text" ? "Hide" : "Show";
  } else if (button.dataset.action === "admin-drawer") {
    const adminShell = root.querySelector(".admin-shell");
    const open = adminShell.classList.toggle("drawer-open");
    button.setAttribute("aria-expanded", String(open));
  } else if (button.dataset.chip) {
    prototypeState.activeCategory = button.dataset.chip;
    render();
  } else if (button.dataset.category) {
    prototypeState.activeCategory = categories[Number(button.dataset.category)-1];
    prototypeState.screen = "C2";
    render();
  }
});
root.addEventListener("change", (event) => {
  if (event.target.name === "order-type") {
    const tableChoice = root.querySelector(".table-choice");
    const dineIn = root.querySelector('input[name="order-type"]:checked')?.parentElement?.textContent.includes("Dine-In");
    if (tableChoice) tableChoice.hidden = !dineIn;
  }
  if (event.target.name === "size") {
    const validation = root.querySelector("[data-options-error]");
    if (validation) validation.hidden = true;
  }
});
render();
