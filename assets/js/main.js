(() => {
  'use strict';
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('#main-nav');
  if (toggle && nav) {
    toggle.hidden = false;
    document.documentElement.classList.add('js');
    const closeMenu = () => { toggle.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); };
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open)); nav.classList.toggle('is-open', open);
    });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  }
  const dialog = document.querySelector('.viewer');
  const image = document.querySelector('#viewer-image');
  const caption = document.querySelector('#viewer-caption');
  const links = [...document.querySelectorAll('[data-viewer]')];
  if (!dialog || !image || !caption || typeof dialog.showModal !== 'function') return;
  let selected = 0;
  let opener;
  const show = index => {
    selected = (index + links.length) % links.length;
    const link = links[selected];
    image.src = link.href;
    image.alt = link.querySelector('img')?.alt || link.dataset.caption || 'UNIORG research screenshot';
    caption.textContent = link.dataset.caption || image.alt;
  };
  links.forEach((link, index) => link.addEventListener('click', event => {
    event.preventDefault(); opener = link; show(index); dialog.showModal(); document.body.classList.add('viewer-open');
  }));
  dialog.querySelector('.viewer-close').addEventListener('click', () => dialog.close());
  dialog.querySelector('.viewer-prev').addEventListener('click', () => show(selected - 1));
  dialog.querySelector('.viewer-next').addEventListener('click', () => show(selected + 1));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') { event.preventDefault(); show(selected - 1); }
    if (event.key === 'ArrowRight') { event.preventDefault(); show(selected + 1); }
  });
  dialog.addEventListener('close', () => { document.body.classList.remove('viewer-open'); opener?.focus(); });
  image.addEventListener('error', () => { caption.textContent = 'This screenshot could not be loaded. Close the viewer and try opening the image link directly.'; });
})();
