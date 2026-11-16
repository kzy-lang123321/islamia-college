const fs = require('fs');
const path = require('path');
const { brand } = require('./build_data');

const root = 'C:\\Ameen Consultancy';

function head(title, desc) {
  return '<!DOCTYPE html>\n' +
'<html lang=\"en\" data-theme=\"emerald\">\n' +
'<head>\n' +
'  <meta charset=\"UTF-8\">\n' +
'  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n' +
'  <title>' + title + ' | Ameen Consultancy (Pvt) Ltd</title>\n' +
'  <meta name=\"description\" content=\"' + (desc || 'Your Trusted Partner for International Education & Career Success. SECP Registered Reg. No. 0315168.') + '\">\n' +
'  <link rel=\"icon\" href=\"assets/images/logo.jpg\" type=\"image/jpeg\">\n' +
'  <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n' +
'  <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n' +
'  <link href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">\n' +
'  <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\">\n' +
'  <link rel=\"stylesheet\" href=\"assets/css/style.css\">\n' +
'</head>\n' +
'<body>\n';
}

function nav(act) {
  const isA = (p) => act === p ? 'active' : '';
  return '<div class=\"topbar\">\n' +
'  <div class=\"container topbar-inner\">\n' +
'    <div class=\"topbar-left\">\n' +
'      <span><i class=\"fas fa-certificate text-accent\"></i> SECP Reg. No: <strong>0315168</strong> (Inc. 13 Nov 2025)</span>\n' +
'      <span class=\"topbar-divider\">|</span>\n' +
'      <span><i class=\"fas fa-map-marker-alt text-accent\"></i> Matta Swat, Khyber Pakhtunkhwa</span>\n' +
'    </div>\n' +
'    <div class=\"topbar-right\">\n' +
'      <a href=\"tel:0946791780\"><i class=\"fas fa-phone-alt\"></i> 0946-791780</a>\n' +
'      <a href=\"tel:03157500060\"><i class=\"fas fa-mobile-alt\"></i> 0315-7500060</a>\n' +
'      <a href=\"mailto:ameenconsultancy.pk@gmail.com\"><i class=\"fas fa-envelope\"></i> ameenconsultancy.pk@gmail.com</a>\n' +
'    </div>\n' +
'  </div>\n' +
'</div>\n' +
'<header class=\"site-header\" id=\"siteHeader\">\n' +
'  <div class=\"container nav-container\">\n' +
'    <a href=\"index.html\" class=\"brand-logo\">\n' +
'      <img src=\"assets/images/logo.jpg\" alt=\"Ameen Consultancy Logo\" class=\"logo-img\">\n' +
'      <div class=\"brand-info\">\n' +
'        <span class=\"brand-name\">AMEEN CONSULTANCY</span>\n' +
'        <span class=\"brand-tag\">(PVT) LIMITED</span>\n' +
'      </div>\n' +
'    </a>\n' +
'    <nav class=\"nav-menu\" id=\"navMenu\">\n' +
'      <ul class=\"nav-list\">\n' +
'        <li><a href=\"index.html\" class=\"nav-link ' + isA('home') + '\">Home</a></li>\n' +
'        <li><a href=\"about.html\" class=\"nav-link ' + isA('about') + '\">About Us</a></li>\n' +
'        <li><a href=\"services.html\" class=\"nav-link ' + isA('services') + '\">Services</a></li>\n' +
'        <li class=\"nav-dropdown\">\n' +
'          <a href=\"destinations.html\" class=\"nav-link dropdown-toggle ' + isA('destinations') + '\">Destinations <i class=\"fas fa-chevron-down nav-arrow\"></i></a>\n' +
'          <ul class=\"dropdown-menu\">\n' +
'            <li><a href=\"china.html\"><img src=\"assets/images/flag_china.png\" class=\"menu-flag\" alt=\"China\"> China</a></li>\n' +
'            <li><a href=\"turkey.html\"><img src=\"assets/images/flag_turkey.png\" class=\"menu-flag\" alt=\"Turkey\"> Turkey</a></li>\n' +
'            <li><a href=\"hong-kong.html\"><img src=\"assets/images/flag_hong_kong.png\" class=\"menu-flag\" alt=\"Hong Kong\"> Hong Kong</a></li>\n' +
'            <li><a href=\"south-korea.html\"><img src=\"assets/images/flag_south_korea.png\" class=\"menu-flag\" alt=\"South Korea\"> South Korea</a></li>\n' +
'            <li><a href=\"usa.html\"><img src=\"assets/images/flag_usa.png\" class=\"menu-flag\" alt=\"USA\"> USA</a></li>\n' +
'            <li><a href=\"canada.html\"><img src=\"assets/images/flag_canada.png\" class=\"menu-flag\" alt=\"Canada\"> Canada</a></li>\n' +
'            <li><a href=\"uk.html\"><img src=\"assets/images/flag_uk.png\" class=\"menu-flag\" alt=\"UK\"> United Kingdom</a></li>\n' +
'            <li><a href=\"albania.html\"><img src=\"assets/images/flag_albania.png\" class=\"menu-flag\" alt=\"Albania\"> Albania</a></li>\n' +
'            <li><a href=\"cyprus.html\"><img src=\"assets/images/flag_cyprus.png\" class=\"menu-flag\" alt=\"Cyprus\"> European Cyprus</a></li>\n' +
'            <li><a href=\"lithuania.html\"><img src=\"assets/images/flag_lithuania.png\" class=\"menu-flag\" alt=\"Lithuania\"> Lithuania</a></li>\n' +
'          </ul>\n' +
'        </li>\n' +
'        <li><a href=\"contact.html\" class=\"nav-link ' + isA('contact') + '\">Contact</a></li>\n' +
'      </ul>\n' +
'    </nav>\n' +
'    <div class=\"nav-actions\">\n' +
'      <div class=\"theme-switch-wrapper\">\n' +
'        <button class=\"theme-btn\" id=\"themeToggleBtn\" title=\"Switch Theme\" aria-label=\"Theme Switcher\">\n' +
'          <i class=\"fas fa-palette\"></i>\n' +
'          <span class=\"theme-current-name\">Theme</span>\n' +
'        </button>\n' +
'        <div class=\"theme-menu\" id=\"themeMenu\">\n' +
'          <button class=\"theme-opt\" data-set-theme=\"emerald\"><span class=\"theme-dot dot-emerald\"></span> Emerald (Default)</button>\n' +
'          <button class=\"theme-opt\" data-set-theme=\"dark\"><span class=\"theme-dot dot-dark\"></span> Midnight Dark</button>\n' +
'          <button class=\"theme-opt\" data-set-theme=\"royal\"><span class=\"theme-dot dot-royal\"></span> Royal Blue &amp; Gold</button>\n' +
'        </div>\n' +
'      </div>\n' +
'      <a href=\"contact.html\" class=\"btn btn-primary btn-sm\"><i class=\"fas fa-paper-plane\"></i> Free Consultation</a>\n' +
'      <button class=\"mobile-toggle\" id=\"mobileToggle\" aria-label=\"Toggle Navigation Menu\"><span></span><span></span><span></span></button>\n' +
'    </div>\n' +
'  </div>\n' +
'</header>\n';
}

