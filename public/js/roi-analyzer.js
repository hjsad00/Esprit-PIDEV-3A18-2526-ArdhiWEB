/**
 * ROI Analyzer - Moteur d'analyse financière avec Python + IA
 * Envoie les données au backend, appelle le moteur Python, affiche les résultats premium
 */

class RoiAnalyzer {
    constructor(analyzeUrl = '/farmer/roi/analyze') {
        console.log('🔨 RoiAnalyzer constructor called with URL:', analyzeUrl);
        this.analyzeUrl = analyzeUrl;
        
        // Chercher le formulaire de plusieurs façons
        this.form = document.getElementById('roi_form');
        if (!this.form) this.form = document.querySelector('form[name="roi_form"]');
        if (!this.form) this.form = document.querySelector('form');
        
        console.log('📋 Form trouvé:', this.form);
        console.log('   - ID:', this.form?.id);
        console.log('   - Name:', this.form?.name);
        
        this.submitBtn = document.querySelector('.btn-calculate');
        this.resultsContainer = document.getElementById('roi-results-container') || document.querySelector('.dashboard-grid');
        this.isLoading = false;
        this.weatherData = null;
        
        console.log('   - Submit btn:', this.submitBtn);
        console.log('   - Results container:', this.resultsContainer);
        
        this.init();
    }

