/**
 * app.js — Utilidades compartidas por todas las páginas.
 */
(() => {
  'use strict';

  /** Atajos de selección del DOM. */
  window.$  = (sel, ctx = document) => ctx.querySelector(sel);
  window.$$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  /** true si el usuario prefiere movimiento reducido. */
  window.prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
})();
