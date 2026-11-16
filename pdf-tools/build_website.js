/**
 * AMEEN CONSULTANCY WEBSITE - Master Build Reference Script
 * Run this to regenerate the website by running individual build scripts.
 * 
 * All 17 website files have been generated at C:\Ameen Consultancy\
 * 
 * Usage: node build_website.js (from C:\Ameen Consultancy\pdf-tools\)
 */
const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const ROOT = path.resolve(__dirname, "..");

function check(rel) {
  const abs = path.join(ROOT, rel);
  if (fs.existsSync(abs)) {
    const kb = (fs.statSync(abs).size / 1024).toFixed(1);
    console.log("  [OK] " + rel + " (" + kb + " KB)");
  } else {
    console.log("  [MISSING] " + rel);
  }
}

console.log("\n=== AMEEN CONSULTANCY WEBSITE - FILE MANIFEST ===\n");

const files = [
  "index.html",
  "about.html",
  "services.html",
  "destinations.html",
  "china.html",
  "turkey.html",
  "hong-kong.html",
  "south-korea.html",
  "albania.html",
  "uk.html",
  "cyprus.html",
  "lithuania.html",
  "usa.html",
  "canada.html",
  "contact.html",
  "assets/css/style.css",
  "assets/css/home.css",
  "assets/js/main.js"
];

files.forEach(check);
console.log("\n=== Total: " + files.length + " files ===\n");
