// Reposition input inside Set New Password dropdown
const el = document.getElementById('current-password-confirm');
const parent = document.getElementsByClassName('wp-pwd')[0];
parent.insertBefore(el, parent.firstChild);
el.style.marginBottom = '1em';

// Hide no JS fallback UI
const nojsParent = document.getElementById('nojs-current-pass');
nojsParent.style.display = 'none';