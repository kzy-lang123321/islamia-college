const fs = require('fs');
const path = require('path');

const root = 'C:\\Ameen Consultancy';

// 1. BRAND DATA
const brand = {
  name: 'AMEEN CONSULTANCY',
  legal: 'AMEEN CONSULTANCY (PVT) LIMITED',
  tagline: 'Your Trusted Partner for International Education & Career Success',
  vision: 'Transforming dreams into international success.',
  mission: 'To connect talented students with world-class educational opportunities and build successful global careers.',
  phone1: '0946-791780',
  phone2: '0315-7500060',
  email: 'ameenconsultancy.pk@gmail.com',
  address: 'Office No. 1 & 2, Islamia College Building, College Chowk, Biha Road, Matta Swat, Khyber Pakhtunkhwa, Pakistan',
  secp: '0315168',
  incDate: '13 Nov 2025',
  stats: [
    { num: '10+', label: 'Countries Covered', icon: 'fa-globe-americas' },
    { num: '100+', label: 'Partner Universities', icon: 'fa-university' },
    { num: '500+', label: 'Students Assisted', icon: 'fa-user-graduate' },
    { num: '100%', label: 'Dedicated Support', icon: 'fa-handshake' }
  ]
};

// 2. DESTINATIONS DATA (From all 18 pages of PDF)
const destinations = {
  china: {
    id: 'china',
    name: 'China',
    file: 'china.html',
    flag: 'flag_china.png',
    tagline: 'World-Class Education in the Heart of Asia',
    unis: ['Tsinghua University', 'Peking University', 'Zhejiang University', 'Fudan University'],
    benefits: [
      'Internationally Recognized Degrees',
      'Government Scholarships (CSC, Belt & Road)',
      'Engineering & Technology Excellence',
      'World’s Second Largest Economy & Innovation Hub',
      'Affordable Tuition Fees & Living Costs',
      'Advanced Research Facilities & Laboratories',
      'Extensive Medical Programs (MBBS in English)',
      'Safe, Secure & Student-Friendly Environment'
    ],
    popularPrograms: ['Clinical Medicine (MBBS)', 'Computer Science & AI', 'Civil & Mechanical Engineering', 'International Trade & Business', 'Chinese Language & Culture'],
    overview: 'China has rapidly emerged as a leading global educational powerhouse. Home to world-class universities like Tsinghua and Peking, China offers Pakistani students cutting-edge education, lucrative government scholarships, and high-tech career exposure.'
  },
  turkey: {
    id: 'turkey',
    name: 'Turkey',
    file: 'turkey.html',
    flag: 'flag_turkey.png',
    tagline: 'Where East Meets West - A Bridge to Global Education',
    unis: ['Istanbul University', 'Ankara University', 'Middle East Technical University', 'Istanbul Medipol University'],
    benefits: [
      'High Quality Education Matching European Standards',
      'Vibrant International Student Environment',
      'Türkiye Bursları & University Scholarship Programs',
      'Student-Friendly Historic & Modern Cities',
      'Affordable Tuition Compared to Western Europe',
      'Unique European & Asian Cultural Exposure',
      'Rich Culture, Islamic Heritage & Warm Hospitality',
      'State-of-the-Art Modern University Campuses'
    ],
    popularPrograms: ['Architecture & Civil Engineering', 'Medicine & Dentistry', 'International Relations', 'Business Administration', 'Software Engineering'],
    overview: 'Turkey bridges Europe and Asia, offering top-tier universities, internationally accredited degrees via the Bologna Process, and generous government scholarships in a welcoming and culturally familiar environment for Pakistani students.'
  },
  'hong-kong': {
    id: 'hong-kong',
    name: 'Hong Kong',
    file: 'hong-kong.html',
    flag: 'flag_hong_kong.png',
    tagline: 'Asia\'s World City - Gateway to Global Opportunities',
    unis: ['University of Hong Kong (HKU)', 'HK Polytechnic University (PolyU)', 'City University of Hong Kong', 'Hong Kong University of Science and Technology (HKUST)'],
    benefits: [
      'World-Class, Top-50 Globally Ranked Universities',
      'Premier Global Financial & International Business Hub',
      'Multicultural International Exposure & Networking',
      'Strategic Location at the Gateway of Asia',
      '100% English-Medium Higher Education',
      'Exceptional Post-Graduation Career Opportunities (IANG Visa)',
      'Ultra-Modern Learning Environment & Research Centers',
      'Globally Prestigious Academic Degrees'
    ],
    popularPrograms: ['Finance & Accounting', 'Data Science & FinTech', 'Biomedical Sciences', 'Supply Chain & Logistics', 'Artificial Intelligence'],
    overview: 'Hong Kong is Asia’s financial capital and home to some of the highest-ranked universities in the world. With English-taught programs, prestigious degrees, and seamless post-study work routes (IANG visa), it is an elite choice for ambitious scholars.'
  },
  'south-korea': {
    id: 'south-korea',
    name: 'South Korea',
    file: 'south-korea.html',
    flag: 'flag_south_korea.png',
    tagline: 'Innovation & Excellence in the Land of the Morning Calm',
    unis: ['Seoul National University', 'Korea University', 'Yonsei University', 'KAIST (Korea Advanced Institute of Science & Technology)'],
    benefits: [
      'Global Leader in Advanced Technology & Robotics',
      'Generous Global Korea Scholarship (GKS) & University Grants',
      'Ultra-Modern Smart Campuses & High-Tech Labs',
      'Dynamic K-Culture, Arts & Innovation Ecosystem',
      'World-Leading R&D and Global Research Opportunities',
      'Exceptionally Safe & Peaceful Living Environment',
      'Strong Global Job Market (Samsung, Hyundai, LG)',
      'Unrivaled STEM Excellence & Scientific Innovation'
    ],
    popularPrograms: ['Robotics & Artificial Intelligence', 'Electrical & Electronics Engineering', 'Biotechnology', 'International Business', 'Digital Media & Game Design'],
    overview: 'South Korea is at the forefront of global technological innovation. With prestigious SKY universities and KAIST, students benefit from cutting-edge research, full scholarships, and dynamic career opportunities in a technologically advanced society.'
  },
  albania: {
    id: 'albania',
    name: 'Albania',
    file: 'albania.html',
    flag: 'flag_albania.png',
    tagline: 'Affordable European Education - Your Gateway to the EU',
    unis: ['University of Tirana', 'Epoka University', 'Beder University', 'Polis University'],
    benefits: [
      'Highly Affordable European Higher Education',
      'Low Living Costs & Budget-Friendly Lifestyle',
      'English-Taught International Programs',
      'Strategic EU Pathway & European Credit Transfer (ECTS)',
      'Bologna-Compliant European Recognized Degrees',
      'Safe, Friendly & Welcoming Mediterranean Environment',
      'Growing Regional & European Career Opportunities',
      'Rich Multicultural Experience in the Heart of the Balkans'
    ],
    popularPrograms: ['Business Informatics & IT', 'Architecture & Urban Planning', 'Civil Engineering', 'Economics & Finance', 'Humanities & Law'],
    overview: 'Albania is an emerging European destination offering Bologna-compliant degrees at very affordable tuition and living costs. It provides students with a comfortable European lifestyle and smooth pathways to wider EU opportunities.'
  },
  uk: {
    id: 'uk',
    name: 'United Kingdom (UK)',
    file: 'uk.html',
    flag: 'flag_uk.png',
    tagline: 'Centuries of Academic Excellence - Study Where the Best Study',
    unis: ['University of Oxford', 'University of Cambridge', 'University of Manchester', 'University of Birmingham'],
    benefits: [
      'Globally Revered Degrees with Worldwide Recognition',
      'World-Class Pioneer Scientific & Academic Research',
      'Short Degree Durations (1-Year Master’s, 3-Year Bachelor’s)',
      '2-Year Post-Study Work Visa (Graduate Route)',
      'Top Employability & Strong Global Graduate Hiring',
      'Diverse, Cosmopolitan Student Community from 150+ Nations',
      'Unmatched International Alumni Networking',
      'Historic Academic Excellence & Cultural Heritage'
    ],
    popularPrograms: ['Law & Legal Studies', 'Data Science & Cybersecurity', 'Finance, Banking & FinTech', 'Mechanical & Aerospace Engineering', 'Public Health & Medicine'],
    overview: 'The UK is a global leader in higher education. With prestigious historic universities, accelerated degree lengths, and the 2-year Graduate Route work visa, studying in the UK provides an unforgettable academic and career springboard.'
  },
  cyprus: {
    id: 'cyprus',
    name: 'European Cyprus',
    file: 'cyprus.html',
    flag: 'flag_cyprus.png',
    tagline: 'European Education in the Mediterranean',
    unis: ['University of Cyprus', 'Cyprus University of Technology', 'University of Nicosia', 'European University Cyprus'],
    benefits: [
      'Official European Union (EU) Accredited Education',
      'High Quality English-Taught Degree Programs',
      'Thriving Vibrant International Student Community',
      'Healthy Mediterranean Lifestyle, Sun & Safety',
      'Exceptional Business & IT Degree Specializations',
      'Renowned Medical & Health Sciences Programs',
      'Full Erasmus+ European Exchange Opportunities',
      'Affordable European Pathway with Transparent Visa Norms'
    ],
    popularPrograms: ['General Medicine (MD)', 'Computer Science & Blockchain', 'Hospitality & Tourism Management', 'Business Administration (MBA)', 'Nursing & Health Sciences'],
    overview: 'European Cyprus combines European Union accredited degrees with a picturesque Mediterranean lifestyle. With high visa success rates and world-renowned medical and IT programs, it is an accessible gateway to EU education.'
  },
  lithuania: {
    id: 'lithuania',
    name: 'Lithuania',
    file: 'lithuania.html',
    flag: 'flag_lithuania.png',
    tagline: 'Quality European Education in the Baltic Region',
    unis: ['Vilnius University', 'Kaunas University of Technology (KTU)', 'Vilnius Tech (VILNIUS TECH)', 'Lithuanian University of Health Sciences (LSMU)'],
    benefits: [
      'Full European Union (EU) Accredited Degrees',
      'Wide Selection of English-Taught Bachelor’s & Master’s',
      'Affordable Tuition Fees and Manageable Living Expenses',
      'Safe, Clean & Highly Developed Student Environment',
      'Leading Hub for FinTech, Technology & Engineering',
      'Globally Recognized Medical & Veterinary Study Options',
      'Full Schengen Area Access & Travel Freedom',
      'Erasmus+ Exchange Opportunities Across 30+ European Countries'
    ],
    popularPrograms: ['General Medicine & Dentistry', 'Software Engineering & FinTech', 'Biomedical Engineering', 'Aviation & Mechanical Engineering', 'Business Management'],
    overview: 'Lithuania, situated in the Baltic region, is a member of the European Union and Schengen Area. Known as Europe’s booming FinTech and tech innovation hub, it offers affordable, globally recognized degrees and unrestricted European travel.'
  },
  usa: {
    id: 'usa',
    name: 'United States of America (USA)',
    file: 'usa.html',
    flag: 'flag_usa.png',
    tagline: 'Home to the World\'s Most Prestigious Universities',
    unis: [
      'Harvard University', 'MIT (Massachusetts Institute of Technology)', 'Stanford University', 'UC Berkeley',
      'Yale University', 'Princeton University', 'Columbia University', 'University of Chicago',
      'University of Pennsylvania', 'Cornell University', 'University of Michigan', 'Arizona State University'
    ],
    benefits: [
      'Home to the World’s Highest-Ranked Universities',
      'Optional Practical Training (OPT) US Work Authorization',
      'Unmatched Flexibility in Majors, Minors & Course Selection',
      'Massive Research Grants & Departmental Funding Opportunities',
      'Diverse, Dynamic & Multicultural Student Body',
      'STEM OPT Extension (Up to 3 Full Years of Work Rights)',
      'Invaluable Global Alumni & Corporate Career Networks',
      'Extensive Portfolio of Need-Based & Merit Scholarships'
    ],
    overview: 'The United States represents the pinnacle of global academia. With flexible degree curriculums, industry-defining research, and up to 3 years of post-study work authorization via STEM OPT, an American degree unlocks limitless career potential.'
  },
  canada: {
    id: 'canada',
    name: 'Canada',
    file: 'canada.html',
    flag: 'flag_canada.png',
    tagline: 'Quality Education, Warm Welcome, Bright Future',
    unis: [
      'University of Toronto', 'University of British Columbia (UBC)', 'McGill University', 'University of Waterloo',
      'University of Alberta', 'McMaster University', 'University of Ottawa', 'Western University',
      'Queen’s University', 'Dalhousie University', 'Simon Fraser University', 'University of Montreal'
    ],
    benefits: [
      'Globally Recognized Degrees with High Academic Prestige',
      'Affordable Tuition Compared to the USA and UK',
      'Post-Graduation Work Permit (PGWP) for up to 3 Years',
      'Clear, Transparent Pathway to Canadian Permanent Residency (PR)',
      'Safe, Welcoming, Peaceful & Multicultural Cities',
      'Built-In Co-op & Paid Industry Internship Opportunities',
      'World-Renowned Quality of Life, Public Safety & Universal Healthcare',
      'Rapidly Growing Tech, AI, Healthcare & STEM Job Markets'
    ],
    overview: 'Canada is one of the world’s most sought-after study destinations for international students. Renowned for academic excellence, safe multicultural communities, paid Co-op internships, and clear PR pathways, Canadian universities offer the ideal bridge to a bright future.'
  }
};

