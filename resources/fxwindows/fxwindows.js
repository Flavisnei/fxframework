/* FX Windows 1.2.3 | JavaScript puro | Distribuicao para navegador */
(function () {
'use strict';
const DEFAULTS = Object.freeze({
  closeOnBackdrop: false,
  closeOnDoubleEscape: true,
  escapeInterval: 2000,
  minWidth: 280,
  minHeight: 180,
  margin: 12,
  updateHash: true,
  homeHash: "home"
});

const focusableSelector = [
  "button:not([disabled])", "[href]", "input:not([disabled]):not([type='hidden'])",
  "select:not([disabled])", "textarea:not([disabled])", "[tabindex]:not([tabindex='-1'])"
].join(",");

const numberOr = (value, fallback) => {
  const parsed = Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const safeId = (value) => String(value || `fxw-${Date.now()}`)
  .trim().replace(/[^a-zA-Z0-9_-]/g, "-");

class FxWindowManager extends EventTarget {
  constructor(options = {}) {
    super();
    this.options = { ...DEFAULTS, ...options };
    this.windows = new Map();
    this.levels = new Map();
    this.zIndex = 10000;
    this.lastEscapeAt = 0;
    this.started = false;
    this.dragState = null;
    this.resizeState = null;
    this.boundKeydown = this.onKeydown.bind(this);
    this.boundClick = this.onDocumentClick.bind(this);
    this.boundHash = this.openFromHash.bind(this);
    this.boundMessage = this.onMessage.bind(this);
    this.boundPointerMove = this.onPointerMove.bind(this);
    this.boundPointerUp = this.onPointerUp.bind(this);
  }

  configure(options = {}) {
    Object.assign(this.options, options);
    this.syncBackdrop();
    return this;
  }

  start() {
    if (this.started) return this;
    this.started = true;
    document.addEventListener("keydown", this.boundKeydown, true);
    document.addEventListener("click", this.boundClick);
    window.addEventListener("hashchange", this.boundHash);
    window.addEventListener("message", this.boundMessage);
    window.addEventListener("resize", () => this.fitAll());
    window.addEventListener("pointermove", this.boundPointerMove);
    window.addEventListener("pointerup", this.boundPointerUp);
    window.setTimeout(() => this.openFromHash(), 0);
    return this;
  }

  stop() {
    document.removeEventListener("keydown", this.boundKeydown, true);
    document.removeEventListener("click", this.boundClick);
    window.removeEventListener("hashchange", this.boundHash);
    window.removeEventListener("message", this.boundMessage);
    window.removeEventListener("pointermove", this.boundPointerMove);
    window.removeEventListener("pointerup", this.boundPointerUp);
    this.started = false;
    return this;
  }

  open(input = {}) {
    const options = this.normalizeOptions(input);
    let record = this.windows.get(options.id);
    if (!record) {
      record = this.createWindow(options);
      this.windows.set(options.id, record);
    } else {
      record.options = { ...record.options, ...options };
      this.applyContent(record);
    }

    this.levels.set(options.level, options.id);
    record.opener = options.opener || document.activeElement;
    record.element.hidden = false;
    record.element.classList.remove("fxw-closing", "fxw-minimized");
    record.element.classList.add("fxw-opening");
    record.state = "open";
    this.setTitle(options.id, options.title);
    this.setSize(options.id, options.width, options.height);
    this.center(options.id);
    this.bringToFront(options.id);
    this.syncBackdrop();
    if (this.options.updateHash && options.hash !== false) this.setHash(options.hashName || options.id);
    requestAnimationFrame(() => record.element.classList.remove("fxw-opening"));
    this.focusWhenReady(record);
    this.emit("open", record);
    return this.publicRecord(record);
  }

  close(id = this.activeId(), { restorePrevious = true, updateHash = true } = {}) {
    const record = this.resolve(id);
    if (!record || record.state === "closed") return false;
    record.element.classList.add("fxw-closing");
    record.state = "closed";
    window.setTimeout(() => { record.element.hidden = true; }, 140);
    if (record.options.unloadOnClose && record.iframe) record.iframe.src = "about:blank";
    if (this.levels.get(record.options.level) === record.options.id) this.levels.delete(record.options.level);
    if (restorePrevious) this.restorePrevious(record.options.level);
    const nextActive = this.activeId();
    if (nextActive) this.bringToFront(nextActive);
    this.syncBackdrop();
    if (updateHash && this.options.updateHash) this.setHash(this.activeId() || this.options.homeHash);
    if (record.opener?.isConnected) record.opener.focus({ preventScroll: true });
    this.reflowMinimized();
    this.emit("close", record);
    return true;
  }

  closeLevel(level) {
    const id = this.levels.get(Number(level));
    return id ? this.close(id) : false;
  }

  closeAll() {
    [...this.windows.values()]
      .filter((record) => record.state !== "closed")
      .sort((a, b) => b.options.level - a.options.level)
      .forEach((record) => this.close(record.options.id, { restorePrevious: false, updateHash: false }));
    this.levels.clear();
    this.syncBackdrop();
    if (this.options.updateHash) this.setHash(this.options.homeHash);
  }

  show(id) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.hidden = false;
    record.state = "open";
    this.levels.set(record.options.level, record.options.id);
    this.bringToFront(id);
    this.syncBackdrop();
    return true;
  }

  hide(id) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.hidden = true;
    record.state = "hidden";
    const nextActive = this.activeId();
    if (nextActive) this.bringToFront(nextActive);
    this.syncBackdrop();
    this.reflowMinimized();
    return true;
  }

  destroy(id) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.remove();
    this.windows.delete(record.options.id);
    if (this.levels.get(record.options.level) === record.options.id) this.levels.delete(record.options.level);
    this.syncBackdrop();
    this.reflowMinimized();
    return true;
  }

  minimize(id = this.activeId()) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.classList.add("fxw-minimized");
    record.element.classList.remove("fxw-maximized");
    record.title.textContent = this.abbreviateTitle(record.options.title);
    const minimizeButton = record.element.querySelector('[data-fxw-action="minimize"]');
    if (minimizeButton) {
      minimizeButton.dataset.fxwAction = "restore";
      minimizeButton.textContent = "↗";
      minimizeButton.title = "Restaurar";
      minimizeButton.setAttribute("aria-label", "Restaurar");
    }
    record.state = "minimized";
    this.reflowMinimized();
    this.emit("minimize", record);
    return true;
  }

  restore(id = this.activeId()) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.classList.remove("fxw-minimized", "fxw-maximized");
    record.title.textContent = record.options.title;
    const restoreButton = record.element.querySelector('[data-fxw-action="restore"]');
    if (restoreButton) {
      restoreButton.dataset.fxwAction = "minimize";
      restoreButton.textContent = "−";
      restoreButton.title = "Minimizar";
      restoreButton.setAttribute("aria-label", "Minimizar");
    }
    record.state = "open";
    record.element.hidden = false;
    this.bringToFront(id);
    this.reflowMinimized();
    this.emit("restore", record);
    return true;
  }

  maximize(id = this.activeId()) {
    const record = this.resolve(id);
    if (!record) return false;
    if (record.state === "minimized") this.restore(id);
    record.element.classList.toggle("fxw-maximized");
    record.title.textContent = record.options.title;
    record.state = record.element.classList.contains("fxw-maximized") ? "maximized" : "open";
    this.emit("maximize", record);
    return true;
  }

  setTitle(id, title = "Janela") {
    const record = this.resolve(id);
    if (!record) return false;
    record.options.title = String(title);
    record.title.textContent = record.state === "minimized" ? this.abbreviateTitle(record.options.title) : record.options.title;
    record.title.title = record.options.title;
    record.element.setAttribute("aria-label", record.options.title);
    return true;
  }

  setSize(id, width, height) {
    const record = this.resolve(id);
    if (!record) return false;
    const maxWidth = window.innerWidth - this.options.margin * 2;
    const maxHeight = window.innerHeight - this.options.margin * 2;
    const desiredWidth = width === 1 || width === "1" ? maxWidth * 0.95 : numberOr(width, 900);
    const desiredHeight = height === 1 || height === "1" ? maxHeight * 0.9 : numberOr(height, 600);
    record.element.style.width = `${Math.min(maxWidth, Math.max(this.options.minWidth, desiredWidth))}px`;
    record.element.style.height = `${Math.min(maxHeight, Math.max(this.options.minHeight, desiredHeight))}px`;
    return true;
  }

  moveTo(id, x, y) {
    const record = this.resolve(id);
    if (!record) return false;
    const maxX = Math.max(this.options.margin, window.innerWidth - record.element.offsetWidth - this.options.margin);
    const maxY = Math.max(this.options.margin, window.innerHeight - record.element.offsetHeight - this.options.margin);
    record.element.style.left = `${Math.min(maxX, Math.max(this.options.margin, numberOr(x, this.options.margin)))}px`;
    record.element.style.top = `${Math.min(maxY, Math.max(this.options.margin, numberOr(y, this.options.margin)))}px`;
    record.element.style.transform = "none";
    return true;
  }

  center(id) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.style.left = "50%";
    record.element.style.top = "50%";
    record.element.style.transform = "translate(-50%, -50%)";
    return true;
  }

  reload(id = this.activeId()) {
    const record = this.resolve(id);
    if (!record?.iframe) return false;
    record.iframe.src = record.iframe.src;
    return true;
  }

  focus(id = this.activeId(), selector) {
    const record = this.resolve(id);
    if (!record) return false;
    return this.focusRecord(record, selector || record.options.focus);
  }

  get(id) {
    const record = this.resolve(id);
    return record ? this.publicRecord(record) : null;
  }

  list() {
    return [...this.windows.values()].map((record) => this.publicRecord(record));
  }

  activeId() {
    const visible = [...this.windows.values()].filter((record) => !record.element.hidden && record.state !== "closed");
    visible.sort((a, b) => numberOr(b.element.style.zIndex, 0) - numberOr(a.element.style.zIndex, 0));
    return visible[0]?.options.id || null;
  }

  openFromHash() {
    const hash = decodeURIComponent(location.hash.slice(1));
    if (!hash || hash === this.options.homeHash) return false;
    const trigger = document.getElementById(hash);
    if (!trigger || !trigger.matches(".fxjanelas, [data-fx-window]")) return false;
    this.open(this.optionsFromElement(trigger));
    return true;
  }

  optionsFromElement(element) {
    const templateSelector = element.dataset.windowTemplate;
    const template = templateSelector ? document.querySelector(templateSelector) : null;
    const templateContent = template instanceof HTMLTemplateElement ? template.content.cloneNode(true) : null;
    return {
      id: element.dataset.windowId || `${element.id || safeId(element.textContent)}3`,
      hashName: element.id,
      url: element.dataset.windowUrl || element.getAttribute("dl") || element.getAttribute("href"),
      element: templateContent,
      title: element.dataset.windowTitle || element.getAttribute("dd") || element.title || "Janela",
      width: element.dataset.windowWidth || element.getAttribute("dw") || 1,
      height: element.dataset.windowHeight || element.getAttribute("dh") || 1,
      level: element.dataset.windowLevel || element.getAttribute("ja") || 1,
      focus: element.dataset.windowFocus || null,
      opener: element
    };
  }

  normalizeOptions(input) {
    const id = safeId(input.id || input.name);
    return {
      id,
      title: input.title || "Janela",
      url: input.url || "about:blank",
      html: input.html,
      element: input.element,
      width: input.width ?? 900,
      height: input.height ?? 600,
      level: Math.max(1, Number.parseInt(input.level ?? 1, 10) || 1),
      focus: input.focus || null,
      hash: input.hash,
      hashName: input.hashName,
      opener: input.opener,
      resizable: input.resizable !== false,
      minimizable: input.minimizable !== false,
      maximizable: input.maximizable !== false,
      unloadOnClose: input.unloadOnClose === true,
      className: input.className || ""
    };
  }

  createWindow(options) {
    const element = document.createElement("section");
    element.id = options.id;
    element.className = `fxw-window ${options.className}`.trim();
    element.hidden = true;
    element.setAttribute("role", "dialog");
    element.setAttribute("aria-modal", "true");
    element.tabIndex = -1;
    element.innerHTML = `
      <header class="fxw-header">
        <h2 class="fxw-title"></h2>
        <div class="fxw-controls">
          <label class="fxw-selection-lock" title="Bloquear seleção de texto fora dos formulários">
            <input type="checkbox" data-fxw-selection-lock checked aria-label="Bloquear seleção de texto">
            <span aria-hidden="true">Seleção</span>
          </label>
          <button type="button" data-fxw-action="minimize" aria-label="Minimizar" title="Minimizar">&#8722;</button>
          <button type="button" data-fxw-action="maximize" aria-label="Maximizar" title="Maximizar">&#9633;</button>
          <button type="button" data-fxw-action="close" aria-label="Fechar" title="Fechar">&#215;</button>
        </div>
      </header>
      <div class="fxw-content"></div>
      <div class="fxw-resize" aria-hidden="true"></div>`;
    document.body.append(element);
    const record = {
      options, element, title: element.querySelector(".fxw-title"),
      content: element.querySelector(".fxw-content"), iframe: null,
      opener: null, state: "closed"
    };
    element.querySelector('[data-fxw-action="minimize"]').hidden = !options.minimizable;
    element.querySelector('[data-fxw-action="maximize"]').hidden = !options.maximizable;
    element.querySelector(".fxw-resize").hidden = !options.resizable;
    element.addEventListener("pointerdown", () => this.bringToFront(options.id));
    record.content.addEventListener("click", (event) => {
      if (event.target.closest("input,textarea,select,button,a,label,[contenteditable='true'],.fx-select2-panel")) return;
      this.focusRecord(record, record.options.focus);
    });
    element.querySelector(".fxw-title").addEventListener("dblclick", (event) => {
      if (record.state !== "minimized") return;
      event.preventDefault();
      event.stopPropagation();
      this.restore(record.options.id);
    });
    element.classList.add("fxw-selection-locked");
    element.querySelector("[data-fxw-selection-lock]").addEventListener("change", (event) => {
      element.classList.toggle("fxw-selection-locked", event.currentTarget.checked);
      this.applySelectionLock(record, event.currentTarget.checked);
    });
    element.querySelector(".fxw-controls").addEventListener("click", (event) => {
      const action = event.target.closest("button")?.dataset.fxwAction;
      if (action && typeof this[action] === "function") this[action](options.id);
    });
    element.querySelector(".fxw-header").addEventListener("pointerdown", (event) => this.beginDrag(event, record));
    element.querySelector(".fxw-resize").addEventListener("pointerdown", (event) => this.beginResize(event, record));
    this.applyContent(record);
    return record;
  }

  applyContent(record) {
    const { options, content } = record;
    if (options.html !== undefined) {
      content.innerHTML = String(options.html);
      record.iframe = null;
    } else if (options.element) {
      content.replaceChildren(options.element);
      record.iframe = null;
    } else {
      let iframe = content.querySelector("iframe");
      if (!iframe) {
        iframe = document.createElement("iframe");
        iframe.className = "fxw-iframe";
        iframe.title = options.title;
        content.replaceChildren(iframe);
      }
      record.iframe = iframe;
      if (iframe.getAttribute("src") !== options.url) iframe.src = options.url;
      iframe.addEventListener("load", () => this.attachIframe(record), { once: true });
    }
  }

  attachIframe(record) {
    try {
      const frameDocument = record.iframe.contentDocument;
      if (!frameDocument) throw new DOMException("Documento não acessível", "SecurityError");
      frameDocument.addEventListener("keydown", this.boundKeydown, true);
      frameDocument.addEventListener("pointerdown", () => this.bringToFront(record.options.id), true);
      frameDocument.addEventListener("focusin", () => this.bringToFront(record.options.id), true);
      this.applySelectionLock(record, record.element.querySelector("[data-fxw-selection-lock]").checked);
      this.focusRecord(record, record.options.focus);
    } catch (_) {
      record.iframe.focus();
    }
    this.emit("load", record);
  }

  focusWhenReady(record) {
    if (!record.iframe) requestAnimationFrame(() => this.focusRecord(record, record.options.focus));
    else {
      try {
        if (record.iframe.contentWindow?.location.origin === window.location.origin && record.iframe.contentDocument?.readyState === "complete") this.attachIframe(record);
        else record.iframe.focus({ preventScroll: true });
      } catch (_) { record.iframe.focus({ preventScroll: true }); }
    }
  }

  focusRecord(record, selector) {
    let target = null;
    try {
      if (record.iframe && record.iframe.contentWindow?.location.origin !== window.location.origin) throw new DOMException("Origem diferente", "SecurityError");
      const root = record.iframe?.contentDocument || record.content;
      target = (selector && root.querySelector(selector)) || root.querySelector("[autofocus]") || root.querySelector(focusableSelector);
    } catch (_) {
      target = record.iframe;
    }
    (target || record.element).focus({ preventScroll: true });
    return Boolean(target);
  }

  applySelectionLock(record, locked) {
    try {
      record.iframe?.contentDocument?.body?.classList.toggle("fxw-document-selection-locked", locked);
    } catch (_) { /* iframe externo: usa postMessage quando o módulo também estiver na página filha */ }
    record.iframe?.contentWindow?.postMessage({ type: "fxwindows:selection-lock", locked }, "*");
  }

  onDocumentClick(event) {
    const trigger = event.target.closest(".fxjanelas, [data-fx-window]");
    if (!trigger) return;
    event.preventDefault();
    const options = this.optionsFromElement(trigger);
    if (options.hashName && this.options.updateHash) this.setHash(options.hashName);
    this.open(options);
  }

  onMessage(event) {
    if (event.data?.type !== "fxwindows:focus") return;
    const record = [...this.windows.values()].find((item) => item.iframe?.contentWindow === event.source);
    if (record) this.bringToFront(record.options.id);
  }

  onKeydown(event) {
    if (event.altKey && event.code === "Digit1") {
      const id = this.activeId();
      if (id) { event.preventDefault(); this.focus(id); }
      return;
    }
    if (event.key !== "Escape" || !this.options.closeOnDoubleEscape) return;
    const now = performance.now();
    if (now - this.lastEscapeAt <= this.options.escapeInterval) {
      this.lastEscapeAt = 0;
      const id = this.activeId();
      if (id) {
        event.preventDefault();
        this.close(id);
      }
    } else {
      this.lastEscapeAt = now;
    }
  }

  beginDrag(event, record) {
    if (event.button !== 0 || event.target.closest("button") || record.state === "maximized" || record.state === "minimized" || window.matchMedia("(max-width: 640px)").matches) return;
    const rect = record.element.getBoundingClientRect();
    this.dragState = { record, offsetX: event.clientX - rect.left, offsetY: event.clientY - rect.top };
    record.element.style.transform = "none";
    event.preventDefault();
  }

  beginResize(event, record) {
    if (event.button !== 0 || record.state === "maximized") return;
    const rect = record.element.getBoundingClientRect();
    this.resizeState = { record, startX: event.clientX, startY: event.clientY, width: rect.width, height: rect.height };
    event.preventDefault();
    event.stopPropagation();
  }

  onPointerMove(event) {
    if (this.dragState) this.moveTo(this.dragState.record.options.id, event.clientX - this.dragState.offsetX, event.clientY - this.dragState.offsetY);
    if (this.resizeState) {
      const state = this.resizeState;
      this.setSize(state.record.options.id, state.width + event.clientX - state.startX, state.height + event.clientY - state.startY);
    }
  }

  onPointerUp() {
    this.dragState = null;
    this.resizeState = null;
  }

  bringToFront(id) {
    const record = this.resolve(id);
    if (!record) return false;
    record.element.style.zIndex = String(++this.zIndex);
    for (const item of this.windows.values()) {
      const visible = !item.element.hidden && item.state !== "closed";
      item.element.classList.toggle("fxw-active", visible && item === record);
      item.element.classList.toggle("fxw-inactive", visible && item !== record);
      item.element.setAttribute("aria-modal", String(item === record));
    }
    return true;
  }

  restorePrevious(closedLevel) {
    const candidates = [...this.windows.values()]
      .filter((record) => record.options.level < closedLevel && record.state !== "closed")
      .sort((a, b) => b.options.level - a.options.level);
    if (candidates[0]) this.show(candidates[0].options.id);
  }

  fitAll() {
    for (const record of this.windows.values()) {
      if (record.element.hidden || record.state === "maximized") continue;
      if (window.matchMedia("(max-width: 640px)").matches) {
        record.element.style.removeProperty("left");
        record.element.style.removeProperty("top");
        record.element.style.removeProperty("width");
        record.element.style.removeProperty("height");
        record.element.style.removeProperty("transform");
        continue;
      }
      const rect = record.element.getBoundingClientRect();
      this.setSize(record.options.id, Math.min(rect.width, window.innerWidth - 24), Math.min(rect.height, window.innerHeight - 24));
      if (rect.right > window.innerWidth || rect.bottom > window.innerHeight) this.center(record.options.id);
    }
    this.reflowMinimized();
  }

  reflowMinimized() {
    const minimized = [...this.windows.values()]
      .filter((record) => record.state === "minimized" && !record.element.hidden)
      .sort((a, b) => numberOr(a.element.style.zIndex, 0) - numberOr(b.element.style.zIndex, 0));
    const itemHeight = 56;
    const itemWidth = Math.min(320, window.innerWidth - 24) + 8;
    const rows = Math.max(1, Math.floor((window.innerHeight - 24) / itemHeight));
    minimized.forEach((record, index) => {
      const row = index % rows;
      const column = Math.floor(index / rows);
      record.element.style.setProperty("--fxw-minimized-bottom", `${12 + row * itemHeight}px`);
      record.element.style.setProperty("--fxw-minimized-left", `${12 + column * itemWidth}px`);
    });
  }

  ensureBackdrop() {
    return null;
  }

  syncBackdrop() {
    return null;
  }

  setHash(value) {
    if (!value || location.hash.slice(1) === value) return;
    history.replaceState(history.state, "", `#${encodeURIComponent(value)}`);
  }

  abbreviateTitle(title, maximum = 22) {
    const value = String(title || "Janela").trim();
    return value.length > maximum ? `${value.slice(0, maximum - 1).trimEnd()}…` : value;
  }

  resolve(id) {
    if (!id) return null;
    if (typeof id === "object" && id.options) return id;
    return this.windows.get(String(id)) || this.windows.get(this.levels.get(Number(id)));
  }

  publicRecord(record) {
    return Object.freeze({ id: record.options.id, level: record.options.level, state: record.state, element: record.element, iframe: record.iframe });
  }

  emit(name, record) {
    this.dispatchEvent(new CustomEvent(`fxwindow:${name}`, { detail: this.publicRecord(record) }));
  }
}

