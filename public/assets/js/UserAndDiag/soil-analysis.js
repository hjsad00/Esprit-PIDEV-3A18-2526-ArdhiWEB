/**
 * soil-analysis.js — Virtual Soil 3D Analysis
 *
 * Uses Three.js for 3D soil core rendering, Leaflet for map,
 * and vanilla JS for stats panel, chart view, CSV export.
 */
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────
    let map, marker;
    let scene, camera, renderer, controls;
    let soilCoreMeshes = [];
    let currentLayers = null;
    let selectedLayerIdx = 0;
    let analysisComplete = false;
    let autoRotate = true;
    let autoRotateTimeout = null;
    const API_URL = document.getElementById('soil-api-url')?.value || '/user-and-diag/soil-analysis/api';

    // ── DOM refs ─────────────────────────────────────────────────────
    const $mapEl = document.getElementById('soil-map');
    const $canvas = document.getElementById('soil-3d-canvas');
    const $chartPane = document.querySelector('.soil-chart-pane');
    const $loading = document.querySelector('.soil-loading');
    const $btnAnalyze = document.getElementById('btn-analyze');
    const $btnExport = document.getElementById('btn-export');
    const $btn3D = document.getElementById('btn-3d');
    const $btnChart = document.getElementById('btn-chart');
    const $statusBadge = document.querySelector('.status-badge');
    const $viewToggle = document.querySelector('.view-toggle');
    const $lblCoords = document.getElementById('lbl-coords');
    const $lblDepth = document.getElementById('lbl-depth');
    const $scoreVal = document.getElementById('score-val');
    const $compareRow = document.querySelector('.compare-row');
    const $miniButtons = document.querySelector('.layer-mini-btns');

    // Card elements
    const cards = {
        ph: document.querySelector('.card-ph'),
        nitrogen: document.querySelector('.card-nitrogen'),
        texture: document.querySelector('.card-texture'),
        cec: document.querySelector('.card-cec'),
        reco: document.querySelector('.card-reco'),
    };

    let selectedLat = 36.8;
    let selectedLon = 10.18;

    // ── Init ─────────────────────────────────────────────────────────
    function init() {
        // Attach button listeners FIRST (before 3D which may fail)
        $btnAnalyze.addEventListener('click', handleAnalyze);
        $btnExport.addEventListener('click', handleExport);
        $btn3D.addEventListener('click', () => switchView('3d'));
        $btnChart.addEventListener('click', () => switchView('chart'));

        initMap();
        try { init3D(); } catch (e) { console.warn('3D init failed:', e); }
    }

    // ── Leaflet Map ──────────────────────────────────────────────────
    function initMap() {
        map = L.map('soil-map', {
            center: [selectedLat, selectedLon],
            zoom: 6,
            zoomControl: true,
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        marker = L.marker([selectedLat, selectedLon], { draggable: true }).addTo(map);

        map.on('click', function (e) {
            selectedLat = parseFloat(e.latlng.lat.toFixed(4));
            selectedLon = parseFloat(e.latlng.lng.toFixed(4));
            marker.setLatLng(e.latlng);
        });

        marker.on('dragend', function () {
            const pos = marker.getLatLng();
            selectedLat = parseFloat(pos.lat.toFixed(4));
            selectedLon = parseFloat(pos.lng.toFixed(4));
        });

        // Fix map size after layout settles
        setTimeout(() => map.invalidateSize(), 300);
    }

    // ── Three.js 3D Scene ────────────────────────────────────────────
    function init3D() {
        scene = new THREE.Scene();

        camera = new THREE.PerspectiveCamera(45, $canvas.clientWidth / $canvas.clientHeight || 1, 0.1, 10000);
        camera.position.set(0, 0, 500);

        renderer = new THREE.WebGLRenderer({ canvas: $canvas, alpha: true, antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.setSize($canvas.clientWidth || 600, $canvas.clientHeight || 500);

        // Lights
        scene.add(new THREE.AmbientLight(0x5a5550, 0.6));
        const key = new THREE.PointLight(0xfff8eb, 1.0, 2000);
        key.position.set(350, -450, -650);
        scene.add(key);
        const fill = new THREE.PointLight(0x467399, 0.5, 1500);
        fill.position.set(-250, -80, -350);
        scene.add(fill);
        const rim = new THREE.PointLight(0x27ae60, 0.4, 1200);
        rim.position.set(0, 200, 320);
        scene.add(rim);

        // OrbitControls
        controls = new THREE.OrbitControls(camera, $canvas);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.enablePan = false;
        controls.minDistance = 200;
        controls.maxDistance = 900;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 1.5;

        // Pause auto-rotate on interaction
        $canvas.addEventListener('pointerdown', () => {
            controls.autoRotate = false;
            clearTimeout(autoRotateTimeout);
        });
        $canvas.addEventListener('pointerup', () => {
            autoRotateTimeout = setTimeout(() => { controls.autoRotate = true; }, 3000);
        });

        window.addEventListener('resize', onResize);
        animate();
    }

    function onResize() {
        if (!$canvas.offsetWidth) return;
        camera.aspect = $canvas.clientWidth / $canvas.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize($canvas.clientWidth, $canvas.clientHeight);
    }

    function animate() {
        requestAnimationFrame(animate);
        if (controls) controls.update();
        if (renderer && scene && camera) renderer.render(scene, camera);
    }

    // ── Handle Analyze ───────────────────────────────────────────────
    function handleAnalyze() {
        if (analysisComplete) {
            // Reset for new analysis
            resetAnalysis();
            return;
        }

        $loading.classList.add('active');
        $btnAnalyze.disabled = true;

        fetch(`${API_URL}?lat=${selectedLat}&lon=${selectedLon}`)
            .then(r => r.json())
            .then(data => {
                $loading.classList.remove('active');
                $btnAnalyze.disabled = false;

                if (data.success && data.layers && data.layers.length) {
                    currentLayers = data.layers;
                    showAnalysisResults();
                }
            })
            .catch(() => {
                $loading.classList.remove('active');
                $btnAnalyze.disabled = false;
            });
    }

    function resetAnalysis() {
        analysisComplete = false;
        // Show map, hide 3D and chart
        $mapEl.style.display = '';
        $canvas.style.display = 'none';
        $chartPane.style.display = 'none';
        // Hide UI elements
        $statusBadge.style.display = 'none';
        $viewToggle.style.display = 'none';
        $btnExport.style.display = 'none';
        $compareRow.style.display = 'none';
        cards.reco.style.display = 'none';
        $lblCoords.textContent = '';
        $btnAnalyze.textContent = '🔬  Analyser ce sol';

        // Clear 3D
        clearSoilCore();
        resetStatsPanel();
        currentLayers = null;
        selectedLayerIdx = 0;

        // Fix map size
        setTimeout(() => map.invalidateSize(), 100);
    }

    function showAnalysisResults() {
        analysisComplete = true;
        // Hide map, show 3D
        $mapEl.style.display = 'none';
        $canvas.style.display = 'block';
        onResize();

        buildSoilCore(currentLayers);
        buildChartView(currentLayers);
        buildMiniLayerButtons(currentLayers);
        updateStatsPanel(currentLayers[0], 0);

        // Show UI
        $statusBadge.style.display = 'flex';
        $viewToggle.style.display = 'flex';
        $btnExport.style.display = 'inline-block';
        $compareRow.style.display = 'flex';
        $btnAnalyze.textContent = '🗺  Analyser nouveau sol';

        $lblCoords.textContent = `📍  ${selectedLat}, ${selectedLon}`;
    }

    // ── Build 3D Soil Core ───────────────────────────────────────────
    function clearSoilCore() {
        soilCoreMeshes.forEach(m => {
            scene.remove(m);
            m.geometry.dispose();
            m.material.dispose();
        });
        soilCoreMeshes = [];
    }

    function buildSoilCore(layers) {
        clearSoilCore();

        const radius = 50;
        const gap = 3;
        const heights = [20, 40, 60, 95, 125, 155];
        const baseColors = [
            0x5a7247, 0x8b6914, 0x7a5c30,
            0x5e432e, 0x6b5a4b, 0x7d756d
        ];

        let currentY = 0;
        const totalHeight = heights.reduce((a, b) => a + b, 0) + gap * (heights.length - 1);

        for (let i = 0; i < layers.length && i < heights.length; i++) {
            const h = heights[i];
            const layer = layers[i];

            let color = new THREE.Color(baseColors[i] || 0x8b7355);
            // Tint based on soil data
            if (layer.sand !== null && layer.sand > 50) {
                color.lerp(new THREE.Color(0xd4c39a), 0.28);
            }
            if (layer.clay !== null && layer.clay > 35) {
                color.lerp(new THREE.Color(0x8a3b2b), 0.25);
            }
            if (layer.phh2o !== null && layer.phh2o < 5.5) {
                color.lerp(new THREE.Color(0x9b4444), 0.12);
            }

            const geo = new THREE.CylinderGeometry(radius, radius, h, 48);
            const mat = new THREE.MeshPhongMaterial({
                color: color,
                specular: 0x373230,
                shininess: 22,
            });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.y = -(currentY + h / 2 - totalHeight / 2);
            mesh.userData = { layerIndex: i, originalColor: color.clone() };
            currentY += h + gap;

            // Click handler
            mesh.cursor = 'pointer';
            scene.add(mesh);
            soilCoreMeshes.push(mesh);
        }

        // Grass cap
        const grassGeo = new THREE.CylinderGeometry(radius + 4, radius + 4, 4, 48);
        const grassMat = new THREE.MeshPhongMaterial({ color: 0x4a7c3f, specular: 0x2d5c28, shininess: 12 });
        const grass = new THREE.Mesh(grassGeo, grassMat);
        grass.position.y = totalHeight / 2 + 2;
        scene.add(grass);
        soilCoreMeshes.push(grass);

        // Base cap
        const baseGeo = new THREE.CylinderGeometry(radius + 3, radius + 3, 3, 48);
        const baseMat = new THREE.MeshPhongMaterial({ color: 0x2c2925 });
        const base = new THREE.Mesh(baseGeo, baseMat);
        base.position.y = -(totalHeight / 2 + 1.5);
        scene.add(base);
        soilCoreMeshes.push(base);

        // Entrance animation
        const group = soilCoreMeshes;
        group.forEach(m => { m.position.y -= 100; m.material.opacity = 0; m.material.transparent = true; });
        const startTime = Date.now();
        function animateEntrance() {
            const elapsed = Date.now() - startTime;
            const t = Math.min(elapsed / 900, 1);
            const ease = 1 - Math.pow(1 - t, 3);
            group.forEach(m => {
                m.position.y += (100 * ease) / 30;
                m.material.opacity = ease;
            });
            if (t < 1) requestAnimationFrame(animateEntrance);
            else group.forEach(m => { m.material.transparent = false; m.material.opacity = 1; });
        }
        animateEntrance();

        // Raycaster for clicks
        setupRaycaster();
    }

    function setupRaycaster() {
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();

        $canvas.addEventListener('click', (event) => {
            const rect = $canvas.getBoundingClientRect();
            mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);
            const clickable = soilCoreMeshes.filter(m => m.userData.layerIndex !== undefined);
            const intersects = raycaster.intersectObjects(clickable);

            if (intersects.length > 0) {
                const idx = intersects[0].object.userData.layerIndex;
                if (idx !== undefined && currentLayers && currentLayers[idx]) {
                    highlightLayer(idx);
                    updateStatsPanel(currentLayers[idx], idx);
                    // Activate mini button
                    $miniButtons.querySelectorAll('button').forEach((b, bi) => {
                        b.classList.toggle('active', bi === idx);
                    });
                }
            }
        });
    }

    function highlightLayer(idx) {
        soilCoreMeshes.forEach(m => {
            if (m.userData.layerIndex !== undefined) {
                if (m.userData.layerIndex === idx) {
                    m.material.color.copy(m.userData.originalColor).multiplyScalar(1.6);
                    m.material.specular = new THREE.Color(0xffffff);
                    m.material.shininess = 5;
                    m.scale.set(1.05, 1, 1.05);
                } else {
                    m.material.color.copy(m.userData.originalColor);
                    m.material.specular = new THREE.Color(0x373230);
                    m.material.shininess = 22;
                    m.scale.set(1, 1, 1);
                }
            }
        });
        selectedLayerIdx = idx;
    }

    // ── View Toggle ──────────────────────────────────────────────────
    function switchView(view) {
        if (view === '3d') {
            $canvas.style.display = 'block';
            $chartPane.style.display = 'none';
            $btn3D.classList.add('active');
            $btnChart.classList.remove('active');
            onResize();
        } else {
            $canvas.style.display = 'none';
            $chartPane.style.display = 'block';
            $btn3D.classList.remove('active');
            $btnChart.classList.add('active');
        }
    }

    // ── Chart View ───────────────────────────────────────────────────
    function buildChartView(layers) {
        const container = document.getElementById('chart-rows');
        container.innerHTML = '';

        const props = [
            { label: 'pH du Sol', key: 'phh2o', max: 14, color: '#e74c3c' },
            { label: 'Azote (cg/kg)', key: 'nitrogen', max: 500, color: '#27ae60' },
            { label: 'Sable (%)', key: 'sand', max: 100, color: '#e6a845' },
            { label: 'Argile (%)', key: 'clay', max: 100, color: '#a56336' },
            { label: 'CEC (mmol(c)/kg)', key: 'cec', max: 40, color: '#3498db' },
        ];

        props.forEach(p => {
            const header = document.createElement('div');
            header.className = 'chart-section-label';
            header.textContent = p.label.toUpperCase();
            container.appendChild(header);

            layers.forEach((layer, li) => {
                const val = layer[p.key] ?? 0;
                const frac = Math.min(val / p.max, 1);

                const row = document.createElement('div');
                row.className = 'chart-row';
                row.innerHTML = `
                    <span class="depth-label">${layer.depthLabel}</span>
                    <div class="chart-bar-wrap">
                        <div class="chart-bar-fill" style="background:${p.color};"></div>
                    </div>
                    <span class="chart-val" style="color:${p.color}">${val.toFixed(1)}</span>
                `;
                container.appendChild(row);

                // Animate bar
                setTimeout(() => {
                    row.querySelector('.chart-bar-fill').style.width = (frac * 100) + '%';
                }, li * 60);
            });
        });
    }

    // ── Mini Layer Buttons ───────────────────────────────────────────
    function buildMiniLayerButtons(layers) {
        $miniButtons.innerHTML = '';
        layers.forEach((layer, i) => {
            const btn = document.createElement('button');
            btn.textContent = layer.depthLabel;
            if (i === 0) btn.classList.add('active');
            btn.addEventListener('click', () => {
                updateStatsPanel(layer, i);
                highlightLayer(i);
                $miniButtons.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
            $miniButtons.appendChild(btn);
        });
    }

    // ── Stats Panel ──────────────────────────────────────────────────
    function resetStatsPanel() {
        $lblDepth.textContent = 'Cliquez sur une couche';
        $scoreVal.textContent = '--';
        $scoreVal.style.color = '#fff';
        document.querySelectorAll('.soil-card .big-value').forEach(el => el.textContent = '--');
        document.querySelectorAll('.soil-card .bar-fill').forEach(el => el.style.width = '0');
        document.querySelectorAll('.soil-card .card-status').forEach(el => {
            el.textContent = 'En attente d\'analyse…';
            el.style.color = 'rgba(255,255,255,0.5)';
        });
        document.querySelectorAll('.soil-card .card-badge').forEach(el => el.textContent = '');
        document.querySelectorAll('.soil-card').forEach(el => el.classList.remove('visible'));

        // Reset texture values
        const sandVal = document.getElementById('val-sand');
        const clayVal = document.getElementById('val-clay');
        if (sandVal) sandVal.textContent = '--%';
        if (clayVal) clayVal.textContent = '--%';
    }

    function updateStatsPanel(layer, idx) {
        $lblDepth.textContent = 'Couche : ' + layer.depthLabel;

        let score = 100;
        const recos = [];

        // pH
        const phVal = document.getElementById('val-ph');
        const phBar = document.getElementById('bar-ph');
        const phStatus = document.getElementById('status-ph');
        const phBadge = document.getElementById('badge-ph');
        if (layer.phh2o !== null) {
            animateCounter(phVal, layer.phh2o, 1);
            animateBar(phBar, layer.phh2o / 14, colorForPh(layer.phh2o));
            if (layer.phh2o < 5.5) {
                score -= 25;
                phStatus.textContent = '⚠  Acide — Favorise Fusarium & Rhizoctonia. Risque carence Ca/Mg.';
                phStatus.style.color = '#e74c3c';
                setBadge(phBadge, 'ACIDE', '#e74c3c');
                recos.push('• Apport de chaux agricole pour relever le pH vers 6.0–7.0.');
            } else if (layer.phh2o > 7.5) {
                score -= 15;
                phStatus.textContent = '⚠  Alcalin — Blocage Fe/Zn, chlorose possible.';
                phStatus.style.color = '#f39c12';
                setBadge(phBadge, 'ALCALIN', '#f39c12');
                recos.push('• Apport de soufre élémentaire ou matière organique pour acidifier.');
            } else {
                phStatus.textContent = '✓  Optimal — Absorption maximale, résistance naturelle élevée.';
                phStatus.style.color = '#2ecc71';
                setBadge(phBadge, 'OPTIMAL', '#27ae60');
            }
        }

        // Nitrogen
        const nVal = document.getElementById('val-nitrogen');
        const nBar = document.getElementById('bar-nitrogen');
        const nStatus = document.getElementById('status-nitrogen');
        const nBadge = document.getElementById('badge-nitrogen');
        if (layer.nitrogen !== null) {
            animateCounter(nVal, layer.nitrogen, 0);
            animateBar(nBar, Math.min(layer.nitrogen / 500, 1), colorForN(layer.nitrogen));
            if (layer.nitrogen < 150) {
                score -= 20;
                nStatus.textContent = '⚠  Déficit — Immunité réduite, sensibilité aux maladies foliaires.';
                nStatus.style.color = '#e74c3c';
                setBadge(nBadge, 'DÉFICIT', '#e74c3c');
                recos.push('• Fertilisation azotée (urée 46%) ou engrais vert (légumineuses).');
            } else if (layer.nitrogen > 400) {
                score -= 10;
                nStatus.textContent = '⚠  Excès — Croissance molle, oïdium & mildiou favorisés.';
                nStatus.style.color = '#f39c12';
                setBadge(nBadge, 'EXCÈS', '#f39c12');
                recos.push('• Réduire les apports azotés, privilégier le fractionnement.');
            } else {
                nStatus.textContent = '✓  Équilibré — Synthèse protéique optimale, résistance renforcée.';
                nStatus.style.color = '#2ecc71';
                setBadge(nBadge, 'OK', '#27ae60');
            }
        }

        // Texture
        const sandVal = document.getElementById('val-sand');
        const clayVal = document.getElementById('val-clay');
        const sandBar = document.getElementById('bar-sand');
        const clayBar = document.getElementById('bar-clay');
        const texStatus = document.getElementById('status-texture');
        if (layer.sand !== null && layer.clay !== null) {
            animateCounter(sandVal, layer.sand, 0, '%');
            animateCounter(clayVal, layer.clay, 0, '%');
            animateBar(sandBar, layer.sand / 100, '#e6a845');
            animateBar(clayBar, layer.clay / 100, '#a56336');
            if (layer.sand > 65) {
                score -= 12;
                texStatus.textContent = 'Sablonneux — Drainage excessif, stress hydrique, vulnérable aux infections.';
                texStatus.style.color = '#f39c12';
                recos.push('• Amendement organique (compost) pour améliorer la rétention d\'eau.');
            } else if (layer.clay > 40) {
                score -= 12;
                texStatus.textContent = 'Argileux — Risque pourriture racinaire (Pythium, Phytophthora).';
                texStatus.style.color = '#f39c12';
                recos.push('• Améliorer le drainage : sablage, labour profond ou drains souterrains.');
            } else {
                texStatus.textContent = 'Équilibré — Bon drainage/rétention. Conditions défavorables aux pathogènes.';
                texStatus.style.color = '#2ecc71';
            }
        }

        // CEC
        const cecVal = document.getElementById('val-cec');
        const cecBar = document.getElementById('bar-cec');
        const cecBadge = document.getElementById('badge-cec');
        if (layer.cec !== null) {
            animateCounter(cecVal, layer.cec, 1);
            animateBar(cecBar, Math.min(layer.cec / 40, 1), '#3498db');
            if (layer.cec < 10) {
                score -= 10;
                setBadge(cecBadge, 'FAIBLE', '#e74c3c');
                recos.push('• Enrichissement en matière organique pour augmenter la CEC.');
            } else if (layer.cec > 28) {
                setBadge(cecBadge, 'ÉLEVÉ', '#3498db');
            } else {
                setBadge(cecBadge, 'NORMAL', '#27ae60');
            }
        }

        // Score
        score = Math.max(0, Math.min(100, score));
        const scoreColor = score >= 80 ? '#2ecc71' : score >= 55 ? '#f39c12' : '#e74c3c';
        animateScoreCounter($scoreVal, score);
        $scoreVal.style.color = scoreColor;

        // Recommendations
        const reco1 = document.getElementById('reco-1');
        const reco2 = document.getElementById('reco-2');
        const reco3 = document.getElementById('reco-3');
        if (recos.length > 0) {
            reco1.textContent = recos[0] || '';
            reco2.textContent = recos[1] || '';
            reco3.textContent = recos[2] || '';
            cards.reco.style.display = 'block';
        } else {
            reco1.textContent = '✓  Aucun amendement urgent détecté pour cette couche.';
            reco2.textContent = '';
            reco3.textContent = '';
            cards.reco.style.display = 'block';
        }

        // Card entrance animation
        const allCards = [cards.ph, cards.nitrogen, cards.texture, cards.cec, cards.reco];
        allCards.forEach((c, i) => {
            c.classList.remove('visible');
            setTimeout(() => c.classList.add('visible'), i * 55);
        });
    }

    // ── Animation helpers ────────────────────────────────────────────
    function animateCounter(el, target, decimals, suffix) {
        suffix = suffix || '';
        const start = 0;
        const duration = 700;
        const startTime = performance.now();
        function step(now) {
            const t = Math.min((now - startTime) / duration, 1);
            const ease = 1 - Math.pow(1 - t, 3);
            const val = start + (target - start) * ease;
            el.textContent = val.toFixed(decimals) + suffix;
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function animateScoreCounter(el, target) {
        const duration = 900;
        const startTime = performance.now();
        function step(now) {
            const t = Math.min((now - startTime) / duration, 1);
            const ease = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(target * ease);
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function animateBar(barFill, fraction, color) {
        barFill.style.background = color;
        barFill.style.width = '0';
        setTimeout(() => {
            barFill.style.width = (fraction * 100) + '%';
        }, 50);
    }

    function setBadge(el, text, hex) {
        el.textContent = text;
        el.style.background = hex + '33';
        el.style.color = hex;
    }

    function colorForPh(ph) {
        if (ph < 5.5) return '#e74c3c';
        if (ph > 7.5) return '#f39c12';
        return '#2ecc71';
    }
    function colorForN(n) {
        if (n < 150) return '#e74c3c';
        if (n > 400) return '#f39c12';
        return '#2ecc71';
    }

    // ── CSV Export ────────────────────────────────────────────────────
    function handleExport() {
        if (!currentLayers || !currentLayers.length) return;
        let csv = 'Profondeur,pH,Azote (cg/kg),Sable (%),Argile (%),CEC (mmol(c)/kg)\n';
        currentLayers.forEach(l => {
            csv += `${l.depthLabel},${(l.phh2o ?? 0).toFixed(2)},${(l.nitrogen ?? 0).toFixed(1)},${(l.sand ?? 0).toFixed(1)},${(l.clay ?? 0).toFixed(1)},${(l.cec ?? 0).toFixed(1)}\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `sol_export_${Date.now()}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        // Visual feedback
        const orig = $btnExport.textContent;
        $btnExport.textContent = '✓  Exporté !';
        setTimeout(() => { $btnExport.textContent = orig; }, 2000);
    }

    // ── Start ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', init);
})();
