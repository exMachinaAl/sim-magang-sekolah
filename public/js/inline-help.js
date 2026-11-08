(() => {
  const buttons = Array.from(document.querySelectorAll('[data-inline-help]'));
  if (!buttons.length) return;

  const tooltip = document.createElement('div');
  tooltip.className = 'inline-help-tooltip';
  tooltip.id = 'inline-help-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.hidden = true;
  document.body.appendChild(tooltip);

  const hoverSupported = window.matchMedia('(hover: hover) and (pointer: fine)');
  const delay = 1000;
  let active = null;
  let timer = null;
  let pinned = false;

  const position = button => {
    const rect = button.getBoundingClientRect();
    const width = Math.min(320, window.innerWidth - 24);
    tooltip.style.width = `${width}px`;
    const left = Math.min(Math.max(12, rect.left), window.innerWidth - width - 12);
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${Math.min(rect.bottom + 9, window.innerHeight - tooltip.offsetHeight - 12)}px`;
  };
  const open = (button, keepOpen = false) => {
    window.clearTimeout(timer);
    if (active && active !== button) active.setAttribute('aria-expanded', 'false');
    active = button;
    pinned = keepOpen;
    tooltip.textContent = button.dataset.helpText;
    tooltip.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    position(button);
  };
  const close = () => {
    window.clearTimeout(timer);
    if (active) active.setAttribute('aria-expanded', 'false');
    active = null;
    pinned = false;
    tooltip.hidden = true;
  };

  buttons.forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      if (active === button && !tooltip.hidden && pinned) close(); else open(button, true);
    });
    button.addEventListener('focus', () => open(button, false));
    button.addEventListener('blur', () => { if (!pinned) close(); });
    button.addEventListener('pointerenter', () => {
      if (hoverSupported.matches && active !== button) timer = window.setTimeout(() => open(button, false), delay);
    });
    button.addEventListener('pointerleave', () => {
      window.clearTimeout(timer);
      if (!pinned && active === button) close();
    });
  });
  document.addEventListener('click', event => {
    if (!tooltip.hidden && !event.target.closest('[data-inline-help]')) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !tooltip.hidden) {
      const button = active;
      close();
      button?.focus();
    }
  });
  window.addEventListener('resize', () => { if (active) position(active); });
  window.addEventListener('scroll', () => { if (active) position(active); }, true);
})();
