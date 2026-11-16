const fs = require('fs');
const path = require('path');
const { head, nav, footer, brand, root } = require('./layout');
const { destinations, services, whyPoints } = require('./build_data');

function innerBanner(title, subtitle, badge, flagImg) {
  return `
  <section class="page-banner">
    <div class="container page-banner-inner">
      <div class="page-banner-text">
        ${badge ? `<span class="banner-badge">${badge}</span>` : ''}
        <h1 class="page-banner-title">${title}</h1>
        <p class="page-banner-subtitle">${subtitle}</p>
      </div>
      ${flagImg ? `
      <div class="page-banner-flag">
        <img src="assets/images/${flagImg}" alt="${title} Flag" class="flag-hero-img">
      </div>` : ''}
    </div>
  </section>`;
}

// ABOUT.HTML
function buildAbout() {
  let h = head('About Us', 'Learn about Ameen Consultancy (Pvt) Ltd, SECP Registration No. 0315168, our vision, mission, and study abroad services.');
  h += nav('about');
  h += innerBanner('About Ameen Consultancy', 'Connecting talented Pakistani students with world-class education and global careers.', 'SECP Reg. No. 0315168');
  h += `
  <section class="section">
    <div class="container grid-2-col">
      <div class="about-text-content">
        <span class="section-tag"><i class="fas fa-building"></i> Company Profile</span>
        <h2 class="section-title">Your Trusted Partner for International Education</h2>
        <div class="quote-box">
          <p class="quote-text">
            <strong>AMEEN CONSULTANCY PRIVATE LIMITED</strong> is a premier international education consultancy incorporated under the Companies Act, 2017 (SECP Reg. No. 0315168). We are dedicated to helping Pakistani students achieve their dreams of studying abroad.
          </p>
        </div>
        <p>
          Our expert team provides end-to-end support from university selection and application to visa processing and career counseling. Based in Matta Swat, we serve students across Khyber Pakhtunkhwa and beyond, connecting them with top universities in China, Turkey, Hong Kong, South Korea, USA, Canada, UK, Albania, Cyprus, and Lithuania.
        </p>
        <p>
          We pride ourselves on transparent practices, ethical counseling, and an unwavering commitment to our students' academic success and financial security.
        </p>
      </div>
      <div class="about-secp-card">
        <div class="secp-card-header">
          <img src="assets/images/logo.jpg" alt="Ameen Consultancy Logo" class="secp-logo">
          <h3>SECP Incorporated</h3>
          <p>Companies Act, 2017</p>
        </div>
        <div class="secp-details-list">
          <div class="secp-row"><span>Registration No:</span><strong>0315168</strong></div>
          <div class="secp-row"><span>Incorporation Date:</span><strong>13 November 2025</strong></div>
          <div class="secp-row"><span>Company Status:</span><strong>Private Limited</strong></div>
          <div class="secp-row"><span>Head Office:</span><strong>Matta Swat, KPK</strong></div>
        </div>
        <div class="secp-badge-verified"><i class="fas fa-check-circle text-accent"></i> Verified Government Registration</div>
      </div>
    </div>
  </section>

  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-compass"></i> Guiding Principles</span>
        <h2 class="section-title">Our Vision &amp; Mission</h2>
      </div>
      <div class="grid-2-col">
        <div class="card vm-detailed-card">
          <div class="vm-det-icon"><i class="fas fa-eye text-primary"></i></div>
          <h3>OUR VISION</h3>
          <p class="vm-statement">"Transforming dreams into international success."</p>
          <p>To be recognized as Pakistan's most trusted, innovative, and accessible education consultancy, bridging the gap between local talent and premier global academia.</p>
        </div>
        <div class="card vm-detailed-card">
          <div class="vm-det-icon"><i class="fas fa-bullseye text-accent"></i></div>
          <h3>OUR MISSION</h3>
          <p class="vm-statement">"To connect talented students with world-class educational opportunities and build successful global careers."</p>
          <p>To empower aspiring scholars with professional counseling, accurate information, merit scholarships, and stress-free visa solutions that create lifelong prosperity.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section bg-dark text-white text-center">
    <div class="container">
      <h2 class="section-title text-white">Our Impact in Numbers</h2>
      <div class="brand-card-stats-grid mt-4">
        <div class="bc-stat"><span class="bc-num text-accent">10+</span><span class="bc-lbl text-white">Countries Covered</span></div>
        <div class="bc-stat"><span class="bc-num text-accent">100+</span><span class="bc-lbl text-white">Partner Universities</span></div>
        <div class="bc-stat"><span class="bc-num text-accent">500+</span><span class="bc-lbl text-white">Students Placed</span></div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-check"></i> Proven Excellence</span>
        <h2 class="section-title">Why Choose Ameen Consultancy?</h2>
        <p class="section-subtitle">"Guiding Students Towards Global Success" &mdash; 10 core pillars of our service.</p>
      </div>
      <div class="why-us-grid">
        ${whyPoints.map((w, idx) => `
        <div class="why-card">
          <div class="why-icon-wrap"><i class="fas ${w.icon}"></i></div>
          <div class="why-content">
            <span class="why-num">0${idx + 1}</span>
            <h4 class="why-title">${w.title}</h4>
            <p class="why-desc">${w.desc}</p>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'about.html'), h, 'utf8');
  console.log('about.html written');
}