// 3. SERVICES DATA (All 8 from PDF page 3 + detailed descriptions)
const services = [
  {
    id: 'admissions',
    title: 'University Admissions',
    icon: 'fa-university',
    tag: 'Step 01',
    desc: 'Personalized evaluation of your academic profile and strategic matching with top accredited universities across 10+ countries. We handle university applications, admission essay editing, and coordinate directly with admissions offices to maximize acceptance rates.'
  },
  {
    id: 'visa',
    title: 'Visa Processing',
    icon: 'fa-passport',
    tag: 'Step 02',
    desc: 'Expert guidance through complex international student visa protocols including US F-1, Canadian Study Permit, UK Student Route, Schengen visas, and Asian study permits. We verify financial documentation, file DS-160/IRCC applications, and eliminate common refusal risks.'
  },
  {
    id: 'documentation',
    title: 'Document Guide',
    icon: 'fa-file-alt',
    tag: 'Step 03',
    desc: 'Complete assistance with academic document compilation, Board/HEC attestations, Ministry of Foreign Affairs (MOFA) verifications, certified translations, police character certificates, and bank statements compliant with embassy criteria.'
  },
  {
    id: 'counseling',
    title: 'Career Counseling',
    icon: 'fa-compass',
    tag: 'Step 04',
    desc: 'One-on-one professional career counseling designed to align your academic strengths and personal ambitions with emerging global labor market demands, ensuring strong post-graduation employability and long-term career growth.'
  },
  {
    id: 'scholarship',
    title: 'Scholarship Help',
    icon: 'fa-award',
    tag: 'Step 05',
    desc: 'Specialized assistance in identifying and applying for fully-funded and partial scholarships, including Chinese CSC, Turkish Türkiye Bursları, South Korean GKS, US Fulbright, and competitive university merit-based fee remissions.'
  },
  {
    id: 'interview',
    title: 'Interview Preparation',
    icon: 'fa-comments',
    tag: 'Step 06',
    desc: 'Intensive mock interview sessions conducted by experienced counselors. We prepare students for embassy visa interviews and university faculty admission calls, building poise, clarity, and rock-solid confidence.'
  },
  {
    id: 'pre-departure',
    title: 'Pre-Departure Guide',
    icon: 'fa-plane-departure',
    tag: 'Step 07',
    desc: 'Comprehensive briefings before you fly: guidance on international student health insurance, on-campus and private accommodation booking, currency exchange, airport arrivals, and cultural adaptation in your host nation.'
  },
  {
    id: 'support',
    title: 'Student Support',
    icon: 'fa-hands-helping',
    tag: 'Step 08',
    desc: 'Our relationship does not end at the airport. We provide continuous student welfare support, guidance on semester registration, visa extensions, post-study work permits, and emergency assistance throughout your academic journey.'
  }
];

