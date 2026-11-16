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

function buildUSA() {
  const d = destinations.usa;
  let h = head('Study in USA', 'Comprehensive guide to studying in the USA. Top 12 Universities, F-1 Visa Process, Requirements, Costs, and 2026 Policy Updates.');
  h += nav('destinations');
  h += innerBanner('Study in United States', '"Home to the World\'s Most Prestigious Universities"', 'Top Ranked Destination', d.flag);
  h += `
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-university"></i> Elite Higher Education</span>
        <h2 class="section-title">USA Top Universities</h2>
        <p class="section-subtitle">Ameen Consultancy connects you with America's leading Ivy League, private, and state research powerhouses.</p>
      </div>
      <div class="grid-3-col">
        ${d.unis.map(u => `
        <div class="uni-card">
          <div class="uni-icon"><i class="fas fa-university"></i></div>
          <div>
            <h4 class="uni-title">${u}</h4>
            <span class="uni-loc"><i class="fas fa-star text-accent"></i> World-Class Institution &bull; USA</span>
          </div>
        </div>
        `).join('')}
      </div>
    </div>
  </section>

  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-star"></i> Strategic Value</span>
        <h2 class="section-title">Key Benefits of Studying in the USA</h2>
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

  <!-- REQUIREMENTS TABLE -->
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-clipboard-check"></i> Eligibility Criteria</span>
        <h2 class="section-title">Admission &amp; English Requirements</h2>
        <p class="section-subtitle">Official benchmark requirements for Pakistani applicants applying to US colleges and universities.</p>
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
              <td>HSSC / A-Levels, 60%+ marks</td>
              <td>4-year Bachelor's, 2.5 - 3.0 / 4.0 GPA</td>
            </tr>
            <tr>
              <td><strong>Standardized Test</strong></td>
              <td>SAT / ACT (often optional)</td>
              <td>GRE / GMAT (program-specific)</td>
            </tr>
            <tr>
              <td><strong>IELTS</strong></td>
              <td>6.0 - 6.5 overall</td>
              <td>6.5 - 7.0 overall</td>
            </tr>
            <tr>
              <td><strong>TOEFL iBT</strong></td>
              <td>76 - 90</td>
              <td>80 - 100</td>
            </tr>
            <tr>
              <td><strong>Duolingo English Test</strong></td>
              <td>105 - 115</td>
              <td>110 - 120</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-calendar-alt"></i> Intakes</h3>
          <ul class="styled-bullet-list">
            <li><strong>Fall (Aug/Sep):</strong> Main Intake</li>
            <li><strong>Spring (Jan):</strong> Secondary Intake</li>
            <li><strong>Summer (May):</strong> Limited Programs</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-book-open"></i> Popular Programs</h3>
          <ul class="styled-bullet-list">
            <li><strong>Computer Science &amp; Data Science</strong></li>
            <li><strong>Business Administration (MBA)</strong></li>
            <li><strong>Engineering</strong></li>
            <li><strong>Public Health</strong></li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- COSTS TABLE -->
  <section class="section bg-light">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-dollar-sign"></i> Financial Planning</span>
        <h2 class="section-title">Tuition &amp; Living Costs (Annual, Approx.)</h2>
      </div>
      <div class="table-responsive">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Category</th>
              <th>Estimated Cost (USD)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Public University (Out-of-State/Int'l)</strong></td>
              <td>$20,000 - $35,000</td>
            </tr>
            <tr>
              <td><strong>Private University</strong></td>
              <td>$40,000 - $60,000</td>
            </tr>
            <tr>
              <td><strong>Community College Pathway</strong></td>
              <td>$8,000 - $15,000</td>
            </tr>
            <tr>
              <td><strong>Living Expenses</strong></td>
              <td>$10,000 - $18,000 (city-dependent)</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-accent"><i class="fas fa-award"></i> Scholarships</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-accent"></i> University Merit-Based Scholarships</li>
            <li><i class="fas fa-check-circle text-accent"></i> Fulbright Scholarship (Graduate Students)</li>
            <li><i class="fas fa-check-circle text-accent"></i> Teaching / Research Assistantships</li>
            <li><i class="fas fa-check-circle text-accent"></i> Athletic &amp; Need-Based Scholarships</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-folder-open"></i> Required Documents</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-primary"></i> Valid Passport</li>
            <li><i class="fas fa-check-circle text-primary"></i> Academic Transcripts &amp; Certificates</li>
            <li><i class="fas fa-check-circle text-primary"></i> Statement of Purpose (SOP)</li>
            <li><i class="fas fa-check-circle text-primary"></i> 2-3 Letters of Recommendation</li>
            <li><i class="fas fa-check-circle text-primary"></i> Updated Resume / CV</li>
            <li><i class="fas fa-check-circle text-primary"></i> Standardized Test Scores</li>
            <li><i class="fas fa-check-circle text-primary"></i> Financial / Sponsorship Documents</li>
            <li><i class="fas fa-check-circle text-primary"></i> Form I-20 (issued after acceptance)</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- F-1 VISA PROCESS (PDF PAGE 14) -->
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <span class="section-tag"><i class="fas fa-passport"></i> Visa Blueprint</span>
        <h2 class="section-title">F-1 Student Visa &mdash; Application Process</h2>
        <p class="section-subtitle">6 essential steps to successfully secure your US F-1 student visa.</p>
      </div>
      <div class="steps-list-horizontal">
        <div class="step-h-card">
          <div class="sh-num">1</div>
          <h4>Get Form I-20</h4>
          <p>Get accepted by a SEVP-certified school and receive your Form I-20.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">2</div>
          <h4>Pay SEVIS Fee</h4>
          <p>Pay the SEVIS I-901 fee (<strong>USD $350</strong>) at fmjfee.com.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">3</div>
          <h4>Complete DS-160</h4>
          <p>Complete the online DS-160 visa application form.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">4</div>
          <h4>Pay MRV Fee</h4>
          <p>Pay the MRV visa application fee (<strong>USD $185</strong>).</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">5</div>
          <h4>Visa Interview</h4>
          <p>Schedule and attend your visa interview at the US Embassy/Consulate.</p>
        </div>
        <div class="step-h-card">
          <div class="sh-num">6</div>
          <h4>Visa Approval</h4>
          <p>Visa approval; travel to the US up to 30 days before your program start date.</p>
        </div>
      </div>

      <div class="grid-2-col mt-5">
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-briefcase"></i> Work Rights During Studies</h3>
          <ul class="styled-check-list">
            <li><i class="fas fa-check-circle text-accent"></i> <strong>On-Campus:</strong> up to 20 hrs/week during term</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>Official Breaks:</strong> Full-time work allowed</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>CPT:</strong> Curricular internships (school-approved)</li>
            <li><i class="fas fa-check-circle text-accent"></i> <strong>OPT:</strong> 12 months post-graduation work authorization</li>
          </ul>
        </div>
        <div class="card p-4">
          <h3 class="mb-3 text-primary"><i class="fas fa-route"></i> Post-Study Pathway</h3>
          <p><strong>STEM OPT Extension:</strong> STEM graduates can extend OPT by an additional 24 months (up to 3 years total work authorization).</p>
          <p><strong>Work Visa Route:</strong> OPT &rarr; employer-sponsored H-1B visa (subject to annual lottery) &rarr; potential employer-sponsored Green Card (EB categories). <em>There is no direct student-to-PR track; the pathway runs through employer sponsorship.</em></p>
        </div>
      </div>

      <!-- IMPORTANT 2026 POLICY UPDATE -->
      <div class="alert-box-warning mt-5">
        <div class="alert-icon"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
        <div>
          <h4 class="alert-title">IMPORTANT 2026 POLICY UPDATE</h4>
          <p>
            In July 2026, the Department of Homeland Security finalized a rule replacing the long-standing "duration of status" policy with a <strong>fixed four-year admission period</strong> for F-1 and J-1 visa holders, taking effect <strong>15 September 2026</strong> (pending possible court challenges). Students requiring more time must apply for an extension. The rule also restricts changing majors or schools during the first year and shortens the post-completion grace period from 60 to 30 days. Applicants should confirm the latest status with their DSO or the US Embassy before applying.
          </p>
        </div>
      </div>

      <div class="card p-4 mt-4 bg-light">
        <h4 class="mb-3 text-primary"><i class="fas fa-lightbulb text-accent"></i> Tips for Students</h4>
        <div class="grid-2-col">
          <div><i class="fas fa-arrow-right text-accent"></i> Apply early &mdash; interview wait times vary by country.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Keep I-20 details consistent with DS-160.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Demonstrate strong home-country ties.</div>
          <div><i class="fas fa-arrow-right text-accent"></i> Prepare 1 year of tuition + living cost proof.</div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-banner-section">
    <div class="container cta-banner-box">
      <div class="cta-banner-content">
        <h2 class="cta-title">Start Your American Academic Dream</h2>
        <p class="cta-desc">Ameen Consultancy prepares your US applications, I-20 requests, and conducts mock interviews for US Embassy success.</p>
      </div>
      <div class="cta-banner-actions">
        <a href="contact.html?country=usa" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Apply for USA</a>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'usa.html'), h, 'utf8');
  console.log('usa.html written');
}

buildUSA();