const FxWindows = new FxWindowManager();


const visibleEnabled = (element) => !element.disabled && element.tabIndex !== -1 && element.getClientRects().length > 0;

class FxFormNavigation {
  constructor(options = {}) {
    this.options = {
      nextSelector: ".pula",
      uppercaseSelector: ".tudomaisculo",
      root: document,
      ...options
    };
    this.onKeydown = this.onKeydown.bind(this);
    this.onInput = this.onInput.bind(this);
  }

  start() {
    this.options.root.addEventListener("keydown", this.onKeydown, true);
    this.options.root.addEventListener("input", this.onInput);
    this.options.root.addEventListener("blur", this.onInput, true);
    return this;
  }

  stop() {
    this.options.root.removeEventListener("keydown", this.onKeydown, true);
    this.options.root.removeEventListener("input", this.onInput);
    this.options.root.removeEventListener("blur", this.onInput, true);
    return this;
  }

  onKeydown(event) {
    if (event.altKey && (event.code === "KeyS" || event.key?.toLowerCase() === "s")) {
      const form = event.target.form || this.options.root.querySelector("form");
      const submit = form?.querySelector("[type='submit'], #enviar");
      if (submit) {
        event.preventDefault();
        if (!submit.disabled) submit.focus();
        else form.querySelector(":invalid")?.focus();
      }
      return;
    }
    if (event.altKey && event.code === "Digit1") {
      const form = event.target.form || this.options.root.querySelector("form");
      const first = form?.querySelector("input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])");
      if (first) {
        event.preventDefault();
        first.focus();
        if (typeof first.select === "function" && first.matches("input:not([type='checkbox']):not([type='radio'])")) first.select();
      }
      return;
    }
    const current = event.target.closest?.(this.options.nextSelector);
    if (!current || event.key !== "Enter" || event.shiftKey || event.ctrlKey || event.altKey || event.isComposing) return;
    if (current.matches(".fx-select2-button")) {
      event.preventDefault();
      current.click();
      return;
    }
    if (current.matches("textarea, [contenteditable='true']")) return;
    const scope = current.form || this.options.root;
    const fields = [...new Set([...scope.querySelectorAll(this.options.nextSelector)].map((field) => {
      if (field instanceof HTMLSelectElement && field.dataset.fxSelect2Ready) return field.closest(".fx-select2")?.querySelector(".fx-select2-button");
      return field;
    }).filter(Boolean))].filter(visibleEnabled);
    const next = fields[fields.indexOf(current) + 1];
    if (!next) return;
    event.preventDefault();
    next.focus();
    if (typeof next.select === "function" && next.matches("input:not([type='checkbox']):not([type='radio'])")) next.select();
  }