// 4. WHY CHOOSE US (All 10 points from PDF page 3)
const whyPoints = [
  { title: 'Professional Consultants', desc: 'Expert team with deep regulatory and academic knowledge guiding Pakistani students.', icon: 'fa-user-tie' },
  { title: 'International University Network', desc: 'Direct partnerships and access to over 100+ prestigious world-class universities.', icon: 'fa-globe' },
  { title: 'Affordable Study Options', desc: 'Tailored university solutions accommodating various budget constraints and financial plans.', icon: 'fa-wallet' },
  { title: 'Visa Assistance', desc: 'Industry-leading visa approval track record with meticulous file preparation and mock drills.', icon: 'fa-stamp' },
  { title: 'Documentation Support', desc: 'End-to-end guidance through HEC, IBCC, MOFA attestation and embassy notarization.', icon: 'fa-check-double' },
  { title: 'Scholarship Guidance', desc: 'Dedicated tracking of global governmental and institutional scholarships for deserving students.', icon: 'fa-graduation-cap' },
  { title: 'Career Counseling', desc: 'Strategic degree planning geared toward high-growth global industries and PR avenues.', icon: 'fa-briefcase' },
  { title: 'Interview Preparation', desc: 'Rigorous 1-on-1 interview simulations ensuring students face consular officers with poise.', icon: 'fa-microphone-alt' },
  { title: 'Student Success Focus', desc: 'Every student receives bespoke attention tailored to their unique academic and career objectives.', icon: 'fa-star' },
  { title: 'Trusted & Reliable', desc: 'Formally incorporated under the Companies Act 2017 with SECP Registration No: 0315168.', icon: 'fa-shield-alt' }
];

console.log('Data structures validated.');

module.exports = { destinations, services, whyPoints, brand };
