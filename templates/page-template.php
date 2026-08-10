<?php
defined('ABSPATH') || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
html, body { margin: 0; padding: 0; width: 100%; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
.elementor-widget-heading .elementor-heading-title { color: #0A3663; font-weight: 800 !important; }
h1.elementor-heading-title, .vp-hero .elementor-heading-title { color: #FFFFFF !important; }
.elementor-widget-text-editor { color: #334155; line-height: 1.8 !important; }
footer a, .vp-footer a { color: #CBD5E1 !important; text-decoration: none !important; }
footer a:hover, .vp-footer a:hover { color: #FFFFFF !important; }

/* Lenis Smooth Scroll Styles */
html.lenis, html.lenis body {
  height: auto;
}
.lenis.lenis-smooth {
  scroll-behavior: auto !important;
}
.lenis.lenis-smooth [data-lenis-prevent] {
  overscroll-behavior: contain;
}
.lenis.lenis-stopped {
  overflow: hidden;
}
.lenis.lenis-scrolling iframe {
  pointer-events: none;
}

/* Custom Interactive Cursor Styles */
.vcpg-custom-cursor-dot {
  display: none !important;
  width: 8px;
  height: 8px;
  background-color: #FFFFFF;
  border-radius: 50%;
  position: fixed;
  transform: translate(-50%, -50%);
  pointer-events: none;
  z-index: 999999;
  opacity: 0;
  mix-blend-mode: difference;
  transition: width 0.15s ease, height 0.15s ease, opacity 0.15s ease;
}
.vcpg-custom-cursor-outline {
  display: none !important;
  width: 40px;
  height: 40px;
  border: 1.5px solid rgba(255, 255, 255, 0.6);
  border-radius: 50%;
  position: fixed;
  transform: translate(-50%, -50%);
  pointer-events: none;
  z-index: 999998;
  opacity: 0;
  mix-blend-mode: difference;
  transition: width 0.25s cubic-bezier(0.25, 1, 0.5, 1), height 0.25s cubic-bezier(0.25, 1, 0.5, 1), border-color 0.25s ease, opacity 0.15s ease;
}

/* Scroll Progress & Back-to-Top Button */
.vcpg-back-to-top {
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 40px;
  height: 40px;
  background-color: #FFFFFF;
  border-radius: 50%;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  cursor: pointer;
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transform: translateY(10px);
  transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease, background-color 0.3s ease;
}
.vcpg-back-to-top.is-active {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
.vcpg-progress-circle {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}
.vcpg-progress-bg {
  fill: none;
  stroke: #F1F5F9;
  stroke-width: 8;
}
.vcpg-progress-bar {
  fill: none;
  stroke: #121212;
  stroke-width: 8;
  stroke-linecap: round;
  stroke-dasharray: 276.46;
  stroke-dashoffset: 276.46;
  transition: stroke-dashoffset 0.1s linear;
}
.vcpg-back-to-top-icon {
  color: #121212;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.3s ease;
}
.vcpg-back-to-top:hover .vcpg-back-to-top-icon {
  transform: translateY(-2px);
}

/* Custom reCAPTCHA Badge */
.vcpg-recaptcha-badge {
  position: fixed;
  bottom: 14px;
  right: 0;
  z-index: 99998;
  background-color: #FFFFFF;
  border-radius: 2px 0 0 2px;
  box-shadow: 0 0 4px rgba(0,0,0,0.14), 0 4px 8px rgba(0,0,0,0.28);
  overflow: hidden;
  transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1);
  transform: translateX(186px);
  width: 236px;
  height: 60px;
}
.vcpg-recaptcha-badge:hover {
  transform: translateX(0);
}
.vcpg-recaptcha-inner {
  display: flex;
  align-items: center;
  height: 60px;
}
.vcpg-recaptcha-text {
  width: 186px;
  padding: 0 14px 0 10px;
  font-family: Roboto, helvetica, arial, sans-serif;
  box-sizing: border-box;
}
.vcpg-recaptcha-text-main {
  font-size: 10px;
  color: #555555;
  line-height: 1.2;
}
.vcpg-recaptcha-text-main strong {
  font-weight: 600;
  color: #333333;
}
.vcpg-recaptcha-links {
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 8px;
  color: #a6a6a6;
}
.vcpg-recaptcha-links a {
  color: #a6a6a6 !important;
  text-decoration: none !important;
}
.vcpg-recaptcha-links a:hover {
  text-decoration: underline !important;
}
.vcpg-recaptcha-separator {
  color: #a6a6a6;
}
.vcpg-recaptcha-logo-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50px;
  height: 60px;
  background-color: #FFFFFF;
}

</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="vcpg-custom-cursor-dot"></div>
<div class="vcpg-custom-cursor-outline"></div>

<!-- Custom Scroll Progress Back-To-Top Button -->
<div class="vcpg-back-to-top">
  <svg class="vcpg-progress-circle" viewBox="0 0 100 100">
    <circle class="vcpg-progress-bg" cx="50" cy="50" r="44"></circle>
    <circle class="vcpg-progress-bar" cx="50" cy="50" r="44"></circle>
  </svg>
  <div class="vcpg-back-to-top-icon">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" width="16" height="16">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
  </div>
</div>

<!-- Custom reCAPTCHA Badge -->
<div class="vcpg-recaptcha-badge">
  <div class="vcpg-recaptcha-inner">
    <div class="vcpg-recaptcha-logo-wrapper">
      <svg class="vcpg-recaptcha-logo" viewBox="0 15 150 105" width="28" height="28">
        <path d="m117 62.063c-2e-3 -0.60232-0.0159-1.2014-0.0429-1.7976v-33.991l-9.3971 9.3971c-7.691-9.4141-19.391-15.427-32.496-15.427-13.638 0-25.754 6.5097-33.413 16.591l15.403 15.565c1.5095-2.7917 3.6539-5.1895 6.2395-7.0005 2.6891-2.0985 6.4993-3.8143 11.77-3.8143 0.63674 0 1.1282 0.0744 1.4893 0.21458 6.5304 0.51543 12.191 4.1194 15.524 9.3503l-10.903 10.903c13.81-0.0542 29.411-0.086 35.825 7e-3" fill="#1c3aa9"/>
        <path d="m74.819 20.246c-0.60232 2e-3 -1.2014 0.0159-1.7976 0.0429h-33.991l9.3971 9.3971c-9.4141 7.691-15.427 19.391-15.427 32.496 0 13.638 6.5098 25.754 16.591 33.413l15.565-15.403c-2.7917-1.5095-5.1895-3.6539-7.0005-6.2395-2.0984-2.6891-3.8143-6.4993-3.8143-11.77 0-0.63674 0.0744-1.1282 0.21458-1.4893 0.51543-6.5304 4.1194-12.191 9.3503-15.524l10.903 10.903c-0.0542-13.81-0.0861-29.411 7e-3 -35.825" fill="#4285f4"/>
        <path d="m33.002 62.181c2e-3 0.60232 0.0159 1.2014 0.0429 1.7976v33.991l9.3971-9.3971c7.691 9.4141 19.391 15.427 32.496 15.427 13.638 0 25.754-6.5097 33.413-16.591l-15.403-15.565c-1.5095 2.7917-3.6539 5.1895-6.2395 7.0005-2.6891 2.0985-6.4993 3.8143-11.77 3.8143-0.63674 0-1.1282-0.0744-1.4893-0.21458-6.5304-0.51543-12.191-4.1194-15.524-9.3503l10.903-10.903c-13.81 0.0542-29.411 0.086-35.825-7e-3" fill="#ababab"/>
      </svg>
    </div>
    <div class="vcpg-recaptcha-text">
      <div class="vcpg-recaptcha-text-main">protected by <strong>reCAPTCHA</strong></div>
      <div class="vcpg-recaptcha-links">
        <a href="https://policies.google.com/privacy" target="_blank" rel="noreferrer">Privacy</a>
        <span class="vcpg-recaptcha-separator">-</span>
        <a href="https://policies.google.com/terms" target="_blank" rel="noreferrer">Terms</a>
      </div>
    </div>
  </div>
</div>


<?php
while(have_posts()): the_post();
    the_content();
endwhile;
wp_footer();
?>

<!-- Lenis Smooth Scrolling CDN -->
<script src="https://unpkg.com/lenis@1.1.13/dist/lenis.min.js"></script>
<script>
// Initialize Lenis Smooth Scroll
const lenis = new Lenis({
  duration: 1.2,
  easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
  direction: 'vertical',
  gestureDirection: 'vertical',
  smooth: true,
  mouseMultiplier: 1,
  smoothTouch: false,
  touchMultiplier: 2,
  infinite: false,
});

function raf(time) {
  lenis.raf(time);
  requestAnimationFrame(raf);
}
requestAnimationFrame(raf);

// Custom Interactive Cursor Script
document.addEventListener('DOMContentLoaded', () => {
  return; // Temporarily paused trailing mouse-follow cursor
  const dot = document.querySelector('.vcpg-custom-cursor-dot');
  const outline = document.querySelector('.vcpg-custom-cursor-outline');
  
  if (!dot || !outline) return;

  // Detect touch devices
  const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  if (isTouchDevice) {
    dot.style.display = 'none';
    outline.style.display = 'none';
    return;
  }

  let mouseX = 0, mouseY = 0;
  let outlineX = 0, outlineY = 0;

  document.addEventListener('mousemove', (e) => {
    mouseX = e.clientX;
    mouseY = e.clientY;
    
    dot.style.left = mouseX + 'px';
    dot.style.top = mouseY + 'px';
    
    // Reveal cursor elements on first move
    dot.style.opacity = '1';
    outline.style.opacity = '1';
  });

  // Animate outer trailing circle with inertia/easing
  function animateOutline() {
    outlineX += (mouseX - outlineX) * 0.08;
    outlineY += (mouseY - outlineY) * 0.08;
    
    outline.style.left = outlineX + 'px';
    outline.style.top = outlineY + 'px';
    
    requestAnimationFrame(animateOutline);
  }
  requestAnimationFrame(animateOutline);

  // Hide cursor when leaving viewport
  document.addEventListener('mouseleave', () => {
    dot.style.opacity = '0';
    outline.style.opacity = '0';
  });
  
  document.addEventListener('mouseenter', () => {
    dot.style.opacity = '1';
    outline.style.opacity = '1';
  });

  // Expand outline on hovering clickables
  const updateHoverTargets = () => {
    const clickables = document.querySelectorAll('a, button, input, textarea, select, [role="button"], .elementor-clickable');
    clickables.forEach(el => {
      if (el.dataset.cursorBound) return;
      el.dataset.cursorBound = '1';

      el.addEventListener('mouseenter', () => {
        outline.style.width = '60px';
        outline.style.height = '60px';
        outline.style.borderColor = 'rgba(255, 255, 255, 0.9)';
        dot.style.width = '0px';
        dot.style.height = '0px';
      });
      el.addEventListener('mouseleave', () => {
        outline.style.width = '40px';
        outline.style.height = '40px';
        outline.style.borderColor = 'rgba(255, 255, 255, 0.6)';
        dot.style.width = '8px';
        dot.style.height = '8px';
      });
    });
  };

  updateHoverTargets();
  const observer = new MutationObserver(updateHoverTargets);
  observer.observe(document.body, { childList: true, subtree: true });
});

// Scroll Progress and Back-to-Top Logic
document.addEventListener('DOMContentLoaded', () => {
  const backToTop = document.querySelector('.vcpg-back-to-top');
  const progressBar = document.querySelector('.vcpg-progress-bar');
  
  if (!backToTop || !progressBar) return;

  const totalLength = 276.46; // 2 * PI * r
  progressBar.style.strokeDasharray = totalLength;
  progressBar.style.strokeDashoffset = totalLength;

  const updateScrollProgress = () => {
    const scrollPosition = window.scrollY;
    const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
    
    if (documentHeight > 0) {
      const progress = scrollPosition / documentHeight;
      const offset = totalLength - (progress * totalLength);
      progressBar.style.strokeDashoffset = offset;
    }

    if (scrollPosition > 150) {
      backToTop.classList.add('is-active');
    } else {
      backToTop.classList.remove('is-active');
    }
  };

  window.addEventListener('scroll', updateScrollProgress);
  window.addEventListener('resize', updateScrollProgress);
  updateScrollProgress();

  backToTop.addEventListener('click', () => {
    if (typeof lenis !== 'undefined') {
      lenis.scrollTo(0, { duration: 1.2 });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
});
</script>
</body>
</html>