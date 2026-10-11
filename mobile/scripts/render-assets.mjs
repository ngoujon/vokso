// Génère les images sources (assets/*.png) attendues par @capacitor/assets à
// partir de la marque Vokso (même dessin que webserver/public/favicon.svg),
// puis `capacitor-assets generate` en dérive toutes les tailles natives.
// Usage : npm run assets
import { mkdir } from 'node:fs/promises';
import sharp from 'sharp';

const YELLOW = '#f5c400';
const INK = '#17140f';

// Ondes + lignes de texte, centrées sur (512, 512) dans un carré de 1024.
const mark = (scale) => `
  <g fill="${INK}" transform="translate(512 512) scale(${scale}) translate(-512 -512)">
    <rect x="258" y="432" width="52" height="160" rx="26"/>
    <rect x="336" y="352" width="52" height="320" rx="26"/>
    <rect x="414" y="402" width="52" height="220" rx="26"/>
    <g opacity="0.62">
      <rect x="500" y="362" width="266" height="52" rx="26"/>
      <rect x="500" y="442" width="200" height="52" rx="26"/>
      <rect x="500" y="522" width="266" height="52" rx="26"/>
      <rect x="500" y="602" width="160" height="52" rx="26"/>
    </g>
  </g>`;

const svg = (body, background) => Buffer.from(
  `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">${
    background ? `<rect width="1024" height="1024" fill="${background}"/>` : ''
  }${body}</svg>`,
);

// Icône pleine (iOS arrondit lui-même les coins) ; pour l'icône adaptative
// Android, la marque reste dans la zone sûre (cercle de 66 % au centre).
const images = {
  'icon-only.png': [svg(mark(1.18), YELLOW), 1024],
  'icon-foreground.png': [svg(mark(0.95)), 1024],
  'icon-background.png': [svg('', YELLOW), 1024],
  'splash.png': [svg(mark(0.5), YELLOW), 2732],
  'splash-dark.png': [svg(mark(0.5), YELLOW), 2732],
};

await mkdir('assets', { recursive: true });
for (const [name, [source, size]] of Object.entries(images)) {
  await sharp(source, { density: 72 * (size / 1024) }).resize(size, size).png().toFile(`assets/${name}`);
  console.log(`assets/${name} (${size}×${size})`);
}
