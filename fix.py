# coding: utf-8
content = """{% extends 'base.html.twig' %}

{% block title %}Analyse Financière ROI - Ardhi{% endblock %}

{% block stylesheets %}
<style>
    .page-header { margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #eee; }
    .page-title { font-family: 'Marcellus', serif; color: #116530; font-size: 2.5rem; margin: 0; }
    .premium-card { background: white; border-radius: 24px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04); padding: 30px; height: 100%; position: relative; overflow: hidden; }
    .card-label { font-size: 12px; font-weight: 700; color: #116530; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
    .form-section { font-size: 0.8rem; font-weight: 700; color: #116530; text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 12px; display: flex; align-items: center; gap: 8px; }
    .form-control, .form-select { border-radius: 12px; padding: 10px 15px; border: 1px solid #eee; transition: all 0.3s; font-size: 0.9rem; }
    .form-control:focus, .form-select:focus { border-color: #116530; box-shadow: 0 0 0 4px rgba(17,101,48,0.05); }
    .form-label { font-weight: 600; color: #2d3436; margin-bottom: 6px; font-size: 0.85rem; }
    .btn-calculate { background: #116530; color: white; border-radius: 50px; padding: 14px; font-weight: 700; border: none; width: 100%; transition: all 0.3s; box-shadow: 0 8px 20px rgba(17,101,48,0.2); display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; }
    .btn-calculate:hover { background: #1e8341; transform: translateY(-2px); }
    .btn-premium-back { color: #116530; background: #e8f5e9; border-radius: 50px; padding: 10px 20px; font-weight: 600; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }
    .btn-premium-back:hover { background: #116530; color: white; }

    /* Dashboard Widgets */
    .dashboard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .widget { background: #f8faf7; border-radius: 16px; padding: 20px; border-left: 4px solid #116530; position: relative; }
    .widget.wide { grid-column: span 2; }
    .widget-title { font-size: 0.75rem; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
    .widget-value { font-size: 1.8rem; font-weight: 800; color: #116530; line-height: 1.2; }
    .widget-sub { font-size: 0.85rem; color: #888; margin-top: 5px; }

    .roi-hero { background: linear-gradient(135deg, #116530, #1e8341); color: white; padding: 30px; border-radius: 20px; text-align: center; margin-bottom: 20px; position: relative; overflow: hidden; }
    .roi-hero .value { font-size: 4rem; font-weight: 900; line-height: 1; }
    .roi-hero .label { font-size: 1rem; opacity: 0.9; margin-top: 5px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; }

    /* AI Card */
    .ai-card { background: linear-gradient(to right, #ffffff, #fdfbf7); border: 1px solid #e2d9c3; border-left: 5px solid #d4af37; border-radius: 16px; padding: 25px; margin-top: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
    .ai-badge { display: inline-flex; align-items: center; gap: 6px; background: #fff9e6; color: #b8860b; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fde68a; margin-bottom: 15px; }
    .ai-title { font-size: 1.2rem; font-weight: 800; color: #2d3436; margin: 0 0 10px 0; }
    .ai-text { font-size: 0.95rem; color: #555; line-height: 1.6; }
    .ai-points { list-style: none; padding: 0; margin: 15px 0 0 0; border-top: 1px solid #f0e6d2; padding-top: 15px; }
    .ai-points li { position: relative; padding-left: 24px; margin-bottom: 10px; font-size: 0.9rem; color: #444; }
    .ai-points li::before { content: '✨'; position: absolute; left: 0; top: 0; font-size: 0.9rem; }
    
    .weather-badge { display: inline-flex; align-items: center; gap: 8px; background: #e0f2fe; color: #0284c7; padding: 8px 15px; border-radius: 12px; font-weight: 600; font-size: 0.85rem; }

</style>
{% endblock %}

{% block body %}
{% include 'parcelles_cultures/components/navigation_menu.html.twig' %}

<div class="container-fluid py-5 px-lg-5">
    <div class="row">
        <div class="col-md-3 col-xl-2">
            {% include 'parcelles_cultures/components/sidebar.html.twig' %}
        </div>

        <div class="col-md-9 col-xl-10">
            <div data-aos="fade-right">
                <a href="{{ path('farmer_roi_index') }}" class="btn-premium-back">
                    <i class="feather-arrow-left"></i> Retour au Hub ROI
                </a>
            </div>

            <div class="page-header d-flex justify-content-between align-items-center" data-aos="fade-down">
                <div>
                    <h1 class="page-title">Simulateur & Analyse Stratégique</h1>
                    <p class="text-muted mt-2 mb-0" style="font-size: 1.1rem;">Évaluez la rentabilité de votre parcelle avec l\'Intelligence Artificielle et la Météo en temps réel.</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Formulaire -->
                <div class="col-lg-5" data-aos="fade-right" data-aos-delay="100">
                    <div class="premium-card">
                        <div class="card-label">
                            <i class="feather-edit-3"></i> Paramètres du Projet
                        </div>

                        {{ form_start(form, {'attr': {'novalidate': 'novalidate'}}) }}

                        <div class="form-section"><i class="feather-map"></i> Général</div>
                        <div class="mb-3">
                            {{ form_label(form.parcelle, 'Sélectionner la Parcelle', {'label_attr': {'class': 'form-label'}}) }}
                            {{ form_widget(form.parcelle, {'attr': {'class': 'form-select'}}) }}
                            {{ form_errors(form.parcelle) }}
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                {{ form_label(form.surface_ha, 'Surface exploitée (ha)', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.surface_ha, {'attr': {'class': 'form-control', 'placeholder': 'Ex: 5.5'}}) }}
                                {{ form_errors(form.surface_ha) }}
                            </div>
                        </div>

                        <div class="form-section"><i class="feather-activity"></i> Production & Vente</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.rendement, 'Rendement (kg/ha)', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.rendement, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.prix_vente, 'Prix vente (DT/kg)', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.prix_vente, {'attr': {'class': 'form-control'}}) }}
                            </div>
                        </div>

                        <div class="form-section"><i class="feather-cloud-rain"></i> Risques Climatiques (Historique)</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                {{ form_label(form.jours_canicule, 'J. Canicule', {'label_attr': {'class': 'form-label', 'style': 'font-size:0.75rem'}}) }}
                                {{ form_widget(form.jours_canicule, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-4 mb-3">
                                {{ form_label(form.jours_excespluie, 'Excès Pluie', {'label_attr': {'class': 'form-label', 'style': 'font-size:0.75rem'}}) }}
                                {{ form_widget(form.jours_excespluie, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-4 mb-3">
                                {{ form_label(form.jours_gel, 'J. Gel', {'label_attr': {'class': 'form-label', 'style': 'font-size:0.75rem'}}) }}
                                {{ form_widget(form.jours_gel, {'attr': {'class': 'form-control'}}) }}
                            </div>
                        </div>

                        <div class="form-section"><i class="feather-dollar-sign"></i> Coûts (DT)</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.cout_semences, 'Semences', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.cout_semences, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.cout_engrais, 'Engrais', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.cout_engrais, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.cout_main_oeuvre, 'Main d\\'œuvre', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.cout_main_oeuvre, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-6 mb-3">
                                {{ form_label(form.cout_irrigation, 'Irrigation', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.cout_irrigation, {'attr': {'class': 'form-control'}}) }}
                            </div>
                            <div class="col-md-12 mb-3">
                                {{ form_label(form.cout_autres, 'Autres', {'label_attr': {'class': 'form-label'}}) }}
                                {{ form_widget(form.cout_autres, {'attr': {'class': 'form-control'}}) }}
                            </div>
                        </div>

                        <div class="form-section"><i class="feather-briefcase"></i> Financement</div>
                        <div class="mb-3">
                            {{ form_label(form.duree_pret, 'Durée prêt (ans)', {'label_attr': {'class': 'form-label'}}) }}
                            {{ form_widget(form.duree_pret, {'attr': {'class': 'form-control'}}) }}
                        </div>

                        <button type="submit" class="btn-calculate w-100">
                            <i class="feather-cpu" style="margin-right:8px;"></i> Lancer l'Analyse Intelligente
                        </button>

                        {{ form_end(form) }}
                    </div>
                </div>

                <!-- Dashboard Résultats -->
                <div class="col-lg-7" data-aos="fade-left" data-aos-delay="150">
                    {% if form.vars.submitted and not form.vars.valid %}
                        <div class="premium-card d-flex align-items-center justify-content-center" style="background: #fff0f0; border-color: #ffcdd2;">
                            <div class="text-center text-danger">
                                <i class="feather-alert-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <h4 class="fw-bold">Erreur de formulaire</h4>
                                <p>Veuillez vérifier les champs en rouge.</p>
                            </div>
                        </div>
                    {% elseif roi_result %}
                        <div class="premium-card" style="background: #fdfdfd;">
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="card-label mb-0">
                                    <i class="feather-pie-chart"></i> Rapport Financier
                                </div>
                                {% if roi_result.weather %}
                                <div class="weather-badge">
                                    <img src="http://openweathermap.org/img/wn/{{ roi_result.weather.weather[0].icon }}.png" width="24" height="24" style="margin-right:4px;">
                                    {{ roi_result.weather.main.temp|round }}°C / {{ roi_result.weather.weather[0].description|capitalize }}
                                </div>
                                {% endif %}
                            </div>

                            <div class="roi-hero shadow-sm">
                                <div class="value">{{ roi_result.score_roi|number_format(1, ',', ' ') }}%</div>
                                <div class="label">Retour sur Investissement</div>
                            </div>

                            <div class="dashboard-grid">
                                <div class="widget">
                                    <div class="widget-title"><i class="feather-trending-up"></i> Marge Brute</div>
                                    <div class="widget-value">{{ roi_result.marge_brute|number_format(0, ',', ' ') }} <span style="font-size:1rem;">DT</span></div>
                                    <div class="widget-sub">Revenu Brut: {{ roi_result.revenu_brut|number_format(0, ',', ' ') }} DT</div>
                                </div>
                                <div class="widget">
                                    <div class="widget-title"><i class="feather-credit-card"></i> Coûts Totaux</div>
                                    <div class="widget-value" style="color: #e53e3e;">{{ roi_result.cout_total|number_format(0, ',', ' ') }} <span style="font-size:1rem;">DT</span></div>
                                    <div class="widget-sub">Prix seuil: {{ roi_result.prix_seuil|number_format(2, ',', ' ') }} DT/kg</div>
                                </div>
                                <div class="widget">
                                    <div class="widget-title"><i class="feather-sun"></i> Climatique Base</div>
                                    <div class="widget-value" style="color: #f59e0b;">{{ (roi_result.facteur_climatique * 100)|number_format(0) }}%</div>
                                    <div class="widget-sub">Score impact intempéries</div>
                                </div>
                                <div class="widget">
                                    <div class="widget-title"><i class="feather-shield"></i> Risque Climat</div>
                                    <div class="widget-value" style="color: {{ roi_result.risque_niveau == 'Faible' ? '#116530' : (roi_result.risque_niveau == 'Modéré' ? '#f59e0b' : '#e53e3e') }}">
                                        {{ roi_result.risque_niveau }}
                                    </div>
                                    <div class="widget-sub">Stratégie globale</div>
                                </div>
                            </div>

                            {% if roi_result.gemini_analysis %}
                            <div class="ai-card">
                                <div class="ai-badge">
                                    <i class="feather-cpu" style="margin-right:5px;"></i> Gemini AI Consult
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h3 class="ai-title mb-0">Score Stratégique:</h3>
                                    <span class="badge" style="background: {{ roi_result.gemini_analysis.score == 'Excellent' or roi_result.gemini_analysis.score == 'Bon' ? '#116530' : (roi_result.gemini_analysis.score == 'Moyen' ? '#b8860b' : '#e53e3e') }}; font-size: 1.1rem; padding: 8px 12px; border-radius: 8px;">
                                        {{ roi_result.gemini_analysis.score }}
                                    </span>
                                </div>
                                
                                <p class="ai-text mb-3" style="font-size: 1.05rem; font-weight: 500; color: #333;">{{ roi_result.gemini_analysis.analyse }}</p>
                                
                                <ul class="ai-points">
                                    {% for conseil in roi_result.gemini_analysis.conseils %}
                                        {% if conseil is not empty %}
                                        <li>{{ conseil }}</li>
                                        {% endif %}
                                    {% endfor %}
                                </ul>

                                <div class="mt-4 p-3" style="background: rgba(212,175,55,0.06); border-radius: 12px; border: 1px dashed rgba(212,175,55,0.3);">
                                    <h6 style="color: #b8860b; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 8px;">
                                        <i class="feather-compass"></i> Recommandation & Alternative
                                    </h6>
                                    <p class="mb-2" style="font-size: 0.9rem; color: #444;">{{ roi_result.gemini_analysis.alternative }}</p>
                                    <div class="d-flex align-items-center mt-2">
                                        <span class="badge bg-success" style="font-size: 0.85rem; padding: 6px 12px;">
                                            <i class="feather-trending-up" style="margin-right: 4px;"></i> {{ roi_result.gemini_analysis.simulation }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {% endif %}

                        </div>
                    {% else %}
                        <div class="premium-card d-flex align-items-center justify-content-center">
                            <div class="text-center text-muted">
                                <i class="feather-bar-chart-2" style="font-size: 4.5rem; opacity: 0.15; margin-bottom: 20px;"></i>
                                <h3 class="fw-bold" style="color: #2d3436;">Prêt pour l'Analyse</h3>
                                <p style="max-width: 320px; margin: 0 auto; line-height: 1.6;">Remplissez les paramètres de votre projet à gauche et lancez le calcul pour obtenir un rapport financier complet boosté par l'IA Gemini et Météo en temps réel.</p>
                            </div>
                        </div>
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
"""

with open('templates/parcelles_cultures/farmer/roi/calculator.html.twig', 'w', encoding='utf-8') as f:
    f.write(content)
print("File updated!")