  onInput(event) {
    const field = event.target.closest?.(this.options.uppercaseSelector);
    if (!field || !(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
    if (field.matches("[type='password'], [type='email'], [type='url'], [type='number']")) return;
    const start = field.selectionStart;
    const end = field.selectionEnd;
    const upper = field.value.toLocaleUpperCase("pt-BR");
    if (upper === field.value) return;
    field.value = upper;
    if (start !== null) field.setSelectionRange(start, end);
  }
}

class FxFormValidator {
  constructor(form, options = {}) {
    this.form = typeof form === "string" ? document.querySelector(form) : form;
    if (!(this.form instanceof HTMLFormElement)) throw new TypeError("FxFormValidator requer um formulário válido.");
    this.options = { submitSelector: "[type='submit'], #enviar", indicatorPrefix: "_", ...options };
    this.onChange = this.onChange.bind(this);
    this.onSubmit = this.onSubmit.bind(this);
  }

  start() {
    this.form.addEventListener("input", this.onChange);
    this.form.addEventListener("change", this.onChange);
    this.form.addEventListener("blur", this.onChange, true);
    this.form.addEventListener("submit", this.onSubmit);
    this.validateAll(false);
    return this;
  }

  stop() {
    this.form.removeEventListener("input", this.onChange);
    this.form.removeEventListener("change", this.onChange);
    this.form.removeEventListener("blur", this.onChange, true);
    this.form.removeEventListener("submit", this.onSubmit);
    return this;
  }

  onChange(event) {
    if (event.target instanceof HTMLElement && event.target.matches("input, select, textarea")) this.validateField(event.target, true);
    this.updateSubmit();
  }

  onSubmit(event) {
    if (this.validateAll(true)) return;
    event.preventDefault();
    this.form.querySelector(":invalid")?.focus();
  }

  validateField(field, touched = true) {
    if (field.disabled || !field.willValidate) return true;
    const valid = field.checkValidity();
    field.classList.toggle("is-valid", touched && valid);
    field.classList.toggle("is-invalid", touched && !valid);
    field.setAttribute("aria-invalid", String(!valid));
    const indicator = field.id ? document.getElementById(`${this.options.indicatorPrefix}${field.id}`) : null;
    if (indicator) {
      indicator.classList.toggle("is-valid", valid);
      indicator.classList.toggle("is-invalid", !valid);
      indicator.dataset.validationMessage = valid ? "" : field.validationMessage;
    }
    return valid;
  }

  validateAll(touched = true) {
    [...this.form.elements].forEach((field) => {
      if (field instanceof HTMLElement && field.matches("input, select, textarea")) this.validateField(field, touched);
    });
    return this.updateSubmit();
  }

  updateSubmit() {
    const valid = this.form.checkValidity();
    this.form.querySelectorAll(this.options.submitSelector).forEach((button) => { button.disabled = !valid; });
    return valid;
  }
}

class FxSelect2 {
  constructor(select) {
    this.select = select;
    if (!(select instanceof HTMLSelectElement)) throw new TypeError("FxSelect2 requer um elemento select.");
  }

  start() {
    if (this.select.dataset.fxSelect2Ready || this.select.multiple) return this;
    this.select.dataset.fxSelect2Ready = "true";
    this.wrapper = document.createElement("div");
    this.wrapper.className = "fx-select2";
    this.button = document.createElement("button");
    this.button.type = "button";
    this.button.className = "fx-select2-button pula";
    this.button.setAttribute("aria-haspopup", "listbox");
    this.clear = document.createElement("button");
    this.clear.type = "button";
    this.clear.className = "select2-selection__clear";
    this.clear.textContent = "×";
    this.clear.title = "Limpar seleção";
    this.clear.setAttribute("aria-label", "Limpar seleção");
    this.panel = document.createElement("div");
    this.panel.className = "fx-select2-panel";
    this.panel.hidden = true;
    this.search = document.createElement("input");
    this.search.type = "search";
    this.search.className = "fx-select2-search";
    this.search.placeholder = "Pesquisar...";
    this.list = document.createElement("div");
    this.list.className = "fx-select2-options";
    this.list.setAttribute("role", "listbox");
    this.panel.append(this.search, this.list);
    this.select.before(this.wrapper);
    this.wrapper.append(this.select, this.button, this.clear, this.panel);
    this.select.classList.add("fx-select2-native");
    this.render();
    this.button.addEventListener("click", () => this.toggle());
    this.button.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        if (this.panel.hidden) this.open();
        this.moveHighlight(event.key === "ArrowDown" ? 1 : -1);
      }
    });
    this.clear.addEventListener("click", () => {
      const emptyOption = [...this.select.options].find((option) => option.value === "");
      this.select.value = emptyOption ? "" : this.select.options[0]?.value || "";
      this.select.dispatchEvent(new Event("change", { bubbles: true }));
      this.sync();
      this.button.focus();
    });
    this.search.addEventListener("input", () => this.render(this.search.value));
    this.search.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        this.moveHighlight(event.key === "ArrowDown" ? 1 : -1);
      } else if (event.key === "Enter") {
        event.preventDefault();
        this.chooseHighlighted();
      } else if (event.key === "Escape") {
        event.preventDefault();
        this.close();
        this.button.focus();
      }
    });
    this.list.addEventListener("click", (event) => {
      const option = event.target.closest("button[data-value]");
      if (!option) return;
      this.selectValue(option.dataset.value);
    });
    this.select.addEventListener("change", () => this.sync());
    document.addEventListener("pointerdown", (event) => { if (!this.wrapper.contains(event.target)) this.close(); });
    return this;
  }

  render(filter = "") {
    const term = filter.toLocaleLowerCase("pt-BR");
    const buttons = [...this.select.options]
      .filter((option) => option.text.toLocaleLowerCase("pt-BR").includes(term))
      .map((option) => {
        const button = document.createElement("button");
        button.type = "button";
        button.dataset.value = option.value;
        button.textContent = option.text;
        button.disabled = option.disabled;
        button.classList.toggle("is-selected", option.selected);
        button.setAttribute("role", "option");
        button.setAttribute("aria-selected", String(option.selected));
        return button;
      });
    this.list.replaceChildren(...buttons);
    this.highlightedIndex = Math.max(0, buttons.findIndex((button) => button.classList.contains("is-selected")));
    this.updateHighlight();
    this.sync();
  }

  sync() {
    this.button.textContent = this.select.selectedOptions[0]?.text || "Selecione";
    this.button.disabled = this.select.disabled;
    const invalid = this.select.required && !this.select.checkValidity();
    this.button.classList.toggle("fx-select2-invalid", invalid);
    this.button.classList.toggle("fx-select2-valid", !invalid);
    this.button.setAttribute("aria-invalid", String(invalid));
  }

  toggle() { this.panel.hidden ? this.open() : this.close(); }
  open() { this.panel.hidden = false; this.button.setAttribute("aria-expanded", "true"); this.search.value = ""; this.render(); this.search.focus(); }
  close() { this.panel.hidden = true; this.button.setAttribute("aria-expanded", "false"); }

  moveHighlight(direction) {
    const options = [...this.list.querySelectorAll("button[data-value]:not([disabled])")];
    if (!options.length) return;
    const current = options.findIndex((option) => option.classList.contains("is-highlighted"));
    this.highlightedIndex = current < 0 ? 0 : (current + direction + options.length) % options.length;
    options.forEach((option, index) => option.classList.toggle("is-highlighted", index === this.highlightedIndex));
    options[this.highlightedIndex].scrollIntoView({ block: "nearest" });
  }

  updateHighlight() {
    const options = [...this.list.querySelectorAll("button[data-value]:not([disabled])")];
    options.forEach((option, index) => option.classList.toggle("is-highlighted", index === this.highlightedIndex));
  }

  chooseHighlighted() {
    const highlighted = this.list.querySelector("button.is-highlighted[data-value]");
    if (highlighted) this.selectValue(highlighted.dataset.value);
  }

  selectValue(value) {
    this.select.value = value;
    this.select.dispatchEvent(new Event("change", { bubbles: true }));
    this.close();
    this.sync();
    this.focusNext();
  }

  focusNext() {
    const scope = this.select.form || document;
    const fields = [...new Set([...scope.querySelectorAll(".pula")].map((field) => {
      if (field instanceof HTMLSelectElement && field.dataset.fxSelect2Ready) return field.closest(".fx-select2")?.querySelector(".fx-select2-button");
      return field;
    }).filter(Boolean))].filter(visibleEnabled);
    const next = fields[fields.indexOf(this.button) + 1];
    if (!next) return;
    next.focus();
    if (typeof next.select === "function" && next.matches("input:not([type='checkbox']):not([type='radio'])")) next.select();
  }
}

