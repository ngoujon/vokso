import React, { useMemo, useRef, useState } from 'react';

/**
 * Courbe quotidienne d'une seule mesure (pas de légende : le titre la nomme),
 * en SVG : ligne 2 px, aire légère, grille discrète, réticule et infobulle
 * au survol ou au clavier (flèches), et tableau des valeurs pour les lecteurs
 * d'écran.
 */
interface Point {
  date: string;
  value: number;
}

interface Props {
  title: string;
  points: Point[];
  format?: (value: number) => string;
}

const W = 600;
const H = 180;
const PAD = { top: 12, right: 12, bottom: 24, left: 40 };

const dayLabel = (date: string) =>
  new Date(`${date}T12:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });

/** Graduation « ronde » de l'axe vertical (1, 2, 5 × 10^n). */
function niceMax(max: number): number {
  if (max <= 0) return 1;
  const step = 10 ** Math.floor(Math.log10(max));
  return [1, 2, 5, 10].map((m) => m * step).find((v) => v >= max) ?? max;
}

export default function LineChart({ title, points, format = (v) => String(v) }: Props) {
  const [active, setActive] = useState<number | null>(null);
  const svgRef = useRef<SVGSVGElement>(null);

  const { max, x, y, path, area } = useMemo(() => {
    const top = niceMax(Math.max(0, ...points.map((p) => p.value)));
    const innerW = W - PAD.left - PAD.right;
    const innerH = H - PAD.top - PAD.bottom;
    const xOf = (i: number) => PAD.left + (points.length > 1 ? (i / (points.length - 1)) * innerW : innerW / 2);
    const yOf = (v: number) => PAD.top + innerH - (v / top) * innerH;
    const d = points.map((p, i) => `${i ? 'L' : 'M'}${xOf(i).toFixed(1)},${yOf(p.value).toFixed(1)}`).join('');
    const base = PAD.top + innerH;
    return {
      max: top,
      x: xOf,
      y: yOf,
      path: d,
      area: points.length ? `${d}L${xOf(points.length - 1).toFixed(1)},${base}L${xOf(0).toFixed(1)},${base}Z` : '',
    };
  }, [points]);

  const total = points.reduce((sum, p) => sum + p.value, 0);
  const ticks = [0, max / 2, max];
  const labelEvery = Math.max(1, Math.ceil(points.length / 6));

  const onMove = (e: React.MouseEvent<SVGSVGElement>) => {
    const rect = svgRef.current?.getBoundingClientRect();
    if (!rect || points.length === 0) return;
    const px = ((e.clientX - rect.left) / rect.width) * W;
    const ratio = (px - PAD.left) / (W - PAD.left - PAD.right);
    setActive(Math.max(0, Math.min(points.length - 1, Math.round(ratio * (points.length - 1)))));
  };

  const onKey = (e: React.KeyboardEvent) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault();
    const step = e.key === 'ArrowRight' ? 1 : -1;
    setActive((i) => Math.max(0, Math.min(points.length - 1, (i ?? points.length - 1) + step)));
  };

  const activePoint = active !== null ? points[active] : null;

  return (
    <figure className="chart-card">
      <figcaption>
        <span className="chart-title">{title}</span>
        <span className="chart-total">{format(total)} sur la période</span>
      </figcaption>
      <div className="chart-plot">
        <svg
          ref={svgRef}
          viewBox={`0 0 ${W} ${H}`}
          role="img"
          aria-label={`${title} : ${format(total)} sur ${points.length} jours`}
          tabIndex={0}
          onMouseMove={onMove}
          onMouseLeave={() => setActive(null)}
          onKeyDown={onKey}
          onBlur={() => setActive(null)}
        >
          {ticks.map((t) => (
            <g key={t}>
              <line className="chart-grid" x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} />
              <text className="chart-axis" x={PAD.left - 6} y={y(t) + 4} textAnchor="end">{format(t)}</text>
            </g>
          ))}
          {points.map((p, i) => (i % labelEvery === 0 || i === points.length - 1) && (
            <text key={p.date} className="chart-axis" x={x(i)} y={H - 6} textAnchor="middle">{dayLabel(p.date)}</text>
          ))}
          <path className="chart-area" d={area} />
          <path className="chart-line" d={path} />
          {activePoint && active !== null && (
            <g>
              <line className="chart-crosshair" x1={x(active)} x2={x(active)} y1={PAD.top} y2={H - PAD.bottom} />
              <circle className="chart-dot" cx={x(active)} cy={y(activePoint.value)} r={5} />
            </g>
          )}
        </svg>
        {activePoint && active !== null && (
          <div
            className="chart-tooltip"
            style={{ left: `${(x(active) / W) * 100}%` }}
            role="status"
          >
            <strong>{format(activePoint.value)}</strong>
            <span>{dayLabel(activePoint.date)}</span>
          </div>
        )}
      </div>
      <table className="visually-hidden">
        <caption>{title}</caption>
        <tbody>
          {points.map((p) => (
            <tr key={p.date}><th scope="row">{p.date}</th><td>{format(p.value)}</td></tr>
          ))}
        </tbody>
      </table>
    </figure>
  );
}
