<main class="status-page" id="contenido">
    <div class="status-card status-card--404 panel">
        <div class="escena-404" aria-hidden="true">
            <span class="flotante-404 flotante-404--izq">4</span>
            <span class="flotante-404 flotante-404--centro">0</span>
            <span class="flotante-404 flotante-404--der">4</span>

            <div class="robot-contenedor">
                <svg class="robot-svg" viewBox="0 0 320 360" width="250" height="280" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Piernas segmentadas -->
                    <rect x="145" y="220" width="22" height="38" rx="4" fill="#33363B"/>
                    <line x1="145" y1="230" x2="167" y2="230" stroke="#1A1C1E" stroke-width="3"/>
                    <line x1="145" y1="240" x2="167" y2="240" stroke="#1A1C1E" stroke-width="3"/>
                    <rect x="188" y="220" width="22" height="38" rx="4" fill="#33363B"/>
                    <line x1="188" y1="230" x2="210" y2="230" stroke="#1A1C1E" stroke-width="3"/>
                    <line x1="188" y1="240" x2="210" y2="240" stroke="#1A1C1E" stroke-width="3"/>

                    <!-- Botas blancas y azules -->
                    <path d="M135 252 H173 L178 312 H128 L135 252Z" fill="#ECEFF4"/>
                    <path d="M182 252 H220 L227 312 H177 L182 252Z" fill="#ECEFF4"/>
                    <rect x="128" y="305" width="50" height="9" rx="4" fill="#009FE3"/>
                    <rect x="177" y="305" width="50" height="9" rx="4" fill="#009FE3"/>
                    <path d="M126 314 H178 V326 C178 331 126 331 126 326 Z" fill="#2A2768"/>
                    <path d="M177 314 H229 V326 C229 331 177 331 177 326 Z" fill="#2A2768"/>

                    <!-- Cinturón y cadera -->
                    <rect x="148" y="192" width="60" height="12" rx="4" fill="#009FE3"/>
                    <path d="M142 204 H214 L206 232 H150 L142 204Z" fill="#ECEFF4"/>

                    <!-- Torso con logo SG -->
                    <path d="M132 132 L146 124 H210 L224 132 L216 192 H140 L132 132Z" fill="#ECEFF4"/>
                    <rect x="148" y="148" width="60" height="32" rx="6" fill="#1E3453" stroke="#CFD6E0" stroke-width="3"/>
                    <text x="178" y="171" text-anchor="middle" fill="#FFFFFF" font-family="sans-serif" font-weight="800" font-size="22" letter-spacing="1">SG</text>

                    <!-- Brazo derecho del robot (izquierda en pantalla) + Laptop -->
                    <g class="robot-brazo-laptop">
                        <path d="M136 140 L110 175 L96 165 L126 130 Z" fill="#33363B"/>
                        <!-- Laptop con código -->
                        <rect x="30" y="120" width="88" height="58" rx="4" fill="#16191D" stroke="#4B5563" stroke-width="2"/>
                        <rect x="35" y="125" width="78" height="48" fill="#1E2229"/>
                        <!-- Líneas de código y 404 parpadeante -->
                        <rect x="40" y="131" width="32" height="3" fill="#5BC0EB"/>
                        <rect x="40" y="138" width="54" height="3" fill="#D2A648"/>
                        <rect x="40" y="145" width="46" height="3" fill="#E06C75"/>
                        <text class="svg-404-code" x="74" y="166" text-anchor="middle" fill="#5BC0EB" font-family="monospace" font-weight="700" font-size="15">404!</text>
                        <!-- Base de la laptop y dedos -->
                        <rect x="20" y="178" width="108" height="5" rx="2" fill="#6B7280"/>
                        <rect x="52" y="182" width="36" height="8" rx="3" fill="#33363B"/>
                    </g>

                    <!-- Brazo izquierdo del robot (derecha en pantalla) -->
                    <g class="robot-brazo-der">
                        <rect x="218" y="138" width="20" height="44" rx="8" transform="rotate(-12 218 138)" fill="#33363B"/>
                        <path d="M224 176 L248 194 L238 235 L218 214 Z" fill="#ECEFF4"/>
                        <line x1="221" y1="184" x2="242" y2="218" stroke="#009FE3" stroke-width="4"/>
                        <path d="M220 214 L240 228 L230 250 L214 238 Z" fill="#33363B"/>
                    </g>

                    <!-- Cuello -->
                    <rect x="165" y="114" width="26" height="12" fill="#22252A"/>

                    <!-- Cabeza animada -->
                    <g class="robot-cabeza">
                        <!-- Antenas -->
                        <polygon points="145,42 151,14 157,42" fill="#F4F6F9"/>
                        <polygon points="199,42 205,14 211,42" fill="#F4F6F9"/>
                        <!-- Casco blanco -->
                        <rect x="116" y="36" width="124" height="82" rx="41" fill="#ECEFF4"/>
                        <!-- Visor Navy -->
                        <rect x="124" y="45" width="108" height="54" rx="27" fill="#1E3453"/>
                        <!-- Ojos (parpadean con CSS) -->
                        <g class="robot-ojos">
                            <circle cx="154" cy="73" r="14" fill="#0B1320" stroke="#5BC0EB" stroke-width="3.5"/>
                            <circle cx="151" cy="69" r="4.5" fill="#FFFFFF"/>
                            <circle cx="202" cy="73" r="14" fill="#0B1320" stroke="#5BC0EB" stroke-width="3.5"/>
                            <circle cx="199" cy="69" r="4.5" fill="#FFFFFF"/>
                        </g>
                        <!-- Sonrisa -->
                        <path d="M164 106 Q178 116 192 106" stroke="#5BC0EB" stroke-width="4" stroke-linecap="round" fill="none"/>
                    </g>
                </svg>
            </div>

            <div class="robot-sombra"></div>
        </div>

        <p class="kicker-404">Error 404 · Página no encontrada</p>
        <h1 class="section-title">¡Ups! Nuestro robot no encontró esta ruta</h1>
        <p>Revisamos el sistema de inventario, pero la dirección que buscas no existe o fue movida.</p>

        <div class="acciones-404">
            <a class="btn btn-primary" href="<?= View::url('/') ?>">Volver al inicio</a>
            <a class="btn btn-outline" href="javascript:history.back()">Regresar atrás</a>
        </div>
    </div>
</main>