function startFxForms(options = {}) {
  const navigation = new FxFormNavigation(options.navigation).start();
  const selects = [...document.querySelectorAll(options.selectSelector || "select")].map((select) => new FxSelect2(select).start());
  const validators = [...document.querySelectorAll(options.formSelector || "form[data-fx-validate], #form")]
    .map((form) => new FxFormValidator(form, options.validation).start());
  return { navigation, validators, selects };
}

const FX_ALERT_DEFAULTS = Object.freeze({
  delay: 5000,
  labels: { ok: "OK", cancel: "Cancelar" },
  buttonFocus: "ok",
  buttonReverse: false,
  allowHTML: false,
  position: "top-right"
});

function fxAlertSafeHTML(value) {
  const template = document.createElement("template");
  template.innerHTML = String(value ?? "");
  template.content.querySelectorAll("script,style,iframe,object,embed,link,meta").forEach((node) => node.remove());
  template.content.querySelectorAll("*").forEach((node) => {
    [...node.attributes].forEach((attribute) => {
      if (/^on/i.test(attribute.name) || /^(javascript|data):/i.test(attribute.value.trim())) node.removeAttribute(attribute.name);
    });
  });
  return template.innerHTML;
}

class FxAlertManager extends EventTarget {
  constructor(options = {}) {
    super();
    this.options = { ...FX_ALERT_DEFAULTS, ...options, labels: { ...FX_ALERT_DEFAULTS.labels, ...options.labels } };
    this.queue = [];
    this.active = null;
    this.toastCounter = 0;
  }

