(() => {
  "use strict";
  if (typeof HTMLDialogElement === "undefined") return;
  const ownScript = document.currentScript;
  const siteRoot = new URL("../../", ownScript.src);
  const documentVersion = new URL(ownScript.src).searchParams.get("v");
  const dialog = document.createElement("dialog");
  dialog.className = "document-reader";
  dialog.id = "document-reader";
  dialog.setAttribute("aria-labelledby", "reader-title");
  dialog.innerHTML = `
    <header class="reader-header">
      <div class="reader-heading"><span class="reader-kicker">UNIORG / RESEARCH LIBRARY</span><h2 id="reader-title" tabindex="-1">Document</h2><p class="reader-path"></p></div>
      <div class="reader-actions"><button type="button" class="reader-back" hidden>← Back</button><a class="reader-download" download>Download</a><button type="button" class="reader-close" aria-label="Close document preview">Close <span aria-hidden="true">×</span></button></div>
    </header>
    <div class="reader-status" role="status" aria-live="polite" hidden></div>
    <div class="reader-scroll" tabindex="0" role="region" aria-label="Document content"><div class="reader-layout"><article class="reader-content"></article><details class="reader-toc"><summary>On this page</summary><nav aria-label="Document sections"></nav></details></div></div>
    <footer class="reader-footer"><span class="reader-format">DOCUMENT</span><span>UNIORG research library</span></footer>`;
  document.body.append(dialog);
  const title = dialog.querySelector("#reader-title");
  const pathLabel = dialog.querySelector(".reader-path");
  const content = dialog.querySelector(".reader-content");
  const scroller = dialog.querySelector(".reader-scroll");
  const toc = dialog.querySelector(".reader-toc");
  const tocNav = toc.querySelector("nav");
  const status = dialog.querySelector(".reader-status");
  const back = dialog.querySelector(".reader-back");
  const close = dialog.querySelector(".reader-close");
  const download = dialog.querySelector(".reader-download");
  let opener,
    currentUrl,
    history = [],
    request,
    serial = 0,
    libraries;
  let restoreFocus = true;

  const documentType = (url) => {
    if (
      url.origin !== siteRoot.origin ||
      !url.pathname.startsWith(siteRoot.pathname)
    )
      return "";
    return /\.(md|json|txt)$/i.exec(url.pathname)?.[1].toLowerCase() || "";
  };
  const enhanceLinks = (root) => {
    root.querySelectorAll("a[href]:not([download])").forEach((link) => {
      if (documentType(new URL(link.href))) {
        link.setAttribute("aria-haspopup", "dialog");
        link.setAttribute("aria-controls", dialog.id);
      }
    });
  };
  const loadScript = (path) =>
    new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = new URL(path, siteRoot).href;
      script.onload = resolve;
      script.onerror = () => {
        script.remove();
        reject(new Error("Document formatting could not be loaded."));
      };
      document.head.append(script);
    });
  const loadLibraries = () => {
    if (window.marked && window.DOMPurify) return Promise.resolve();
    if (!libraries)
      libraries = Promise.all([
        loadScript("assets/js/third-party/marked.umd.js"),
        loadScript("assets/js/third-party/purify.min.js"),
      ]).catch((error) => {
        libraries = null;
        throw error;
      });
    return libraries;
  };
  const slug = (text) =>
    text
      .normalize("NFKD")
      .toLowerCase()
      .replace(/[^\p{Letter}\p{Number}\s-]/gu, "")
      .trim()
      .replace(/[\s-]+/g, "-");
  const scrollToHeading = (hash) => {
    const name = decodeURIComponent(hash.replace(/^#/, ""));
    const target = [...content.querySelectorAll("[data-section-slug]")].find(
      (el) => el.dataset.sectionSlug === name || el.id === name,
    );
    if (!target) return;
    scroller.scrollTo({
      top:
        scroller.scrollTop +
        target.getBoundingClientRect().top -
        scroller.getBoundingClientRect().top -
        24,
      behavior: "auto",
    });
    target.setAttribute("tabindex", "-1");
    target.focus({ preventScroll: true });
  };
  const decorateMarkdown = (url) => {
    const first = content.querySelector("h1");
    if (first) {
      title.textContent = first.textContent;
      first.remove();
    }
    const slugs = new Map();
    content.querySelectorAll("h1,h2,h3,h4,h5,h6").forEach((heading) => {
      const originalLevel = Number(heading.tagName[1]);
      const replacement = document.createElement(
        `h${Math.min(6, Math.max(3, originalLevel + 1))}`,
      );
      replacement.innerHTML = heading.innerHTML;
      const base = slug(heading.textContent) || "section";
      const count = slugs.get(base) || 0;
      slugs.set(base, count + 1);
      const sectionSlug = base + (count ? `-${count}` : "");
      replacement.id = `document-section-${sectionSlug}`;
      replacement.dataset.sectionSlug = sectionSlug;
      heading.replaceWith(replacement);
      if (originalLevel <= 3) {
        const link = document.createElement("a");
        link.href = `#${replacement.id}`;
        link.textContent = replacement.textContent;
        link.className = originalLevel >= 3 ? "reader-subsection" : "";
        link.addEventListener("click", (event) => {
          event.preventDefault();
          scrollToHeading(replacement.id);
        });
        tocNav.append(link);
      }
    });
    content.querySelectorAll("a[href]").forEach((link) => {
      const target = new URL(link.getAttribute("href"), url);
      if (!["http:", "https:", "mailto:", "tel:"].includes(target.protocol)) {
        link.removeAttribute("href");
        return;
      }
      link.href = target.href;
      if (
        target.origin !== siteRoot.origin ||
        /\.pdf$/i.test(target.pathname)
      ) {
        link.target = "_blank";
        link.rel = "noopener noreferrer";
      } else if (target.pathname === url.pathname && target.hash) {
        link.addEventListener("click", (event) => {
          event.preventDefault();
          scrollToHeading(target.hash);
        });
      }
    });
    content.querySelectorAll("img[src]").forEach((img) => {
      const target = new URL(img.getAttribute("src"), url);
      if (!["http:", "https:"].includes(target.protocol)) {
        img.remove();
        return;
      }
      img.src = target.href;
      img.loading = "lazy";
      if (!img.hasAttribute("alt")) img.alt = "";
    });
    content.querySelectorAll("table").forEach((table) => {
      const wrapper = document.createElement("div");
      wrapper.className = "reader-table";
      wrapper.tabIndex = 0;
      wrapper.setAttribute("role", "region");
      wrapper.setAttribute(
        "aria-label",
        "Document table; scroll horizontally if needed",
      );
      table.before(wrapper);
      wrapper.append(table);
    });
    content.querySelectorAll("pre").forEach((pre) => {
      const code = pre.querySelector("code");
      if (!code) return;
      const wrapper = document.createElement("div");
      wrapper.className = "reader-code";
      const toolbar = document.createElement("div");
      toolbar.className = "reader-code-toolbar";
      const label = document.createElement("span");
      label.textContent =
        /language-([\w-]+)/.exec(code.className)?.[1] || "Code";
      pre.tabIndex = 0;
      pre.setAttribute("role", "region");
      pre.setAttribute("aria-label", `${label.textContent} code block`);
      const copy = document.createElement("button");
      copy.type = "button";
      copy.textContent = "Copy";
      copy.setAttribute("aria-label", `Copy ${label.textContent} code`);
      copy.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(code.textContent);
          copy.textContent = "Copied";
          setTimeout(() => {
            copy.textContent = "Copy";
          }, 1800);
        } catch {
          copy.textContent = "Select to copy";
        }
      });
      toolbar.append(label, copy);
      pre.before(wrapper);
      wrapper.append(toolbar, pre);
    });
    enhanceLinks(content);
  };
  const load = async (url, { push = false, scrollTop = 0 } = {}) => {
    if (push && currentUrl)
      history.push({ url: currentUrl, scrollTop: scroller.scrollTop });
    currentUrl = url;
    back.hidden = history.length === 0;
    request?.abort();
    request = new AbortController();
    const token = ++serial;
    title.textContent = decodeURIComponent(url.pathname.split("/").pop())
      .replace(/\.md$/i, "")
      .replace(/[_-]+/g, " ");
    pathLabel.textContent = decodeURIComponent(
      url.pathname.slice(siteRoot.pathname.length),
    );
    download.href = url.href;
    dialog.querySelector(".reader-format").textContent =
      documentType(url).toUpperCase();
    content.replaceChildren();
    tocNav.replaceChildren();
    toc.hidden = true;
    dialog.querySelector(".reader-layout").classList.remove("has-toc");
    status.hidden = false;
    status.textContent = "Loading document…";
    status.setAttribute("role", "status");
    content.setAttribute("aria-busy", "true");
    scroller.scrollTop = 0;
    try {
      const requestUrl = new URL(url);
      if (documentVersion) requestUrl.searchParams.set("v", documentVersion);
      const textPromise = fetch(requestUrl, {
        signal: request.signal,
        cache: "no-store",
      }).then((response) => {
        if (!response.ok) throw new Error("This document could not be loaded.");
        return response.text();
      });
      const [text] = await Promise.all([textPromise, loadLibraries()]);
      if (token !== serial || !dialog.open) return;
      if (documentType(url) === "md") {
        const html = window.marked.parse(text.replace(/^\uFEFF/, ""), {
          gfm: true,
          breaks: false,
        });
        content.innerHTML = window.DOMPurify.sanitize(html, {
          USE_PROFILES: { html: true },
          FORBID_TAGS: ["style", "iframe", "object", "embed", "form"],
          FORBID_ATTR: ["style"],
        });
        decorateMarkdown(url);
      } else {
        const pre = document.createElement("pre");
        pre.className = "reader-plain-text";
        if (documentType(url) === "json") {
          try {
            pre.textContent = JSON.stringify(JSON.parse(text), null, 2);
          } catch {
            pre.textContent = text;
          }
        } else pre.textContent = text;
        content.append(pre);
      }
      toc.hidden = !tocNav.children.length;
      dialog
        .querySelector(".reader-layout")
        .classList.toggle("has-toc", !toc.hidden);
      toc.open = matchMedia("(min-width: 1001px)").matches;
      status.hidden = true;
      content.removeAttribute("aria-busy");
      title.focus({ preventScroll: true });
      if (url.hash) scrollToHeading(url.hash);
      else scroller.scrollTop = scrollTop;
    } catch (error) {
      if (error.name === "AbortError" || token !== serial) return;
      content.removeAttribute("aria-busy");
      status.setAttribute("role", "alert");
      status.textContent =
        error.message || "This document could not be loaded.";
      const retry = document.createElement("button");
      retry.type = "button";
      retry.textContent = "Try again";
      retry.addEventListener("click", () => load(currentUrl));
      status.append(retry);
    }
  };
  enhanceLinks(document);
  document.addEventListener("click", (event) => {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    )
      return;
    const link = event.target.closest("a[href]");
    if (!link || link.hasAttribute("download") || link.target === "_blank")
      return;
    const url = new URL(link.href);
    if (!documentType(url)) {
      if (
        link.closest(".reader-content") &&
        url.origin === location.origin &&
        url.pathname === location.pathname
      )
        dialog.close();
      return;
    }
    if (typeof dialog.showModal !== "function") return;
    event.preventDefault();
    if (dialog.open) {
      if (url.pathname === currentUrl?.pathname && url.hash)
        scrollToHeading(url.hash);
      else load(url, { push: true });
      return;
    }
    opener = link;
    history = [];
    restoreFocus = true;
    dialog.showModal();
    document.body.classList.add("viewer-open");
    document.dispatchEvent(
      new CustomEvent("uniorg:document-state", { detail: { open: true } }),
    );
    close.focus();
    load(url);
  });
  back.addEventListener("click", () => {
    const previous = history.pop();
    if (previous) load(previous.url, { scrollTop: previous.scrollTop });
  });
  close.addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target !== dialog) return;
    const box = dialog.getBoundingClientRect();
    if (
      event.clientX < box.left ||
      event.clientX > box.right ||
      event.clientY < box.top ||
      event.clientY > box.bottom
    )
      dialog.close();
  });
  dialog.addEventListener("keydown", (event) => {
    if (event.key !== "Tab") return;
    const items = [
      ...dialog.querySelectorAll(
        'a[href],button:not([disabled]),summary,[tabindex="0"]',
      ),
    ].filter((el) => el.getClientRects().length);
    const first = items[0],
      last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  dialog.addEventListener("close", () => {
    request?.abort();
    serial++;
    document.body.classList.remove("viewer-open");
    document.dispatchEvent(
      new CustomEvent("uniorg:document-state", { detail: { open: false } }),
    );
    if (restoreFocus) opener?.focus({ preventScroll: true });
  });
})();
