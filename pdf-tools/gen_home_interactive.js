const fs = require('fs');
const path = require('path');
const { head, nav, footer, brand, root } = require('./layout');
const { destinations, services, whyPoints } = require('./build_data');

function buildInteractiveIndex() {
  let h = head('Your Trusted Partner for International Education & Career Success');
  h += nav('home');
  h += `
  <!-- HERO SLIDESHOW SECTION -->
  <section class="hero-slider-section">
    <div class="hero-slider-container">
      <div class="hero-slides-wrapper" id="heroSlidesWrapper">

        <!-- Slide 1: China -->
        <div class="hero-slide active" data-slide="0" style="background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(22,163,74,0.75)), url('assets/images/flag_china.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_china.png" alt="China" class="slide-flag"> Study in China &bull; Full Scholarships</div>
            <h1 class="slide-title">World-Class Education in the <span class="text-accent">Heart of Asia</span></h1>
            <p class="slide-subtitle">Tsinghua University &bull; Peking University &bull; Zhejiang University &bull; Fudan University</p>
            <p class="slide-desc">Government Scholarships (CSC, Belt &amp; Road), affordable tuition, top medical MBBS and cutting-edge engineering programs for Pakistani students.</p>
            <div class="slide-actions">
              <a href="china.html" class="btn btn-primary btn-lg"><i class="fas fa-university"></i> Explore China Programs</a>
              <a href="contact.html?country=china" class="btn btn-outline-white btn-lg"><i class="fas fa-paper-plane"></i> Apply for China</a>
            </div>
          </div>
        </div>

        <!-- Slide 2: USA -->
        <div class="hero-slide" data-slide="1" style="background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(29,78,216,0.75)), url('assets/images/flag_usa.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_usa.png" alt="USA" class="slide-flag"> Study in USA &bull; 3-Year STEM OPT</div>
            <h1 class="slide-title">Home to the World's Most <span class="text-accent">Prestigious Universities</span></h1>
            <p class="slide-subtitle">Harvard &bull; MIT &bull; Stanford &bull; UC Berkeley &bull; Yale &bull; Princeton &bull; Columbia</p>
            <p class="slide-desc">Up to 3 years post-study work authorization with STEM OPT, merit scholarships, and end-to-end F-1 visa preparation by certified counselors.</p>
            <div class="slide-actions">
              <a href="usa.html" class="btn btn-primary btn-lg"><i class="fas fa-star"></i> View 12 Top US Universities</a>
              <a href="contact.html?country=usa" class="btn btn-outline-white btn-lg"><i class="fas fa-passport"></i> Start F-1 Visa Process</a>
            </div>
          </div>
        </div>

        <!-- Slide 3: Canada -->
        <div class="hero-slide" data-slide="2" style="background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(220,38,38,0.7)), url('assets/images/flag_canada.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_canada.png" alt="Canada" class="slide-flag"> Study in Canada &bull; Up to 3-Yr PGWP &bull; PR Pathway</div>
            <h1 class="slide-title">Quality Education, Warm Welcome, <span class="text-accent">Bright Future</span></h1>
            <p class="slide-subtitle">University of Toronto &bull; UBC &bull; McGill &bull; Waterloo &bull; Alberta &bull; McMaster</p>
            <p class="slide-desc">Post-Graduation Work Permit (PGWP), direct Express Entry / PNP Permanent Residency pathway, and paid Co-op internships.</p>
            <div class="slide-actions">
              <a href="canada.html" class="btn btn-primary btn-lg"><i class="fas fa-map-signs"></i> Explore Canada Guide</a>
              <a href="contact.html?country=canada" class="btn btn-outline-white btn-lg"><i class="fas fa-file-signature"></i> Apply for Study Permit</a>
            </div>
          </div>
        </div>

        <!-- Slide 4: UK -->
        <div class="hero-slide" data-slide="3" style="background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(30,58,138,0.8)), url('assets/images/flag_uk.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_uk.png" alt="UK" class="slide-flag"> Study in UK &bull; 2-Year Graduate Route Visa</div>
            <h1 class="slide-title">Centuries of Academic Excellence &mdash; <span class="text-accent">Study Where Best Study</span></h1>
            <p class="slide-subtitle">Oxford &bull; Cambridge &bull; Manchester &bull; Birmingham &bull; Russell Group</p>
            <p class="slide-desc">Short degree duration (1-Year Master's, 3-Year Bachelor's), high employability, and 2-year post-study work visa.</p>
            <div class="slide-actions">
              <a href="uk.html" class="btn btn-primary btn-lg"><i class="fas fa-graduation-cap"></i> View UK Universities</a>
              <a href="contact.html?country=uk" class="btn btn-outline-white btn-lg"><i class="fas fa-comments"></i> Book Free Consultation</a>
            </div>
          </div>
        </div>

        <!-- Slide 5: Turkey -->
        <div class="hero-slide" data-slide="4" style="background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(185,28,28,0.75)), url('assets/images/flag_turkey.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_turkey.png" alt="Turkey" class="slide-flag"> Study in Turkey &bull; Where East Meets West</div>
            <h1 class="slide-title">A Bridge to <span class="text-accent">Global Education</span></h1>
            <p class="slide-subtitle">Istanbul University &bull; Ankara University &bull; METU &bull; Medipol University</p>
            <p class="slide-desc">European Bologna standard degrees, low tuition fees, and Türkiye Bursları scholarship opportunities in welcoming student cities.</p>
            <div class="slide-actions">
              <a href="turkey.html" class="btn btn-primary btn-lg"><i class="fas fa-compass"></i> Discover Turkey</a>
              <a href="contact.html?country=turkey" class="btn btn-outline-white btn-lg"><i class="fas fa-paper-plane"></i> Apply for Turkey</a>
            </div>
          </div>
        </div>

        <!-- Slide 6: South Korea -->
        <div class="hero-slide" data-slide="5" style="background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(29,78,216,0.7)), url('assets/images/flag_south_korea.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_south_korea.png" alt="South Korea" class="slide-flag"> Study in South Korea &bull; Tech &amp; Innovation</div>
            <h1 class="slide-title">Innovation &amp; Excellence in the <span class="text-accent">Land of Morning Calm</span></h1>
            <p class="slide-subtitle">Seoul National &bull; Korea University &bull; Yonsei &bull; KAIST</p>
            <p class="slide-desc">Global leadership in Robotics, AI &amp; STEM, full Global Korea Scholarships (GKS), and career avenues with tech conglomerates.</p>
            <div class="slide-actions">
              <a href="south-korea.html" class="btn btn-primary btn-lg"><i class="fas fa-microchip"></i> Explore South Korea</a>
              <a href="contact.html?country=south-korea" class="btn btn-outline-white btn-lg"><i class="fas fa-paper-plane"></i> Apply for GKS</a>
            </div>
          </div>
        </div>

        <!-- Slide 7: Hong Kong -->
        <div class="hero-slide" data-slide="6" style="background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(220,38,38,0.75)), url('assets/images/flag_hong_kong.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_hong_kong.png" alt="Hong Kong" class="slide-flag"> Study in Hong Kong &bull; Asia's World City</div>
            <h1 class="slide-title">Gateway to <span class="text-accent">Global Opportunities</span></h1>
            <p class="slide-subtitle">HKU &bull; PolyU &bull; City University &bull; HKUST</p>
            <p class="slide-desc">Top 50 world-ranked universities, English medium education, and attractive IANG post-graduation stay-back visas.</p>
            <div class="slide-actions">
              <a href="hong-kong.html" class="btn btn-primary btn-lg"><i class="fas fa-city"></i> Explore Hong Kong</a>
              <a href="contact.html?country=hong-kong" class="btn btn-outline-white btn-lg"><i class="fas fa-paper-plane"></i> Apply Now</a>
            </div>
          </div>
        </div>

        <!-- Slide 8: European Pathways -->
        <div class="hero-slide" data-slide="7" style="background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(22,163,74,0.7)), url('assets/images/flag_lithuania.png') center/cover no-repeat;">
          <div class="container hero-slide-content">
            <div class="slide-badge"><img src="assets/images/flag_lithuania.png" alt="Lithuania" class="slide-flag"> Study in Europe &bull; Schengen Area &bull; Affordable</div>
            <h1 class="slide-title">European Union Degrees in <span class="text-accent">Cyprus, Lithuania &amp; Albania</span></h1>
            <p class="slide-subtitle">Vilnius Tech &bull; University of Cyprus &bull; Epoka University &bull; LSMU</p>
            <p class="slide-desc">Bologna-accredited European degrees, low living costs, English-taught programs, and Schengen mobility.</p>
            <div class="slide-actions">
              <a href="destinations.html" class="btn btn-primary btn-lg"><i class="fas fa-globe-europe"></i> View All European Options</a>
              <a href="contact.html" class="btn btn-outline-white btn-lg"><i class="fas fa-comments"></i> Free Eligibility Check</a>
            </div>
          </div>
        </div>

      </div>

      <!-- Slider Controls -->
      <button class="slider-arrow prev" id="sliderPrev" aria-label="Previous Slide"><i class="fas fa-chevron-left"></i></button>
      <button class="slider-arrow next" id="sliderNext" aria-label="Next Slide"><i class="fas fa-chevron-right"></i></button>

      <!-- Slider Indicators -->
      <div class="slider-dots" id="sliderDots">
        <button class="dot active" data-go="0" aria-label="Slide 1"></button>
        <button class="dot" data-go="1" aria-label="Slide 2"></button>
        <button class="dot" data-go="2" aria-label="Slide 3"></button>
        <button class="dot" data-go="3" aria-label="Slide 4"></button>
        <button class="dot" data-go="4" aria-label="Slide 5"></button>
        <button class="dot" data-go="5" aria-label="Slide 6"></button>
        <button class="dot" data-go="6" aria-label="Slide 7"></button>
        <button class="dot" data-go="7" aria-label="Slide 8"></button>
      </div>
    </div>
  </section>

  <!-- STATS BAR WITH COUNTER ANIMATION -->
  <section class="stats-bar-section">
    <div class="container stats-bar-grid">
      <div class="sb-item" data-animate="fade-up">
        <div class="sb-icon"><i class="fas fa-globe-asia"></i></div>
        <div class="sb-data">
          <h3 class="counter" data-target="10">0</h3>
          <p>Global Countries</p>
        </div>
      </div>
      <div class="sb-item" data-animate="fade-up" data-delay="100">
        <div class="sb-icon"><i class="fas fa-university"></i></div>
        <div class="sb-data">
          <h3 class="counter" data-target="100">0</h3>
          <p>Partner Universities</p>
        </div>
      </div>
      <div class="sb-item" data-animate="fade-up" data-delay="200">
        <div class="sb-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="sb-data">
          <h3 class="counter" data-target="500">0</h3>
          <p>Successful Students</p>
        </div>
      </div>
      <div class="sb-item" data-animate="fade-up" data-delay="300">
        <div class="sb-icon"><i class="fas fa-certificate"></i></div>
        <div class="sb-data">
          <h3>SECP Reg. 0315168</h3>
          <p>Incorporated 13 Nov 2025</p>
        </div>
      </div>
    </div>
  </section>

  <!-- INTERACTIVE STUDY ABROAD FINDER & CALCULATOR -->
  <section class="section bg-light" id="calculator">
    <div class="container">
      <div class="section-header text-center" data-animate="fade-up">
        <span class="section-tag"><i class="fas fa-calculator"></i> Interactive Tool</span>
        <h2 class="section-title">Study Abroad Eligibility &amp; Program Finder</h2>
        <p class="section-subtitle">Select your academic goals and budget below to receive instant matching destination recommendations and visa tips.</p>
      </div>

      <div class="interactive-finder-box" data-animate="fade-up">
        <div class="finder-form-grid">
          <div class="finder-field">
            <label for="findCountry"><i class="fas fa-globe text-primary"></i> <strong>Preferred Destination:</strong></label>
            <select id="findCountry" class="form-control">
              <option value="any">Any Country / Show Best Options</option>
              <option value="china">China (Affordable &amp; Scholarships)</option>
              <option value="turkey">Turkey (Bologna &amp; European Pathway)</option>
              <option value="hong-kong">Hong Kong (Asia Top 50 &amp; Finance)</option>
              <option value="south-korea">South Korea (Robotics &amp; STEM)</option>
              <option value="usa">USA (STEM OPT &amp; Top Ranked)</option>
              <option value="canada">Canada (PGWP &amp; PR Pathway)</option>
              <option value="uk">United Kingdom (2-Yr Graduate Route)</option>
              <option value="albania">Albania (Budget European Pathway)</option>
              <option value="cyprus">European Cyprus (EU Accredited)</option>
              <option value="lithuania">Lithuania (Schengen &amp; FinTech)</option>
            </select>
          </div>

          <div class="finder-field">
            <label for="findLevel"><i class="fas fa-graduation-cap text-primary"></i> <strong>Degree Level:</strong></label>
            <select id="findLevel" class="form-control">
              <option value="undergrad">Undergraduate / Bachelor's</option>
              <option value="master">Postgraduate / Master's</option>
              <option value="phd">PhD / Doctoral Research</option>
              <option value="diploma">College Diploma / Pathway</option>
            </select>
          </div>

          <div class="finder-field">
            <label for="findIelts"><i class="fas fa-certificate text-primary"></i> <strong>IELTS / English Status:</strong></label>
            <select id="findIelts" class="form-control">
              <option value="none">No IELTS Yet / Want Without IELTS</option>
              <option value="60">IELTS 6.0 / Duolingo 105</option>
              <option value="65">IELTS 6.5+ / TOEFL 85+</option>
              <option value="70">IELTS 7.0+ / Fluent English</option>
            </select>
          </div>

          <div class="finder-field">
            <label for="findBudget"><i class="fas fa-wallet text-primary"></i> <strong>Annual Budget (Tuition + Living):</strong></label>
            <select id="findBudget" class="form-control">
              <option value="low">Under $5,000 USD (Full Scholarship Seeking)</option>
              <option value="mid">$6,000 - $15,000 USD (Affordable Global)</option>
              <option value="high">$16,000 - $35,000+ USD (USA, Canada, UK)</option>
            </select>
          </div>
        </div>

        <div class="finder-btn-wrap">
          <button type="button" class="btn btn-primary btn-lg" id="runFinderBtn"><i class="fas fa-search"></i> Match My Profile &amp; Universities</button>
        </div>

        <!-- Result Box -->
        <div class="finder-result-box" id="finderResult" style="display: none;">
          <div class="result-card">
            <div class="result-header">
              <div class="result-flag-wrap" id="resFlagWrap">
                <img src="assets/images/flag_china.png" alt="Flag" id="resFlagImg" class="res-flag">
              </div>
              <div>
                <h3 id="resTitle" class="res-title">Matching Country Recommendation</h3>
                <p id="resSub" class="res-sub">Personalized evaluation based on your inputs</p>
              </div>
            </div>
            <div class="result-body">
              <div class="res-highlight-grid">
                <div class="rh-box">
                  <strong>Featured Universities:</strong>
                  <p id="resUnis">Tsinghua University, Peking University, Zhejiang University</p>
                </div>
                <div class="rh-box">
                  <strong>Estimated Annual Cost:</strong>
                  <p id="resCost">$2,500 - $6,000 / Year (Scholarships Available)</p>
                </div>
                <div class="rh-box">
                  <strong>Post-Study Career &amp; Visa:</strong>
                  <p id="resVisa">Fast visa processing with embassy guidance</p>
                </div>
              </div>
              <div class="res-tip-box" id="resTip">
                <i class="fas fa-lightbulb text-accent"></i> <span>Apply early for the best scholarship allocations!</span>
              </div>
              <div class="res-actions">
                <a href="contact.html" id="resApplyLink" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Apply for this Recommendation</a>
                <a href="destinations.html" id="resDetailsLink" class="btn btn-outline"><i class="fas fa-info-circle"></i> View Country Guide</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- INTERACTIVE FILTERABLE DESTINATIONS -->
  <section class="section" id="destinations">
    <div class="container">
      <div class="section-header text-center" data-animate="fade-up">
        <span class="section-tag"><i class="fas fa-globe"></i> World-Class Universities</span>
        <h2 class="section-title">Explore Global Study Destinations</h2>
        <p class="section-subtitle">Click the filters below to browse our 10 destinations by region.</p>
        
        <!-- Filter Tabs -->
        <div class="dest-filter-tabs">
          <button class="filter-tab active" data-filter="all">All (10 Countries)</button>
          <button class="filter-tab" data-filter="north-america">North America (USA, Canada)</button>
          <button class="filter-tab" data-filter="europe">Europe (UK, Albania, Cyprus, Lithuania)</button>
          <button class="filter-tab" data-filter="asia">Asia (China, Turkey, Hong Kong, S. Korea)</button>
        </div>
      </div>

      <div class="destination-cards-grid" id="destCardsGrid">
        ${Object.values(destinations).map(d => {
          let region = 'asia';
          if (['usa', 'canada'].includes(d.id)) region = 'north-america';
          else if (['uk', 'albania', 'cyprus', 'lithuania'].includes(d.id)) region = 'europe';
          return `
          <div class="dest-card" data-region="${region}" data-animate="fade-up">
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
          </div>`;
        }).join('')}
      </div>
    </div>
  </section>

  <!-- INTERACTIVE DOCUMENT READINESS CHECKLIST -->
  <section class="section bg-light">
    <div class="container">
      <div class="grid-2-col">
        <div class="doc-checklist-box card p-4" data-animate="fade-right">
          <span class="section-tag"><i class="fas fa-tasks"></i> Self-Assessment Tool</span>
          <h3 class="mb-3 text-primary">Student Document Readiness Checklist</h3>
          <p class="text-muted mb-4">Check the documents you currently hold to calculate your application readiness percentage:</p>
          
          <div class="checklist-items" id="docChecklist">
            <label class="check-item"><input type="checkbox" class="doc-check" value="15"> <span>Valid Passport (minimum 6 months validity)</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="20"> <span>Academic Transcripts &amp; Degree Certificates</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="15"> <span>Statement of Purpose (SOP) / Study Plan</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="15"> <span>English Test (IELTS / TOEFL / Duolingo or English Proficiency)</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="15"> <span>Bank Statement / Financial Sponsorship Proof</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="10"> <span>2-3 Letters of Recommendation (LOR)</span></label>
            <label class="check-item"><input type="checkbox" class="doc-check" value="10"> <span>Police Character Certificate &amp; CV</span></label>
          </div>

          <div class="progress-wrap mt-4">
            <div class="d-flex justify-content-between mb-1">
              <strong>Your Application Readiness:</strong>
              <strong id="progressPercent" class="text-primary">0%</strong>
            </div>
            <div class="progress-bar-bg">
              <div class="progress-bar-fill" id="progressFill" style="width: 0%;"></div>
            </div>
            <p class="progress-msg mt-2" id="progressMsg">Select your available documents above.</p>
          </div>
        </div>

        <div class="checklist-guide-content" data-animate="fade-left">
          <span class="section-tag"><i class="fas fa-shield-alt"></i> Complete Legal Care</span>
          <h2 class="section-title">Missing Some Documents? Ameen Consultancy Assists You!</h2>
          <p class="lead-text">
            Don't worry if you don't have all the documents ready yet. Our certified consultants in Matta Swat provide full documentation support.
          </p>
          <ul class="styled-check-list mt-3">
            <li><i class="fas fa-check-circle text-accent"></i> Board of Intermediate &amp; Secondary Education (BISE) attestation guide</li>
            <li><i class="fas fa-check-circle text-accent"></i> Higher Education Commission (HEC) degree verification assistance</li>
            <li><i class="fas fa-check-circle text-accent"></i> Ministry of Foreign Affairs (MOFA) attestation filing</li>
            <li><i class="fas fa-check-circle text-accent"></i> Professional SOP &amp; Recommendation Letter drafting</li>
            <li><i class="fas fa-check-circle text-accent"></i> Financial affidavit and bank statement audit for embassy compliance</li>
          </ul>
          <div class="pt-3">
            <a href="contact.html" class="btn btn-primary"><i class="fas fa-hand-holding-heart"></i> Get Documentation Support</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- SERVICES OVERVIEW -->
  <section class="section" id="services">
    <div class="container">
      <div class="section-header text-center" data-animate="fade-up">
        <span class="section-tag"><i class="fas fa-concierge-bell"></i> What We Offer</span>
        <h2 class="section-title">Our Comprehensive Services</h2>
        <p class="section-subtitle">"Guiding Students Towards Global Success" &mdash; Complete 360-degree support at every phase.</p>
      </div>
      <div class="services-cards-grid">
        ${services.map(s => `
        <div class="service-box" data-animate="fade-up">
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
      <div class="section-header text-center" data-animate="fade-up">
        <span class="section-tag badge-white"><i class="fas fa-check-circle"></i> Why Choose Ameen Consultancy</span>
        <h2 class="section-title text-white">Guiding Students Towards Global Success</h2>
        <p class="section-subtitle text-muted">10 verified reasons why Pakistani students trust Ameen Consultancy (Pvt) Ltd for their foreign study dreams.</p>
      </div>
      <div class="why-us-grid">
        ${whyPoints.map((w, idx) => `
        <div class="why-card" data-animate="fade-up">
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

  <!-- INTERACTIVE FAQ ACCORDION -->
  <section class="section bg-light" id="faq">
    <div class="container">
      <div class="section-header text-center" data-animate="fade-up">
        <span class="section-tag"><i class="fas fa-question-circle"></i> Common Inquiries</span>
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-subtitle">Quick answers to key questions about studying abroad, admissions, scholarships, and visas.</p>
      </div>
      <div class="faq-accordion-wrap">
        <div class="faq-item active">
          <button class="faq-question"><span>Can I apply for universities abroad without IELTS?</span> <i class="fas fa-chevron-down"></i></button>
          <div class="faq-answer">
            <p>Yes! Several destinations such as China, Turkey, Cyprus, Albania, and select universities in Lithuania and the UK accept an English Proficiency Certificate (issued by your previous college/university) or alternative tests like the Duolingo English Test (DET).</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question"><span>Are government scholarships available for Pakistani students?</span> <i class="fas fa-chevron-down"></i></button>
          <div class="faq-answer">
            <p>Absolutely. We specialize in Chinese Government Scholarships (CSC &amp; Provincial), Türkiye Bursları in Turkey, Global Korea Scholarship (GKS) in South Korea, and competitive university entrance waivers in Canada and the USA.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question"><span>What are the work rights during studies in the USA and Canada?</span> <i class="fas fa-chevron-down"></i></button>
          <div class="faq-answer">
            <p>In the USA, F-1 students can work on-campus up to 20 hours/week during term time and full-time during holidays, with CPT internships and 1-3 years of OPT after graduation. In Canada, international students can work off-campus up to 24 hours/week during academic terms and unlimited hours during scheduled breaks, followed by up to 3 years of Post-Graduation Work Permit (PGWP).</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question"><span>Is Ameen Consultancy legally certified in Pakistan?</span> <i class="fas fa-chevron-down"></i></button>
          <div class="faq-answer">
            <p>Yes. AMEEN CONSULTANCY (PVT) LIMITED is formally incorporated under the Companies Act, 2017 with Securities &amp; Exchange Commission of Pakistan (SECP Registration No: 0315168, incorporated on 13 November 2025).</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-question"><span>Where is the office located and how do I visit?</span> <i class="fas fa-chevron-down"></i></button>
          <div class="faq-answer">
            <p>Our head office is at Office No. 1 &amp; 2, Islamia College Building, College Chowk, Biha Road, Matta Swat, Khyber Pakhtunkhwa. You can call us at 0946-791780 or 0315-7500060 for prior appointments or walk in during business hours.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA BANNER -->
  <section class="cta-banner-section">
    <div class="container cta-banner-box" data-animate="scale-in">
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
        <a href="https://wa.me/923157500060?text=Hello%20Ameen%20Consultancy,%20I%20want%20to%20apply%20for%20study%20abroad" target="_blank" class="btn btn-outline-white btn-lg"><i class="fab fa-whatsapp"></i> WhatsApp Chat</a>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'index.html'), h, 'utf8');
  console.log('index.html with interactive slideshow, calculator, and filters written successfully.');
}

buildInteractiveIndex();
