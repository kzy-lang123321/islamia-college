const fs = require('fs');
const path = require('path');
const { head, nav, footer, brand, root } = require('./layout');

function innerBanner(title, subtitle, badge) {
  return `
  <section class="page-banner">
    <div class="container page-banner-inner">
      <div class="page-banner-text">
        ${badge ? `<span class="banner-badge">${badge}</span>` : ''}
        <h1 class="page-banner-title">${title}</h1>
        <p class="page-banner-subtitle">${subtitle}</p>
      </div>
    </div>
  </section>`;
}

function buildContact() {
  let h = head('Contact Us', 'Get in touch with Ameen Consultancy (Pvt) Ltd in Matta Swat, Khyber Pakhtunkhwa. Phone: 0946-791780, 0315-7500060.');
  h += nav('contact');
  h += innerBanner('Contact Ameen Consultancy', '"Dream Big, Study Abroad, Build Your Future."', 'Office No. 1 & 2, Islamia College Building, Matta Swat');
  h += `
  <section class="section">
    <div class="container">
      <div class="grid-2-col">
        <!-- FORM -->
        <div class="card p-5">
          <h3 class="mb-2 text-primary">Get in Touch Now</h3>
          <p class="text-muted mb-4">Fill in the inquiry form below and our certified counselor will get in touch within 24 hours.</p>
          <form id="contactForm" onsubmit="event.preventDefault(); alert('Thank you for contacting Ameen Consultancy (Pvt) Ltd! Your inquiry has been received. Our team will contact you shortly.');">
            <div class="form-group mb-3">
              <label for="cName"><strong>Full Name *</strong></label>
              <input type="text" id="cName" class="form-control" placeholder="Enter your full name" required>
            </div>
            <div class="form-row mb-3">
              <div class="form-col">
                <label for="cEmail"><strong>Email Address *</strong></label>
                <input type="email" id="cEmail" class="form-control" placeholder="name@domain.com" required>
              </div>
              <div class="form-col">
                <label for="cPhone"><strong>Phone / WhatsApp *</strong></label>
                <input type="tel" id="cPhone" class="form-control" placeholder="e.g. 0315-7500060" required>
              </div>
            </div>
            <div class="form-row mb-3">
              <div class="form-col">
                <label for="cCountry"><strong>Destination Country *</strong></label>
                <select id="cCountry" class="form-control" required>
                  <option value="" disabled selected>Select country of interest</option>
                  <option value="china">China</option>
                  <option value="turkey">Turkey</option>
                  <option value="hong-kong">Hong Kong</option>
                  <option value="south-korea">South Korea</option>
                  <option value="usa">USA</option>
                  <option value="canada">Canada</option>
                  <option value="uk">United Kingdom</option>
                  <option value="albania">Albania</option>
                  <option value="cyprus">European Cyprus</option>
                  <option value="lithuania">Lithuania</option>
                </select>
              </div>
              <div class="form-col">
                <label for="cLevel"><strong>Study Level</strong></label>
                <select id="cLevel" class="form-control">
                  <option value="undergrad">Undergraduate / Bachelor's</option>
                  <option value="master">Postgraduate / Master's</option>
                  <option value="phd">PhD / Doctoral</option>
                  <option value="diploma">College Diploma / Pathway</option>
                </select>
              </div>
            </div>
            <div class="form-group mb-4">
              <label for="cMsg"><strong>Your Message or Academic Background</strong></label>
              <textarea id="cMsg" rows="4" class="form-control" placeholder="Mention your highest qualification, marks/GPA, IELTS score (if any), and desired field of study..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-paper-plane"></i> Submit Free Inquiry</button>
          </form>
        </div>

        <!-- DETAILS (PDF PAGE 18) -->
        <div class="contact-sidebar">
          <div class="card p-4 mb-4">
            <div class="d-flex align-items-center mb-3">
              <img src="assets/images/logo.jpg" alt="Ameen Consultancy Logo" style="width: 60px; height: 60px; border-radius: 50%; margin-right: 15px;">
              <div>
                <h3 class="mb-0">AMEEN CONSULTANCY</h3>
                <small class="text-accent font-weight-bold">(PVT) LIMITED</small>
              </div>
            </div>
            <p class="quote-text mb-4">"Dream Big, Study Abroad, Build Your Future."</p>
            <div class="contact-info-list">
              <div class="ci-item">
                <i class="fas fa-map-marker-alt text-primary fa-lg"></i>
                <div>
                  <strong>Head Office:</strong>
                  <p>Office No. 1 &amp; 2, Islamia College Building, College Chowk, Biha Road, Matta Swat, Khyber Pakhtunkhwa, Pakistan</p>
                </div>
              </div>
              <div class="ci-item">
                <i class="fas fa-phone-alt text-primary fa-lg"></i>
                <div>
                  <strong>Office Landline:</strong>
                  <p><a href="tel:0946791780">0946-791780</a></p>
                </div>
              </div>
              <div class="ci-item">
                <i class="fas fa-mobile-alt text-primary fa-lg"></i>
                <div>
                  <strong>Mobile &amp; WhatsApp:</strong>
                  <p><a href="tel:03157500060">0315-7500060</a></p>
                </div>
              </div>
              <div class="ci-item">
                <i class="fas fa-envelope text-primary fa-lg"></i>
                <div>
                  <strong>Official Email:</strong>
                  <p><a href="mailto:ameenconsultancy.pk@gmail.com">ameenconsultancy.pk@gmail.com</a></p>
                </div>
              </div>
              <div class="ci-item">
                <i class="fas fa-certificate text-primary fa-lg"></i>
                <div>
                  <strong>Corporate Registration:</strong>
                  <p>SECP Reg. No: <strong>0315168</strong><br>Incorporated 13 Nov 2025</p>
                </div>
              </div>
            </div>
          </div>

          <!-- COUNTRIES SERVED PILLS -->
          <div class="card p-4">
            <h4 class="mb-3 text-primary"><i class="fas fa-globe"></i> Destinations Represented</h4>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge-tag">China</span>
              <span class="badge-tag">Turkey</span>
              <span class="badge-tag">Hong Kong</span>
              <span class="badge-tag">South Korea</span>
              <span class="badge-tag">USA</span>
              <span class="badge-tag">Albania</span>
              <span class="badge-tag">UK</span>
              <span class="badge-tag">Cyprus</span>
              <span class="badge-tag">Lithuania</span>
              <span class="badge-tag">Canada</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>`;
  h += footer();
  fs.writeFileSync(path.join(root, 'contact.html'), h, 'utf8');
  console.log('contact.html written');
}

buildContact();
