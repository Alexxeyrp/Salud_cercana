document.addEventListener('DOMContentLoaded', () => {
  const button = document.querySelector('.nav-toggle');
  const menu = document.querySelector('.nav-links');
  if (!button || !menu) return;
  button.addEventListener('click', () => {
    const abierto = menu.classList.toggle('open');
    button.setAttribute('aria-expanded', String(abierto));
  });
});
