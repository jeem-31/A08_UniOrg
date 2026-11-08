(() => {
  "use strict";
  const mainScriptUrl = document.currentScript.src;
  document.documentElement.classList.add("js");
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
  const compact = window.matchMedia("(max-width: 1000px)");

  // Load and play the HD presentation only while it is in view.
  const presentation = document.querySelector("#uniorg-avp");
  if (presentation) {
    const sound = document.querySelector(".avp-sound");
    const note = document.querySelector("#avp-note");
    let inView = false;
    let userPaused = false;
    let environmentalPause = false;
    let playPending = false;
    const canPlay = () =>
      (inView || document.fullscreenElement === presentation) &&
      !document.hidden &&
      !document.querySelector("dialog[open]") &&
      !document.body.classList.contains("drawer-open");
    const pauseForEnvironment = () => {
      if (!presentation.paused) {
        environmentalPause = true;
        presentation.pause();
      }
    };
    const syncPresentation = () => {
      if (!canPlay()) {
        pauseForEnvironment();
        return;
      }
      if (
        reduced.matches ||
        userPaused ||
        presentation.ended ||
        playPending ||
        !presentation.paused
      )
        return;
      playPending = true;
      presentation
        .play()
        .then(() => {
          if (!canPlay()) pauseForEnvironment();
        })
        .catch(() => {
          if (canPlay())
            note.textContent = "Select Play to watch the Full HD presentation.";
        })
        .finally(() => {
          playPending = false;
        });
    };
    const updateSound = () => {
      sound.textContent = presentation.muted ? "Turn sound on" : "Mute sound";
      sound.setAttribute("aria-pressed", String(!presentation.muted));
    };
    sound.hidden = false;
    sound.addEventListener("click", () => {
      presentation.muted = !presentation.muted;
    });
    presentation.addEventListener("volumechange", updateSound);
    presentation.addEventListener("play", () => {
      userPaused = false;
    });
    presentation.addEventListener("pause", () => {
      if (environmentalPause) environmentalPause = false;
      else userPaused = true;
    });
    presentation.addEventListener("error", () => {
      note.textContent =
        "The video could not load. Use the Full HD download link to open it.";
    });
    const motionPreference = () => {
      note.textContent = reduced.matches
        ? "Select Play to watch. Automatic playback is off for reduced motion."
        : "Autoplays muted. Turn sound on to hear the presentation.";
      if (reduced.matches) pauseForEnvironment();
      else syncPresentation();
    };
    reduced.addEventListener("change", motionPreference);
    motionPreference();
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(
        ([entry]) => {
          inView = entry.isIntersecting && entry.intersectionRatio >= 0.35;
          syncPresentation();
        },
        { threshold: [0, 0.35] },
      ).observe(presentation);
    }
    document.addEventListener("visibilitychange", syncPresentation);
    document.addEventListener("fullscreenchange", syncPresentation);
    new MutationObserver(syncPresentation).observe(document.body, {
      subtree: true,
      attributes: true,
      attributeFilter: ["open", "class"],
    });
  }

  // One shared navigation surface: a fixed rail or an inert, modal side drawer.
  const panel = document.querySelector("#site-sidebar");
  const trigger = document.querySelector(".menu-toggle");
  const closeButton = document.querySelector(".drawer-close");
  const backdrop = document.querySelector(".drawer-backdrop");
  const outside = [
    ...document.querySelectorAll(
      "body > main, body > footer, .mobile-bar, .skip-link",
    ),
  ];
  let drawerOpen = false;
  const closeDrawer = (restore = true) => {
    drawerOpen = false;
    panel?.classList.remove("is-open");
    panel?.setAttribute("role", "complementary");
    panel?.removeAttribute("aria-modal");
    if (panel) panel.inert = compact.matches;
    trigger?.setAttribute("aria-expanded", "false");
    if (backdrop) backdrop.hidden = true;
    outside.forEach((el) => {
      el.inert = false;
    });
    document.body.classList.remove("drawer-open");
    if (restore && compact.matches) trigger?.focus();
  };
  if (panel && trigger && closeButton && backdrop) {
    trigger.hidden = false;
    closeButton.hidden = false;
    panel.inert = compact.matches;
    trigger.addEventListener("click", () => {
      if (!compact.matches) return;
      if (drawerOpen) {
        closeDrawer();
        return;
      }
      drawerOpen = true;
      panel.inert = false;
      panel.classList.add("is-open");
      panel.setAttribute("role", "dialog");
      panel.setAttribute("aria-modal", "true");
      trigger.setAttribute("aria-expanded", "true");
      backdrop.hidden = false;
      outside.forEach((el) => {
        el.inert = true;
      });
      document.body.classList.add("drawer-open");
      closeButton.focus();
    });
    closeButton.addEventListener("click", () => closeDrawer());
    backdrop.addEventListener("click", () => closeDrawer());
    panel.querySelectorAll("a").forEach((link) =>
      link.addEventListener("click", () => {
        if (!drawerOpen) return;
        closeDrawer(false);
        if (link.pathname === location.pathname && link.hash) {
          const target = document.getElementById(
            decodeURIComponent(link.hash.slice(1)),
          );
          if (target) {
            target.setAttribute("tabindex", "-1");
            target.focus({ preventScroll: true });
          }
        } else trigger.focus();
      }),
    );
    compact.addEventListener("change", () => closeDrawer(false));
    document.addEventListener("keydown", (event) => {
      if (!drawerOpen) return;
      if (event.key === "Escape") {
        event.preventDefault();
        closeDrawer();
      }
      if (event.key !== "Tab") return;
      const items = [
        ...panel.querySelectorAll("a[href],button:not([hidden])"),
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
  }

  // Two complementary paint masks adapt each pixel of the rail at section edges.
  // The light layer is the only interactive/accessible navigation; the dark paint
  // copy contains inert spans, so there are no duplicate links or tab stops.
  const sidebarContent = panel?.querySelector(":scope > .sidebar-inner");
  let darkContent;
  let syncGlassState = () => {};
  if (panel && sidebarContent) {
    const lightSurface = document.createElement("div");
    lightSurface.className = "rail-surface rail-surface-light";
    lightSurface.setAttribute("aria-hidden", "true");
    const darkSurface = lightSurface.cloneNode();
    darkSurface.className = "rail-surface rail-surface-dark";
    const darkPaint = document.createElement("div");
    darkPaint.className = "site-header rail-copy is-over-dark";
    darkPaint.setAttribute("aria-hidden", "true");
    darkPaint.inert = true;
    darkContent = sidebarContent.cloneNode(true);
    darkPaint.append(darkContent);
    const originalControls = [...sidebarContent.querySelectorAll("a,button")];
    const paintedControls = [...darkContent.querySelectorAll("a,button")].map(
      (control) => {
        const paint = document.createElement("span");
        paint.className = control.className;
        paint.setAttribute("data-rail-link", "");
        if (control.hasAttribute("aria-current"))
          paint.setAttribute(
            "aria-current",
            control.getAttribute("aria-current"),
          );
        paint.append(...control.childNodes);
        control.replaceWith(paint);
        return paint;
      },
    );
    darkContent
      .querySelectorAll("[id]")
      .forEach((element) => element.removeAttribute("id"));
    darkContent
      .querySelectorAll("[aria-controls],[aria-labelledby],[data-section]")
      .forEach((element) => {
        element.removeAttribute("aria-controls");
        element.removeAttribute("aria-labelledby");
        element.removeAttribute("data-section");
      });
    panel.prepend(lightSurface, darkSurface);
    panel.append(darkPaint);
    panel.classList.add("local-glass");
    sidebarContent.classList.add("rail-content-light");
    syncGlassState = () => {
      originalControls.forEach((control, i) => {
        const paint = paintedControls[i];
        const current = control.getAttribute("aria-current");
        if (paint.getAttribute("aria-current") !== current) {
          if (current) paint.setAttribute("aria-current", current);
          else paint.removeAttribute("aria-current");
        }
        paint.classList.toggle(
          "is-keyboard-focus",
          control === document.activeElement &&
            control.matches(":focus-visible"),
        );
      });
      darkContent.scrollTop = sidebarContent.scrollTop;
    };
    originalControls.forEach((control, i) => {
      control.addEventListener("pointerenter", () =>
        paintedControls[i].classList.add("is-hovered"),
      );
      control.addEventListener("pointerleave", () =>
        paintedControls[i].classList.remove("is-hovered"),
      );
    });
    sidebarContent.addEventListener("scroll", syncGlassState, {
      passive: true,
    });
    sidebarContent.addEventListener("focusin", syncGlassState);
    sidebarContent.addEventListener("focusout", () =>
      requestAnimationFrame(syncGlassState),
    );
  }

  // Active location and local glass masks follow native scroll in the same frame.
  const sectionLinks = [
    ...document.querySelectorAll("#main-nav [data-section]"),
  ];
  const sections = sectionLinks
    .map((link) => document.getElementById(link.dataset.section))
    .filter(Boolean);
  const surfaceSections = [...document.querySelectorAll("main > section")];
  const glassMask = (ranges, height, dark) => {
    const outside = dark ? "transparent" : "#000";
    const inside = dark ? "#000" : "transparent";
    const stops = [`${outside} 0px`];
    ranges.forEach(([top, bottom]) => {
      stops.push(
        `${outside} ${top}px`,
        `${inside} ${top}px`,
        `${inside} ${bottom}px`,
        `${outside} ${bottom}px`,
      );
    });
    stops.push(`${outside} ${height}px`);
    return `linear-gradient(to bottom, ${stops.join(",")})`;
  };
  let scrollQueued = false;
  const updateScroll = () => {
    scrollQueued = false;
    const max = document.documentElement.scrollHeight - innerHeight;
    const fraction = max > 0 ? Math.min(1, Math.max(0, scrollY / max)) : 1;
    if (panel && sidebarContent) {
      const railTop = panel.getBoundingClientRect().top + panel.clientTop;
      const height = panel.clientHeight;
      const ranges = compact.matches
        ? [[0, height]]
        : surfaceSections
            .filter((section) => section.matches(".hero, .results"))
            .map((section) => {
              const bounds = section.getBoundingClientRect();
              return [
                Math.max(0, bounds.top - railTop),
                Math.min(height, bounds.bottom - railTop),
              ];
            })
            .filter(([top, bottom]) => bottom > top);
      panel.style.setProperty(
        "--rail-light-mask",
        glassMask(ranges, height, false),
      );
      panel.style.setProperty(
        "--rail-dark-mask",
        glassMask(ranges, height, true),
      );
    }
    let active = sections[0];
    sections.forEach((section) => {
      if (section.getBoundingClientRect().top <= (compact.matches ? 140 : 180))
        active = section;
    });
    if (sections.length && fraction > 0.995)
      active = sections[sections.length - 1];
    sectionLinks.forEach((link) => {
      if (active?.id === link.dataset.section)
        link.setAttribute("aria-current", "location");
      else link.removeAttribute("aria-current");
    });
    syncGlassState();
  };
  const queueScroll = () => {
    if (!scrollQueued) {
      scrollQueued = true;
      requestAnimationFrame(updateScroll);
    }
  };
  addEventListener("scroll", queueScroll, { passive: true });
  addEventListener("resize", queueScroll, { passive: true });
  addEventListener("load", queueScroll);
  document.fonts?.ready.then(queueScroll);
  updateScroll();

  // The six original figures become a carousel only after successful enhancement.
  const carousel = document.querySelector(".screenshot-carousel");
  let carouselShow = () => {};
  let syncAutoplay = () => {};
  let viewerOpen = false;
  document.addEventListener("uniorg:document-state", (event) => {
    viewerOpen = Boolean(event.detail?.open);
    syncAutoplay();
  });
  if (carousel) {
    const slides = [...carousel.querySelectorAll(".gallery > figure")];
    const controls = carousel.querySelector(".carousel-controls");
    const play = carousel.querySelector(".carousel-play");
    const dotsHost = carousel.querySelector(".carousel-dots");
    const count = carousel.querySelector(".carousel-count");
    const announcement = carousel.querySelector(".carousel-announcement");
    let selected = 0,
      timer,
      hovered = false,
      focusStopped = false,
      inView = false;
    let userPaused = reduced.matches;
    const dots = slides.map((slide, i) => {
      slide.id = `system-screen-${i + 1}`;
      slide.setAttribute("role", "group");
      slide.setAttribute("aria-roledescription", "slide");
      slide.setAttribute("aria-label", `${i + 1} of ${slides.length}`);
      const dot = document.createElement("button");
      dot.type = "button";
      dot.setAttribute(
        "aria-label",
        `Show screenshot ${i + 1}: ${slide.querySelector("h4").textContent}`,
      );
      dot.setAttribute("aria-controls", slide.id);
      dot.addEventListener("click", () => carouselShow(i, true));
      dotsHost.append(dot);
      return dot;
    });
    const updatePlayLabel = () => {
      const stopped = userPaused || focusStopped || reduced.matches;
      play.setAttribute(
        "aria-label",
        stopped ? "Play screenshot carousel" : "Pause screenshot carousel",
      );
      play.querySelector(".play-label").textContent = stopped
        ? "Play"
        : "Pause";
      play.querySelector(".play-symbol").textContent = stopped ? "▷" : "Ⅱ";
      play.disabled = reduced.matches;
      if (reduced.matches) {
        play.querySelector(".play-label").textContent = "Autoplay off";
        play.querySelector(".play-symbol").textContent = "";
      }
      play.title = reduced.matches
        ? "Your reduced-motion preference disables autoplay"
        : "";
      if (reduced.matches)
        play.setAttribute("aria-label", "Autoplay off: reduced motion enabled");
    };
    syncAutoplay = () => {
      clearTimeout(timer);
      updatePlayLabel();
      if (
        !userPaused &&
        !focusStopped &&
        !hovered &&
        !viewerOpen &&
        !reduced.matches &&
        inView &&
        !document.hidden
      ) {
        timer = setTimeout(() => {
          carouselShow(selected + 1);
        }, 5000);
      }
    };
    carouselShow = (index, announce = false) => {
      const old = slides[selected];
      const wasFocused = old.contains(document.activeElement);
      selected = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => {
        slide.hidden = i !== selected;
      });
      dots.forEach((dot, i) =>
        dot.setAttribute("aria-current", String(i === selected)),
      );
      count.textContent = `${String(selected + 1).padStart(2, "0")} / ${String(slides.length).padStart(2, "0")}`;
      if (wasFocused && announce)
        slides[selected].querySelector("a").focus({ preventScroll: true });
      if (announce)
        announcement.textContent = `Screenshot ${selected + 1} of ${slides.length}: ${slides[selected].querySelector("h4").textContent}`;
      // No animation is required for state correctness; rapid actions stay interruptible.
      if (
        announce &&
        !reduced.matches &&
        typeof slides[selected].animate === "function"
      ) {
        slides[selected]
          .getAnimations()
          .forEach((animation) => animation.cancel());
        slides[selected].animate([{ opacity: 0.65 }, { opacity: 1 }], {
          duration: 220,
          easing: "ease-out",
        });
      }
      syncAutoplay();
      queueScroll();
    };
    carousel.classList.add("carousel-ready");
    carousel.setAttribute("role", "region");
    carousel.setAttribute("aria-roledescription", "carousel");
    controls.hidden = false;
    carouselShow(0);
    carousel
      .querySelector(".carousel-prev")
      .addEventListener("click", () => carouselShow(selected - 1, true));
    carousel
      .querySelector(".carousel-next")
      .addEventListener("click", () => carouselShow(selected + 1, true));
    // Capture pointer intent before focusin stops the carousel and changes the label.
    let pointerPlayIntent = null;
    play.addEventListener("pointerdown", () => {
      pointerPlayIntent = userPaused || focusStopped;
    });
    play.addEventListener("pointercancel", () => {
      pointerPlayIntent = null;
    });
    play.addEventListener("click", () => {
      const stopped = pointerPlayIntent ?? (userPaused || focusStopped);
      pointerPlayIntent = null;
      userPaused = !stopped;
      focusStopped = false;
      syncAutoplay();
    });
    carousel.addEventListener("mouseenter", () => {
      hovered = true;
      syncAutoplay();
    });
    carousel.addEventListener("mouseleave", () => {
      hovered = false;
      syncAutoplay();
    });
    // Focus stops rotation until the user explicitly presses Play (WAI carousel pattern).
    carousel.addEventListener("focusin", () => {
      focusStopped = true;
      syncAutoplay();
    });
    carousel.addEventListener("keydown", (event) => {
      if (event.altKey || event.ctrlKey || event.metaKey) return;
      if (event.key === "ArrowRight" || event.key === "ArrowLeft") {
        event.preventDefault();
        carouselShow(selected + (event.key === "ArrowRight" ? 1 : -1), true);
      }
      if (event.key === "Home" || event.key === "End") {
        event.preventDefault();
        carouselShow(event.key === "Home" ? 0 : slides.length - 1, true);
      }
    });
    let touchStart,
      suppressClick = false;
    const gallery = carousel.querySelector(".gallery");
    gallery.addEventListener(
      "touchstart",
      (event) => {
        if (event.touches.length === 1)
          touchStart = {
            x: event.touches[0].clientX,
            y: event.touches[0].clientY,
          };
        else touchStart = null;
      },
      { passive: true },
    );
    gallery.addEventListener(
      "touchend",
      (event) => {
        if (!touchStart || !event.changedTouches.length) return;
        const dx = event.changedTouches[0].clientX - touchStart.x;
        const dy = event.changedTouches[0].clientY - touchStart.y;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.4) {
          userPaused = true;
          suppressClick = true;
          carouselShow(selected + (dx < 0 ? 1 : -1), true);
          setTimeout(() => {
            suppressClick = false;
          }, 350);
        }
        touchStart = null;
      },
      { passive: true },
    );
    gallery.addEventListener(
      "click",
      (event) => {
        if (suppressClick) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      },
      true,
    );
    new IntersectionObserver(
      (entries) => {
        inView = entries[0].isIntersecting;
        syncAutoplay();
      },
      { threshold: 0.15 },
    ).observe(carousel);
    document.addEventListener("visibilitychange", syncAutoplay);
    reduced.addEventListener("change", () => {
      if (reduced.matches) userPaused = true;
      syncAutoplay();
    });
  }

  // Full-size viewer: deduplicate the hero/dashboard image, retain all source captions.
  const dialog = document.querySelector(".viewer");
  const image = document.querySelector("#viewer-image");
  const caption = document.querySelector("#viewer-caption");
  const links = [...document.querySelectorAll("[data-viewer]")];
  if (dialog && image && caption && typeof dialog.showModal === "function") {
    const unique = [
      ...new Map(links.map((link) => [link.href, link])).values(),
    ];
    const original = document.createElement("a");
    original.className = "viewer-image-link";
    original.textContent = "Open full-size ↗";
    original.target = "_blank";
    original.rel = "noopener noreferrer";
    dialog
      .querySelector(".viewer-toolbar")
      .insertBefore(original, dialog.querySelector(".viewer-close"));
    let selected = 0,
      opener;
    const show = (index, initialLink) => {
      selected = (index + unique.length) % unique.length;
      const link = initialLink || unique[selected];
      image.src = link.href;
      original.href = link.href;
      image.alt =
        link.querySelector("img")?.alt ||
        link.dataset.caption ||
        "UNIORG research screenshot";
      caption.textContent = link.dataset.caption || image.alt;
      if (carousel && opener?.closest(".gallery")) carouselShow(selected);
    };
    links.forEach((link) =>
      link.addEventListener("click", (event) => {
        event.preventDefault();
        opener = link;
        viewerOpen = true;
        syncAutoplay();
        show(
          unique.findIndex((item) => item.href === link.href),
          link,
        );
        dialog.showModal();
        document.body.classList.add("viewer-open");
        dialog.querySelector(".viewer-close").focus();
      }),
    );
    dialog
      .querySelector(".viewer-close")
      .addEventListener("click", () => dialog.close());
    dialog
      .querySelector(".viewer-prev")
      .addEventListener("click", () => show(selected - 1));
    dialog
      .querySelector(".viewer-next")
      .addEventListener("click", () => show(selected + 1));
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
      if (event.key === "Tab") {
        const items = [...dialog.querySelectorAll("a[href], button")];
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        show(selected - 1);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        show(selected + 1);
      }
    });
    dialog.addEventListener("close", () => {
      viewerOpen = false;
      document.body.classList.remove("viewer-open");
      // If the user inspected another carousel image, keep the opener visible on return.
      if (opener?.closest(".gallery"))
        carouselShow(unique.findIndex((item) => item.href === opener.href));
      opener?.focus({ preventScroll: true });
      syncAutoplay();
    });
    image.addEventListener("error", () => {
      caption.textContent =
        "This image could not be loaded. Close the viewer and open its original link directly.";
    });
  }

  // A real, self-hosted PDF reader loads only when the manuscript is requested.
  const manuscriptLinks = [...document.querySelectorAll("[data-manuscript]")];
  if (manuscriptLinks.length && typeof HTMLDialogElement !== "undefined") {
    const manuscript = document.createElement("dialog");
    manuscript.className = "manuscript-dialog";
    manuscript.id = "manuscript-modal";
    manuscript.setAttribute("aria-labelledby", "manuscript-heading");
    manuscript.setAttribute("aria-describedby", "manuscript-help");
    manuscript.innerHTML = `
      <div class="manuscript-header">
        <div><span class="manuscript-kicker">UNIORG / RESEARCH LIBRARY</span><h2 id="manuscript-heading">Research manuscript</h2></div>
        <div class="manuscript-actions">
          <a class="manuscript-original" target="_blank" rel="noopener noreferrer">Open PDF <span aria-hidden="true">↗</span></a>
          <a class="manuscript-download" download>Download</a>
          <button class="manuscript-close" type="button" aria-label="Close research manuscript">Close <span aria-hidden="true">×</span></button>
        </div>
      </div>
      <iframe class="manuscript-frame" title="UNIORG research manuscript PDF reader"></iframe>
      <p id="manuscript-help">Use the reader controls to search, zoom, or change pages.</p>`;
    document.body.append(manuscript);
    const frame = manuscript.querySelector("iframe");
    const close = manuscript.querySelector(".manuscript-close");
    const readerUrl = new URL("../pdfjs/web/viewer.html", mainScriptUrl);
    let manuscriptOpener;
    const closeManuscript = () => manuscript.close();
    const readerFocusItems = () =>
      [
        ...(frame.contentDocument?.querySelectorAll(
          'button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]',
        ) || []),
      ].filter((element) => element.getClientRects().length);
    manuscriptLinks.forEach((link) => {
      link.setAttribute("aria-controls", manuscript.id);
      link.addEventListener("click", (event) => {
        if (typeof manuscript.showModal !== "function") return;
        event.preventDefault();
        manuscriptOpener = link;
        manuscript.querySelector(".manuscript-original").href = link.href;
        manuscript.querySelector(".manuscript-download").href = link.href;
        readerUrl.searchParams.set("file", link.href);
        readerUrl.hash = "zoom=page-fit&pagemode=none";
        if (frame.getAttribute("src") !== readerUrl.href)
          frame.src = readerUrl.href;
        viewerOpen = true;
        syncAutoplay();
        manuscript.showModal();
        document.body.classList.add("viewer-open");
        close.focus();
      });
    });
    close.addEventListener("click", closeManuscript);
    manuscript.addEventListener("click", (event) => {
      if (event.target !== manuscript) return;
      const box = manuscript.getBoundingClientRect();
      if (
        event.clientX < box.left ||
        event.clientX > box.right ||
        event.clientY < box.top ||
        event.clientY > box.bottom
      )
        closeManuscript();
    });
    manuscript.addEventListener("keydown", (event) => {
      if (
        event.key === "Tab" &&
        event.shiftKey &&
        document.activeElement ===
          manuscript.querySelector(".manuscript-original")
      ) {
        event.preventDefault();
        const items = readerFocusItems();
        if (items.length) items[items.length - 1].focus();
        else frame.focus();
      }
    });
    frame.addEventListener("load", () => {
      // Keyboard events inside an iframe do not bubble to its parent dialog.
      frame.contentDocument?.addEventListener(
        "keydown",
        (event) => {
          if (event.key === "Escape" && manuscript.open) {
            event.preventDefault();
            event.stopImmediatePropagation();
            closeManuscript();
          }
          if (event.key === "Tab" && manuscript.open) {
            const items = readerFocusItems();
            if (
              event.shiftKey &&
              frame.contentDocument.activeElement === items[0]
            ) {
              event.preventDefault();
              close.focus();
            } else if (
              !event.shiftKey &&
              frame.contentDocument.activeElement === items[items.length - 1]
            ) {
              event.preventDefault();
              manuscript.querySelector(".manuscript-original").focus();
            }
          }
        },
        true,
      );
    });
    manuscript.addEventListener("close", () => {
      viewerOpen = false;
      document.body.classList.remove("viewer-open");
      manuscriptOpener?.focus({ preventScroll: true });
      syncAutoplay();
    });
  }

  // Cinematic camera, entirely on native scroll. No pinning or synthetic scroll distances.
  if (
    window.gsap &&
    window.ScrollTrigger &&
    document.querySelector(".hero-scene")
  ) {
    gsap.registerPlugin(ScrollTrigger);
    const media = gsap.matchMedia();
    media.add(
      "(min-width: 1001px) and (prefers-reduced-motion: no-preference)",
      () => {
        const scene = document.querySelector(".hero-scene");
        const rig = scene.querySelector(".pointer-rig");
        const camera = scene.querySelector(".hero-visual");
        const timeline = gsap.timeline({
          scrollTrigger: {
            trigger: scene,
            start: "top bottom",
            end: "center 38%",
            scrub: 0.65,
            invalidateOnRefresh: true,
          },
        });
        timeline
          .fromTo(
            camera,
            {
              rotationX: 10,
              rotationY: -12,
              rotationZ: -1.5,
              y: 30,
              scale: 0.94,
            },
            {
              rotationX: 0,
              rotationY: 0,
              rotationZ: 0,
              y: 0,
              scale: 1,
              duration: 1,
              ease: "none",
            },
            0,
          )
          .fromTo(
            ".hero-panel-accreditation",
            { autoAlpha: 0, x: -16, y: 25, z: 0 },
            { autoAlpha: 1, x: 0, y: 0, z: 35, duration: 0.35 },
            0.32,
          )
          .fromTo(
            ".hero-panel-documents",
            { autoAlpha: 0, x: 14, y: 20, z: 0 },
            { autoAlpha: 1, x: 0, y: 0, z: 45, duration: 0.35 },
            0.55,
          )
          .fromTo(
            ".visual-foot",
            { opacity: 0.3, y: 8 },
            { opacity: 1, y: 0, duration: 0.2 },
            0.8,
          );
        const turnX = gsap.quickTo(rig, "rotationX", {
          duration: 0.55,
          ease: "power2.out",
        });
        const turnY = gsap.quickTo(rig, "rotationY", {
          duration: 0.55,
          ease: "power2.out",
        });
        const move = (event) => {
          if (
            event.pointerType !== "mouse" ||
            !matchMedia("(pointer: fine)").matches
          )
            return;
          const box = scene.getBoundingClientRect();
          turnY(((event.clientX - box.left) / box.width - 0.5) * 3);
          turnX(-((event.clientY - box.top) / box.height - 0.5) * 2);
        };
        const reset = () => {
          turnX(0);
          turnY(0);
        };
        scene.addEventListener("pointermove", move);
        scene.addEventListener("pointerleave", reset);
        scene.addEventListener("focusin", reset);
        return () => {
          scene.removeEventListener("pointermove", move);
          scene.removeEventListener("pointerleave", reset);
          scene.removeEventListener("focusin", reset);
        };
      },
    );
    media.add("(prefers-reduced-motion: no-preference)", () => {
      document
        .querySelectorAll(
          "main > .section .section-top, .intro-grid, .team-strip, .objective-grid, .method-notes, .feature-index, .stat-grid, .resource-grid",
        )
        .forEach((group) => {
          gsap.from(group.children, {
            y: 12,
            opacity: 0,
            duration: 0.45,
            stagger: 0.045,
            ease: "power2.out",
            scrollTrigger: { trigger: group, start: "top 92%", once: true },
            clearProps: "transform,opacity",
          });
        });
    });
    addEventListener("load", () => ScrollTrigger.refresh());
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
  }
})();
