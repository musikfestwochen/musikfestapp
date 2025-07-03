// scripts/generate-responsive-images.js
// Generates responsive and LQIP images for a given input image using sharp
// Usage: node scripts/generate-responsive-images.js <inputPath> <outputBaseName>

import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

const sizes = [480, 768, 1280, 1920];
const lqipWidth = 20;

async function generateImages(inputPath, outputBaseName) {
    const ext = path.extname(inputPath);
    const base = path.basename(outputBaseName, ext);
    const dir = path.dirname(outputBaseName);

    const absInput = path.resolve(inputPath);
    const absDir = path.resolve(dir);
    console.log(`Resolved input: ${absInput}`);
    console.log(`Resolved output dir: ${absDir}`);

    // Check if input file exists
    if (!fs.existsSync(absInput)) {
        console.error(`ERROR: Input file does not exist: ${absInput}`);
        process.exit(1);
    }

    // Ensure output directory exists
    if (!fs.existsSync(absDir)) fs.mkdirSync(absDir, { recursive: true });

    // Generate responsive images (jpg and webp)
    for (const size of sizes) {
        const jpgOut = path.join(absDir, `${base}-${size}.jpg`);
        const webpOut = path.join(absDir, `${base}-${size}.webp`);
        try {
            await sharp(absInput).resize(size).jpeg({ quality: 80, progressive: true }).toFile(jpgOut);
            await sharp(absInput).resize(size).webp({ quality: 80 }).toFile(webpOut);
            console.log(`Generated: ${jpgOut}, ${webpOut}`);
        } catch (err) {
            console.error(`Failed to generate for size ${size}:`, err);
        }
    }

    // Generate LQIP (jpg and webp)
    const lqipJpg = path.join(absDir, `${base}-lqip.jpg`);
    const lqipWebp = path.join(absDir, `${base}-lqip.webp`);
    try {
        await sharp(absInput).resize(lqipWidth).blur().jpeg({ quality: 40, progressive: true }).toFile(lqipJpg);
        await sharp(absInput).resize(lqipWidth).blur().webp({ quality: 40 }).toFile(lqipWebp);
        console.log(`Generated: ${lqipJpg}, ${lqipWebp}`);
    } catch (err) {
        console.error('Failed to generate LQIP:', err);
    }
}

// Always run when called directly
const [inputPath, outputBaseName] = process.argv.slice(2);
if (!inputPath || !outputBaseName) {
    console.error('Usage: node scripts/generate-responsive-images.js <inputPath> <outputBaseName>');
    process.exit(1);
}
generateImages(inputPath, outputBaseName).catch((err) => {
    console.error(err);
    process.exit(1);
});