    init() {
        if (!this.form) {
            console.error('❌ Form not found, cannot initialize');
            return;
        }

        console.log('🚀 RoiAnalyzer init() - Initialisation');

        // Remplacer le submit du formulaire par AJAX
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            console.log('📤 Form submitted');
            this.analyzeROI();
        });

        // 🔍 Trouver le select parcelle
        let parcelleSelect = document.getElementById('roi_form_parcelle');
        
        if (!parcelleSelect) {
            parcelleSelect = this.form.querySelector('select[id*="parcelle"]');
            console.log('✅ Found select with parcelle ID:', parcelleSelect?.id);
        }
        
        if (!parcelleSelect) {
            parcelleSelect = this.form.querySelector('select');
            console.log('✅ Using first select in form:', parcelleSelect);
        }
        
        console.log('📍 Parcelle select:', parcelleSelect);
        console.log('   - ID:', parcelleSelect?.id);
        console.log('   - Name:', parcelleSelect?.name);
        
        if (parcelleSelect) {
            // Écouter les changements
            parcelleSelect.addEventListener('change', (e) => {
                console.log('🔄 Parcelle changée:', e.target.value);
                this.loadWeatherData();
            });

            // Charger la météo si une parcelle est déjà sélectionnée
            if (parcelleSelect.value) {
                console.log('✅ Parcelle pré-sélectionnée, chargement météo...');
                this.loadWeatherData();
            }
        } else {
            console.error('❌ Parcelle select not found!');
        }
    }

    /**
     * Charge les données météo depuis Open-Meteo API (100% gratuit, CORS activé)
     */
    async loadWeatherData() {
        try {
            console.log('🌍 loadWeatherData() - Début du chargement');
            
            // Récupérer la parcelle sélectionnée
            let parcelleSelect = document.getElementById('roi_form_parcelle');
            if (!parcelleSelect) parcelleSelect = document.querySelector('form select');
            
            const parcelleId = parcelleSelect?.value;
            
            if (!parcelleId) {
                console.warn('⚠️ Aucune parcelle sélectionnée');
                return;
            }
            
            console.log('📍 Parcelle ID:', parcelleId);
            
            // Afficher que ça charge
            const statusEl = document.getElementById('weather-status');
            if (statusEl) {
                statusEl.innerHTML = `<div class="spinner-border spinner-border-sm text-success me-2" role="status"></div> Localisation de la parcelle...`;
            }
            
            // 1️⃣ Récupérer les coordonnées de la parcelle via API PHP
            const coordResponse = await fetch(`/api/weather/get-coordinates/${parcelleId}`, { method: 'GET' });
            
            console.log('📊 API Coordonnées status:', coordResponse.status);
            
            if (!coordResponse.ok) {
                console.warn('⚠️ Impossible de récupérer les coordonnées');
                if (statusEl) {
                    statusEl.innerHTML = `⚠️ Coordonnées non disponibles (HTTP ${coordResponse.status})`;
                }
                return;
            }
            
            const coordData = await coordResponse.json();
            console.log('✅ Coordonnées reçues:', coordData);
            
            const lat = coordData.latitude;
            const lon = coordData.longitude;
            
            if (!lat || !lon) {
                console.warn('⚠️ Parcelle sans coordonnées GPS');
                if (statusEl) {
                    statusEl.innerHTML = `⚠️ Cette parcelle n'a pas de coordonnées GPS. Météo indisponible.`;
                }
                return;
            }
            
            // Mettre à jour le statut
            if (statusEl) {
                statusEl.innerHTML = `<div class="spinner-border spinner-border-sm text-success me-2" role="status"></div> Synchronisation météo (${lat}, ${lon})...`;
            }
            
            // 2️⃣ Appel Open-Meteo API (gratuit, pas de clé, CORS OK)
            const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m&timezone=auto`;
            
            console.log('📡 Appel Open-Meteo:', url);
            
            const response = await fetch(url, { method: 'GET' });
            
            console.log('📊 Open-Meteo status:', response.status);
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ Données météo reçues:', data);
                
                // Transformer les données Open-Meteo au format attendu
                const weather = {
                    main: {
                        temp: data.current.temperature_2m,
                        humidity: data.current.relative_humidity_2m
                    },
                    rain: {
                        '1h': data.current.precipitation
                    },
                    wind: {
                        speed: data.current.wind_speed_10m / 3.6  // Convertir km/h en m/s
                    },
                    name: coordData.localisation || `${lat}, ${lon}`
                };
                
                console.log('✅ Météo transformée:', weather);
                
                this.displayWeatherBlock(weather);
                
                // Mettre à jour le statut
                if (statusEl) {
                    const temp = Math.round(weather.main.temp);
                    const humidity = weather.main.humidity;
                    const rain = weather.rain['1h'].toFixed(1);
                    const wind = Math.round(weather.wind.speed * 3.6);
                    statusEl.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> Synchronisé : ${temp}°C, ${humidity}% hum., ${rain}mm pluie`;
                }
            } else {
                console.warn('⚠️ Open-Meteo indisponible (HTTP ' + response.status + ')');
                
                if (statusEl) {
                    statusEl.innerHTML = `⚠️ Météo non disponible (HTTP ${response.status}). Le calcul continuera avec les données du formulaire.`;
                }
            }
            
        } catch (error) {
            console.error('❌ Erreur météo:', error);
            
            const statusEl = document.getElementById('weather-status');
            if (statusEl) {
                statusEl.innerHTML = `⚠️ Erreur: ${error.message}`;
            }
        }
    }

    /**
     * Charge les prévisions 5 jours et estime les jours critiques
     * Si Forecast échoue, utilise la météo actuelle pour estimer
     */
    async loadForecastAndEstimateClimateRisks(city, currentWeather = null) {
        try {
            console.log('📊 Chargement prévisions pour:', city);
            
            // Essayer d'abord avec Forecast
            const forecastUrl = `https://api.openweathermap.org/data/2.5/forecast?q=${city}&units=metric&appid=demo`;
            const response = await fetch(forecastUrl, { method: 'GET' });
            
            if (response.ok) {
                // Forecast OK
                const forecast = await response.json();
                console.log('✅ Prévisions reçues:', forecast);
                this.analyzeForecastData(forecast);
            } else {
                console.warn('⚠️ Forecast API non disponible (code ' + response.status + ')');
                console.log('💡 Estimation basée sur la météo actuelle...');
                
                if (currentWeather) {
                    this.estimateFromCurrentWeather(currentWeather);
                }
            }
            
        } catch (error) {
            console.error('❌ Erreur prévisions:', error);
            console.log('💡 Utilisation de données par défaut');
        }
    }

    /**
     * Analyse les données de prévisions
     */
    analyzeForecastData(forecast) {
        let canicule_days = 0;
        let pluie_jours = 0;
        let gel_jours = 0;
        
        const analyzed_dates = new Set();
        
        forecast.list.forEach(item => {
            const date = item.dt_txt.split(' ')[0];
            
            if (!analyzed_dates.has(date)) {
                analyzed_dates.add(date);
                
                const temp = item.main.temp;
                const rain = item.rain?.['3h'] || 0;
                
                console.log(`📅 ${date}: Temp=${temp}°C, Rain=${rain}mm`);
                
                if (temp > 35) {
                    canicule_days++;
                    console.log(`  ☀️ Canicule détectée`);
                }
                
                if (temp < 0) {
                    gel_jours++;
                    console.log(`  ❄️ Gel détecté`);
                }
                
                if (rain > 5) {
                    pluie_jours++;
                    console.log(`  🌧️ Pluie excessive détectée`);
                }
            }
        });
        
        this.fillClimateFields(canicule_days, pluie_jours, gel_jours);
    }

    /**
     * Estime les jours critiques basé sur la météo actuelle
     */
    estimateFromCurrentWeather(weather) {
        let canicule_days = 0;
        let pluie_jours = 0;
        let gel_jours = 0;
        
        const temp = weather.main.temp;
        const rain = weather.rain?.['1h'] || 0;
        const humidity = weather.main.humidity;
        
        console.log(`🌡️ Météo actuelle: ${temp}°C, ${humidity}% humidité, ${rain}mm pluie`);
        
        // Estimer sur 5 jours basé sur la météo actuelle
        // C'est une approximation: si chaud maintenant, risque de canicule
        if (temp > 30) {
            canicule_days = Math.ceil((temp - 30) / 3);  // 1-2 jours estimés
            console.log(`☀️ Estimation canicule: ${canicule_days}j`);
        }
        
        if (temp < 10) {
            gel_jours = Math.ceil((10 - temp) / 5);  // 1-2 jours estimés
            console.log(`❄️ Estimation gel: ${gel_jours}j`);
        }
        
        if (rain > 2 || humidity > 80) {
            pluie_jours = rain > 5 ? 2 : 1;  // 1-2 jours estimés
            console.log(`🌧️ Estimation pluie: ${pluie_jours}j`);
        }
        
        this.fillClimateFields(canicule_days, pluie_jours, gel_jours);
    }

    /**
     * Remplit les champs du formulaire
     */
    fillClimateFields(canicule, pluie, gel) {
        const caniculeInput = document.getElementById('roi_form_jours_canicule');
        const pluieInput = document.getElementById('roi_form_jours_excespluie');
        const gelInput = document.getElementById('roi_form_jours_gel');
        
        console.log('🎯 Remplissage des champs:');
        console.log('  - Canicule:', caniculeInput);
        console.log('  - Pluie:', pluieInput);
        console.log('  - Gel:', gelInput);
        
        if (caniculeInput) {
            caniculeInput.value = canicule;
            console.log(`✅ Canicule rempli: ${canicule}`);
        }
        if (pluieInput) {
            pluieInput.value = pluie;
            console.log(`✅ Pluie remplie: ${pluie}`);
        }
        if (gelInput) {
            gelInput.value = gel;
            console.log(`✅ Gel rempli: ${gel}`);
        }
    }

    /**
     * Affiche le bloc météo et stocke les données
     */
    displayWeatherBlock(weather) {
        const weatherBlock = document.getElementById('weather-block');
        if (!weatherBlock) return;
        
        // Stocker les données météo pour l'analyse Python
        this.weatherData = {
            temperature: weather.main.temp,
            humidity: weather.main.humidity,
            rain: weather.rain['1h'] || 0,
            wind_speed: weather.wind.speed,  // En m/s
            city: weather.name
        };
        
        console.log('💾 Données météo stockées:', this.weatherData);
        
        const temp = Math.round(weather.main.temp);
        const humidity = weather.main.humidity;
        const rain = weather.rain['1h'].toFixed(1);
        const wind = Math.round(weather.wind.speed * 3.6);
        
        const html = `
            <div class="weather-block-header">
                <i class="bi bi-geo-alt-fill"></i> Météo Locale : ${weather.name}
            </div>
            <div class="weather-data-row">
                <div class="weather-item">
                    <i class="bi bi-thermometer-sun"></i>
                    <div class="val">${temp}°C</div>
                    <div class="lbl">Température</div>
                </div>
                <div class="weather-item">
                    <i class="bi bi-moisture"></i>
                    <div class="val">${humidity}%</div>
                    <div class="lbl">Humidité</div>
                </div>
                <div class="weather-item">
                    <i class="bi bi-cloud-haze2-fill"></i>
                    <div class="val">${rain} <small>mm</small></div>
                    <div class="lbl">Pluie</div>
                </div>
                <div class="weather-item">
                    <i class="bi bi-wind"></i>
                    <div class="val">${wind} <small>km/h</small></div>
                    <div class="lbl">Vent</div>
                </div>
            </div>
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.75rem; color: #999; font-weight: 600;">
                <i class="bi bi-check2-circle" style="color: #1e8341;"></i> 
                <span>Moteur Météo Open-Meteo synchronisé</span>
            </div>
        `;
        
        weatherBlock.innerHTML = html;
        weatherBlock.style.display = 'block';
        
        console.log('✅ Bloc météo affiché');
    }

    /**
     * Extrait les données du formulaire + météo
     */
    getFormData() {
        const formData = new FormData(this.form);
        const data = {};

        // Mapper les champs du formulaire aux paramètres attendus par Python
        data.surface = parseFloat(formData.get('roi_form[surface_ha]')) || 0;
        data.rendement = parseFloat(formData.get('roi_form[rendement]')) || 0;
        data.prix_vente = parseFloat(formData.get('roi_form[prix_vente]')) || 0;
        data.cout_semences = parseFloat(formData.get('roi_form[cout_semences]')) || 0;
        data.cout_engrais = parseFloat(formData.get('roi_form[cout_engrais]')) || 0;
        data.cout_main_oeuvre = parseFloat(formData.get('roi_form[cout_main_oeuvre]')) || 0;
        data.cout_irrigation = parseFloat(formData.get('roi_form[cout_irrigation]')) || 0;
        data.autres_couts = parseFloat(formData.get('roi_form[cout_autres]')) || 0;
        
        // 🌡️ Paramètres climatiques du formulaire
        data.jours_canicule = parseInt(formData.get('roi_form[jours_canicule]')) || 0;
        data.jours_pluie = parseInt(formData.get('roi_form[jours_excespluie]')) || 0;
        data.jours_gel = parseInt(formData.get('roi_form[jours_gel]')) || 0;
        data.duree_pret = parseInt(formData.get('roi_form[duree_pret]')) || 5;
        
        data.parcelle_id = formData.get('roi_form[parcelle]');
        data.culture = 'Tomate'; 

        // 🌤️ Utiliser les données météo si disponibles
        if (this.weatherData) {
            data.temperature = this.weatherData.temperature || 25;
            data.humidity = this.weatherData.humidity || 60;
            data.rain = this.weatherData.rain || 0;
            data.wind_speed = this.weatherData.wind_speed || 3;
        }

        console.log('📊 Form data prepared:', data);
        return data;
    }

    /**
     * Analyse la rentabilité via le moteur Python
     */
    async analyzeROI() {
        if (this.isLoading) return;

        try {
            this.isLoading = true;
            this.showLoader();
            this.submitBtn.disabled = true;

            const data = this.getFormData();

            console.log('📤 Envoi des données:', data);

            console.log('📤 Envoi des données vers:', this.analyzeUrl);
            const response = await fetch(this.analyzeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                let errorMsg = `Erreur HTTP: ${response.status}`;
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorMsg;
                } catch (e) {}
                this.showError(errorMsg);
                return;
            }

            const result = await response.json();
            console.log('📥 Résultat du moteur:', result);

            if (result.success) {
                this.displayResults(result);
            } else {
                this.showError(result.error || 'Erreur d\'analyse');
            }

        } catch (error) {
            console.error('❌ Erreur:', error);
            this.showError('Erreur de communication avec le serveur');
        } finally {
            this.isLoading = false;
            this.submitBtn.disabled = false;
        }
    }

    /**
     * Affiche le loader pendant l'analyse
     */
    showLoader() {
        const loader = document.createElement('div');
        loader.id = 'roi-loader';
        loader.innerHTML = `
            <div style="text-align: center; padding: 40px; background: #f8faf7; border-radius: 16px; margin-top: 20px;">
                <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Analyse en cours...</span>
                </div>
                <h5 class="text-success mt-3"><strong>⏳ Moteur Python en action</strong></h5>
                <p class="text-muted">Calcul ROI • Simulation climatique • Recommandations IA...</p>
            </div>
        `;

        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = '';
            this.resultsContainer.appendChild(loader);
        }
    }

    /**
     * Affiche les résultats premium
     */
    displayResults(result) {
        const html = `
            <div class="premium-card-results" style="animation: slideIn 0.5s ease;">
                <!-- Badge IA -->
                <div class="d-flex justify-content-end mb-3">
                    <span class="badge rounded-pill bg-success-light text-success border border-success px-3 py-2" style="font-size: 0.7rem; font-weight: 800; letter-spacing: 1px; background: rgba(17,101,48,0.05);">
                        <i class="bi bi-robot me-1"></i> MOTEUR IA ACTIF (PYTHON)
                    </span>
                </div>
                
                <!-- ROI Score -->
                <div class="roi-score-box" style="background: linear-gradient(135deg, #116530, #1e8341); border-radius: 20px; padding: 30px; text-align: center; color: white; margin-bottom: 20px;">
                    <div style="font-size: 0.95rem; opacity: 0.9; margin-bottom: 10px; font-weight: 500; letter-spacing: 1px;">
                        ${result.emoji} SCORE RENTABILITÉ
                    </div>
                    <div style="font-size: 3.5rem; font-weight: 900; line-height: 1; margin: 10px 0;">
                        ${result.roi.toFixed(1)}%
                    </div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">
                        Niveau: <strong>${result.niveau}</strong>
                    </div>
                </div>

                <!-- Metrics Grid -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px; margin-bottom: 20px;">
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Production Réelle (kg)</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #2d3436; margin-top: 5px;">
                            ${result.production.toLocaleString('fr-FR', {maximumFractionDigits: 0})}
                        </div>
                    </div>
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Revenu Brut (DT)</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #28a745; margin-top: 5px;">
                            ${result.revenu.toLocaleString('fr-FR', {maximumFractionDigits: 2})}
                        </div>
                    </div>
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Coût Total (DT)</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #d32f2f; margin-top: 5px;">
                            ${result.cout_total.toLocaleString('fr-FR', {maximumFractionDigits: 2})}
                        </div>
                    </div>
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Marge (DT)</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #2d3436; margin-top: 5px;">
                            ${result.marge.toLocaleString('fr-FR', {maximumFractionDigits: 2})}
                        </div>
                    </div>
                </div>

                <!-- Climate Factor & Loan -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Facteur Climatique</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #2d3436; margin-top: 5px;">
                            ${result.facteur_climatique.toFixed(3)}
                        </div>
                    </div>
                    <div style="background: #f8faf7; border-radius: 14px; padding: 18px; border-left: 4px solid #116530;">
                        <div style="font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px;">Capacité de Prêt (DT)</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #2d3436; margin-top: 5px;">
                            ${result.capacite_pret.toLocaleString('fr-FR', {maximumFractionDigits: 2})}
                        </div>
                    </div>
                </div>

                <!-- Risk Band -->
                <div style="border-radius: 14px; padding: 20px; text-align: center; font-weight: bold; margin-bottom: 20px; background: ${this.getRiskBgColor(result.risque)}; color: ${this.getRiskTextColor(result.risque)}; border: 1px solid ${this.getRiskBorderColor(result.risque)};">
                    🚨 Niveau de Risque: <strong>${result.risque.toUpperCase()}</strong>
                </div>

                <!-- Conseils -->
                ${this.buildConseils(result.conseils)}

                <!-- Alternative Culture -->
                ${this.buildAlternative(result.alternative)}
            </div>

            <style>
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>
        `;

        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = html;
        }
    }

    /**
     * Construit la section des conseils
     */
    buildConseils(conseils) {
        if (!conseils || conseils.length === 0) {
            return '';
        }

        const coneilsHtml = conseils
            .map(c => `<li style="margin-bottom: 10px; font-size: 0.95rem; color: #333;">${c}</li>`)
            .join('');

        return `
            <div style="background: #f0f7f2; border-left: 5px solid #116530; border-radius: 16px; padding: 25px; margin-bottom: 20px;">
                <div style="color: #116530; font-weight: 800; font-size: 1.1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-lightbulb-fill"></i> Recommandations Intelligentes
                </div>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    ${coneilsHtml}
                </ul>
            </div>
        `;
    }

    /**
     * Construit la section alternative
     */
    buildAlternative(alternative) {
        if (!alternative || alternative === 'Maintenir la culture actuelle') {
            return '';
        }

        return `
            <div style="background: #fff3e0; border-radius: 16px; padding: 20px; border: 1px solid #ffe0b2; color: #e65100;">
                <h5 style="font-weight: 800; font-size: 1rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-arrow-repeat"></i> Culture Alternative Recommandée
                </h5>
                <div style="background: white; color: #e65100; padding: 10px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; display: inline-block; box-shadow: 0 2px 5px rgba(230,81,0,0.1);">
                    🔥 ${alternative}
                </div>
            </div>
        `;
    }

    /**
     * Retourne les couleurs pour le badge de risque
     */
    getRiskBgColor(risque) {
        switch(risque.toLowerCase()) {
            case 'faible': return '#e8f5e9';
            case 'modéré':
            case 'modere': return '#fff8e1';
            case 'élevé':
            case 'eleve': return '#fff0f0';
            default: return '#f0f0f0';
        }
    }

    getRiskTextColor(risque) {
        switch(risque.toLowerCase()) {
            case 'faible': return '#116530';
            case 'modéré':
            case 'modere': return '#f59e0b';
            case 'élevé':
            case 'eleve': return '#e53e3e';
            default: return '#666';
        }
    }

    getRiskBorderColor(risque) {
        switch(risque.toLowerCase()) {
            case 'faible': return '#c8e6c9';
            case 'modéré':
            case 'modere': return '#ffecb3';
            case 'élevé':
            case 'eleve': return '#ffcdd2';
            default: return '#ddd';
        }
    }

    /**
     * Affiche un message d'erreur
     */
    showError(message) {
        const errorHtml = `
            <div style="background: #fff0f0; border-radius: 16px; padding: 25px; color: #e53e3e; border: 1px solid #ffcdd2;">
                <div style="font-weight: 800; font-size: 1.1rem; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-exclamation-triangle-fill"></i> Erreur lors de l'analyse
                </div>
                <p style="margin: 0; font-size: 0.95rem;">
                    ${message}
                </p>
            </div>
        `;

        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = errorHtml;
        }
    }
}
