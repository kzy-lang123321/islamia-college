document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 1. MULTI-THEME ENGINE (Emerald, Dark, Royal)
  // =========================================================================
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  const themeMenu = document.getElementById('themeMenu');
  const themeOpts = document.querySelectorAll('[data-set-theme]');
  const htmlRoot = document.documentElement;

  // Load saved theme or default to emerald
  const savedTheme = localStorage.getItem('ameen_theme') || 'emerald';
  setTheme(savedTheme);

  if (themeToggleBtn && themeMenu) {
    themeToggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      themeMenu.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!themeMenu.contains(e.target) && e.target !== themeToggleBtn) {
        themeMenu.classList.remove('show');
      }
    });
  }

  themeOpts.forEach(btn => {
    btn.addEventListener('click', () => {
      const theme = btn.getAttribute('data-set-theme');
      setTheme(theme);
      if (themeMenu) themeMenu.classList.remove('show');
    });
  });

  function setTheme(t) {
    htmlRoot.setAttribute('data-theme', t);
    localStorage.setItem('ameen_theme', t);
    const themeNameSpan = document.querySelector('.theme-current-name');
    if (themeNameSpan) {
      if (t === 'dark') themeNameSpan.textContent = 'Dark';
      else if (t === 'royal') themeNameSpan.textContent = 'Royal';
      else themeNameSpan.textContent = 'Emerald';
    }
  }

  // =========================================================================
  // 2. HERO SLIDESHOW (Auto-play, next/prev, dots, touch-friendly)
  // =========================================================================
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.slider-dots .dot');
  const prevBtn = document.getElementById('sliderPrev');
  const nextBtn = document.getElementById('sliderNext');
  let currentSlide = 0;
  let slideInterval = null;

  function showSlide(index) {
    if (!slides.length) return;
    if (index >= slides.length) currentSlide = 0;
    else if (index < 0) currentSlide = slides.length - 1;
    else currentSlide = index;

    slides.forEach((s, idx) => {
      s.classList.toggle('active', idx === currentSlide);
    });

    dots.forEach((d, idx) => {
      d.classList.toggle('active', idx === currentSlide);
    });
  }

  function nextSlide() {
    showSlide(currentSlide + 1);
  }

  function prevSlide() {
    showSlide(currentSlide - 1);
  }

  if (slides.length > 0) {
    // Autoplay every 5 seconds
    slideInterval = setInterval(nextSlide, 5000);

    // Pause on hover
    const sliderContainer = document.querySelector('.hero-slider-container');
    if (sliderContainer) {
      sliderContainer.addEventListener('mouseenter', () => clearInterval(slideInterval));
      sliderContainer.addEventListener('mouseleave', () => {
        clearInterval(slideInterval);
        slideInterval = setInterval(nextSlide, 5000);
      });
    }

    if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); clearInterval(slideInterval); });
    if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); clearInterval(slideInterval); });

    dots.forEach((d, idx) => {
      d.addEventListener('click', () => {
        showSlide(idx);
        clearInterval(slideInterval);
      });
    });
  }

  // =========================================================================
  // 3. INTERACTIVE STUDY ABROAD ELIGIBILITY & BUDGET FINDER
  // =========================================================================
  const runFinderBtn = document.getElementById('runFinderBtn');
  const finderResult = document.getElementById('finderResult');

  if (runFinderBtn && finderResult) {
    runFinderBtn.addEventListener('click', () => {
      const country = document.getElementById('findCountry').value;
      const level = document.getElementById('findLevel').value;
      const ielts = document.getElementById('findIelts').value;
      const budget = document.getElementById('findBudget').value;

      let rec = {
        title: 'China &mdash; Full CSC Scholarship & Top Rankings',
        sub: 'Best match for low budget and strong engineering or medical aspirations',
        unis: 'Tsinghua University, Peking University, Zhejiang University, Fudan University',
        cost: '$1,500 - $4,500 / Year (100% Tuition Waivers Available)',
        visa: 'Fast JW202 form issuance & high visa approval rate',
        tip: 'CSC Chinese Government Scholarships cover full tuition, accommodation, and living stipends!',
        flag: 'flag_china.png',
        link: 'china.html'
      };

      if (country === 'usa' || (country === 'any' && budget === 'high')) {
        rec = {
          title: 'United States &mdash; Top Tier Ivy & State Universities',
          sub: 'Ideal for students seeking STEM OPT (3-Year Work Rights) and global research leadership',
          unis: 'Harvard, MIT, Stanford, UC Berkeley, Columbia, Arizona State University',
          cost: '$20,000 - $35,000 / Year (Assistantships & Merit Scholarships available)',
          visa: 'F-1 Visa with mandatory I-20 and consular interview in Islamabad',
          tip: 'Book 6 months in advance for US embassy visa appointment dates.',
          flag: 'flag_usa.png',
          link: 'usa.html'
        };
      } else if (country === 'canada' || (country === 'any' && (level === 'master' || level === 'undergrad') && budget === 'high')) {
        rec = {
          title: 'Canada &mdash; World-Class Education & Clear PR Pathway',
          sub: 'Top choice for students aiming for up to 3 years PGWP and Canadian Permanent Residency',
          unis: 'University of Toronto, UBC, McGill, Waterloo, Alberta, McMaster',
          cost: '$15,000 - $30,000 CAD / Year (Living GIC ~$20,635 CAD)',
          visa: 'IRCC Study Permit with biometric verification & Port of Entry approval',
          tip: 'Ensure your designated college program is linked to high-demand IRCC labour sectors.',
          flag: 'flag_canada.png',
          link: 'canada.html'
        };
      } else if (country === 'uk') {
        rec = {
          title: 'United Kingdom &mdash; 1-Year Accelerated Master\'s & Graduate Visa',
          sub: 'Fast-track degree qualification with 2-year post-study work rights',
          unis: 'Oxford, Cambridge, Manchester, Birmingham, Russell Group',
          cost: '£14,000 - £25,000 / Year',
          visa: 'UK Student Route CAS sponsorship with priority processing',
          tip: 'Saves 1 full year of living expenses due to accelerated 1-year master’s structure.',
          flag: 'flag_uk.png',
          link: 'uk.html'
        };
      } else if (country === 'turkey' || (country === 'any' && budget === 'mid')) {
        rec = {
          title: 'Turkey &mdash; Bologna European Standard Education',
          sub: 'No IELTS required with English proficiency letter, generous Türkiye Bursları',
          unis: 'Istanbul University, Ankara University, METU, Istanbul Medipol',
          cost: '$2,000 - $8,000 / Year',
          visa: 'Easy embassy student visa processing with university acceptance letter',
          tip: 'Cultural familiarity and low cost of living make Turkey very attractive.',
          flag: 'flag_turkey.png',
          link: 'turkey.html'
        };
      } else if (country === 'south-korea') {
        rec = {
          title: 'South Korea &mdash; Advanced Robotics, Tech & Full GKS Grants',
          sub: 'World-leading R&D with major tech employers (Samsung, Hyundai, LG)',
          unis: 'Seoul National University, Korea University, Yonsei, KAIST',
          cost: '$4,000 - $9,000 / Year (100% GKS Scholarships available)',
          visa: 'D-2 Student Visa issued with certificate of admission',
          tip: 'High demand for AI and Electrical Engineering scholars in Seoul.',
          flag: 'flag_south_korea.png',
          link: 'south-korea.html'
        };
      } else if (['albania', 'cyprus', 'lithuania'].includes(country) || (country === 'any' && ielts === 'none')) {
        rec = {
          title: 'European Union Pathways (Cyprus, Lithuania, Albania)',
          sub: 'English-taught programs, low tuition fees, and Schengen Area access',
          unis: 'Vilnius Tech, University of Cyprus, Epoka University, LSMU',
          cost: '€2,500 - €7,000 / Year',
          visa: 'National Student Visa with Erasmus+ mobility benefits',
          tip: 'Pakistani students can enter easily using prior English medium certificates.',
          flag: country === 'lithuania' ? 'flag_lithuania.png' : (country === 'cyprus' ? 'flag_cyprus.png' : 'flag_albania.png'),
          link: country === 'lithuania' ? 'lithuania.html' : (country === 'cyprus' ? 'cyprus.html' : 'albania.html')
        };
      }

      document.getElementById('resTitle').innerHTML = rec.title;
      document.getElementById('resSub').textContent = rec.sub;
      document.getElementById('resUnis').textContent = rec.unis;
      document.getElementById('resCost').textContent = rec.cost;
      document.getElementById('resVisa').textContent = rec.visa;
      document.getElementById('resTip').innerHTML = '<i class="fas fa-lightbulb text-accent"></i> <span>' + rec.tip + '</span>';
      document.getElementById('resFlagImg').src = 'assets/images/' + rec.flag;
      document.getElementById('resDetailsLink').href = rec.link;

      finderResult.style.display = 'block';
      finderResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }

  // =========================================================================
  // 4. INTERACTIVE DESTINATION FILTER TABS
  // =========================================================================
  const filterTabs = document.querySelectorAll('.filter-tab');
  const destCards = document.querySelectorAll('#destCardsGrid .dest-card');

  filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const filter = tab.getAttribute('data-filter');

      destCards.forEach(card => {
        const region = card.getAttribute('data-region');
        if (filter === 'all' || region === filter) {
          card.style.display = 'flex';
          card.style.animation = 'fadeInUp 0.4s ease forwards';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // =========================================================================
  // 5. INTERACTIVE DOCUMENT READINESS CHECKLIST
  // =========================================================================
  const docChecks = document.querySelectorAll('.doc-check');
  const progressFill = document.getElementById('progressFill');
  const progressPercent = document.getElementById('progressPercent');
  const progressMsg = document.getElementById('progressMsg');

  if (docChecks.length > 0 && progressFill) {
    docChecks.forEach(check => {
      check.addEventListener('change', updateChecklist);
    });

    function updateChecklist() {
      let total = 0;
      docChecks.forEach(c => {
        if (c.checked) total += parseInt(c.value);
      });
      if (total > 100) total = 100;

      progressFill.style.width = total + '%';
      progressPercent.textContent = total + '%';

      if (total === 0) {
        progressMsg.textContent = 'Select your available documents above.';
        progressFill.style.background = 'var(--primary)';
      } else if (total < 40) {
        progressMsg.textContent = 'Getting started! Ameen Consultancy can help you gather the rest.';
        progressFill.style.background = '#f59e0b';
      } else if (total < 80) {
        progressMsg.textContent = 'Good progress! You are eligible for initial university evaluations.';
        progressFill.style.background = '#3b82f6';
      } else {
        progressMsg.textContent = 'Outstanding! You are 100% ready for university admission & visa submission.';
        progressFill.style.background = '#16a34a';
      }
    }
  }

  // =========================================================================
  // 6. INTERACTIVE FAQ ACCORDION
  // =========================================================================
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    const q = item.querySelector('.faq-question');
    if (q) {
      q.addEventListener('click', () => {
        const isActive = item.classList.contains('active');
        faqItems.forEach(i => i.classList.remove('active'));
        if (!isActive) item.classList.add('active');
      });
    }
  });

  // =========================================================================
  // 7. COUNTER ANIMATION ON SCROLL
  // =========================================================================
  const counters = document.querySelectorAll('.counter');
  let animated = false;

  function runCounters() {
    if (animated) return;
    counters.forEach(counter => {
      const target = +counter.getAttribute('data-target');
      let count = 0;
      const speed = target / 30;

      const updateCount = () => {
        count += speed;
        if (count < target) {
          counter.innerText = Math.ceil(count) + '+';
          setTimeout(updateCount, 40);
        } else {
          counter.innerText = target + '+';
        }
      };
      updateCount();
    });
    animated = true;
  }

  // Trigger counters when scrolled into view
  const statsSection = document.querySelector('.stats-bar-section');
  if (statsSection) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          runCounters();
        }
      });
    }, { threshold: 0.3 });
    observer.observe(statsSection);
  }

  // =========================================================================
  // 8. SCROLL ANIMATION OBSERVER
  // =========================================================================
  const animElements = document.querySelectorAll('[data-animate]');
  if (animElements.length > 0) {
    const animObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const delay = entry.target.getAttribute('data-delay') || 0;
          setTimeout(() => {
            entry.target.classList.add('animated');
          }, delay);
          animObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    animElements.forEach(el => animObserver.observe(el));
  }

  // =========================================================================
  // 9. NAVBAR & BACK TO TOP
  // =========================================================================
  const mobileToggle = document.getElementById('mobileToggle');
  const navMenu = document.getElementById('navMenu');
  const header = document.getElementById('siteHeader');
  const backToTop = document.getElementById('backToTop');

  if (mobileToggle && navMenu) {
    mobileToggle.addEventListener('click', () => {
      navMenu.classList.toggle('open');
      mobileToggle.classList.toggle('active');
    });
  }

  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) {
      if (header) header.classList.add('scrolled');
      if (backToTop) backToTop.style.display = 'flex';
    } else {
      if (header) header.classList.remove('scrolled');
      if (backToTop) backToTop.style.display = 'none';
    }
  });

  if (backToTop) {
    backToTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Auto-select country parameter in forms
  const urlParams = new URLSearchParams(window.location.search);
  const countryParam = urlParams.get('country');
  const countrySelect = document.getElementById('cCountry');
  if (countryParam && countrySelect) {
    countrySelect.value = countryParam;
  }
});