  configure(options = {}) {
    if (options.buttonFocus && /^(não|nao|cancel)$/i.test(options.buttonFocus)) options.buttonFocus = "cancel";
    if (options.labels) this.options.labels = { ...this.options.labels, ...options.labels };
    Object.assign(this.options, { ...options, labels: this.options.labels });
    return this;
  }

  set(options = {}) { return this.configure(options); }

  alert(message, options = {}) {
    return this.enqueue({ type: "alert", message, ...options });
  }

  confirm(message, options = {}) {
    return this.enqueue({ type: "confirm", message, ...options });
  }

  prompt(message, options = {}) {
    if (typeof options === "string") options = { value: options };
    return this.enqueue({ type: "prompt", message, value: "", placeholder: "", ...options });
  }

  log(message, type = "info", wait) {
    return this.toast(message, { type: type || "info", delay: wait });
  }

  success(message, wait) { return this.toast(message, { type: "success", delay: wait }); }
  error(message, wait) { return this.toast(message, { type: "error", delay: wait }); }
  info(message, wait) { return this.toast(message, { type: "info", delay: wait }); }
  warning(message, wait) { return this.toast(message, { type: "warning", delay: wait }); }

  enqueue(config) {
    return new Promise((resolve) => {
      this.queue.push({ config, resolve, opener: document.activeElement });
      this.showNext();
    });
  }

