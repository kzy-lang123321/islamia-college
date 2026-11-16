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

function buildCanada() {
  const d = destinations.canada;
  let h = head('Study in Canada', 'Complete guide to studying in Canada. Top 12 Universities, Admission Requirements, Study Permit Process, PGWP, and 2026 Updates.');
  h += nav('destinations');
  h += innerBanner('Study in Canada', '"Quality Education, Warm Welcome, Bright Future"', 'Premier Commonwealth Destination', d.flag);
  h += `
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-university"></i> World-Class Institutions</span>
        <h2 class="section-title">Canada Top Universities</h2>
        <p class="section-subtitle">World-class education across Canada's globally ranked universities and institutes.</p>
      </div>
      <div class="grid-3-col">
        ${d.unis.map(u => `
        <div class="uni-card">
          <div class="uni-icon"><i class="fas fa-university"></i></div>
          <div>
            <h4 class="uni-title">${u}</h4>
            <span class="uni-loc"><i class="fas fa-star text-accent"></i> World-Class Institution &bull; Canada</span>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-star"></i> Canadian Advantage</span>
        <h2 class="section-title">Key Benefits of Studying in Canada</h2>
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

  <!-- REQUIREMENTS TABLE (PDF PAGE 16) -->
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-clipboard-list"></i> Academic Standards</span>
        <h2 class="section-title">Admission &amp; English Requirements</h2>
        <p class="section-subtitle">Official admission benchmarks for undergraduate and postgraduate Pakistani applicants.</p>
      </div>
      <div class="table-responsive">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Requirement</th>
              <th>Undergraduate</th>
              <th>Graduate / Master's</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Academic Record</strong></td>
              <td>HSSC / A-Levels, 65%+ marks</td>
              <td>4-year Bachelor's, 70%+ / 3.0 GPA</td>
            </tr>
            <tr>
              <td><strong>IELTS</strong></td>
              <td>6.0 overall (min 6.0 each band)</td>
              <td>6.5 overall</td>
            </tr>
            <tr>
              <td><strong>TOEFL iBT</strong></td>
              <td>80 - 86</td>
              <td>86 - 90</td>
            </tr>
            <tr>
              <td><strong>PTE Academic</strong></td>
              <td>58 - 60</td>
              <td>60 - 65</td>
            </tr>
            <tr>
              <td><strong>Duolingo English Test</strong></td>
              <td>105 - 110</td>
              <td>110 - 115</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-calendar-alt"></i> Intakes</h3>
          <ul class="styled-bullet-list">
            <li><strong>Fall (Sept):</strong> Main Intake</li>
            <li><strong>Winter (Jan):</strong> Secondary Intake</li>
            <li><strong>Summer (May):</strong> Select Programs</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Popular Programs</h3>
          <ul class="styled-bullet-list">
            <li><strong>Business &amp; Management</strong></li>
            <li><strong>Engineering</strong></li>
            <li><strong>Computer Science</strong></li>
            <li><strong>Health Sciences</strong></li>
            <li><strong>Hospitality &amp; Tourism</strong></li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- COSTS TABLE (PDF PAGE 16) -->
  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-coins"></i> Budget Overview</span>
        <h2 class="section-title">Tuition &amp; Living Costs (Annual, Approx.)</h2>
      </div>
      <div class="table-responsive">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Category</th>
              <th>Estimated Cost (CAD)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Undergraduate Degree</strong></td>
              <td>$20,000 - $35,000</td>
            </tr>
            <tr>
              <td><strong>Graduate / Master's Degree</strong></td>
              <td>$15,000 - $30,000</td>
            </tr>
            <tr>
              <td><strong>College Diploma Program</strong></td>
              <td>$12,000 - $18,000</td>
            </tr>
            <tr>
              <td><strong>Living Expenses (incl. GIC ~$20,635)</strong></td>
              <td>$12,000 - $15,000+</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-accent"><i class="fas fa-award"></i> Scholarships</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-accent"></i> Vanier Canada Graduate Scholarships</li>
            <li><i class="fas fa-check-circle text-accent"></i> University Entrance Scholarships</li>
            <li><i class="fas fa-check-circle text-accent"></i> Provincial Scholarship Programs</li>
            <li><i class="fas fa-check-circle text-accent"></i> On-Campus Teaching/Research Assistantships</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-folder-open"></i> Required Documents</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-primary"></i> Letter of Acceptance (Designated Learning Institution)</li>
            <li><i class="fas fa-check-circle text-primary"></i> Valid Passport</li>
            <li><i class="fas fa-check-circle text-primary"></i> Proof of Funds / GIC Certificate</li>
            <li><i class="fas fa-check-circle text-primary"></i> Academic Transcripts &amp; Certificates</li>
            <li><i class="fas fa-check-circle text-primary"></i> Statement of Purpose / Letter of Explanation</li>
            <li><i class="fas fa-check-circle text-primary"></i> Language Test Results</li>
            <li><i class="fas fa-check-circle text-primary"></i> Medical Exam (if required)</li>
            <li><i class="fas fa-check-circle text-primary"></i> Passport-Size Photos &amp; Biometrics</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- STUDY PERMIT PROCESS (PDF PAGE 17) -->
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-passport"></i> IRCC Roadmap</span>
        <h2 class="section-title">Study Permit &mdash; Application Process</h2>
        <p class="section-subtitle">6 steps to obtaining your Canadian Study Permit.</p>
      </div>
      <div class="steps-list-horizontal">
        <div class="step-h-card">
          <div class="sh-num">1</div>
          <h4>Acceptance Letter</h4>
          <p>Receive a Letter of Acceptance from a Designated Learning Institution (DLI).</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">2</div>
          <h4>Apply Online</h4>
          <p>Apply online through IRCC, submitting proof of funds and language scores.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">3</div>
          <h4>Provide Biometrics</h4>
          <p>Provide biometrics (fingerprints and photo).</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">4</div>
          <h4>Medical Exam</h4>
          <p>Complete a medical exam, if required.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">5</div>
          <h4>Receive Approval</h4>
          <p>Receive study permit approval / Port of Entry (POE) letter.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">6</div>
          <h4>Travel to Canada</h4>
          <p>Travel to Canada; get the study permit stamped/issued at the border.</p>
        </div>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-briefcase"></i> Work Rights During Studies</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-accent"></i> <strong>Off-Campus:</strong> up to 24 hrs/week during academic terms</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>Scheduled Breaks:</strong> Unlimited hours during breaks</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>On-Campus:</strong> no set hour limit</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>Co-op Placements:</strong> Authorized under study permit (up to 50% of program hours)</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-id-card"></i> Post-Study Work &amp; PR Pathway</h3>
          <p>
            <strong>Post-Graduation Work Permit (PGWP):</strong> Apply within 180 days of your completion letter. Programs of 2+ years (or Master's degrees) qualify for up to a <strong>3-year</strong> open work permit; shorter programs (8 months-2 years) receive a permit matching the study duration. <em>A language test (CLB 7 for degree graduates, CLB 5 for college graduates) is required for applications since November 2024.</em>
          </p>
          <p>
            <strong>Permanent Residency:</strong> Gain 1 year of skilled Canadian work experience on a PGWP, then apply through <strong>Express Entry - Canadian Experience Class</strong> or a <strong>Provincial Nominee Program (PNP)</strong>. <em>The PGWP is non-renewable, so timing your PR application is essential.</em>
          </p>
        </div>
      </div>

      <!-- IMPORTANT 2026 UPDATES ALERT -->
      <div class="alert-box-warning mt-5">
        <div class="alert-icon"><i class="fas fa-info-circle fa-2x"></i></div>
        <div>
          <h4 class="alert-title">IMPORTANT 2026 UPDATES</h4>
          <p>
            Study permit approvals remain capped nationally, and PGWP eligibility for diploma/certificate programs now depends on IRCC-approved fields of study linked to labour-market needs. Study permits for prerequisite/pathway programs (e.g. English language bridging) are valid only for the program length plus 90 days. Confirm current DLI and PGWP-eligibility status before enrolling.
          </p>
        </div>
      </div>

      <div class="card p-4 mt-4 bg-light">
        <h4 class="mb-3 text-primary"><i class="fas fa-lightbulb text-accent"></i> Tips for Students</h4>
        <div class="grid-2-col">
          <div><i class="fas fa-arrow-right text-accent"></i> Choose a PGWP-eligible program and DLI.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Track off-campus work hours carefully.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Take language tests early for PR planning.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Apply for PGWP within 180 days of graduating.</div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-banner-section">
    <div class="container cta-banner-box">
      <div class="cta-banner-content">
        <h2 class="cta-title">Unlock Your Pathway to Canada</h2>
        <p class="cta-desc">Ameen Consultancy secures verified LOAs from Canadian DLIs, assists with GIC accounts, and ensures flawless visa submissions.</p>
      </div>
      <div class="cta-banner-actions">
        <a href="contact.html?country=canada" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Apply for Canada</a>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'canada.html'), h, 'utf8');
  console.log('canada.html written');
}

buildCanada();
