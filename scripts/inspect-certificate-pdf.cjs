// Optional artifact inspection: node scripts/inspect-certificate-pdf.cjs <temporary npm tools dir> <pdf>
// Uses pdfjs-dist and @napi-rs/canvas installed outside the app; no database access.
const fs=require('node:fs');
const path=require('node:path');
const {pathToFileURL}=require('node:url');
const assert=require('node:assert/strict');
const modules=path.resolve(process.argv[2],'node_modules');
const canvasTools=require(path.join(modules,'@napi-rs/canvas'));
for (const name of ['DOMMatrix','ImageData','Path2D']) globalThis[name]=canvasTools[name];
(async()=>{
  const pdfjs=await import(pathToFileURL(path.join(modules,'pdfjs-dist/legacy/build/pdf.mjs')).href);
  const input=path.resolve(process.argv[3]);
  const task=pdfjs.getDocument({data:new Uint8Array(fs.readFileSync(input)),
    standardFontDataUrl:path.join(modules,'pdfjs-dist/standard_fonts').replaceAll('\\','/')+'/',useSystemFonts:true});
  const doc=await task.promise;
  assert.equal(doc.numPages,2,'Certificate must have exactly two pages');
  for (let n=1;n<=doc.numPages;n++) {
    const page=await doc.getPage(n); const viewport=page.getViewport({scale:1.2});
    const canvas=canvasTools.createCanvas(Math.ceil(viewport.width),Math.ceil(viewport.height));
    await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
    const text=(await page.getTextContent()).items.map(item=>item.str).join(' ');
    assert.match(text,/CONTOH DEKORASI/,'Background artwork text must survive composition');
    if (n===2) assert.match(text,/Nilai akhir resmi/,'Back must contain assessment results');
    fs.writeFileSync(input+'.page-'+n+'.png',canvas.toBuffer('image/png'));
    console.log('PASS: page '+n+', A4 '+Math.round(viewport.width/1.2)+' x '+Math.round(viewport.height/1.2)+' pt, artwork and data rendered');
  }
  await task.destroy();
})().catch(error=>{console.error(error);process.exitCode=1;});