  showNext() {
    if (this.active || !this.queue.length) return;
    this.ensureContainers();
    this.active = this.queue.shift();
    const { config } = this.active;
    const labels = { ...this.options.labels, ...config.labels };
    const dialog = document.createElement("section");
    dialog.className = `fxa-dialog fxa-dialog--${config.type}`;
    dialog.setAttribute("role", config.type === "alert" ? "alertdialog" : "dialog");
    dialog.setAttribute("aria-modal", "true");
    dialog.innerHTML = `
      <header class="fxa-dialog-header">
        <strong>${config.title || this.titleFor(config.type)}</strong>
        <button type="button" class="fxa-dialog-x" aria-label="Fechar">×</button>
      </header>
      <div class="fxa-dialog-message"></div>
      ${config.type === "prompt" ? '<input class="fxa-dialog-input" type="text">' : ""}
      <footer class="fxa-dialog-actions"></footer>`;
    const message = dialog.querySelector(".fxa-dialog-message");
    if (config.allowHTML ?? this.options.allowHTML) message.innerHTML = fxAlertSafeHTML(config.message);
    else message.textContent = String(config.message ?? "");
    const actions = dialog.querySelector(".fxa-dialog-actions");
    if (config.type !== "alert") actions.append(this.makeButton(labels.cancel, "cancel"));
    actions.append(this.makeButton(labels.ok, "ok", true));
    if (this.options.buttonReverse || config.buttonReverse) actions.classList.add("fxa-dialog-actions--reverse");
    const input = dialog.querySelector(".fxa-dialog-input");
    if (input) { input.value = config.value ?? ""; input.placeholder = config.placeholder || ""; }
    this.dialogLayer.append(dialog);
    this.active.dialog = dialog;
    dialog.querySelector(".fxa-dialog-x").addEventListener("click", () => this.finish(false));
    actions.addEventListener("click", (event) => {
      const action = event.target.closest("button")?.dataset.action;
      if (action) this.finish(action === "ok");
    });
    dialog.addEventListener("keydown", (event) => {
      if (event.key === "Escape") { event.preventDefault(); this.finish(false); }
      if (event.key === "Enter" && !event.shiftKey) { event.preventDefault(); this.finish(true); }
    });
    requestAnimationFrame(() => dialog.classList.add("fxa-visible"));
    const preferred = config.type === "prompt" ? input : dialog.querySelector(`[data-action="${config.buttonFocus || this.options.buttonFocus}"]`);
    (preferred || dialog.querySelector("button"))?.focus();
    this.dispatchEvent(new CustomEvent("fxalert:open", { detail: { type: config.type, dialog } }));
  }

