// Repeatable certificate geometry inspection.
// Usage: node scripts/inspect-certificate-pdf.cjs <temporary npm tools dir> <pdf>
//   [--preview|--issued] [--artwork=required|forbidden|ignore] [--output-dir <dir>] [--no-png]
// Requires pdfjs-dist and @napi-rs/canvas outside the app; never accesses the database.
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');

const toolRoot = process.argv[2];
const source = process.argv[3];
assert.ok(toolRoot && source, 'Tools directory and PDF path are required.');

const args = process.argv.slice(4);
const preview = args.includes('--preview');
const issued = args.includes('--issued');
assert.notEqual(preview, issued, 'Choose exactly one of --preview or --issued.');
const artworkArg = args.find(value => value.startsWith('--artwork='));
const artwork = artworkArg ? artworkArg.split('=', 2)[1] : 'ignore';
assert.ok(['required', 'forbidden', 'ignore'].includes(artwork), 'Invalid --artwork value.');
const outputIndex = args.indexOf('--output-dir');
assert.ok(outputIndex < 0 || args[outputIndex + 1], '--output-dir needs a path.');
const outputDirectory = path.resolve(outputIndex >= 0 ? args[outputIndex + 1] : path.dirname(source));
fs.mkdirSync(outputDirectory, { recursive: true });

const modules = path.resolve(toolRoot, 'node_modules');
const canvasTools = require(path.join(modules, '@napi-rs/canvas'));
for (const name of ['DOMMatrix', 'ImageData', 'Path2D']) globalThis[name] = canvasTools[name];

const center = item => item.transform[4] + item.width / 2;
const near = (actual, expected, tolerance, message) => assert.ok(
  Math.abs(actual - expected) <= tolerance,
  `${message}: ${actual.toFixed(2)} pt, expected ${expected.toFixed(2)} ± ${tolerance} pt`,
);

(async () => {
  const pdfjs = await import(pathToFileURL(path.join(modules, 'pdfjs-dist/legacy/build/pdf.mjs')).href);
  const input = path.resolve(source);
  const task = pdfjs.getDocument({
    data: new Uint8Array(fs.readFileSync(input)),
    standardFontDataUrl: path.join(modules, 'pdfjs-dist/standard_fonts').replaceAll('\\', '/') + '/',
    useSystemFonts: true,
  });
  const document = await task.promise;
  assert.equal(document.numPages, 2, 'Certificate must have exactly two pages.');

  const report = {
    status: 'passed',
    file: path.basename(input),
    document: preview ? 'preview' : 'issued',
    artwork,
    pages: [],
  };

  for (let number = 1; number <= document.numPages; number++) {
    const page = await document.getPage(number);
    const viewport = page.getViewport({ scale: 1 });
    near(viewport.width, 595.28, 1, `Page ${number} must be A4 width`);
    near(viewport.height, 841.89, 1, `Page ${number} must be A4 height`);

    const content = await page.getTextContent();
    const items = content.items.filter(item => item.str.trim() !== '');
    const text = items.map(item => item.str).join(' ');
    const watermark = /PRATINJAU - BELUM DITERBITKAN/.test(text);
    assert.equal(watermark, preview, `Page ${number} preview watermark mismatch.`);
    if (artwork === 'required') assert.match(text, /CONTOH DEKORASI/, 'Background artwork text must survive composition.');
    if (artwork === 'forbidden') assert.doesNotMatch(text, /CONTOH DEKORASI/, 'Unexpected background artwork.');

    for (const item of items) {
      assert.ok(item.transform[4] >= -0.5, `Page ${number} text crosses the left edge: ${item.str}`);
      assert.ok(item.transform[4] + item.width <= viewport.width + 0.5, `Page ${number} text crosses the right edge: ${item.str}`);
      assert.ok(item.transform[5] >= -0.5 && item.transform[5] <= viewport.height + 0.5, `Page ${number} text crosses a vertical edge: ${item.str}`);
    }

    const metrics = {
      number,
      width_pt: Number(viewport.width.toFixed(2)),
      height_pt: Number(viewport.height.toFixed(2)),
      text_items: items.length,
      watermark,
    };
    if (number === 1) {
      const labelIndex = items.findIndex(item => item.str.trim() === 'Diberikan kepada');
      assert.ok(labelIndex >= 0 && items[labelIndex + 1], 'Recipient heading/name is missing.');
      const recipient = items[labelIndex + 1];
      near(center(recipient), viewport.width / 2, 1, 'Recipient name must be centered');

      const separators = items.filter(item => item.str.trim() === ':');
      assert.ok(separators.length >= 5, 'Five recipient identity separators are required.');
      for (const separator of separators.slice(0, 5)) near(center(separator), viewport.width / 2, 1, 'Identity separator must be centered');
      metrics.recipient_center_pt = Number(center(recipient).toFixed(2));
      metrics.identity_separator_centers_pt = separators.slice(0, 5).map(item => Number(center(item).toFixed(2)));
    } else {
      assert.match(text, /Nilai akhir resmi/, 'Back page must contain assessment results.');
      const prefixes = ['Nama:', 'NISN / NIPD:', 'Konsentrasi:', 'Industri:', 'Lampiran sertifikat:'];
      const identityLines = prefixes.map(prefix => items.find(item => item.str.trim().startsWith(prefix)));
      assert.ok(identityLines.every(Boolean), 'Back-page recipient identity summary is incomplete.');
      for (const line of identityLines) near(center(line), viewport.width / 2, 1, 'Back-page identity line must be centered');
      metrics.identity_line_centers_pt = identityLines.map(item => Number(center(item).toFixed(2)));
    }

    if (!args.includes('--no-png')) {
      const renderViewport = page.getViewport({ scale: 1.2 });
      const canvas = canvasTools.createCanvas(Math.ceil(renderViewport.width), Math.ceil(renderViewport.height));
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: renderViewport }).promise;
      const png = path.join(outputDirectory, `${path.basename(input)}.page-${number}.png`);
      fs.writeFileSync(png, canvas.toBuffer('image/png'));
      metrics.png = png;
    }
    report.pages.push(metrics);
    console.log(`PASS: page ${number}, A4, text inside page, certificate geometry verified`);
  }

  const reportPath = path.join(outputDirectory, `${path.basename(input)}.layout.json`);
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(`PASS: certificate layout (${preview ? 'preview' : 'issued'}), report: ${reportPath}`);
  await task.destroy();
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
