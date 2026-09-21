// Piezas comunes de los manuales de la plataforma Crecer (formato WORKBENCH IT).
// Cada manual arma su contenido con estos helpers y llama a construir().
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
  WidthType, ShadingType, BorderStyle, LevelFormat, PageBreak, Footer, Header, PageNumber, ImageRun,
} = require('docx');

const ROJO = 'E31E24';       // rojo WORKBENCH (sitio: --red #e31e24)
const TINTA = '1A1A1D';
const GRIS = '6F6F7A';
const FONDO_NOTA = 'F5F5F7';
const FONDO_OJO = 'FDECEC';
const FONDO_TABLA = 'F2F2F4';

const LOGO = fs.readFileSync(path.join(__dirname, 'logo', 'logo-workbench.png'));
const LOGO_W = 1426, LOGO_H = 192; // px del PNG (proporción)

function runs(texto, base = {}) {
  const out = [];
  const re = /(\*\*[^*]+\*\*)/g;
  let i = 0, m;
  while ((m = re.exec(texto))) {
    if (m.index > i) out.push(new TextRun({ text: texto.slice(i, m.index), ...base }));
    out.push(new TextRun({ text: m[0].slice(2, -2), bold: true, ...base }));
    i = m.index + m[0].length;
  }
  if (i < texto.length) out.push(new TextRun({ text: texto.slice(i), ...base }));
  return out;
}

const P = (t, opts = {}) => new Paragraph({ children: runs(t), spacing: { after: 120, line: 290 }, ...opts });
const H1 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(t)], keepNext: true, spacing: { before: 520, after: 200 } });
const H2 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(t)], keepNext: true });
const H3 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun(t)], keepNext: true });
const saltoPagina = () => new Paragraph({ children: [new PageBreak()] });
const espacio = () => new Paragraph({ children: [], spacing: { after: 120 } });

function bullets(items) {
  return items.map((t) => new Paragraph({ children: runs(t), numbering: { reference: 'vinetas', level: 0 }, spacing: { after: 80, line: 280 } }));
}

let instanciaPasos = 0;
function pasos(items) {
  instanciaPasos++;
  return items.map((t) => new Paragraph({
    children: runs(t), numbering: { reference: 'pasos', level: 0, instance: instanciaPasos }, spacing: { after: 80, line: 280 },
  }));
}

// Recuadro: fondo suave + borde izquierdo. tipo 'ojo' = advertencia (rojo).
function recuadro(titulo, lineas, tipo = 'nota') {
  const fondo = tipo === 'ojo' ? FONDO_OJO : FONDO_NOTA;
  const borde = tipo === 'ojo' ? ROJO : 'A0A0AA';
  const par = (children, primero, ultimo) => new Paragraph({
    children,
    shading: { type: ShadingType.CLEAR, color: 'auto', fill: fondo },
    border: { left: { style: BorderStyle.SINGLE, size: 18, color: borde, space: 8 } },
    indent: { left: 200, right: 200 },
    keepLines: true, keepNext: !ultimo,
    spacing: { before: primero ? 160 : 0, after: ultimo ? 200 : 60, line: 280 },
  });
  const out = [par([new TextRun({ text: titulo, bold: true, color: tipo === 'ojo' ? ROJO : TINTA })], true, lineas.length === 0)];
  lineas.forEach((l, i) => out.push(par(runs(l), false, i === lineas.length - 1)));
  return out;
}

function tabla(encabezados, filas, anchos) {
  const total = anchos.reduce((a, b) => a + b, 0);
  const b = { style: BorderStyle.SINGLE, size: 4, color: 'D6D6DC' };
  const bordes = { top: b, bottom: b, left: b, right: b };
  const celda = (texto, i, cab) => new TableCell({
    width: { size: anchos[i], type: WidthType.DXA },
    borders: bordes,
    shading: cab ? { type: ShadingType.CLEAR, color: 'auto', fill: FONDO_TABLA } : undefined,
    margins: { top: 80, bottom: 80, left: 120, right: 120 },
    children: [new Paragraph({ children: runs(texto, cab ? { bold: true, size: 19 } : { size: 19 }), spacing: { after: 0, line: 260 } })],
  });
  return new Table({
    width: { size: total, type: WidthType.DXA },
    columnWidths: anchos,
    rows: [
      new TableRow({ tableHeader: true, cantSplit: true, children: encabezados.map((e, i) => celda(e, i, true)) }),
      ...filas.map((f) => new TableRow({ cantSplit: true, children: f.map((t, i) => celda(t, i, false)) })),
    ],
  });
}