  finish(accepted) {
    if (!this.active) return;
    const { config, resolve, opener, dialog } = this.active;
    const input = dialog.querySelector(".fxa-dialog-input");
    const result = config.type === "prompt" ? { accepted, value: accepted ? input.value : null } : accepted;
    dialog.classList.remove("fxa-visible");
    window.setTimeout(() => dialog.remove(), 130);
    this.active = null;
    resolve(result);
    if (opener?.isConnected && !opener.disabled) opener.focus({ preventScroll: true });
    this.dispatchEvent(new CustomEvent("fxalert:close", { detail: { type: config.type, result } }));
    window.setTimeout(() => this.showNext(), 140);
  }

  toast(message, options = {}) {
    this.ensureContainers();
    const type = options.type || "info";
    const toast = document.createElement("button");
    toast.type = "button";
    toast.className = `fxa-toast fxa-toast--${type}`;
    toast.setAttribute("aria-label", "Fechar notificação");
    toast.dataset.toastId = String(++this.toastCounter);
    if (options.allowHTML ?? this.options.allowHTML) toast.innerHTML = fxAlertSafeHTML(message);
    else toast.textContent = String(message ?? "");
    this.toastLayer.dataset.position = options.position || this.options.position;
    this.toastLayer.append(toast);
    requestAnimationFrame(() => toast.classList.add("fxa-visible"));
    const close = () => {
      if (!toast.isConnected) return;
      toast.classList.remove("fxa-visible");
      window.setTimeout(() => toast.remove(), 150);
    };
    toast.addEventListener("click", close);
    const delay = options.delay ?? this.options.delay;
    if (Number(delay) > 0) window.setTimeout(close, Number(delay));
    return { id: toast.dataset.toastId, element: toast, close };
  }

