-- Eliminar publicaciones anteriores al 05/06/2026
UPDATE publications SET status = 'removed'
WHERE status != 'removed'
  AND (
    (starts_at IS NOT NULL AND starts_at < '2026-06-05')
    OR (starts_at IS NULL AND created_at < '2026-06-05 00:00:00')
  );

-- ── Eventos ──────────────────────────────────────────────────────────────────
INSERT INTO publications (user_id, type, title, description, latitude, longitude, address, category, token_cost, attendees, max_attendees, starts_at, expires_at, status, created_at) VALUES

(1, 'event', 'Concierto Jazz al Parc de la Ciutadella',
 'Noche de jazz en vivo en el parque de la Ciutadella. Trae tu manta y disfruta bajo las estrellas con los mejores grupos de jazz de Barcelona.',
 41.3862, 2.1861, 'Parc de la Ciutadella, Barcelona', 'Música', 0, 134, 300,
 '2026-06-07 21:00:00', '2026-06-08 00:00:00', 'active', NOW()),

(2, 'event', 'Mercadillo de Diseño – El Born',
 'Más de 60 creadores locales con moda, joyería, ilustración y objetos de diseño. Entrada gratuita. Food trucks y música en directo.',
 41.3853, 2.1826, 'Passeig del Born, Barcelona', 'Arte y Cultura', 0, 89, 500,
 '2026-06-08 11:00:00', '2026-06-08 20:00:00', 'active', NOW()),

(1, 'event', 'Festival Gastronómico Poblenou',
 'Showcooking con chefs locales, degustaciones de productos de temporada y maridaje de vinos catalanes. Edición especial verano 2026.',
 41.4044, 2.1968, 'Rambla del Poblenou, Barcelona', 'Gastronomía', 0, 212, 400,
 '2026-06-12 12:00:00', '2026-06-12 22:00:00', 'active', NOW()),

(4, 'event', 'Yoga al Amanecer – Barceloneta',
 'Sesión de yoga y meditación frente al mar al amanecer. Todos los niveles bienvenidos. Lleva tu esterilla.',
 41.3797, 2.1893, 'Platja de la Barceloneta, Barcelona', 'Deporte', 0, 47, 80,
 '2026-06-10 07:00:00', '2026-06-10 09:00:00', 'active', NOW()),

(2, 'event', 'Ruta Modernista en Bicicleta',
 'Recorrido guiado por las obras más icónicas del modernismo barcelonés: Sagrada Família, Casa Batlló, Palau de la Música. 15 km, nivel medio.',
 41.4036, 2.1744, 'Plaça de Gaudí, Barcelona', 'Arte y Cultura', 0, 23, 30,
 '2026-06-14 09:30:00', '2026-06-14 13:00:00', 'active', NOW()),

(29, 'event', 'Hackathon IA & Ciudad',
 'Hackathon de 24h para desarrollar soluciones tecnológicas aplicadas a movilidad urbana, sostenibilidad y servicios municipales. Premios en metálico.',
 41.3966, 2.1905, 'Palo Alto Market, Poblenou', 'Arte y Cultura', 0, 78, 120,
 '2026-06-20 10:00:00', '2026-06-21 10:00:00', 'active', NOW()),

(3, 'event', 'Open Mic – Comedias & Monólogos',
 'Noche de humor abierto en el corazón del Eixample. Sube al escenario o ven solo a reír. Consumición mínima 5€.',
 41.3910, 2.1611, 'Carrer del Consell de Cent, 312, Barcelona', 'Cultura', 0, 56, 90,
 '2026-06-18 20:00:00', '2026-06-18 23:30:00', 'active', NOW()),

(4, 'event', 'Cine Aire Libre – Plaça del Rei',
 'Proyección de El laberinto del fauno en versión original subtitulada. Acceso libre hasta completar aforo.',
 41.3833, 2.1762, 'Plaça del Rei, Barcelona', 'Cultura', 0, 190, 250,
 '2026-06-23 22:00:00', '2026-06-24 00:30:00', 'active', NOW()),

(3, 'event', 'Taller de Fotografía Urbana',
 'Aprende a capturar la esencia de la ciudad con tu móvil o cámara. Recorrido por el Barrio Gótico y el Born. Plazas limitadas.',
 41.3840, 2.1775, 'Plaça de Sant Jaume, Barcelona', 'Arte y Cultura', 0, 12, 20,
 '2026-06-09 17:00:00', '2026-06-09 20:00:00', 'active', NOW()),

(1, 'event', 'Partido Baloncesto 3x3 – Barceloneta',
 'Torneo de baloncesto callejero 3x3 en las pistas de la Barceloneta. Inscripción gratuita por equipos de 3. Categorías: sub-18 y adultos.',
 41.3804, 2.1907, 'Pistes de la Barceloneta, Barcelona', 'Deporte', 0, 64, 96,
 '2026-06-15 10:00:00', '2026-06-15 18:00:00', 'active', NOW()),

-- ── Incidencias ──────────────────────────────────────────────────────────────
(2, 'incident', 'Corte de tráfico – Avinguda Diagonal',
 'La Diagonal cortada al tráfico entre Passeig de Gràcia y Plaça Francesc Macià por obras de remodelación del carril bici. Desviaciones por Carrer de Mallorca.',
 41.3951, 2.1488, 'Avinguda Diagonal, Barcelona', 'Tráfico', 0, 0, NULL,
 NULL, '2026-06-15 23:59:00', 'active', NOW()),

(3, 'incident', 'Avería Metro L3 – Retrasos',
 'Incidencia técnica en la línea 3 entre Liceu y Drassanes. Servicio con frecuencias reducidas. Se recomienda usar bus 59 y 120 como alternativa.',
 41.3796, 2.1717, 'Estació Liceu – Metro L3, Barcelona', 'Tráfico', 0, 0, NULL,
 NULL, '2026-06-06 22:00:00', 'active', NOW()),

(1, 'incident', 'Manifestació Via Laietana',
 'Manifestació convocada per sindicats al sector del transport. Via Laietana tallada des de Plaça Antoni Maura fins a Plaça Urquinaona. Previst fins les 14h.',
 41.3862, 2.1760, 'Via Laietana, Barcelona', 'Tráfico', 0, 0, NULL,
 '2026-06-09 14:00:00', NULL, 'active', NOW()),

(4, 'incident', 'Corte de agua – Carrer de Provença',
 'Corte de suministro de agua en Provença entre Bruc y Girona por rotura de tubería. Afecta a unas 400 viviendas. Previsión de reparación: 6 horas.',
 41.3955, 2.1611, 'Carrer de Provença, Barcelona', 'Obras', 0, 0, NULL,
 '2026-06-07 18:00:00', NULL, 'active', NOW()),

(2, 'incident', 'Presència de Meduses – Playa Barceloneta',
 'Aviso de presencia de medusas en la playa de Sant Sebastià y la Barceloneta. Se desaconseja el baño. Servicio de vigilancia activo en la orilla.',
 41.3769, 2.1887, 'Platja de Sant Sebastià, Barcelona', 'Avería', 0, 0, NULL,
 '2026-06-08 20:00:00', NULL, 'active', NOW()),

(29, 'incident', 'Semáforos averiados – Passeig de Gràcia',
 'Varios semáforos sin funcionamiento en el cruce de Passeig de Gràcia con Gran Via. Agentes de la Guardia Urbana regulando el tráfico manualmente.',
 41.3819, 2.1648, 'Passeig de Gràcia amb Gran Via, Barcelona', 'Tráfico', 0, 0, NULL,
 '2026-06-06 15:00:00', NULL, 'active', NOW());
