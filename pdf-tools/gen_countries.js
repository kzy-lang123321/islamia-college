const fs = require('fs');
const path = require('path');
const { head, nav, footer, brand, root } = require('./layout');
const { destinations } = require('./build_data');

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

function buildStandardCountry(d) {
  let h = head(`Study in ${d.name}`, `${d.tagline}. Discover top universities, benefits, and requirements with Ameen Consultancy.`);
  h += nav('destinations');
  h += innerBanner(`Study in ${d.name}`, `"${d.tagline}"`, 'International Education', d.flag);
  h += `
  <section class="section">
    <div class="container grid-2-col">
      <div>
        <span class="section-tag"><i class="fas fa-info-circle"></i> Destination Overview</span>
        <h2 class="section-title">Why Study in ${d.name}?</h2>
        <p class="lead-text">${d.overview}</p>
        <p>Ameen Consultancy provides complete documentation, admission assistance, and visa processing for Pakistani students aiming for top-ranked institutions across ${d.name}.</p>
        <div class="pt-3">
          <a href="contact.html?country=${d.id}" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Apply for ${d.name}</a>
        </div>
      </div>
      <div class="card p-4">
        <div class="d-flex align-items-center mb-3">
          <img src="assets/images/${d.flag}" alt="${d.name} Flag" style="width: 70px; height: 38px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); margin-right: 15px;">
          <div>
            <h3 class="mb-0">${d.name}</h3>
            <small class="text-muted">Study Abroad Profile</small>
          </div>
        </div>
        <hr>
        <div class="mini-facts-list">
          <p><strong><i class="fas fa-language text-accent"></i> Language:</strong> English &bull; Local Language Options</p>
          <p><strong><i class="fas fa-calendar-alt text-accent"></i> Main Intakes:</strong> Fall (September) &amp; Spring (February/March)</p>
          <p><strong><i class="fas fa-graduation-cap text-accent"></i> Degree Levels:</strong> Bachelor’s, Master’s, PhD</p>
          <p><strong><i class="fas fa-shield-alt text-accent"></i> Consultancy:</strong> Full End-to-End Admission &amp; Visa</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-university"></i> Premier Institutions</span>
        <h2 class="section-title">${d.name} Top Universities</h2>
        <p class="section-subtitle">World-class education and globally recognized qualifications.</p>
      </div>
      <div class="grid-2-col">
        ${d.unis.map(u => `
        <div class="uni-card">
          <div class="uni-icon"><i class="fas fa-university"></i></div>
          <div>
            <h4 class="uni-title">${u}</h4>
            <span class="uni-loc"><i class="fas fa-map-marker-alt text-accent"></i> World-Class Institution &bull; ${d.name}</span>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-star"></i> Key Advantages</span>
        <h2 class="section-title">Key Benefits of Studying in ${d.name}</h2>
        <p class="section-subtitle">Everything you gain from studying in this premier international destination.</p>
      </div>
      <div class="grid-4-col">
        ${d.benefits.map(b => `
        <div class="benefit-card">
          <div class="benefit-icon"><i class="fas fa-check-circle text-accent"></i></div>
          <h4>${b}</h4>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <section class="cta-banner-section">
    <div class="container cta-banner-box">
      <div class="cta-banner-content">
        <h2 class="cta-title">Begin Your Application for ${d.name}</h2>
        <p class="cta-desc">Contact our expert counselors today for university admission, scholarship assessment, and visa file preparation.</p>
      </div>
      <div class="cta-banner-actions">
        <a href="contact.html?country=${d.id}" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Apply Now</a>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, d.file), h, 'utf8');
  console.log(`${d.file} written`);
}

const list = ['china', 'turkey', 'hong-kong', 'south-korea', 'albania', 'uk', 'cyprus', 'lithuania'];
list.forEach(k => buildStandardCountry(destinations[k]));