  dismissAll() {
    this.toastLayer?.querySelectorAll(".fxa-toast").forEach((toast) => toast.remove());
    if (this.active) this.finish(false);
    this.queue.splice(0).forEach((item) => item.resolve(false));
  }

  extend(type = "info") {
    return (message, wait) => { this.toast(message, { type, delay: wait }); return this; };
  }

  ensureContainers() {
    if (!this.dialogLayer) {
      this.dialogLayer = document.createElement("div");
      this.dialogLayer.className = "fxa-dialog-layer";
      document.body.append(this.dialogLayer);
    }
    if (!this.toastLayer) {
      this.toastLayer = document.createElement("div");
      this.toastLayer.className = "fxa-toast-layer";
      document.body.append(this.toastLayer);
    }
  }

  makeButton(label, action, primary = false) {
    const button = document.createElement("button");
    button.type = "button";
    button.dataset.action = action;
    button.className = primary ? "fxa-button fxa-button--primary" : "fxa-button";
    button.textContent = label;
    return button;
  }

  titleFor(type) {
    return ({ alert: "Atenção", confirm: "Confirmação", prompt: "Informe" })[type] || "Mensagem";
  }
}

const FxAlerts = new FxAlertManager();


const legacyIdByLevel = new Map();

function abrirjanela(url, nome, title, largura = 1, altura = 1, nivel = 1, foco = null) {
  const level = Number.parseInt(nivel, 10) || 1;
  const id = String(nome || `fxjanela-${level}`);
  legacyIdByLevel.set(level, id);
  return FxWindows.open({ id, url, title, width: largura, height: altura, level, focus: foco });
}

function fecharjanelas(nivel = 1) {
  return FxWindows.close(legacyIdByLevel.get(Number(nivel)) || Number(nivel));
}

function fecharjanelasem(nivel = 1) {
  return fecharjanelas(nivel);
}

function fecharjanelaDina(fechar, abrir) {
  if (Number(fechar) > 0) FxWindows.hide(legacyIdByLevel.get(Number(fechar)) || Number(fechar));
  if (Number(abrir) > 0) FxWindows.show(legacyIdByLevel.get(Number(abrir)) || Number(abrir));
}

function maxjanela(nivel = 1) {
  return FxWindows.maximize(legacyIdByLevel.get(Number(nivel)) || Number(nivel));
}

function titulojanela(titulo, janela) {
  return FxWindows.setTitle(janela, titulo);
}

function rolarpagina() {
  window.scrollTo({ top: 0, behavior: "smooth" });
}

Object.assign(window, {
  FxWindows,
  abrirjanela,
  fecharjanelas,
  fecharjanelasem,
  fecharjanelaDina,
  maxjanela,
  titulojanela,
  rolarpagina
});

FxWindows.start();
window.addEventListener("message", (event) => {
  if (event.data?.type === "fxwindows:selection-lock") document.body?.classList.toggle("fxw-document-selection-locked", Boolean(event.data.locked));
});
if (window.parent !== window) {
  document.addEventListener("pointerdown", () => window.parent.postMessage({ type: "fxwindows:focus" }, "*"), true);
  document.addEventListener("focusin", () => window.parent.postMessage({ type: "fxwindows:focus" }, "*"), true);
}



const alertify = {
  alert(message, callback, cssClass) {
    FxAlerts.alert(message, { allowHTML: true, className: cssClass }).then((result) => callback?.(result));
    return alertify;
  },
  confirm(message, callback, cssClass) {
    FxAlerts.confirm(message, { allowHTML: true, className: cssClass }).then((result) => callback?.(result));
    return alertify;
  },
  prompt(message, callback, placeholder = "", cssClass) {
    FxAlerts.prompt(message, { allowHTML: true, placeholder, className: cssClass }).then((result) => callback?.(result.accepted, result.value));
    return alertify;
  },
  log(message, type, wait) { FxAlerts.toast(message, { type: type || "info", delay: wait, allowHTML: true }); return alertify; },
  success(message, wait) { FxAlerts.toast(message, { type: "success", delay: wait, allowHTML: true }); return alertify; },
  error(message, wait) { FxAlerts.toast(message, { type: "error", delay: wait, allowHTML: true }); return alertify; },
  set(options) { FxAlerts.configure(options); return alertify; },
  extend(type = "info") { return (message, wait) => { alertify.log(message, type, wait); return alertify; }; },
  init() { FxAlerts.ensureContainers(); return alertify; },
  labels: FxAlerts.options.labels
};

Object.assign(window, { FxAlerts, alertify });

Object.assign(window, { FxWindowManager, FxFormNavigation, FxFormValidator, FxSelect2, FxAlertManager, FxAlerts, alertify, startFxForms });
})();

