const fs = require('fs');
const path = require('path');
const { head, nav, footer, brand, root } = require('./layout');
const { destinations, services, whyPoints } = require('./build_data');

function buildIndex() {
  let h = head('Your Trusted Partner for International Education & Career Success');
  h += nav('home');
  h += `
  <!-- HERO SECTION -->
  <section class="hero-section">
    <div class="container hero-inner">
      <div class="hero-content">
        <div class="hero-badge">
          <img src="assets/images/logo.jpg" alt="Logo" class="badge-logo-mini">
          <span>SECP Registered No: 0315168 &bull; Incorporated 13 Nov 2025</span>
        </div>
        <h1 class="hero-title">
          Your Trusted Partner for<br>
          <span class="text-gradient">International Education</span><br>
          &amp; Career Success
        </h1>
        <p class="hero-subtitle">
          Study Abroad &bull; Admissions &bull; Visa Guidance &bull; Career Counseling &bull; Documentation Support
        </p>
        <p class="hero-text">
          Dedicated to helping Pakistani students achieve their dreams of studying abroad. From Matta Swat to premier universities across China, Turkey, Hong Kong, South Korea, USA, Canada, UK, Albania, Cyprus, and Lithuania.
        </p>
        <div class="hero-cta-group">
          <a href="destinations.html" class="btn btn-primary btn-lg"><i class="fas fa-compass"></i> Explore Destinations</a>
          <a href="contact.html" class="btn btn-outline btn-lg"><i class="fas fa-comments"></i> Get Free Counseling</a>
        </div>
        <div class="hero-countries-pills">
          <span class="pills-label">Top Destinations:</span>
          <div class="pills-scroll">
            <a href="china.html" class="c-pill"><img src="assets/images/flag_china.png" alt="China"> China</a>
            <a href="turkey.html" class="c-pill"><img src="assets/images/flag_turkey.png" alt="Turkey"> Turkey</a>
            <a href="hong-kong.html" class="c-pill"><img src="assets/images/flag_hong_kong.png" alt="Hong Kong"> Hong Kong</a>
            <a href="south-korea.html" class="c-pill"><img src="assets/images/flag_south_korea.png" alt="South Korea"> South Korea</a>
            <a href="usa.html" class="c-pill"><img src="assets/images/flag_usa.png" alt="USA"> USA</a>
            <a href="canada.html" class="c-pill"><img src="assets/images/flag_canada.png" alt="Canada"> Canada</a>
            <a href="uk.html" class="c-pill"><img src="assets/images/flag_uk.png" alt="UK"> UK</a>
            <a href="albania.html" class="c-pill"><img src="assets/images/flag_albania.png" alt="Albania"> Albania</a>
            <a href="cyprus.html" class="c-pill"><img src="assets/images/flag_cyprus.png" alt="Cyprus"> Cyprus</a>
            <a href="lithuania.html" class="c-pill"><img src="assets/images/flag_lithuania.png" alt="Lithuania"> Lithuania</a>
          </div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="hero-brand-card">
          <div class="brand-card-header">
            <img src="assets/images/logo.jpg" alt="Ameen Consultancy Official Logo" class="hero-main-logo">
            <h2 class="card-brand-title">AMEEN CONSULTANCY</h2>
            <span class="card-brand-sub">(PRIVATE) LIMITED</span>
          </div>
          <div class="brand-card-vision">
            <p class="vision-quote">"Transforming dreams into international success."</p>
          </div>
          <div class="brand-card-stats-grid">
            <div class="bc-stat">
              <span class="bc-num">10+</span>
              <span class="bc-lbl">Countries</span>
            </div>
            <div class="bc-stat">
              <span class="bc-num">100+</span>
              <span class="bc-lbl">Universities</span>
            </div>
            <div class="bc-stat">
              <span class="bc-num">500+</span>
              <span class="bc-lbl">Students</span>
            </div>
          </div>
          <div class="brand-card-footer">
            <div class="bcf-item"><i class="fas fa-map-marker-alt"></i> Matta Swat, KPK</div>
            <div class="bcf-item"><i class="fas fa-phone-alt"></i> 0946-791780</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STATS BAR -->
  <section class="stats-bar-section">
    <div class="container stats-bar-grid">
      <div class="sb-item">
        <div class="sb-icon"><i class="fas fa-globe-asia"></i></div>
        <div class="sb-data">
          <h3>10+ Countries</h3>
          <p>Global Destinations</p>
        </div>
      </div>
      <div class="sb-item">
        <div class="sb-icon"><i class="fas fa-university"></i></div>
        <div class="sb-data">
          <h3>100+ Universities</h3>
          <p>Partner Institutions</p>
        </div>
      </div>
      <div class="sb-item">
        <div class="sb-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="sb-data">
          <h3>500+ Students</h3>
          <p>Guided Toward Success</p>
        </div>
      </div>
      <div class="sb-item">
        <div class="sb-icon"><i class="fas fa-certificate"></i></div>
        <div class="sb-data">
          <h3>SECP Reg. 0315168</h3>
          <p>Fully Certified &amp; Trusted</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ABOUT PREVIEW -->
  <section class="section about-preview">
    <div class="container grid-2-col">
      <div class="about-img-box">
        <div class="about-logo-frame">
          <img src="assets/images/logo.jpg" alt="Ameen Consultancy Logo" class="about-frame-img">
        </div>
        <div class="floating-badge-box">
          <i class="fas fa-award text-accent fa-2x"></i>
          <div>
            <strong>Incorporated Nov 2025</strong>
            <p>Companies Act, 2017</p>
          </div>
        </div>
      </div>
      <div class="about-text-content">
        <span class="section-tag"><i class="fas fa-info-circle"></i> About Ameen Consultancy</span>
        <h2 class="section-title">Empowering Students Across Khyber Pakhtunkhwa &amp; Beyond</h2>
        <p class="lead-text">
          <strong>AMEEN CONSULTANCY PRIVATE LIMITED</strong> is a premier international education consultancy incorporated under the Companies Act, 2017 (SECP Reg. No. 0315168).
        </p>
        <p>
          We are dedicated to helping Pakistani students achieve their dreams of studying abroad. Our expert team provides end-to-end support from university selection and application to visa processing and career counseling. Based in Matta Swat, we serve students across Khyber Pakhtunkhwa and beyond, connecting them with top universities in China, Turkey, Hong Kong, South Korea, USA, Canada, UK, Albania, Cyprus, and Lithuania.
        </p>
        <div class="vision-mission-cards">
          <div class="vm-card">
            <div class="vm-icon"><i class="fas fa-eye text-primary"></i></div>
            <div>
              <h4>OUR VISION</h4>
              <p>"Transforming dreams into international success."</p>
            </div>
          </div>
          <div class="vm-card">
            <div class="vm-icon"><i class="fas fa-bullseye text-accent"></i></div>
            <div>
              <h4>OUR MISSION</h4>
              <p>"To connect talented students with world-class educational opportunities and build successful global careers."</p>
            </div>
          </div>
        </div>
        <div class="pt-3">
          <a href="about.html" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Read Full Profile</a>
        </div>
      </div>
    </div>
  </section>

  <!-- DESTINATIONS GRID -->
  <section class="section bg-light" id="destinations">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-globe"></i> World-Class Universities</span>
        <h2 class="section-title">Explore Global Study Destinations</h2>
        <p class="section-subtitle">We open doors to world-renowned academic institutions across Asia, Europe, and North America.</p>
      </div>
      <div class="destination-cards-grid">
        ${Object.values(destinations).map(d => `
        <div class="dest-card">
          <div class="dest-card-header">
            <img src="assets/images/${d.flag}" alt="${d.name} Flag" class="dest-flag-img">
            <div class="dest-title-wrap">
              <h3 class="dest-name">${d.name}</h3>
              <span class="dest-badge">${d.unis.length} Top Universities</span>
            </div>
          </div>
          <div class="dest-card-body">
            <p class="dest-tagline"><em>"${d.tagline}"</em></p>
            <div class="dest-unis-list">
              <strong>Featured Universities:</strong>
              <ul>
                ${d.unis.slice(0, 4).map(u => `<li><i class="fas fa-check-circle text-accent"></i> ${u}</li>`).join('')}
              </ul>
            </div>
          </div>
          <div class="dest-card-footer">
            <a href="${d.file}" class="btn btn-sm btn-outline-primary w-100">View Requirements &amp; Details <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <!-- SERVICES OVERVIEW -->
  <section class="section" id="services">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-concierge-bell"></i> What We Offer</span>
        <h2 class="section-title">Our Comprehensive Services</h2>
        <p class="section-subtitle">"Guiding Students Towards Global Success" &mdash; Complete 360-degree support at every phase.</p>
      </div>
      <div class="services-cards-grid">
        ${services.map(s => `
        <div class="service-box">
          <div class="sb-head">
            <div class="sb-icon-wrap"><i class="fas ${s.icon}"></i></div>
            <span class="sb-step">${s.tag}</span>
          </div>
          <h3 class="sb-title">${s.title}</h3>
          <p class="sb-desc">${s.desc}</p>
          <a href="services.html#${s.id}" class="sb-link">Learn Details <i class="fas fa-chevron-right"></i></a>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <!-- WHY CHOOSE US -->
  <section class="section bg-dark text-white">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag badge-white"><i class="fas fa-check-circle"></i> Why Choose Ameen Consultancy</span>
        <h2 class="section-title text-white">Guiding Students Towards Global Success</h2>
        <p class="section-subtitle text-muted">10 verified reasons why Pakistani students trust Ameen Consultancy (Pvt) Ltd for their foreign study dreams.</p>
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
  </section>

  <!-- PROCESS -->
  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-route"></i> Roadmap</span>
        <h2 class="section-title">Your 5-Step Journey Abroad</h2>
        <p class="section-subtitle">From initial counseling in Matta Swat to landing at your dream campus.</p>
      </div>
      <div class="process-flow-grid">
        <div class="process-card">
          <div class="pc-num">1</div>
          <div class="pc-icon"><i class="fas fa-comments"></i></div>
          <h4>Profile Evaluation</h4>
          <p>We review your academic marks, budget, and career goals to select optimal countries &amp; universities.</p>
        </div>
        <div class="process-card">
          <div class="pc-num">2</div>
          <div class="pc-icon"><i class="fas fa-file-signature"></i></div>
          <h4>University Application</h4>
          <p>Complete documentation, SOP preparation, scholarship application, and offer letter issuance.</p>
        </div>
        <div class="process-card">
          <div class="pc-num">3</div>
          <div class="pc-icon"><i class="fas fa-stamp"></i></div>
          <h4>Visa Processing</h4>
          <p>Financial document validation, embassy filing, appointment scheduling, and thorough mock interview prep.</p>
        </div>
        <div class="process-card">
          <div class="pc-num">4</div>
          <div class="pc-icon"><i class="fas fa-plane-departure"></i></div>
          <h4>Pre-Departure Briefing</h4>
          <p>Accommodation arrangements, currency exchange, travel insurance, and cultural adaptation guidance.</p>
        </div>
        <div class="process-card">
          <div class="pc-num">5</div>
          <div class="pc-icon"><i class="fas fa-graduation-cap"></i></div>
          <h4>Campus Arrival</h4>
          <p>Enrollment at university, residence permit registration, and continuous student welfare support.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA BANNER -->
  <section class="cta-banner-section">
    <div class="container cta-banner-box">
      <div class="cta-banner-content">
        <span class="cta-badge">Start Today</span>
        <h2 class="cta-title">"Dream Big, Study Abroad, Build Your Future."</h2>
        <p class="cta-desc">
          Contact our certified education consultants in Matta Swat today. We are ready to assist you with university selection, scholarships, and visa processing.
        </p>
        <div class="cta-contacts-row">
          <span><i class="fas fa-phone-alt"></i> 0946-791780</span>
          <span><i class="fas fa-mobile-alt"></i> 0315-7500060</span>
          <span><i class="fas fa-envelope"></i> ameenconsultancy.pk@gmail.com</span>
        </div>
      </div>
      <div class="cta-banner-actions">
        <a href="contact.html" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Book Free Appointment</a>
        <a href="tel:03157500060" class="btn btn-outline-white btn-lg"><i class="fab fa-whatsapp"></i> Call / WhatsApp</a>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'index.html'), h, 'utf8');
  console.log('index.html written');
}

buildIndex();
