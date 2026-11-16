const fs = require('fs');
const path = require('path');

const root = 'C:\\Ameen Consultancy';
const imgDir = 'assets/images';

// Brand info
const brand = {
  name: 'AMEEN CONSULTANCY',
  legal: 'AMEEN CONSULTANCY (PVT) LIMITED',
  tagline: 'Your Trusted Partner for International Education & Career Success',
  slogan: 'Transforming dreams into international success.',
  mission: 'To connect talented students with world-class educational opportunities and build successful global careers.',
  phone1: '0946-791780',
  phone2: '0315-7500060',
  email: 'ameenconsultancy.pk@gmail.com',
  address: 'Office No. 1 & 2, Islamia College Building, College Chowk, Biha Road, Matta Swat, Khyber Pakhtunkhwa, Pakistan',
  secp: '0315168',
  incDate: '13 Nov 2025'
};

const countries = [
  { id: 'china', name: 'China', file: 'china.html', flag: 'flag_china.png', badge: 'World-Class Education in the Heart of Asia', unis: ['Tsinghua University', 'Peking University', 'Zhejiang University', 'Fudan University'] },
  { id: 'turkey', name: 'Turkey', file: 'turkey.html', flag: 'flag_turkey.png', badge: 'Where East Meets West - A Bridge to Global Education', unis: ['Istanbul University', 'Ankara University', 'Middle East Technical University', 'Istanbul Medipol University'] },
  { id: 'hong-kong', name: 'Hong Kong', file: 'hong-kong.html', flag: 'flag_hong_kong.png', badge: 'Asia\'s World City - Gateway to Global Opportunities', unis: ['University of Hong Kong', 'HK Polytechnic University', 'City University of HK', 'HKUST'] },
  { id: 'south-korea', name: 'South Korea', file: 'south-korea.html', flag: 'flag_south_korea.png', badge: 'Innovation & Excellence in the Land of the Morning Calm', unis: ['Seoul National University', 'Korea University', 'Yonsei University', 'KAIST'] },
  { id: 'usa', name: 'United States (USA)', file: 'usa.html', flag: 'flag_usa.png', badge: 'Home to the World\'s Most Prestigious Universities', unis: ['Harvard University', 'MIT', 'Stanford University', 'UC Berkeley', 'Yale University', 'Princeton University', 'Columbia University', 'University of Chicago', 'University of Pennsylvania', 'Cornell University', 'University of Michigan', 'Arizona State University'] },
  { id: 'canada', name: 'Canada', file: 'canada.html', flag: 'flag_canada.png', badge: 'Quality Education, Warm Welcome, Bright Future', unis: ['University of Toronto', 'University of British Columbia', 'McGill University', 'University of Waterloo', 'University of Alberta', 'McMaster University', 'University of Ottawa', 'Western University', 'Queen\'s University', 'Dalhousie University', 'Simon Fraser University', 'University of Montreal'] },
  { id: 'uk', name: 'United Kingdom (UK)', file: 'uk.html', flag: 'flag_uk.png', badge: 'Centuries of Academic Excellence - Study Where the Best Study', unis: ['University of Oxford', 'University of Cambridge', 'University of Manchester', 'University of Birmingham'] },
  { id: 'albania', name: 'Albania', file: 'albania.html', flag: 'flag_albania.png', badge: 'Affordable European Education - Your Gateway to the EU', unis: ['University of Tirana', 'Epoka University', 'Beder University', 'Polis University'] },
  { id: 'cyprus', name: 'European Cyprus', file: 'cyprus.html', flag: 'flag_cyprus.png', badge: 'European Education in the Mediterranean', unis: ['University of Cyprus', 'Cyprus University of Technology', 'University of Nicosia', 'European University Cyprus'] },
  { id: 'lithuania', name: 'Lithuania', file: 'lithuania.html', flag: 'flag_lithuania.png', badge: 'Quality European Education in the Baltic Region', unis: ['Vilnius University', 'Kaunas University of Technology', 'Vilnius Tech', 'Lithuanian University of Health Sciences'] }
];

console.log('Setup loaded successfully. Country count: ' + countries.length);