function footer() {
  return '<footer class=\"site-footer\">\n' +
'  <div class=\"footer-main\">\n' +
'    <div class=\"container\">\n' +
'      <div class=\"footer-grid\">\n' +
'        <div class=\"footer-col footer-col-brand\">\n' +
'          <div class=\"footer-brand\">\n' +
'            <img src=\"assets/images/logo.jpg\" alt=\"Ameen Consultancy Logo\" class=\"footer-logo-img\">\n' +
'            <div>\n' +
'              <h3 class=\"footer-brand-title\">AMEEN CONSULTANCY</h3>\n' +
'              <span class=\"footer-brand-sub\">(PRIVATE) LIMITED</span>\n' +
'            </div>\n' +
'          </div>\n' +
'          <p class=\"footer-tagline\">\"Your Trusted Partner for International Education &amp; Career Success\"</p>\n' +
'          <p class=\"footer-desc\">Dedicated to helping Pakistani students achieve their dreams of studying abroad with end-to-end support from university admission to visa processing and global career placement.</p>\n' +
'          <div class=\"footer-badge\">\n' +
'            <i class=\"fas fa-shield-alt text-accent\"></i>\n' +
'            <div><strong>SECP Registered Entity</strong><span>Reg. No: 0315168 | Incorporated 13 Nov 2025</span></div>\n' +
'          </div>\n' +
'        </div>\n' +
'        <div class=\"footer-col\">\n' +
'          <h4 class=\"footer-title\">Study Destinations</h4>\n' +
'          <ul class=\"footer-links country-links\">\n' +
'            <li><a href=\"china.html\"><img src=\"assets/images/flag_china.png\" class=\"f-flag\" alt=\"China\"> China</a></li>\n' +
'            <li><a href=\"turkey.html\"><img src=\"assets/images/flag_turkey.png\" class=\"f-flag\" alt=\"Turkey\"> Turkey</a></li>\n' +
'            <li><a href=\"hong-kong.html\"><img src=\"assets/images/flag_hong_kong.png\" class=\"f-flag\" alt=\"Hong Kong\"> Hong Kong</a></li>\n' +
'            <li><a href=\"south-korea.html\"><img src=\"assets/images/flag_south_korea.png\" class=\"f-flag\" alt=\"South Korea\"> South Korea</a></li>\n' +
'            <li><a href=\"usa.html\"><img src=\"assets/images/flag_usa.png\" class=\"f-flag\" alt=\"USA\"> USA</a></li>\n' +
'            <li><a href=\"canada.html\"><img src=\"assets/images/flag_canada.png\" class=\"f-flag\" alt=\"Canada\"> Canada</a></li>\n' +
'            <li><a href=\"uk.html\"><img src=\"assets/images/flag_uk.png\" class=\"f-flag\" alt=\"UK\"> United Kingdom</a></li>\n' +
'            <li><a href=\"albania.html\"><img src=\"assets/images/flag_albania.png\" class=\"f-flag\" alt=\"Albania\"> Albania</a></li>\n' +
'            <li><a href=\"cyprus.html\"><img src=\"assets/images/flag_cyprus.png\" class=\"f-flag\" alt=\"Cyprus\"> European Cyprus</a></li>\n' +
'            <li><a href=\"lithuania.html\"><img src=\"assets/images/flag_lithuania.png\" class=\"f-flag\" alt=\"Lithuania\"> Lithuania</a></li>\n' +
'          </ul>\n' +
'        </div>\n' +
'        <div class=\"footer-col\">\n' +
'          <h4 class=\"footer-title\">Our Services</h4>\n' +
'          <ul class=\"footer-links\">\n' +
'            <li><a href=\"services.html#admissions\"><i class=\"fas fa-chevron-right text-accent\"></i> University Admissions</a></li>\n' +
'            <li><a href=\"services.html#visa\"><i class=\"fas fa-chevron-right text-accent\"></i> Visa Processing</a></li>\n' +
'            <li><a href=\"services.html#documentation\"><i class=\"fas fa-chevron-right text-accent\"></i> Document Guide</a></li>\n' +
'            <li><a href=\"services.html#counseling\"><i class=\"fas fa-chevron-right text-accent\"></i> Career Counseling</a></li>\n' +
'            <li><a href=\"services.html#scholarship\"><i class=\"fas fa-chevron-right text-accent\"></i> Scholarship Help</a></li>\n' +
'            <li><a href=\"services.html#interview\"><i class=\"fas fa-chevron-right text-accent\"></i> Interview Preparation</a></li>\n' +
'            <li><a href=\"services.html#pre-departure\"><i class=\"fas fa-chevron-right text-accent\"></i> Pre-Departure Guide</a></li>\n' +
'            <li><a href=\"services.html#support\"><i class=\"fas fa-chevron-right text-accent\"></i> Student Support</a></li>\n' +
'          </ul>\n' +
'        </div>\n' +
'        <div class=\"footer-col\">\n' +
'          <h4 class=\"footer-title\">Get In Touch</h4>\n' +
'          <div class=\"contact-pill\">\n' +
'            <i class=\"fas fa-map-marker-alt text-accent\"></i>\n' +
'            <div><strong>Office Address:</strong><p>Office No. 1 &amp; 2, Islamia College Building, College Chowk, Biha Road, Matta Swat, Khyber Pakhtunkhwa, Pakistan</p></div>\n' +
'          </div>\n' +
'          <div class=\"contact-pill\">\n' +
'            <i class=\"fas fa-phone-alt text-accent\"></i>\n' +
'            <div><strong>Direct Contact:</strong><p><a href=\"tel:0946791780\">0946-791780</a> &bull; <a href=\"tel:03157500060\">0315-7500060</a></p></div>\n' +
'          </div>\n' +
'          <div class=\"contact-pill\">\n' +
'            <i class=\"fas fa-envelope text-accent\"></i>\n' +
'            <div><strong>Email:</strong><p><a href=\"mailto:ameenconsultancy.pk@gmail.com\">ameenconsultancy.pk@gmail.com</a></p></div>\n' +
'          </div>\n' +
'        </div>\n' +
'      </div>\n' +
'    </div>\n' +
'  </div>\n' +
'  <div class=\"footer-bottom\">\n' +
'    <div class=\"container footer-bottom-inner\">\n' +
'      <p>&copy; 2025-2026 Ameen Consultancy (Pvt) Limited. All Rights Reserved.</p>\n' +
'      <p class=\"slogan-quote\">\"Dream Big, Study Abroad, Build Your Future.\"</p>\n' +
'      <p>SECP Reg. No: 0315168 | Incorporated under the Companies Act, 2017</p>\n' +
'    </div>\n' +
'  </div>\n' +
'</footer>\n' +
'<!-- Floating Interactive Quick Action Hub -->\n' +
'<div class=\"floating-action-hub\">\n' +
'  <a href=\"https://wa.me/923157500060?text=Hello%20Ameen%20Consultancy,%20I%20want%20to%20inquire%20about%20study%20abroad\" target=\"_blank\" class=\"float-btn float-whatsapp\" title=\"Chat on WhatsApp\" aria-label=\"WhatsApp\">\n' +
'    <i class=\"fab fa-whatsapp\"></i>\n' +
'  </a>\n' +
'  <a href=\"tel:0946791780\" class=\"float-btn float-phone\" title=\"Call Direct\" aria-label=\"Call Office\">\n' +
'    <i class=\"fas fa-phone-alt\"></i>\n' +
'  </a>\n' +
'  <button class=\"float-btn float-top\" id=\"backToTop\" title=\"Back to top\" aria-label=\"Back to top\">\n' +
'    <i class=\"fas fa-arrow-up\"></i>\n' +
'  </button>\n' +
'</div>\n' +
'<script src=\"assets/js/main.js\"></script>\n' +
'</body>\n' +
'</html>';
}

module.exports = { head, nav, footer, brand, root };
console.log('Layout updated with brand export.');
