document.addEventListener('DOMContentLoaded', function () {
  const links = document.querySelectorAll('link[data-css-handle]');

  links.forEach(link => {
    const cssHref = link.getAttribute('href');
    fetch(cssHref)
      .then(response => response.text())
      .then(cssText => {
        let used = false;
        const regex = /([.#][a-zA-Z0-9_-]+)/g;
        const matches = cssText.match(regex) || [];

        matches.forEach(selector => {
          try {
            if (document.querySelector(selector)) {
              used = true;
            }
          } catch (e) {
            // ignore invalid selectors
          }
        });

        // If no selector is used, remove the CSS
        if (!used) {
          link.parentNode.removeChild(link);
          console.log('Removed unused CSS:', cssHref);
        }
      })
      .catch(err => {
        console.error('Error fetching CSS:', cssHref, err);
      });
  });
});
