<?php
// ─── Spin / Ruleta · Configuración ───────────────────

const SPIN_COST          = 100;   // Tokens por tirada pagada
const SPIN_FREE_INTERVAL = 86400; // Segundos entre tiradas gratis (86400 = 24 h)
const SPIN_FREE_ENABLED  = true;  // Activar/desactivar tirada diaria gratuita

/*
 * Tabla de premios · Valor esperado por tirada
 * ┌──────────┬─────────┬─────────────────┐
 * │  Tokens  │  Prob   │  Contrib EV     │
 * ├──────────┼─────────┼─────────────────┤
 * │     0    │  30 %   │   0,00          │
 * │    10    │  25 %   │   2,50          │
 * │    25    │  20 %   │   5,00          │
 * │    75    │  13 %   │   9,75          │
 * │   150    │   7 %   │  10,50          │
 * │   400    │   4 %   │  16,00          │
 * │  1500    │   1 %   │  15,00          │
 * ├──────────┼─────────┼─────────────────┤
 * │          │  100 %  │  58,75 ⬡/tirada │
 * └──────────┴─────────┴─────────────────┘
 *
 * Margen de la app en tiradas pagadas (100 ⬡):
 *   100 − 58,75 = 41,25 tokens (41,25 % house edge)
 *
 * Coste de tirada gratis para la app: ≈ 58,75 ⬡/usuario/día.
 * Los tokens gifteados regresan como pago en actividades y tiradas pagadas.
 */
const SPIN_PRIZES = [
    ['tokens' =>    0, 'probability' => 30.0, 'label' => 'Sin premio',  'color' => '#64748b'],
    ['tokens' =>   10, 'probability' => 25.0, 'label' => '+10 ⬡',      'color' => '#3b82f6'],
    ['tokens' =>   25, 'probability' => 20.0, 'label' => '+25 ⬡',      'color' => '#10b981'],
    ['tokens' =>   75, 'probability' => 13.0, 'label' => '+75 ⬡',      'color' => '#8b5cf6'],
    ['tokens' =>  150, 'probability' =>  7.0, 'label' => '+150 ⬡',     'color' => '#f59e0b'],
    ['tokens' =>  400, 'probability' =>  4.0, 'label' => '+400 ⬡',     'color' => '#ef4444'],
    ['tokens' => 1500, 'probability' =>  1.0, 'label' => '+1500 ⬡',    'color' => '#ec4899'],
];
