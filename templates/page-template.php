<?php
defined('ABSPATH') || exit;
get_header();
?>
<style>
/* Hide legacy custom template header & duplicate widgets to display single Elementor theme header */
html body.vcpg-page .vp-topbar,
html body.vcpg-page .vp-header,
html body.vcpg-page header.vp-header,
html body.vcpg-page .vp-nav,
html body.vcpg-page .vp-footer,
html body.vcpg-page footer.vp-footer,
html body.vcpg-page #vcpg-header,
html body.vcpg-page #vcpg-footer,
html body.vcpg-page footer#vcpg-footer,
html body.vcpg-page .vcpg-footer,
html body.vcpg-page .elementor-element-e000003,
html body.vcpg-page .elementor-element-e000043,
html body.vcpg-page .elementor-element-e000044,
html body.vcpg-page [data-id="e000043"],
html body.vcpg-page [data-id="e000044"] {
    display: none !important;
}

/* Core Layout & Colors for all VCPG pages */
html body.vcpg-page {
  --vp-primary: #0B63F6;
  --vp-primary-dark: #094bc4;
  --vp-dark: #0A3663;
  --vp-dark-2: #070D18;
  --vp-white: #FFFFFF;
  --vp-bg: #F8FAFC;
  --vp-text: #334155;
  --vp-text-light: #64748B;
  --vp-border: #E2E8F0;
  --vp-radius: 12px;
  --vp-radius-lg: 16px;
  --vp-shadow: 0 4px 20px rgba(0,0,0,0.05);
  --vp-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

html body.vcpg-page .vp-container { max-width: 1200px !important; margin: 0 auto !important; padding: 0 24px !important; width: 100% !important; box-sizing: border-box !important; }
html body.vcpg-page .vp-section { padding: 80px 0 !important; }
html body.vcpg-page .vp-title { font-size: 2.2rem !important; font-weight: 800 !important; color: #0A3663 !important; line-height: 1.25 !important; margin-bottom: 16px !important; }
html body.vcpg-page .vp-desc { font-size: 1rem !important; color: #334155 !important; line-height: 1.8 !important; max-width: 800px !important; }
html body.vcpg-page .vp-title-center { text-align: center !important; }
html body.vcpg-page .vp-desc-center { margin-left: auto !important; margin-right: auto !important; text-align: center !important; }

/* HERO */
html body.vcpg-page .vp-hero { position: relative !important; padding: 190px 0 90px !important; color: #000000 !important; overflow: hidden !important; background: #FFFFFF !important; }
html body.vcpg-page .vp-hero video { transform: translate(-50%, -50%) scale(1.4) !important; }
html body.vcpg-page .vp-hero-grid { display: grid !important; grid-template-columns: 1fr 480px !important; gap: 60px !important; align-items: center !important; position: relative !important; z-index: 1 !important; }
html body.vcpg-page .vp-hero h1 { font-size: 3.2rem !important; font-weight: 800 !important; line-height: 1.15 !important; margin-bottom: 10px !important; color: #02426A !important; }
html body.vcpg-page .vp-hero h2 { color: #000000 !important; font-size: 1.6rem !important; font-weight: 500 !important; margin-bottom: 20px !important; line-height: 1.3 !important; }
html body.vcpg-page .vp-hero p { font-size: 1.1rem !important; color: #334155 !important; line-height: 1.65 !important; margin-bottom: 30px !important; }
html body.vcpg-page .vp-btn-hero { background: #02426A !important; color: #FFFFFF !important; padding: 14px 32px !important; border-radius: 50px !important; text-decoration: none !important; font-weight: 700 !important; font-size: 0.95rem !important; display: inline-flex !important; align-items: center !important; gap: 10px !important; }

/* Hero inquiry form card border */
html body.vcpg-page .vp-hero-form-card,
html body.vcpg-page .vp-hero-right {
    background: rgba(255, 255, 255, 0.92) !important;
    border: 2px solid #02426A !important;
    border-radius: 30px !important;
    padding: 35px 30px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
    box-sizing: border-box !important;
}
html body.vcpg-page .vp-hero-form-card h3,
html body.vcpg-page .vp-hero-right h3 {
    font-size: 28px !important;
    font-weight: 700 !important;
    margin-bottom: 24px !important;
    text-align: left !important;
    color: #02426A !important;
}

/* Legacy hero left column & typography */
html body.vcpg-page .vp-hero-left h1 {
    font-size: 3.2rem !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
    margin-bottom: 15px !important;
    color: #02426A !important;
}
html body.vcpg-page .vp-hero-left h3 {
    font-size: 1.5rem !important;
    font-weight: 600 !important;
    color: #0A3663 !important;
    margin-bottom: 20px !important;
    line-height: 1.3 !important;
}
html body.vcpg-page .vp-hero-left p {
    font-size: 1.1rem !important;
    color: #334155 !important;
    line-height: 1.65 !important;
    margin-bottom: 24px !important;
}
html body.vcpg-page .vp-hero-left a[href="#contact"] {
    background: #02426A !important;
    color: #FFFFFF !important;
    padding: 14px 32px !important;
    border-radius: 50px !important;
    text-decoration: none !important;
    font-weight: 700 !important;
    font-size: 0.95rem !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
}

/* Legacy page body container & typography */
html body.vcpg-page .vp-legacy-content {
    max-width: 1200px !important;
    margin: 0 auto !important;
    padding: 60px 24px 100px !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    color: #334155 !important;
    line-height: 1.8 !important;
    box-sizing: border-box !important;
}
html body.vcpg-page .vp-legacy-content h2 {
    color: #0A3663 !important;
    font-size: 2.3rem !important;
    font-weight: 800 !important;
    line-height: 1.25 !important;
    margin-top: 80px !important;
    margin-bottom: 24px !important;
    text-align: center !important;
}
html body.vcpg-page .vp-legacy-content h2:first-of-type {
    margin-top: 30px !important;
}
html body.vcpg-page .vp-legacy-content h2 + p {
    font-size: 1.05rem !important;
    color: #334155 !important;
    line-height: 1.8 !important;
    max-width: 860px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    margin-bottom: 35px !important;
    text-align: center !important;
}
html body.vcpg-page .vp-legacy-content h3 {
    color: #02426A !important;
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    margin-top: 36px !important;
    margin-bottom: 12px !important;
}
html body.vcpg-page .vp-legacy-content h4 {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: #02426A !important;
    margin-top: 28px !important;
    margin-bottom: 10px !important;
}
html body.vcpg-page .vp-legacy-content p {
    font-size: 1.05rem !important;
    color: #334155 !important;
    line-height: 1.8 !important;
    margin-bottom: 20px !important;
}
html body.vcpg-page .vp-legacy-content svg[width="40"],
html body.vcpg-page .vp-legacy-content svg[width="42"] {
    display: inline-block !important;
    padding: 12px !important;
    background: #EFF6FF !important;
    border-radius: 12px !important;
    stroke: #02426A !important;
    margin-top: 24px !important;
    margin-bottom: 10px !important;
}
html body.vcpg-page .vp-legacy-content img {
    max-width: 100% !important;
    height: auto !important;
    border-radius: 20px !important;
    margin: 35px auto !important;
    display: block !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
}
html body.vcpg-page .vp-legacy-content p:has(> button[onclick*="vcpgSwitchTab"]),
html body.vcpg-page .vp-legacy-content p:has(button) {
    display: flex !important;
    gap: 12px !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    margin: 35px 0 !important;
}

/* Proposal form styling */
html body.vcpg-page #hero_proposal,
html body.vcpg-page #contact_proposal {
    display: flex !important;
    flex-direction: column !important;
    gap: 16px !important;
}
html body.vcpg-page #hero_proposal input[type="text"],
html body.vcpg-page #hero_proposal input[type="email"],
html body.vcpg-page #hero_proposal input[type="tel"],
html body.vcpg-page #hero_proposal textarea,
html body.vcpg-page #hero_proposal select,
html body.vcpg-page #contact_proposal input[type="text"],
html body.vcpg-page #contact_proposal input[type="email"],
html body.vcpg-page #contact_proposal input[type="tel"],
html body.vcpg-page #contact_proposal textarea,
html body.vcpg-page #contact_proposal select {
    width: 100% !important;
    padding: 13px 22px !important;
    border-radius: 50px !important;
    border: 1px solid #7E7E7E !important;
    background: #F3F4F6 !important;
    color: #1E293B !important;
    font-size: 14px !important;
    box-sizing: border-box !important;
    outline: none !important;
}
html body.vcpg-page #hero_proposal button[type="submit"],
html body.vcpg-page #hero_proposal input[type="submit"],
html body.vcpg-page #contact_proposal button[type="submit"],
html body.vcpg-page #contact_proposal input[type="submit"] {
    width: 100% !important;
    padding: 15px !important;
    border-radius: 50px !important;
    background: #02426A !important;
    color: #FFFFFF !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    border: none !important;
    cursor: pointer !important;
}

/* INTRO */
html body.vcpg-page .vp-intro { background: #FFFFFF !important; text-align: center !important; }

/* ABOUT */
html body.vcpg-page .vp-about { background: #FFFFFF !important; padding: 80px 0 !important; }
html body.vcpg-page .vp-about-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 60px !important; align-items: center !important; }
html body.vcpg-page .vp-feature-card { background: #FFFFFF !important; border: 1px solid #E2E8F0 !important; border-radius: 18px !important; padding: 28px 24px !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; text-align: left !important; box-shadow: 0 6px 24px rgba(0,0,0,0.04) !important; box-sizing: border-box !important; }

/* SERVICES */
html body.vcpg-page .vp-services-sec { background: #F8FAFC !important; padding: 90px 0 !important; }
html body.vcpg-page .vp-services-sec .vp-title { color: #0A3663 !important; }
html body.vcpg-page .vp-services-sec .vp-desc { color: #334155 !important; }
html body.vcpg-page .vp-service-card { background: #FFFFFF !important; border-radius: 16px !important; padding: 36px 32px !important; color: #0A3663 !important; box-shadow: 0 10px 30px rgba(0,0,0,0.06) !important; display: flex !important; flex-direction: column !important; text-align: left !important; height: 100% !important; box-sizing: border-box !important; }

/* WHY CHOOSE */
html body.vcpg-page .vp-why-sec { background: #FFFFFF !important; padding: 90px 0 !important; }
html body.vcpg-page .vp-tabs { display: flex !important; gap: 12px !important; flex-wrap: wrap !important; justify-content: center !important; margin-top: 30px !important; }
html body.vcpg-page .vp-tab-active { background: #FFFFFF !important; color: #081828 !important; border: 1px solid #CBD5E1 !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 700 !important; }
html body.vcpg-page .vp-tab-dark { background: #0F172A !important; color: #FFFFFF !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 600 !important; }

/* CASE STUDY */
html body.vcpg-page .vp-casestudy-sec { background: #FFFFFF !important; padding: 90px 0 !important; }
html body.vcpg-page .vp-casestudy-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 50px !important; align-items: stretch !important; }
html body.vcpg-page .vp-casestudy-grid img { width: 100% !important; height: 100% !important; min-height: 100% !important; border-radius: 24px !important; box-shadow: 0 12px 36px rgba(2,66,106,0.12) !important; display: block !important; object-fit: cover !important; margin: 0 !important; }

/* LOGOS */
html body.vcpg-page .vp-logos-bar { padding: 40px 0 !important; background: #FFFFFF !important; border-top: 1px solid #E2E8F0 !important; border-bottom: 1px solid #E2E8F0 !important; }
html body.vcpg-page .vcpg-marquee-container { width: 100% !important; overflow: hidden !important; position: relative !important; padding: 16px 0 !important; }
html body.vcpg-page .vcpg-marquee-track { display: flex !important; align-items: center !important; width: max-content !important; }

/* TESTIMONIAL */
html body.vcpg-page .vp-testi-sec { background: #FFFFFF !important; padding: 80px 0 !important; text-align: center !important; }

/* CERTIFICATIONS */
html body.vcpg-page .vp-cert-sec { background: #F8FAFC !important; padding: 80px 0 !important; text-align: center !important; }

/* CONTACT FORM */
html body.vcpg-page .vp-contact-sec { background: #070D18 !important; padding: 90px 0 !important; }
html body.vcpg-page .vp-contact-sec .vp-contact-card,
html body.vcpg-page .vp-contact-card {
    max-width: 760px !important;
    margin: 0 auto !important;
    background: #FFFFFF !important;
    border-radius: 20px !important;
    padding: 48px 36px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
    border: none !important;
    box-sizing: border-box !important;
}
html body.vcpg-page .vp-contact-card h2,
html body.vcpg-page .vp-contact-card h3 {
    text-align: center !important;
    color: #02426A !important;
    margin-top: 0 !important;
    margin-bottom: 24px !important;
}

/* Suppress unwanted portfolio section */
html body.vcpg-page .vp-portfolio-sec {
    display: none !important;
}

/* Suppress unwanted empty capsule box above hero header */
html body.vcpg-page .vp-hero div[style*="border-radius:30px"]:empty,
html body.vcpg-page .vp-hero div[style*="border-radius: 30px"]:empty,
html body.vcpg-page .vp-hero-city-label {
    display: none !important;
}

/* Universal Layout Standards for All Generated Pages */
html body.vcpg-page .vp-services-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 24px !important;
}
@media (max-width: 768px) {
    html body.vcpg-page .vp-services-grid {
        grid-template-columns: 1fr !important;
    }
}

html body.vcpg-page .vp-cert-card {
    max-width: 1180px !important;
    margin: 0 auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 48px !important;
    box-sizing: border-box !important;
    padding: 24px 32px !important;
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 20px !important;
    box-shadow: 0 8px 30px rgba(0,0,0,0.04) !important;
}
@media (max-width: 900px) {
    html body.vcpg-page .vp-cert-card {
        flex-direction: column !important;
        gap: 24px !important;
        padding: 20px !important;
    }
}

/* Fallback: Ensure partner logos never stack vertically */
html body.vcpg-page .vp-logos-bar,
html body.vcpg-page .vcpg-marquee-container {
    display: flex !important;
    overflow: hidden !important;
    width: 100% !important;
    align-items: center !important;
}

@media (max-width: 900px) {
  html body.vcpg-page .vp-hero-grid, html body.vcpg-page .vp-about-grid, html body.vcpg-page .vp-footer-grid, html body.vcpg-page .vp-casestudy-grid { grid-template-columns: 1fr !important; gap: 40px !important; }
  html body.vcpg-page .vp-casestudy-grid > div:first-child { order: 1 !important; }
  html body.vcpg-page .vp-casestudy-grid > div:last-child { order: 2 !important; }
}

html body.vcpg-page .elementor-widget-heading .elementor-heading-title { color: #0A3663; font-weight: 800 !important; }
html body.vcpg-page h1.elementor-heading-title, html body.vcpg-page .vp-hero .elementor-heading-title { color: #02426A !important; }


/* Global Image Sizing & Aspect Ratio Protections */
html body.vcpg-page img {
    max-width: 100%;
    height: auto;
}
html body.vcpg-page .vp-about-grid img,
html body.vcpg-page .vp-about img {
    max-height: 520px !important;
    width: auto !important;
    max-width: 100% !important;
    object-fit: cover !important;
    display: block !important;
    margin: 0 auto !important;
}
html body.vcpg-page .vp-cta-sec img,
html body.vcpg-page .vp-cta img {
    max-height: 380px !important;
    width: auto !important;
    max-width: 100% !important;
    object-fit: contain !important;
}
html body.vcpg-page .vp-casestudy-grid img,
html body.vcpg-page .vp-casestudy img {
    max-height: 500px !important;
    width: 100% !important;
    object-fit: cover !important;
}

/* Ensure Theme & ElementsKit Header is 100% visible at scroll 0 */
html body.vcpg-page .ekit-template-content-header,
html body.vcpg-page header.elementskit-menu-container,
html body.vcpg-page .elementor-location-header,
html body.vcpg-page .elementor-35930 {
    position: relative !important;
    z-index: 999999 !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    background-color: #FFFFFF !important;
    width: 100% !important;
}
html body.vcpg-page .elementor-35930 .elementor-element.elementor-element-8eb9496 {
    position: relative !important;
    z-index: 999999 !important;
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100% !important;
}
html body.vcpg-page .elementor-35930 .elementor-element.elementor-element-8602ba9 {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    background-color: #FFFFFF !important;
}
html body.vcpg-page .vp-hero {
    position: relative !important;
    z-index: 1 !important;
    margin-top: 0 !important;
}

/* Custom Interactive Cursor Styles */
html body.vcpg-page .vcpg-custom-cursor-dot {
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
html body.vcpg-page .vcpg-custom-cursor-outline {
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
html body.vcpg-page .vcpg-back-to-top {
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
html body.vcpg-page .vcpg-back-to-top.is-active {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
html body.vcpg-page .vcpg-progress-circle {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}
html body.vcpg-page .vcpg-progress-bg {
  fill: none;
  stroke: #F1F5F9;
  stroke-width: 8;
}
html body.vcpg-page .vcpg-progress-bar {
  fill: none;
  stroke: #121212;
  stroke-width: 8;
  stroke-linecap: round;
  stroke-dasharray: 276.46;
  stroke-dashoffset: 276.46;
  transition: stroke-dashoffset 0.1s linear;
}
html body.vcpg-page .vcpg-back-to-top-icon {
  color: #121212;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.3s ease;
}
html body.vcpg-page .vcpg-back-to-top:hover .vcpg-back-to-top-icon {
  transform: translateY(-2px);
}

/* Custom reCAPTCHA Badge */
html body.vcpg-page .vcpg-recaptcha-badge {
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
html body.vcpg-page .vcpg-recaptcha-badge:hover {
  transform: translateX(0);
}
html body.vcpg-page .vcpg-recaptcha-inner {
  display: flex;
  align-items: center;
  height: 60px;
}
html body.vcpg-page .vcpg-recaptcha-text {
  width: 186px;
  padding: 0 14px 0 10px;
  font-family: Roboto, helvetica, arial, sans-serif;
  box-sizing: border-box;
}
html body.vcpg-page .vcpg-recaptcha-text-main {
  font-size: 10px;
  color: #555555;
  line-height: 1.2;
}
html body.vcpg-page .vcpg-recaptcha-text-main strong {
  font-weight: 600;
  color: #333333;
}
html body.vcpg-page .vcpg-recaptcha-links {
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 8px;
  color: #a6a6a6;
}
html body.vcpg-page .vcpg-recaptcha-links a {
  color: #a6a6a6 !important;
  text-decoration: none !important;
}
html body.vcpg-page .vcpg-recaptcha-links a:hover {
  text-decoration: underline !important;
}
html body.vcpg-page .vcpg-recaptcha-separator {
  color: #a6a6a6;
}
html body.vcpg-page .vcpg-recaptcha-logo-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50px;
  height: 60px;
  background-color: #FFFFFF;
}

</style>
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


<main class="vcpg-page-content is-vcpg-page">
<?php
remove_filter('the_content', 'wpautop');

if (!class_exists('VCPG_Elementor_Template_Builder')) {
    $builder_file = dirname(__DIR__) . '/includes/class-elementor-template-builder.php';
    if (file_exists($builder_file)) {
        require_once $builder_file;
    }
}
if (!class_exists('VCPG_Page_Generator')) {
    $gen_file = dirname(__DIR__) . '/includes/class-page-generator.php';
    if (file_exists($gen_file)) {
        require_once $gen_file;
    }
}

while(have_posts()): the_post();
    $post_id     = get_the_ID();
    $raw_content = get_the_content();

    // If content is already built with the modern unified template (contains VCPG marker or all major grid sections), output it directly
    if (strpos($raw_content, '<!-- VCPG-TEMPLATE') !== false || (strpos($raw_content, 'vp-hero-grid') !== false && strpos($raw_content, 'vp-about-grid') !== false && strpos($raw_content, 'vp-casestudy-sec') !== false)) {
        echo do_shortcode($raw_content);
    } else {
        // Render earlier generated page using the unified template engine!
        $city         = get_post_meta($post_id, '_vcpg_city', true);
        $state        = get_post_meta($post_id, '_vcpg_state', true);
        $country      = get_post_meta($post_id, '_vcpg_country', true);
        $country_code = get_post_meta($post_id, '_vcpg_country_code', true);
        $service      = get_post_meta($post_id, '_vcpg_service', true);
        $faq          = get_post_meta($post_id, '_vcpg_faq', true);

        global $post;
        $title = $post ? $post->post_title : get_the_title();
        $slug  = $post ? $post->post_name : '';

        // Derive country from parent page if empty
        if (empty($country) && $post && $post->post_parent > 0) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                $parent_slug = strtolower($parent->post_name);
                if ($parent_slug === 'us' || $parent_slug === 'united-states') {
                    $country      = 'United States';
                    $country_code = 'us';
                } elseif ($parent_slug === 'in' || $parent_slug === 'india') {
                    $country      = 'India';
                    $country_code = 'in';
                } else {
                    $country      = ucwords(str_replace('-', ' ', $parent_slug));
                    $country_code = $parent_slug;
                }
            }
        }

        // Derive service and city if empty
        if (empty($service) || empty($city)) {
            if (strpos($slug, '-in-') !== false) {
                list($svc_part, $loc_part) = explode('-in-', $slug, 2);
                if (empty($service)) {
                    $service = ucwords(str_replace('-', ' ', $svc_part));
                }
                if (empty($city)) {
                    $loc_words = explode('-', $loc_part);
                    $city = ucwords($loc_words[0]);
                    if (empty($state) && count($loc_words) > 1) {
                        $state = ucwords($loc_words[1]);
                    }
                }
            } elseif (preg_match('/^(.*?)\s+(?:in|for)\s+([A-Za-z\s]+)(?:,\s*([A-Za-z\s]+))?$/i', $title, $tm)) {
                if (empty($service)) {
                    $service = trim($tm[1]);
                }
                if (empty($city)) {
                    $city = trim($tm[2]);
                }
                if (empty($state) && !empty($tm[3])) {
                    $state = trim($tm[3]);
                }
            }
        }

        if (empty($service)) $service = 'Digital Marketing Services';
        if (empty($city)) $city = 'Your City';

        $data = array(
            'service'      => $service,
            'city'         => $city,
            'state'        => $state,
            'country'      => !empty($country) ? $country : 'United States',
            'country_code' => !empty($country_code) ? $country_code : 'us',
        );
        if (!empty($faq)) {
            $data['faq'] = $faq;
        }

        // Check if database has cached AI content for this service & city
        global $wpdb;
        $table = $wpdb->prefix . 'vcpg_ai_content';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            $cached_ai = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT content FROM $table WHERE service = %s AND city = %s LIMIT 1",
                    $service,
                    $city
                )
            );
            if ($cached_ai && !empty($cached_ai->content)) {
                $decoded = json_decode($cached_ai->content, true);
                if (is_array($decoded)) {
                    $data = array_merge($data, $decoded);
                }
            }
        }

        // Extract custom headlines from existing page content if not already populated
        if (empty($data['hero_title']) && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $raw_content, $m)) {
            $data['hero_title'] = trim(strip_tags($m[1]));
        }
        if (empty($data['hero_subtitle']) && preg_match('/<h3[^>]*>(.*?)<\/h3>/is', $raw_content, $m)) {
            $data['hero_subtitle'] = trim(strip_tags($m[1]));
        }
        if (empty($data['intro_title']) && preg_match('/<h2[^>]*>((?:Get|Why|Elevate).*?)<\/h2>(.*?)(?=<h2)/is', $raw_content, $m)) {
            $data['intro_title']   = trim(strip_tags($m[1]));
            $data['intro_content'] = trim($m[2]);
        }
        if (empty($data['about_title']) && preg_match('/<h2[^>]*>((?:Creating|About|Proven|Dedicated|Transform|Unlock|Strategic).*?)<\/h2>/is', $raw_content, $m)) {
            $data['about_title'] = trim(strip_tags($m[1]));
        }

        if (class_exists('VCPG_Elementor_Template_Builder')) {
            $builder = new VCPG_Elementor_Template_Builder();
            echo $builder->build_html($data);
        } else {
            the_content();
        }
    }
endwhile;
?>
</main>

<script>
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
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
});
</script>
<?php
get_footer();
?>