// SERVICES.HTML
function buildServices() {
  let h = head('Our Services', 'Comprehensive study abroad services from university admissions, visa processing, scholarships, to pre-departure support.');
  h += nav('services');
  h += innerBanner('Our Professional Services', 'Comprehensive end-to-end support for international education and career success.', 'Guiding Students Towards Global Success');
  h += `
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-hand-holding-heart"></i> End-to-End Solutions</span>
        <h2 class="section-title">Services Tailored for Your Academic Journey</h2>
        <p class="section-subtitle">We guide students with transparency, precision, and dedication at every single milestone.</p>
      </div>
      <div class="services-detailed-list">
        ${services.map(s => `
        <div class="service-detail-card" id="${s.id}">
          <div class="sd-icon-box">
            <i class="fas ${s.icon}"></i>
            <span class="sd-step-badge">${s.tag}</span>
          </div>
          <div class="sd-content">
            <h3 class="sd-title">${s.title}</h3>
            <p class="sd-desc">${s.desc}</p>
            <div class="sd-perks">
              <span><i class="fas fa-check text-accent"></i> Dedicated Specialist</span>
              <span><i class="fas fa-check text-accent"></i> Embassy Standard</span>
              <span><i class="fas fa-check text-accent"></i> Guaranteed Attention</span>
            </div>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'services.html'), h, 'utf8');
  console.log('services.html written');
}

// DESTINATIONS.HTML
function buildDestinations() {
  let h = head('Study Destinations', 'Explore world-class universities in China, Turkey, Hong Kong, South Korea, USA, Canada, UK, Albania, Cyprus, and Lithuania.');
  h += nav('destinations');
  h += innerBanner('Global Study Destinations', 'We connect talented students with world-class universities across 10+ countries.', '100+ Universities');
  h += `
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-globe-americas"></i> 10+ Countries</span>
        <h2 class="section-title">Choose Your Dream Destination</h2>
        <p class="section-subtitle">Click on any country to explore featured universities, admission requirements, costs, and career pathways.</p>
      </div>
      <div class="destination-cards-grid">
        ${Object.values(destinations).map(d => `
        <div class="dest-card">
          <div class="dest-card-header">
            <img src="assets/images/${d.flag}" alt="${d.name} Flag" class="dest-flag-img">
            <div class="dest-title-wrap">
              <h3 class="dest-name">${d.name}</h3>
              <span class="dest-badge">${d.unis.length} Featured Unis</span>
            </div>
          </div>
          <div class="dest-card-body">
            <p class="dest-tagline"><em>"${d.tagline}"</em></p>
            <div class="dest-unis-list">
              <strong>Top Universities:</strong>
              <ul>
                ${d.unis.slice(0, 4).map(u => `<li><i class="fas fa-check-circle text-accent"></i> ${u}</li>`).join('')}
              </ul>
            </div>
          </div>
          <div class="dest-card-footer">
            <a href="${d.file}" class="btn btn-sm btn-primary w-100">Explore ${d.name} <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'destinations.html'), h, 'utf8');
  console.log('destinations.html written');
}

buildAbout();
buildServices();
buildDestinations();
