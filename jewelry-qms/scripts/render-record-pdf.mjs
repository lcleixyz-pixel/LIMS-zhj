import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL, URL } from 'node:url';

const [, , modeOrInput, maybeOutput] = process.argv;
const projectRoot = process.cwd();

let inputUrl = modeOrInput;
let outputPath = maybeOutput;

if (modeOrInput === 'smoke' && !maybeOutput) {
  inputUrl = path.join(projectRoot, 'tests/record_forms_pdf_smoke.html');
  outputPath = path.join(projectRoot, 'runtime/record-form-smoke.pdf');
}

if (!inputUrl || !outputPath) {
  console.error('Usage: node scripts/render-record-pdf.mjs <url-or-file> <output-path>');
  process.exit(2);
}

if (!path.isAbsolute(outputPath)) {
  console.error('Output path must be absolute');
  process.exit(2);
}

function resolveTarget(input) {
  const schemeMatch = input.match(/^([a-zA-Z][a-zA-Z0-9+.-]*):/);
  if (schemeMatch) {
    const url = new URL(input);
    if (!['http:', 'https:', 'file:'].includes(url.protocol)) {
      console.error(`Unsupported input URL scheme: ${url.protocol}. Use http:, https:, file:, or a local file path.`);
      process.exit(2);
    }

    return url.href;
  }

  return pathToFileURL(path.resolve(projectRoot, input)).href;
}

fs.mkdirSync(path.dirname(outputPath), { recursive: true });

const target = resolveTarget(inputUrl);
let browser;

try {
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1240, height: 1754 } });

  await page.goto(target, { waitUntil: 'networkidle' });
  await page.pdf({
    path: outputPath,
    format: 'A4',
    printBackground: true,
    margin: {
      top: '0mm',
      right: '0mm',
      bottom: '0mm',
      left: '0mm'
    }
  });

  console.log(outputPath);
} finally {
  if (browser) {
    await browser.close();
  }
}
