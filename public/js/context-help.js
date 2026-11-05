(() => {
  const widget = document.querySelector('[data-context-help]');
  if (!widget) return;

  const button = widget.querySelector('.context-help-button');
  const panel = widget.querySelector('.context-help-panel');
  const closeButton = widget.querySelector('.context-help-close');
  const hoverSupported = window.matchMedia('(hover: hover) and (pointer: fine)');
  const delay = Number(widget.dataset.hoverDelay) || 1000;
  let timer = null;
  let pinned = false;

  const open = (keepOpen = false) => {
    window.clearTimeout(timer);
    pinned = keepOpen;
    panel.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    widget.classList.add('open');
  };
  const close = () => {
    window.clearTimeout(timer);
    pinned = false;
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    widget.classList.remove('open');
  };

  button.addEventListener('click', () => panel.hidden ? open(true) : close());
  closeButton.addEventListener('click', () => {
    close();
    button.focus();
  });
  button.addEventListener('pointerenter', () => {
    if (hoverSupported.matches && panel.hidden) timer = window.setTimeout(() => open(false), delay);
  });
  widget.addEventListener('pointerleave', () => {
    window.clearTimeout(timer);
    if (!pinned) close();
  });
  document.addEventListener('click', event => {
    if (!panel.hidden && !widget.contains(event.target)) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) {
      close();
      button.focus();
    }
  });
})();