const logo = (anchoPx) => new ImageRun({
  type: 'png', data: LOGO,
  transformation: { width: anchoPx, height: Math.round(anchoPx * LOGO_H / LOGO_W) },
  altText: { title: 'WORKBENCH IT', description: 'Logo de WORKBENCH IT', name: 'logo' },
});

function portada({ cliente, titulo, subtitulo, para, version, indice }) {
  return [
    new Paragraph({ children: [logo(260)], spacing: { after: 0 } }),
    new Paragraph({ children: [], spacing: { before: 1900 } }),
    new Paragraph({ children: [new TextRun({ text: cliente, size: 26, color: GRIS })], spacing: { after: 200 } }),
    new Paragraph({ children: [new TextRun({ text: titulo, bold: true, size: 52, color: TINTA })], spacing: { after: 160 } }),
    new Paragraph({ children: [new TextRun({ text: subtitulo, size: 28, color: GRIS })], spacing: { after: 600 },
      border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: ROJO, space: 12 } } }),
    new Paragraph({ children: runs('**Para:** ' + para), spacing: { after: 80 } }),
    new Paragraph({ children: runs('**Versión:** ' + version), spacing: { after: 80 } }),
    new Paragraph({ children: runs('**Preparado por:** WORKBENCH IT'), spacing: { after: 80 } }),
    saltoPagina(),
    new Paragraph({ children: [new TextRun({ text: 'Contenido', bold: true, size: 32, color: TINTA })], spacing: { after: 200 } }),
    ...indice.map((t, i) => new Paragraph({ children: runs(`**${i + 1}.**  ${t}`), spacing: { after: 90 } })),
    saltoPagina(),
  ];
}

async function construir({ titulo, cabecera, contenido, salida }) {
  const doc = new Document({
    creator: 'WORKBENCH IT',
    title: titulo,
    styles: {
      default: { document: { run: { font: 'Calibri', size: 21, color: '2A2A2E' } } },
      paragraphStyles: [
        { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 34, bold: true, color: ROJO }, paragraph: { outlineLevel: 0 } },
        { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 26, bold: true, color: TINTA }, paragraph: { spacing: { before: 280, after: 120 }, outlineLevel: 1 } },
        { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true,
          run: { size: 22, bold: true, color: GRIS }, paragraph: { spacing: { before: 200, after: 100 }, outlineLevel: 2 } },
      ],
    },
    numbering: {
      config: [
        { reference: 'vinetas', levels: [
          { level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 270 } } } },
        ] },
        { reference: 'pasos', levels: [
          { level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 320 } } } },
        ] },
      ],
    },
    sections: [{
      properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1440, bottom: 1300, left: 1440, right: 1440 } }, titlePage: true },
      headers: {
        default: new Header({ children: [new Paragraph({
          children: [logo(120), new TextRun({ text: '\t' + cabecera, size: 16, color: GRIS })],
          tabStops: [{ type: 'right', position: 9026 }],
          border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'E2E2E6', space: 6 } },
        })] }),
        first: new Header({ children: [new Paragraph({ children: [] })] }),
      },
      footers: {
        default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
          new TextRun({ text: 'WORKBENCH IT  ·  Página ', size: 16, color: GRIS }),
          new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRIS }),
          new TextRun({ text: ' de ', size: 16, color: GRIS }),
          new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 16, color: GRIS }),
        ] })] }),
        first: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
          children: [new TextRun({ text: 'Documento de uso interno de la clínica', size: 16, color: GRIS })] })] }),
      },
      children: contenido,
    }],
  });
  const buf = await Packer.toBuffer(doc);
  fs.writeFileSync(salida, buf);
  console.log('OK ' + salida + ' (' + Math.round(buf.length / 1024) + ' KB)');
}

module.exports = { P, H1, H2, H3, bullets, pasos, recuadro, tabla, espacio, saltoPagina, portada, construir, runs };
