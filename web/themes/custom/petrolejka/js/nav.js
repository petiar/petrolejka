/**
 * @file
 * Rozbaľovacie menu na mobile.
 */
((Drupal, once) => {
  Drupal.behaviors.petrolejkaNav = {
    attach(context) {
      once('petrolejka-nav', '[data-nav-toggle]', context).forEach((button) => {
        const nav = document.getElementById(button.getAttribute('aria-controls'));
        button.addEventListener('click', () => {
          const open = button.getAttribute('aria-expanded') === 'true';
          button.setAttribute('aria-expanded', String(!open));
          nav.classList.toggle('is-open', !open);
        });
      });
    },
  };
})(Drupal, once);
